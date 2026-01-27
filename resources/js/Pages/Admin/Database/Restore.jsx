import { GlassCard } from '@/Components/ui/GlassCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';

export default function Restore({
    backups = [],
    selectedBackup = null,
    restoreCommand = null,
}) {
    const { data, setData, post, processing } = useForm({
        filename: selectedBackup ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('admin.database.restore.run'));
    };

    const handleDelete = (filename) => {
        if (!confirm(`Delete backup "${filename}"?`)) {
            return;
        }

        router.delete(route('admin.database.backups.delete'), {
            data: { filename },
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Database Restore" />

            <div className="min-h-screen p-6 text-zinc-100 md:p-12">
                <div className="mx-auto max-w-5xl space-y-8">
                    <div className="space-y-2">
                        <h1 className="text-4xl font-bold text-zinc-100">
                            Database Restore
                        </h1>
                        <p className="text-zinc-400">
                            Select a backup and generate the exact restore
                            command for the running Docker services.
                        </p>
                    </div>

                    <GlassCard accent="indigo" className="space-y-5">
                        <div className="space-y-1">
                            <h2 className="text-lg font-semibold text-zinc-100">
                                Select Backup
                            </h2>
                            <p className="text-sm text-zinc-400">
                                Backups are read from
                                <span className="mx-1 rounded bg-white/5 px-1.5 py-0.5 font-mono text-xs text-zinc-200">
                                    storage/app/backups
                                </span>
                                .
                            </p>
                        </div>

                        {backups.length === 0 ? (
                            <p className="text-sm text-zinc-400">
                                No backups found. Create one on the backup page
                                first.
                            </p>
                        ) : (
                            <form onSubmit={submit} className="flex flex-col gap-4 md:flex-row md:items-end">
                                <div className="flex-1 space-y-2">
                                    <label className="text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                        Backup File
                                    </label>
                                    <select
                                        value={data.filename}
                                        onChange={(event) => setData('filename', event.target.value)}
                                        className="w-full rounded-xl border border-white/10 bg-zinc-950/70 px-4 py-3 text-sm text-zinc-100 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                                        required
                                    >
                                        {backups.map((backup) => (
                                            <option key={backup.filename} value={backup.filename}>
                                                {backup.filename}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-xl border border-indigo-500/30 bg-indigo-500/15 px-5 py-3 text-sm font-semibold text-indigo-200 transition hover:bg-indigo-500/25 disabled:opacity-60"
                                >
                                    {processing ? 'Preparing…' : 'Prepare Restore Command'}
                                </button>
                            </form>
                        )}
                    </GlassCard>

                    <GlassCard accent="red" className="space-y-4">
                        <h2 className="text-lg font-semibold text-zinc-100">
                            Restore Command
                        </h2>
                        <p className="text-sm text-zinc-400">
                            Run this from the project root on the host machine.
                        </p>
                        <pre className="overflow-x-auto rounded-xl border border-white/10 bg-zinc-950/80 p-4 text-sm text-zinc-200">
                            <code>
                                {restoreCommand ?? 'Select a backup above to generate the restore command.'}
                            </code>
                        </pre>
                    </GlassCard>

                    {backups.length > 0 && (
                        <GlassCard accent="orange" className="space-y-4">
                            <h2 className="text-lg font-semibold text-zinc-100">
                                Manage Backups
                            </h2>
                            <div className="overflow-hidden rounded-xl border border-white/10">
                                <table className="min-w-full divide-y divide-white/10 text-sm">
                                    <thead className="bg-white/5 text-left text-xs uppercase tracking-wider text-zinc-400">
                                        <tr>
                                            <th className="px-4 py-3">Filename</th>
                                            <th className="px-4 py-3">Size</th>
                                            <th className="px-4 py-3">Modified</th>
                                            <th className="px-4 py-3 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-white/5">
                                        {backups.map((backup) => (
                                            <tr key={backup.filename} className="bg-zinc-950/40">
                                                <td className="px-4 py-3 font-mono text-xs text-zinc-200">
                                                    {backup.filename}
                                                </td>
                                                <td className="px-4 py-3 text-zinc-300">
                                                    {backup.size_human}
                                                </td>
                                                <td className="px-4 py-3 text-zinc-400">
                                                    {new Date(backup.modified_at).toLocaleString()}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <button
                                                        type="button"
                                                        onClick={() => handleDelete(backup.filename)}
                                                        className="rounded-lg border border-red-500/30 bg-red-500/15 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-red-200 transition hover:bg-red-500/25"
                                                    >
                                                        Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </GlassCard>
                    )}

                    <GlassCard accent="orange" className="space-y-4">
                        <h2 className="text-lg font-semibold text-zinc-100">
                            Important
                        </h2>
                        <ul className="list-disc space-y-2 pl-5 text-sm text-zinc-300">
                            <li>
                                Restores will overwrite existing data when the
                                dump contains the same records.
                            </li>
                            <li>
                                Make a fresh backup before restoring into a
                                shared environment.
                            </li>
                        </ul>
                    </GlassCard>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
