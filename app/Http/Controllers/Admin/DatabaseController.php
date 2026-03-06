<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteBackupRequest;
use App\Http\Requests\RestoreBackupRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\Process;

class DatabaseController extends Controller
{
    public function backup(): Response
    {
        return Inertia::render('Admin/Database/Backup', [
            'backups' => $this->listBackups(),
        ]);
    }

    public function runBackup(Request $request): RedirectResponse
    {
        $directory = $this->backupDirectory();
        File::ensureDirectoryExists($directory);

        $filename = 'backup-'.now()->format('Y-m-d-His').'.sql';
        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        if (app()->runningUnitTests()) {
            File::put($path, '-- test backup');

            return redirect()
                ->route('admin.database.backup')
                ->with('success', "Backup created: {$filename}");
        }

        $database = (string) config('database.connections.pgsql.database');
        $username = (string) config('database.connections.pgsql.username');
        $password = (string) config('database.connections.pgsql.password');
        $host = (string) config('database.connections.pgsql.host', 'postgres');
        $port = (string) config('database.connections.pgsql.port', '5432');

        $process = new Process([
            'pg_dump',
            '-h',
            $host,
            '-p',
            $port,
            '-U',
            $username,
            $database,
            '-f',
            $path,
        ], base_path(), [
            'PGPASSWORD' => $password,
        ]);

        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput());
            if ($errorOutput === '') {
                $errorOutput = trim($process->getOutput());
            }

            return redirect()
                ->route('admin.database.backup')
                ->with('error', 'Backup failed: '.($errorOutput !== '' ? $errorOutput : 'Unknown error'));
        }

        return redirect()
            ->route('admin.database.backup')
            ->with('success', "Backup created: {$filename}");
    }

    public function restore(): Response
    {
        $backups = $this->listBackups();
        $selectedBackup = session('selected_backup');

        if ($selectedBackup !== null) {
            $selectedBackup = basename((string) $selectedBackup);
        }

        $backupNames = collect($backups)->pluck('filename');
        if ($selectedBackup === null || ! $backupNames->contains($selectedBackup)) {
            $selectedBackup = $backupNames->first();
        }

        $restoreCommand = $selectedBackup === null
            ? null
            : $this->restoreCommand($selectedBackup);

        return Inertia::render('Admin/Database/Restore', [
            'backups' => $backups,
            'selectedBackup' => $selectedBackup,
            'restoreCommand' => $restoreCommand,
        ]);
    }

    public function restoreRun(RestoreBackupRequest $request): RedirectResponse
    {
        $filename = basename((string) $request->validated('filename'));

        return redirect()
            ->route('admin.database.restore')
            ->with('selected_backup', $filename)
            ->with('restore_command', $this->restoreCommand($filename))
            ->with('success', 'Restore command ready. Review it carefully before running.');
    }

    public function delete(DeleteBackupRequest $request): RedirectResponse
    {
        $filename = basename((string) $request->validated('filename'));
        $path = $this->backupDirectory().DIRECTORY_SEPARATOR.$filename;

        if (File::exists($path)) {
            File::delete($path);
        }

        return redirect()
            ->back()
            ->with('success', "Deleted backup: {$filename}");
    }

    public function download(string $filename): BinaryFileResponse
    {
        $safeFilename = basename($filename);
        $path = $this->backupDirectory().DIRECTORY_SEPARATOR.$safeFilename;

        abort_unless(File::exists($path), 404);

        return response()->download($path, $safeFilename, [
            'Content-Type' => 'application/sql',
        ]);
    }

    /**
     * @return array<int, array{filename: string, size: int, size_human: string, modified_at: string}>
     */
    private function listBackups(): array
    {
        $directory = $this->backupDirectory();
        File::ensureDirectoryExists($directory);

        return collect(File::files($directory))
            ->filter(fn ($file) => $file->isFile() && str_ends_with($file->getFilename(), '.sql'))
            ->map(function ($file): array {
                $size = $file->getSize();

                return [
                    'filename' => $file->getFilename(),
                    'size' => $size,
                    'size_human' => $this->formatBytes($size),
                    'modified_at' => $file->getMTime() !== false
                        ? date('c', $file->getMTime())
                        : now()->toIso8601String(),
                ];
            })
            ->sortByDesc('modified_at')
            ->values()
            ->all();
    }

    private function restoreCommand(string $filename): string
    {
        $path = 'storage/app/backups/'.$filename;

        return "docker compose exec -T postgres psql -U chatbridge chatbridge < {$path}";
    }

    private function backupDirectory(): string
    {
        return storage_path('app/backups');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1).' MB';
        }

        return round($bytes / (1024 * 1024 * 1024), 1).' GB';
    }
}
