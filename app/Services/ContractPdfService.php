<?php

namespace App\Services;

use App\Enums\{ContractDocumentType, ContractVersionStatus};
use App\Models\{CompanySetting, ContractSignature, ContractVersion, Document, DocumentVersion, User};
use App\Support\ContractPdfContentLimits;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/** Renders and issues immutable ContractVersion PDFs using the shared document store. */
class ContractPdfService
{
    public function preview(User $actor, ContractVersion $version): string
    {
        $this->assertViewable($actor, $version);
        $version = $this->version($version);

        if (! in_array($version->status, [ContractVersionStatus::Draft, ContractVersionStatus::RevisionRequired], true)) {
            $this->invalid('version', __('contract_domain.pdf.preview_requires_draft'));
        }

        return $this->render($version, true, []);
    }

    /** Creates at most one official PDF for the exact approved ContractVersion. */
    public function generateOfficial(User $actor, ContractVersion $version): DocumentVersion
    {
        $this->canIssue($actor, $version);

        return DB::transaction(function () use ($actor, $version): DocumentVersion {
            $locked = $this->lockedVersion($version);
            if ($locked->status !== ContractVersionStatus::Approved) {
                $this->invalid('version', __('contract_domain.pdf.official_requires_approved'));
            }

            $existing = $this->typedDocumentVersion($locked, ContractDocumentType::OfficialApprovedPdf);
            if ($existing) {
                return $existing;
            }

            return $this->storeIssuedPdf($locked, $actor, ContractDocumentType::OfficialApprovedPdf, $this->render($locked, false, []));
        });
    }

    /** Produces a distinct signed artefact without changing the official approved PDF. */
    public function generateSignedFinal(User $actor, ContractVersion $version): DocumentVersion
    {
        $this->canIssue($actor, $version);

        return DB::transaction(function () use ($actor, $version): DocumentVersion {
            $locked = $this->lockedVersion($version);
            if (! in_array($locked->status, [ContractVersionStatus::Approved, ContractVersionStatus::Signed], true)) {
                $this->invalid('version', __('contract_domain.pdf.signed_requires_approved'));
            }
            if (! $this->typedDocumentVersion($locked, ContractDocumentType::OfficialApprovedPdf)) {
                $this->invalid('document', __('contract_domain.pdf.official_required'));
            }
            $signatures = $locked->signatures()->with(['party', 'documentVersion.document'])->lockForUpdate()->get();
            if ($locked->parties()->count() === 0 || $signatures->count() !== $locked->parties()->count()) {
                $this->invalid('signatures', __('contract_domain.pdf.all_signatures_required'));
            }

            $existing = $this->typedDocumentVersion($locked, ContractDocumentType::SignedFinalPdf);
            if ($existing) {
                return $existing;
            }

            return $this->storeIssuedPdf($locked, $actor, ContractDocumentType::SignedFinalPdf, $this->render($locked, false, $signatures->all()));
        });
    }

    private function storeIssuedPdf(ContractVersion $version, User $actor, ContractDocumentType $type, string $binary): DocumentVersion
    {
        $path = sprintf('documents/contracts/%d/version-%d/%s.pdf', $version->company_id, $version->id, $type->value);
        Storage::disk(config('filesystems.default'))->put($path, $binary);

        try {
            $document = Document::create([
                'documentable_type' => ContractVersion::class,
                'documentable_id' => $version->id,
                'document_type' => $type,
                'title' => $type === ContractDocumentType::OfficialApprovedPdf ? __('contract_domain.pdf.official_title') : __('contract_domain.pdf.signed_title'),
                'description' => __('contract_domain.pdf.issued_description'),
                'created_by' => $actor->id,
            ]);

            return DocumentVersion::withoutGlobalScopes()->create([
                'company_id' => $version->company_id,
                'document_id' => $document->id,
                'version_number' => 1,
                'file_name' => ($version->contract->contract_number ?: 'contract').'-V'.$version->version_number.'-'.$type->value.'.pdf',
                'file_path' => $path,
                'mime_type' => 'application/pdf',
                'file_size' => strlen($binary),
                'checksum' => hash('sha256', $binary),
                'uploaded_by' => $actor->id,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk(config('filesystems.default'))->delete($path);
            throw $exception;
        }
    }

    /** @param array<int, ContractSignature> $signatures */
    private function render(ContractVersion $version, bool $draft, array $signatures): string
    {
        $version->loadMissing(['contract', 'templateVersion.template', 'parties.party', 'terms']);
        if (! $version->templateVersion) {
            $this->invalid('template_version_id', __('contract_domain.pdf.template_required'));
        }

        $settings = CompanySetting::withoutGlobalScopes()->where('company_id', $version->company_id)->first();
        $body = $this->replacePlaceholders($version->templateVersion->body, $version);
        $this->assertRenderableContent($version, $body);
        $contractSummary = $this->summary($version);

        $temporaryDirectory = storage_path('app/mpdf');
        if (! is_dir($temporaryDirectory)) {
            mkdir($temporaryDirectory, 0775, true);
        }

        // Dompdf produced disconnected Arabic glyphs in legal documents. mPDF
        // supports Unicode shaping and RTL directionality for contract output.
        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'default_font' => 'dejavusans',
            'tempDir' => $temporaryDirectory,
            'margin_left' => 16,
            'margin_right' => 16,
            'margin_top' => 18,
            'margin_bottom' => 18,
        ]);
        $pdf->autoScriptToLang = true;
        $pdf->autoLangToFont = true;
        $pdf->SetDirectionality(app()->getLocale() === 'ar' ? 'rtl' : 'ltr');

        if ($background = $this->backgroundPath($settings?->contract_background)) {
            // mPDF's native watermark is painted behind every page. An HTML
            // image can enter normal layout flow and push legal content onto
            // an unnecessary extra page.
            $pdf->SetWatermarkImage($background, 0.08, 'F', 'F', true);
            $pdf->showWatermarkImage = true;
        }

        $html = view('pdf.contract-version', [
            'contractVersion' => $version,
            'contract' => $version->contract,
            'settings' => $settings,
            'body' => $body,
            'contractSummary' => $contractSummary,
            'draft' => $draft,
            'signatures' => $signatures,
            'images' => $this->images($settings, $signatures),
        ])->render();

        try {
            $pdf->WriteHTML($html);
        } catch (\Mpdf\MpdfException $exception) {
            report($exception);
            $this->invalid('pdf', __('contract_domain.content.too_complex'));
        }

        return $pdf->Output('', Destination::STRING_RETURN);
    }

