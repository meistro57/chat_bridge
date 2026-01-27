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
