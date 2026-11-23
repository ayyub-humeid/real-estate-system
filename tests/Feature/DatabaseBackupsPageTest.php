<?php

namespace Tests\Feature;

use App\Filament\Pages\DatabaseBackups;
use App\Jobs\RunDatabaseBackup;
use App\Models\{Company, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, Queue, Storage};
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** A backup holds every company's data, so only the platform super admin may see or start one. */
class DatabaseBackupsPageTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create(['company_id' => null]);
        $user->assignRole(Role::findOrCreate('super_admin', 'web'));

        return $user;
    }

    private function fakeStorage(): string
    {
        config(['backup.backup.destination.disks' => ['backups_fake'], 'backup.backup.name' => 'Madar']);
        config(['filesystems.disks.backups_fake' => ['driver' => 'local', 'root' => storage_path('framework/testing/disks/backups_fake')]]);
        Storage::fake('backups_fake');
        Storage::disk('backups_fake')->put('Madar/2026-01-01-02-00-00.zip', 'zip-bytes');
        Storage::disk('backups_fake')->put('Madar/notes.txt', 'ignore me');

        return 'Madar/2026-01-01-02-00-00.zip';
    }

    public function test_a_company_admin_cannot_open_the_backups_page(): void
    {
        $company = Company::create(['name' => 'Acme', 'email' => 'acme@example.test']);
        $this->actingAs(User::factory()->create(['company_id' => $company->id]));

        $this->assertFalse(DatabaseBackups::canAccess());
        Livewire::test(DatabaseBackups::class)->assertForbidden();
    }

    public function test_the_super_admin_sees_the_saved_backups_and_only_zip_files(): void
    {
        $this->fakeStorage();
        $this->actingAs($this->superAdmin());

        Livewire::test(DatabaseBackups::class)
            ->assertOk()
            ->assertSee('2026-01-01-02-00-00.zip')
            ->assertDontSee('notes.txt');
    }

    public function test_the_button_queues_one_backup_and_refuses_a_second_while_it_runs(): void
    {
        $this->fakeStorage();
        Queue::fake();
        Cache::forget(RunDatabaseBackup::RUNNING_KEY);
        $this->actingAs($this->superAdmin());

        Livewire::test(DatabaseBackups::class)->call('createBackup')->assertNotified(__('backups.started_title'));
        Queue::assertPushed(RunDatabaseBackup::class, 1);

        Livewire::test(DatabaseBackups::class)->call('createBackup')->assertNotified(__('backups.already_running'));
        Queue::assertPushed(RunDatabaseBackup::class, 1);

        Cache::forget(RunDatabaseBackup::RUNNING_KEY);
    }

    public function test_a_download_is_refused_for_a_path_that_is_not_a_listed_backup(): void
    {
        $this->fakeStorage();
        $this->actingAs($this->superAdmin());

        Livewire::test(DatabaseBackups::class)
            ->call('download', '../.env')
            ->assertNotified(__('backups.download_missing'))
            ->assertNoRedirect();
    }
}