    private function replacePlaceholders(?string $body, ContractVersion $version): string
    {
        $body = ContractTemplateTokenService::normalise($body);
        $snapshot = $version->unit_snapshot ?? [];
        $company = $version->contract->company ?? null;
        $parties = $version->parties
            ->map(fn ($party) => e(($party->snapshot['name'] ?? $party->party?->name ?? '—').' ('.$this->partyRoleLabel($party->role).')'))
            ->implode('<br>');
        $formatDate = fn ($date) => $date ? $date->format('Y-m-d') : '—';

        $values = [
            '{{contract_number}}' => e($version->contract->contract_number),
            '{{contract_type}}' => e($version->contract->contract_type?->getLabel() ?? (string) $version->contract->contract_type),
            '{{company_name}}' => e($company?->name ?? ''),
            '{{total_amount}}' => e(number_format((float) $version->total_amount, 2)),
            '{{currency}}' => e($version->currency ?? ''),
            '{{start_date}}' => e($formatDate($version->start_date)),
            '{{end_date}}' => e($formatDate($version->end_date)),
            '{{delivery_date}}' => e($formatDate($version->delivery_date)),
            '{{unit_number}}' => e($this->snapshotValue($snapshot, 'unit_number', 'planned_unit_code')),
            '{{property_name}}' => e($this->snapshotValue($snapshot, 'property_name')),
            '{{project_name}}' => e($this->snapshotValue($snapshot, 'project_name')),
            '{{parties}}' => $parties,
            // Terms are rendered once, in the controlled terms section below
            // the legal template body. Keeping legacy placeholders harmless
            // prevents duplicate clauses in PDFs made from older templates.
            '{{terms}}' => '',
            '{{contract_terms}}' => '',
        ];

        return strtr($body ?: '<h1>{{contract_number}}</h1><p>{{parties}}</p>{{terms}}', $values);
    }

    private function assertRenderableContent(ContractVersion $version, string $body): void
    {
        $termsLength = $version->terms->sum(fn ($term): int => mb_strlen((string) $term->title) + mb_strlen((string) $term->text));
        if ((mb_strlen($body) + $termsLength) > ContractPdfContentLimits::RENDERED_CONTENT_MAX_CHARACTERS) {
            $this->invalid('pdf', __('contract_domain.content.too_complex'));
        }
    }

    /** @return array{unit:string,property:string,project:string} */
    private function summary(ContractVersion $version): array
    {
        $snapshot = $version->unit_snapshot ?? [];

        return [
            'unit' => $this->snapshotValue($snapshot, 'unit_number', 'planned_unit_code'),
            'property' => $this->snapshotValue($snapshot, 'property_name'),
            'project' => $this->snapshotValue($snapshot, 'project_name'),
        ];
    }

    /** Supports current snapshots and older snapshots that stored nested records. */
    private function snapshotValue(array $snapshot, string ...$keys): string
    {
        foreach ($keys as $key) {
            $value = $snapshot[$key] ?? null;
            if (is_string($value) || is_numeric($value)) {
                return (string) $value;
            }
            if (is_array($value)) {
                foreach (['name', 'unit_number', 'code', 'label'] as $labelKey) {
                    if (isset($value[$labelKey]) && (is_string($value[$labelKey]) || is_numeric($value[$labelKey]))) {
                        return (string) $value[$labelKey];
                    }
                }
            }
        }

        return '—';
    }

    private function partyRoleLabel(?string $role): string
    {
        return match ($role) {
            'buyer' => __('Buyer'),
            'seller' => __('Seller'),
            'landlord' => __('Landlord'),
            'tenant' => __('Tenant'),
            'guarantor' => __('Guarantor'),
            default => $role ?: '—',
        };
    }

