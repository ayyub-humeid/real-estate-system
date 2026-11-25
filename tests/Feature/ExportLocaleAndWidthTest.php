<?php

namespace Tests\Feature;

use App\Filament\Exports\PaymentExporter;
use App\Jobs\CreateNumericAwareXlsxFile;
use App\Models\{Company, Contract, ContractParty, ContractVersion, Party, Plan, Subscription, User};
use App\Enums\{ContractStatus, ContractType, ContractVersionStatus};
use App\Services\ContractFinancialService;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\Job as JobContract;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

/**
 * Two things an Arabic admin notices when an export lands in her inbox:
 *  - labels that stayed in English because the queue worker's default locale
 *    is 'en', not hers;
 *  - columns too narrow to read without resizing by hand.
 * Both are promised by the service provider and the XLSX job; locked down here.
 */
class ExportLocaleAndWidthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_queued_job_restores_the_invoking_users_locale(): void
    {
        app()->setLocale('en');

        $job = $this->createMock(JobContract::class);
        $job->method('payload')->willReturn(['locale' => 'ar']);

        Event::dispatch(new JobProcessing('database', $job));

        $this->assertSame('ar', app()->getLocale());
    }

    public function test_a_payload_without_a_locale_leaves_the_current_one_alone(): void
    {
        app()->setLocale('en');

        $job = $this->createMock(JobContract::class);
        $job->method('payload')->willReturn([]);

        Event::dispatch(new JobProcessing('database', $job));

        $this->assertSame('en', app()->getLocale());
    }

    public function test_payload_stamping_is_installed_so_workers_receive_the_right_locale(): void
    {
        // Laravel exposes the queue payload hook on the base queue; a registered
        // closure means the AppServiceProvider actually wired the stamp.
        $queue = app('queue')->connection();
        $this->assertNotEmpty((new \ReflectionProperty($queue, 'createPayloadCallbacks'))->getValue($queue));
    }

    public function test_a_payment_row_renders_in_arabic_when_the_app_locale_is_arabic(): void
    {
        $previous = app()->getLocale();
        app()->setLocale('ar');

        try {
            $payment = $this->recordedPayment();

            $export = Export::create([
                'file_disk' => 'local', 'file_name' => 'ar-payments', 'exporter' => PaymentExporter::class,
                'total_rows' => 1, 'user_id' => $payment->recorded_by,
            ]);
            $exporter = new PaymentExporter($export, array_fill_keys(['direction', 'payment_scope', 'status'], true), []);

            $row = $exporter($payment->fresh());

            $this->assertSame(__('payment_directory.directions.incoming', [], 'ar'), $row[0]);
            $this->assertSame(__('payment_directory.purposes.contract_receipt', [], 'ar'), $row[1]);
            $this->assertSame(__('payment_directory.statuses.completed', [], 'ar'), $row[2]);
        } finally {
            app()->setLocale($previous);
        }
    }

    public function test_the_xlsx_writer_sizes_every_column_to_fit_its_longest_cell(): void
    {
        Storage::fake('local');

        $actor = $this->actorOnStarter();
        $payment = $this->recordedPayment($actor);

        $export = $this->runPaymentExport($actor, $payment, ['payment_date', 'reference_number', 'amount', 'currency', 'contract.contract_number']);

        $xml = $this->sheetXml($export);
        preg_match_all('/<col[^>]+min="(\d+)"[^>]+max="\d+"[^>]+width="([\d.]+)"/', $xml, $matches);

        $this->assertNotEmpty($matches[1], 'The sheet should declare column widths.');

        $widths = array_combine($matches[1], array_map('floatval', $matches[2]));
        // Reference column holds "TRF-9001" (8 chars) plus padding, inside [10, 50].
        $this->assertGreaterThanOrEqual(10.0, $widths[2]);
        $this->assertLessThanOrEqual(50.0, $widths[2]);
        // Date column ("Payment date" header = 12 chars) widens to at least the header length.
        $this->assertGreaterThanOrEqual(12.0 + 2.0, $widths[1]);
    }

    private function actorOnStarter(): User
    {
        $company = Company::create(['name' => 'Width Co', 'email' => str()->uuid().'@x.test', 'is_active' => true]);
        Subscription::create([
            'company_id' => $company->id,
            'plan_id' => Plan::create(['name' => 'S '.str()->uuid(), 'slug' => 's-'.str()->uuid(), 'price' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['max_units' => 50, 'max_projects' => 2]])->id,
            'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(),
        ]);
        $actor = User::factory()->create(['company_id' => $company->id]);
        $actor->assignRole(Role::findOrCreate('width_test_role', 'web'));
        foreach (['create_contract_payment_schedule', 'submit_contract_payment_schedule', 'approve_contract_payment_schedule', 'record_contract_payment', 'allocate_contract_payment'] as $p) {
            Permission::findOrCreate($p, 'web');
            $actor->givePermissionTo($p);
        }

        return $actor;
    }

    private function recordedPayment(?User $actor = null): \App\Models\Payment
    {
        $actor ??= $this->actorOnStarter();
        $companyId = $actor->company_id;

        $contract = Contract::withoutGlobalScopes()->create([
            'company_id' => $companyId, 'contract_number' => 'CNT-'.str()->uuid(), 'contract_type' => ContractType::Sale,
            'status' => ContractStatus::Active, 'created_by' => $actor->id,
        ]);
        $version = ContractVersion::withoutGlobalScopes()->create([
            'company_id' => $companyId, 'contract_id' => $contract->id, 'version_number' => 1,
            'status' => ContractVersionStatus::Signed, 'total_amount' => 500, 'currency' => 'USD', 'created_by' => $actor->id,
        ]);
        $buyer = Party::withoutGlobalScopes()->create(['company_id' => $companyId, 'type' => 'person', 'name' => 'Buyer', 'is_active' => true]);
        ContractParty::withoutGlobalScopes()->create([
            'company_id' => $companyId, 'contract_version_id' => $version->id, 'party_id' => $buyer->id,
            'role' => 'buyer', 'snapshot' => ['name' => $buyer->name],
        ]);

        $service = app(ContractFinancialService::class);
        $schedule = $service->createDraft($actor, $contract, $version, [
            'currency' => 'USD',
            'installments' => [['kind' => 'installment', 'amount' => 500, 'due_date' => '2027-01-31']],
        ]);
        $service->submit($actor, $schedule);
        $service->issue($actor, $schedule->fresh());
        $installment = collect($service->outstandingInstallments($schedule->fresh()))->first();

        return $service->recordReceipt($actor, $schedule->fresh(), [
            'party_id' => $buyer->id, 'amount' => $installment['outstanding'], 'currency' => 'USD',
            'payment_date' => '2026-11-01', 'payment_method' => 'bank_transfer', 'reference_number' => 'TRF-9001',
            'allocations' => [['installment_id' => $installment['id'], 'allocated_amount' => $installment['outstanding'], 'currency' => 'USD']],
        ]);
    }

    private function runPaymentExport(User $actor, \App\Models\Payment $payment, array $columns): Export
    {
        $disk = Storage::fake('local');
        $export = Export::create([
            'file_disk' => 'local', 'file_name' => 'widths-'.$payment->id,
            'exporter' => PaymentExporter::class, 'total_rows' => 1, 'user_id' => $actor->id,
        ]);
        $columnMap = array_fill_keys($columns, true);
        $exporter = new PaymentExporter($export, $columnMap, []);

        // Mirror what Filament's pipeline writes to disk: a header CSV and one data CSV.
        $dir = $export->getFileDirectory();
        $headerRow = [];
        foreach (PaymentExporter::getColumns() as $column) {
            if (! isset($columnMap[$column->getName()])) {
                continue;
            }
            $headerRow[] = $column->getLabel();
        }
        $disk->put($dir.'/headers.csv', implode(',', $headerRow)."\n");
        $data = $exporter($payment->fresh());
        $disk->put($dir.'/batch-1.csv', implode(',', array_map(fn ($v): string => (string) $v, $data))."\n");

        $job = new CreateNumericAwareXlsxFile($export, $columnMap, []);
        $job->handle();

        return $export->fresh();
    }

    private function sheetXml(Export $export): string
    {
        $disk = Storage::disk('local');
        $xlsxPath = $disk->path($export->getFileDirectory().'/'.$export->file_name.'.xlsx');
        $this->assertFileExists($xlsxPath);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($xlsxPath) === true);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertNotFalse($xml);

        return $xml;
    }
}
