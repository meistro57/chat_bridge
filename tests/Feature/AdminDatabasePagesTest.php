<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminDatabasePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_database_backup_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->createBackupFile('backup-test.sql');

        $response = $this->actingAs($admin)->get(route('admin.database.backup'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Database/Backup')
        );
    }

    public function test_admin_can_view_database_restore_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->createBackupFile('restore-test.sql');

        $response = $this->actingAs($admin)->get(route('admin.database.restore'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Database/Restore')
        );
    }

    public function test_admin_can_prepare_restore_command_for_a_backup(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $filename = 'prepare-restore.sql';
        $this->createBackupFile($filename);

        $response = $this->actingAs($admin)->post(route('admin.database.restore.run'), [
            'filename' => $filename,
        ]);

        $response->assertRedirect(route('admin.database.restore'));
        $response->assertSessionHas('selected_backup', $filename);
        $response->assertSessionHas('restore_command');
    }

    public function test_admin_can_delete_a_backup_file(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $filename = 'delete-me.sql';
        $path = $this->createBackupFile($filename);
        $this->assertTrue(File::exists($path));

        $response = $this->actingAs($admin)->delete(route('admin.database.backups.delete'), [
            'filename' => $filename,
        ]);

        $response->assertRedirect();
        $this->assertFalse(File::exists($path));
    }

    public function test_admin_can_run_backup_action(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.database.backup.run'));

        $response->assertRedirect(route('admin.database.backup'));
        $response->assertSessionHas('success');

        $backups = collect(File::files(storage_path('app/backups')))
            ->map(fn ($file) => $file->getFilename())
            ->filter(fn ($name) => str_starts_with($name, 'backup-') && str_ends_with($name, '.sql'));

        $this->assertTrue($backups->isNotEmpty());
    }

    public function test_admin_can_download_backup_file(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $filename = 'download-me.sql';
        $this->createBackupFile($filename);

        $response = $this->actingAs($admin)->get(route('admin.database.backups.download', ['filename' => $filename]));

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    public function test_non_admin_cannot_view_database_pages(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        $this->actingAs($user)
            ->get(route('admin.database.backup'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.database.restore'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.database.backup.run'))
            ->assertForbidden();
    }

    private function createBackupFile(string $filename): string
    {
        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        File::put($path, '-- test backup');

        return $path;
    }
}
