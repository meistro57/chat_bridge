import { GlassCard } from '@/Components/ui/GlassCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Backup({ backups = [] }) {
    return (
        <AuthenticatedLayout>
            <Head title="Database Backup" />

            <div className="min-h-screen p-6 text-zinc-100 md:p-12">
                <div className="mx-auto max-w-5xl space-y-8">
                    <div className="space-y-2">
                        <h1 className="text-4xl font-bold text-zinc-100">
                            Database Backup
                        </h1>
                        <p className="text-zinc-400">
                            Use these commands to create a PostgreSQL backup
                            from the running Docker services.
                        </p>
                    </div>

                    <GlassCard accent="emerald" className="space-y-4">
                        <h2 className="text-lg font-semibold text-zinc-100">
                            Backup Command
                        </h2>
                        <p className="text-sm text-zinc-400">
                            Run this from the project root on the host machine.
                        </p>
                        <pre className="overflow-x-auto rounded-xl border border-white/10 bg-zinc-950/80 p-4 text-sm text-zinc-200">
                            <code>
                                docker compose exec postgres pg_dump -U
                                chatbridge chatbridge &gt;
                                storage/app/backups/backup.sql
                            </code>
                        </pre>
                    </GlassCard>

                    <GlassCard accent="indigo" className="space-y-4">
                        <h2 className="text-lg font-semibold text-zinc-100">
                            Existing Backups
                        </h2>
                        {backups.length === 0 ? (
                            <p className="text-sm text-zinc-400">
                                No backups found in
                                <span className="mx-1 rounded bg-white/5 px-1.5 py-0.5 font-mono text-xs text-zinc-200">
                                    storage/app/backups
                                </span>
                                .
                            </p>
                        ) : (
                            <div className="overflow-hidden rounded-xl border border-white/10">
                                <table className="min-w-full divide-y divide-white/10 text-sm">
                                    <thead className="bg-white/5 text-left text-xs uppercase tracking-wider text-zinc-400">
                                        <tr>
                                            <th className="px-4 py-3">Filename</th>
                                            <th className="px-4 py-3">Size</th>
                                            <th className="px-4 py-3">Modified</th>
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
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </GlassCard>

                    <GlassCard accent="cyan" className="space-y-4">
                        <h2 className="text-lg font-semibold text-zinc-100">
                            Notes
                        </h2>
                        <ul className="list-disc space-y-2 pl-5 text-sm text-zinc-300">
                            <li>
                                This creates a plain SQL dump inside
                                <span className="mx-1 rounded bg-white/5 px-1.5 py-0.5 font-mono text-xs text-zinc-200">
                                    storage/app/backups
                                </span>
                                that will appear here and on the restore page.
                            </li>
                            <li>
                                Consider storing backups outside the repository
                                directory.
                            </li>
                        </ul>
                    </GlassCard>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
