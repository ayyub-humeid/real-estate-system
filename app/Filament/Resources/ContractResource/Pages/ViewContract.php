<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Resources\ContractResource;
use App\Enums\{ContractDocumentType, ContractVersionStatus};
use App\Models\{Company, ContractParty, ContractVersion, Document, DocumentVersion};
use App\Services\CompanyLegalPartyService;
use App\Services\ContractWorkflowService;
use App\Services\ContractPdfService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    public function getTitle(): string
    {
        return $this->record->contract_number;
    }

    public function getContentTabLabel(): ?string
    {
        return __('Overview');
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return ContractResource::infolist($infolist);
    }

    protected function getHeaderActions(): array
    {
        $latest = fn (): ?ContractVersion => $this->record->versions()->latest('version_number')->first();
        $hasOfficialPdf = fn (?ContractVersion $version): bool => $version?->documents()
            ->where('document_type', ContractDocumentType::OfficialApprovedPdf->value)
            ->exists() ?? false;
        $run = function (callable $callback, string $successTitle, ?string $failureTitle = null): void {
            try {
                $callback();
                Notification::make()->success()->title($successTitle)->send();
            } catch (ValidationException $exception) {
                Notification::make()->danger()
                    ->title($failureTitle ?? __('Cannot update contract'))
                    ->body(collect($exception->errors())->flatten()->first())
                    ->persistent()
                    ->send();
            } catch (AuthorizationException) {
                Notification::make()->danger()
                    ->title(__('Unauthorized'))
                    ->body(__('You do not have permission to perform this contract action.'))
                    ->persistent()
                    ->send();
            } catch (\Throwable $e) {
                Notification::make()->danger()
                    ->title(__('An unexpected error occurred'))
                    ->body($e->getMessage())
                    ->persistent()
                    ->send();
            }
        };
        $download = function (callable $callback, string $name) {
            try {
                $pdf = $callback();

                return response()->streamDownload(static function () use ($pdf): void {
                    echo $pdf;
                }, $name, ['Content-Type' => 'application/pdf']);
            } catch (ValidationException $exception) {
                Notification::make()->danger()->title(__('contract_domain.pdf.cannot_generate'))
                    ->body(collect($exception->errors())->flatten()->first())->persistent()->send();
            } catch (AuthorizationException) {
                Notification::make()->danger()->title(__('Unauthorized'))
                    ->body(__('You do not have permission to generate this contract PDF.'))->persistent()->send();
            } catch (\Throwable $exception) {
                Notification::make()->danger()->title(__('contract_domain.pdf.cannot_generate'))
                    ->body($exception->getMessage())->persistent()->send();
            }

            return null;
        };

        return [
            Actions\Action::make('previewPdf')
                ->label(__('contract_domain.pdf.preview'))
                ->icon('heroicon-o-eye')
                ->visible(fn (): bool => in_array($latest()?->status, [ContractVersionStatus::Draft, ContractVersionStatus::RevisionRequired], true) && auth()->user()->can('view_contract'))
                ->action(fn (): mixed => $download(
                    fn (): string => app(ContractPdfService::class)->preview(auth()->user(), $latest()),
                    $this->record->contract_number.'-V'.$latest()?->version_number.'-draft.pdf',
                )),
            Actions\Action::make('submit')
                ->label(__('Submit for review'))
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn (): bool => in_array($latest()?->status, [ContractVersionStatus::Draft, ContractVersionStatus::RevisionRequired], true) && auth()->user()->can('submit_contract_version'))
                ->requiresConfirmation()
                ->modalHeading(__('Submit contract version for review'))
                ->modalDescription(__('Are you sure you want to submit this draft contract version for approval?'))
                ->modalSubmitActionLabel(__('Submit'))
                ->action(fn (): mixed => $run(fn () => app(ContractWorkflowService::class)->submit(auth()->user(), $latest()), __('Submitted for review'))),
            Actions\Action::make('approve')
                ->label(__('Approve version'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $latest()?->status === ContractVersionStatus::Submitted && auth()->user()->can('approve_contract_version'))
                ->modalHeading(__('Approve contract version'))
                ->modalSubmitActionLabel(__('Approve'))
                ->form([Forms\Components\Textarea::make('notes')->label(__('Approval notes'))])
                ->action(fn (array $data): mixed => $run(fn () => app(ContractWorkflowService::class)->approve(auth()->user(), $latest(), $data['notes'] ?? null), __('Version approved'))),
            Actions\Action::make('requestRevision')
                ->label(__('Request revision'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool => $latest()?->status === ContractVersionStatus::Submitted && auth()->user()->can('request_contract_revision'))
                ->modalHeading(__('Request contract revision'))
                ->modalSubmitActionLabel(__('Request revision'))
                ->form([Forms\Components\Textarea::make('notes')->label(__('Revision notes'))->required()])
                ->action(fn (array $data): mixed => $run(fn () => app(ContractWorkflowService::class)->requestRevision(auth()->user(), $latest(), $data['notes']), __('Revision requested'))),
            Actions\Action::make('createRevision')
                ->label(__('Create revision'))
                ->icon('heroicon-o-document-duplicate')
                ->visible(fn (): bool => $latest()?->status === ContractVersionStatus::Approved && auth()->user()->can('create_contract_revision'))
                ->requiresConfirmation()
                ->modalHeading(__('Create new draft revision'))
                ->modalDescription(__('This will clone the current contract state into a new draft version for editing.'))
                ->modalSubmitActionLabel(__('Create revision'))
                ->action(fn (): mixed => $run(fn () => app(ContractWorkflowService::class)->createRevision(auth()->user(), $latest()), __('Draft revision created'))),
            Actions\Action::make('attachDocument')
                ->label(__('Upload contract document'))
                ->icon('heroicon-o-arrow-up-tray')
                ->visible(fn (): bool => in_array($latest()?->status, [ContractVersionStatus::Draft, ContractVersionStatus::RevisionRequired], true) && auth()->user()->can('attach_contract_document'))
                ->modalHeading(__('Upload contract document'))
                ->modalDescription(__('Contract documents can be changed until this version is submitted for review.'))
                ->modalSubmitActionLabel(__('Upload document'))
                ->form([
                    Forms\Components\TextInput::make('title')->label(__('Document title'))->required(),
                    Forms\Components\Textarea::make('description')->label(__('Description')),
                    Forms\Components\FileUpload::make('file_path')
                        ->label(__('Contract file (PDF/Document)'))
                        ->required()
                        ->disk(config('filesystems.default'))
                        ->directory('documents/contracts')
                        ->storeFileNamesIn('file_name'),
                ])
                ->action(fn (array $data): mixed => $run(fn () => app(ContractWorkflowService::class)->attachDocument(auth()->user(), $latest(), $data), __('Legal document attached'))),
            Actions\Action::make('recordSignature')
                ->label(__('Record signature'))
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (): bool => $latest()?->status === ContractVersionStatus::Approved
                    && auth()->user()->can('record_contract_signature'))
                ->disabled(fn (): bool => ! $hasOfficialPdf($latest()))
                ->tooltip(fn (): ?string => $hasOfficialPdf($latest()) ? null : __('contract_domain.pdf.official_required'))
                ->modalHeading(__('Record party signature'))
                ->modalSubmitActionLabel(__('Record signature'))
                ->form(function () use ($latest): array {
                    $version = $latest();
                    $roles = [
                        'buyer' => __('Buyer'),
                        'seller' => __('Seller'),
                        'landlord' => __('Landlord'),
                        'tenant' => __('Tenant'),
                        'guarantor' => __('Guarantor'),
                    ];
                    $parties = $version?->parties()->with('party')->get() ?? collect();
                    $partyOptions = $parties->mapWithKeys(fn (ContractParty $party): array => [
                        $party->id => ($roles[$party->role] ?? ($party->role ?: __('Party'))).' — '.($party->snapshot['name'] ?? $party->party?->name ?? '—'),
                    ])->all();
                    $company = Company::withoutGlobalScopes()->find($version?->company_id);
                    $companyLegalPartyId = $company
                        ? app(CompanyLegalPartyService::class)->resolve($company)->id
                        : null;
                    $companyContractPartyId = $parties
                        ->first(fn (ContractParty $party): bool => (int) $party->party_id === (int) $companyLegalPartyId)
                        ?->id;
                    $companyPartyOptions = $companyContractPartyId && isset($partyOptions[$companyContractPartyId])
                        ? [$companyContractPartyId => $partyOptions[$companyContractPartyId]]
                        : [];

                    return [
                        Forms\Components\Select::make('contract_party_id')
                            ->label(__('Contract party'))
                            ->options(fn (Forms\Get $get): array => $get('signing_method') === 'company_authorized'
                                ? $companyPartyOptions
                                : $partyOptions)
                            ->required(),
                        Forms\Components\Select::make('document_version_id')
                ->label(__('contract_domain.pdf.official'))
                            ->options($version?->documents()->where('document_type', ContractDocumentType::OfficialApprovedPdf->value)->with('latestVersion')->get()->mapWithKeys(fn (Document $document): array => [
                                $document->latestVersion?->id => $document->title.' — V'.($document->latestVersion?->version_number ?? 1),
                            ])->filter()->all() ?? [])
                            ->required(),
                        Forms\Components\TextInput::make('signatory_name')->label(__('Signatory name'))->required(),
                        Forms\Components\Select::make('signing_method')
                            ->label(__('Signing method'))
                            ->options([
                                'manual' => __('contract_domain.signing_methods.manual'),
                                'electronic' => __('contract_domain.signing_methods.electronic'),
                                'company_authorized' => __('contract_domain.signing_methods.company_authorized'),
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) use ($companyContractPartyId): void {
                                if ($state === 'company_authorized') {
                                    $set('signature_image_path', null);
                                    $set('contract_party_id', $companyContractPartyId);
                                }
                            }),
                        Forms\Components\FileUpload::make('signature_image_path')
                            ->label(__('Signature image'))
                            ->image()
                            ->disk(config('filesystems.default'))
                            ->directory('signatures/contracts')
                            ->maxSize(5120)
                            ->visible(fn (Forms\Get $get): bool => $get('signing_method') !== 'company_authorized')
                            ->dehydrated(fn (Forms\Get $get): bool => $get('signing_method') !== 'company_authorized'),
                        Forms\Components\Placeholder::make('company_authorized_signature_notice')
                            ->label(__('Signature image'))
                            ->content(__('contract_domain.signing_methods.company_authorized_notice'))
                            ->visible(fn (Forms\Get $get): bool => $get('signing_method') === 'company_authorized'),
                        Forms\Components\Placeholder::make('company_authorized_party_notice')
                            ->label(__('Contract party'))
                            ->content($companyContractPartyId
                                ? __('contract_domain.signing_methods.company_party_selected')
                                : __('contract_domain.signing_methods.company_party_missing'))
                            ->visible(fn (Forms\Get $get): bool => $get('signing_method') === 'company_authorized'),
                        Forms\Components\Textarea::make('evidence.notes')->label(__('Signature evidence notes')),
                    ];
                })
                ->action(fn (array $data): mixed => $run(fn () => app(ContractWorkflowService::class)->recordSignature(
                    auth()->user(),
                    $latest(),
                    ContractParty::withoutGlobalScopes()->findOrFail($data['contract_party_id']),
                    DocumentVersion::withoutGlobalScopes()->findOrFail($data['document_version_id']),
                    $data['signatory_name'],
                    $data['signing_method'],
                    $data['evidence'] ?? [],
                    $data['signature_image_path'] ?? null,
                ), __('Signature recorded'))),
            Actions\Action::make('generateOfficialPdf')
                ->label(__('contract_domain.pdf.generate_official'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->visible(fn (): bool => $latest()?->status === ContractVersionStatus::Approved
                    && ! $latest()?->documents()->where('document_type', ContractDocumentType::OfficialApprovedPdf->value)->exists()
                    && auth()->user()->can('generate_contract_pdf'))
                ->requiresConfirmation()
                ->modalHeading(__('contract_domain.pdf.official_title'))
                ->modalDescription(__('contract_domain.pdf.official_description'))
                ->disabled(fn (): bool => ! $latest()?->template_version_id)
                ->tooltip(fn (): ?string => $latest()?->template_version_id ? null : __('contract_domain.pdf.template_required'))
                ->action(fn (): mixed => $run(fn () => app(ContractPdfService::class)->generateOfficial(auth()->user(), $latest()), __('contract_domain.pdf.generated_official'), __('contract_domain.pdf.cannot_generate'))),
            Actions\Action::make('generateSignedFinalPdf')
                ->label(__('contract_domain.pdf.generate_signed'))
                ->icon('heroicon-o-document-check')
                ->color('success')
                ->visible(fn (): bool => in_array($latest()?->status, [ContractVersionStatus::Approved, ContractVersionStatus::Signed], true)
                    && $latest()?->documents()->where('document_type', ContractDocumentType::OfficialApprovedPdf->value)->exists()
                    && ! $latest()?->documents()->where('document_type', ContractDocumentType::SignedFinalPdf->value)->exists()
                    && $latest()?->parties()->count() === $latest()?->signatures()->count()
                    && auth()->user()->can('generate_contract_pdf'))
                ->requiresConfirmation()
                ->modalHeading(__('contract_domain.pdf.signed_title'))
                ->modalDescription(__('contract_domain.pdf.signed_description'))
                ->disabled(fn (): bool => ! $latest()?->template_version_id)
                ->tooltip(fn (): ?string => $latest()?->template_version_id ? null : __('contract_domain.pdf.template_required'))
                ->action(fn (): mixed => $run(fn () => app(ContractPdfService::class)->generateSignedFinal(auth()->user(), $latest()), __('contract_domain.pdf.generated_signed'), __('contract_domain.pdf.cannot_generate'))),
            Actions\Action::make('activate')
                ->label(__('Activate contract'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => $latest()?->status === ContractVersionStatus::Approved && auth()->user()->can('activate_contract'))
                ->requiresConfirmation()
                ->modalHeading(__('Activate contract'))
                ->modalDescription(__('Are you sure you want to activate this contract? This will activate the contract and conclude reservation obligations.'))
                ->modalSubmitActionLabel(__('Activate contract'))
                ->action(fn (): mixed => $run(fn () => app(ContractWorkflowService::class)->activate(auth()->user(), $this->record, $latest()), __('Contract activated'))),
        ];
    }
}
