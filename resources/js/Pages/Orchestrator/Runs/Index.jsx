import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const STATUS_STYLES = {
    queued: 'text-violet-400 bg-violet-900/30',
    running: 'text-emerald-400 bg-emerald-900/30',
    paused: 'text-amber-400 bg-amber-900/30',
    completed: 'text-sky-400 bg-sky-900/30',
    failed: 'text-red-400 bg-red-900/30',
};

function StatusBadge({ status }) {
    const cls = STATUS_STYLES[status] ?? 'text-zinc-400 bg-zinc-800';
    return (
        <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium ${cls}`}>
            {status === 'running' && <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />}
            {status}
        </span>
    );
}

export default function RunsIndex({ orchestration, runs }) {
    return (
        <AuthenticatedLayout>
            <Head title={`Runs — ${orchestration.name}`} />

            <div className="min-h-screen text-zinc-100 p-6 md:p-12">
                <div className="max-w-5xl mx-auto space-y-8">
                    <div className="pb-6 border-b border-white/5">
                        <Link href={route('orchestrator.show', orchestration.id)} className="text-xs font-mono text-zinc-500 hover:text-white mb-2 block uppercase tracking-wide">
                            &larr; Back to {orchestration.name}
                        </Link>
                        <h1 className="text-2xl font-bold text-white">All Runs</h1>
                        <p className="text-zinc-500 text-sm mt-1">{runs.total} run{runs.total !== 1 ? 's' : ''} total</p>
                    </div>

                    {runs.data.length === 0 ? (
                        <p className="text-zinc-500 text-sm">No runs yet.</p>
                    ) : (
                        <div className="space-y-2">
                            {runs.data.map((run) => {
                                const duration = run.started_at && run.completed_at
                                    ? Math.round((new Date(run.completed_at) - new Date(run.started_at)) / 1000)
                                    : null;

                                return (
                                    <div key={run.id} className="flex items-center gap-4 px-4 py-3 rounded-xl border border-white/5 bg-zinc-900/40">
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2">
                                                <StatusBadge status={run.status} />
                                                <span className="text-xs text-zinc-500">{run.triggered_by}</span>
                                            </div>
                                            <p className="text-xs text-zinc-500 mt-1">
                                                {run.started_at ? new Date(run.started_at).toLocaleString() : 'Not started'}
                                                {duration !== null && ` · ${duration}s`}
                                            </p>
                                            {run.error_message && (
                                                <p className="text-xs text-red-400 mt-1 truncate">{run.error_message}</p>
                                            )}
                                        </div>
                                        <Link
                                            href={route('orchestrator.runs.show', run.id)}
                                            className="text-xs text-indigo-400 hover:text-indigo-300 shrink-0"
                                        >
                                            Details →
                                        </Link>
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    {runs.last_page > 1 && (
                        <div className="flex justify-center gap-2 pt-4">
                            {runs.links.map((link, i) => (
                                link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        className={`px-3 py-1.5 rounded-lg text-sm ${link.active ? 'bg-indigo-600 text-white' : 'bg-zinc-800 text-zinc-400 hover:text-white'}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span key={i} className="px-3 py-1.5 rounded-lg text-sm text-zinc-600" dangerouslySetInnerHTML={{ __html: link.label }} />
                                )
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