    /** @param array<int, ContractSignature> $signatures */
    private function images(?CompanySetting $settings, array $signatures): array
    {
        $paths = [
            'logo' => [$settings?->logo, 800, 400],
        ];
        foreach ($signatures as $signature) {
            $paths['signature_'.$signature->id] = [$signature->signature_image_path, 800, 300];
            $paths['stamp_'.$signature->id] = [
                $signature->signing_method === 'company_authorized'
                    ? data_get($signature->evidence, 'company_stamp_snapshot')
                    : null,
                800,
                800,
            ];
        }

        return collect($paths)->map(fn (array $image) => $this->localImageUri(...$image))->all();
    }

    /**
     * mPDF parses inline Base64 image strings as part of the document HTML.
     * A small-but-dense logo can exceed its PCRE backtrack limit, so external
     * storage images are always normalized to a bounded local PNG first.
     */
    private function localImageUri(?string $path, int $maxWidth, int $maxHeight): ?string
    {
        if (! $path || ! Storage::disk(config('filesystems.default'))->exists($path)) {
            return null;
        }

        $disk = Storage::disk(config('filesystems.default'));
        $contents = $disk->get($path);
        $dimensions = @getimagesizefromstring($contents);
        if (! $dimensions || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        [$width, $height] = $dimensions;
        if (($width * $height) > 16_000_000) {
            return null;
        }
        $source = @imagecreatefromstring($contents);
        if (! $source) {
            return null;
        }

        $scale = min($maxWidth / $width, $maxHeight / $height, 1);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 255, 255, 255, 127));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $directory = storage_path('app/mpdf/images');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $cachedPath = $directory.DIRECTORY_SEPARATOR.hash('sha256', $contents."|{$maxWidth}x{$maxHeight}").'.png';
        if (! is_file($cachedPath)) {
            imagepng($target, $cachedPath, 6);
        }
        imagedestroy($source);
        imagedestroy($target);

        return is_file($cachedPath)
            ? 'file:///'.ltrim(str_replace('\\', '/', $cachedPath), '/')
            : null;
    }

    /**
     * A full-resolution PNG can exhaust mPDF's memory budget even when its
     * file size is small. Contracts only need a subtle A4 watermark, so make
     * a bounded cached JPEG watermark from the company setting.
     */
    private function backgroundPath(?string $path): ?string
    {
        if (! $path || ! Storage::disk(config('filesystems.default'))->exists($path)) {
            return null;
        }

        $contents = Storage::disk(config('filesystems.default'))->get($path);
        $dimensions = @getimagesizefromstring($contents);
        if (! $dimensions || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        [$width, $height] = $dimensions;
        // Avoid decoding unusually large uploads in a worker with a normal
        // PHP memory limit. The administrator can replace it with a smaller
        // background from Company Settings if this fallback is reached.
        if (($width * $height) > 16_000_000) {
            return null;
        }

        $source = @imagecreatefromstring($contents);
        if (! $source) {
            return null;
        }

        // mPDF expands embedded images in memory. A soft watermark remains
        // visually effective at this size while keeping preview generation
        // safe on the normal production PHP memory limit.
        $maxWidth = 360;
        $maxHeight = 509;
        $scale = min($maxWidth / $width, $maxHeight / $height, 1);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $directory = storage_path('app/mpdf/backgrounds');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $cachedPath = $directory.DIRECTORY_SEPARATOR.hash('sha256', $contents).'.jpg';
        if (! is_file($cachedPath)) {
            imagejpeg($target, $cachedPath, 60);
        }
        imagedestroy($source);
        imagedestroy($target);

        return is_file($cachedPath) ? $cachedPath : null;
    }

    private function typedDocumentVersion(ContractVersion $version, ContractDocumentType $type): ?DocumentVersion
    {
        return $version->documents()
            ->where('document_type', $type->value)
            ->with('latestVersion')
            ->lockForUpdate()
            ->first()?->latestVersion;
    }

    private function lockedVersion(ContractVersion $version): ContractVersion
    {
        return ContractVersion::withoutGlobalScopes()
            ->with(['contract.company', 'templateVersion.template', 'parties.party', 'terms'])
            ->lockForUpdate()
            ->findOrFail($version->id);
    }

    private function version(ContractVersion $version): ContractVersion
    {
        return ContractVersion::withoutGlobalScopes()
            ->with(['contract.company', 'templateVersion.template', 'parties.party', 'terms'])
            ->findOrFail($version->id);
    }

    private function assertViewable(User $actor, ContractVersion $version): void
    {
        if (! $actor->can('view_contract') || (! $actor->isSuperAdmin() && (int) $actor->company_id !== (int) $version->company_id)) {
            throw new AuthorizationException;
        }
    }

    private function canIssue(User $actor, ContractVersion $version): void
    {
        if (! $actor->can('generate_contract_pdf') || (! $actor->isSuperAdmin() && (int) $actor->company_id !== (int) $version->company_id)) {
            throw new AuthorizationException;
        }
    }

    private function invalid(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
