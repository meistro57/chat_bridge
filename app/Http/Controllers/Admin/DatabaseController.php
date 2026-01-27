<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteBackupRequest;
use App\Http\Requests\RestoreBackupRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class DatabaseController extends Controller
{
    public function backup(): Response
    {
        return Inertia::render('Admin/Database/Backup', [
            'backups' => $this->listBackups(),
        ]);
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
