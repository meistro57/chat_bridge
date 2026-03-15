import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

const STATUS_STYLES = {
    idle: 'text-zinc-400 bg-zinc-800',
    running: 'text-emerald-400 bg-emerald-900/30',
    paused: 'text-amber-400 bg-amber-900/30',
    completed: 'text-sky-400 bg-sky-900/30',
    failed: 'text-red-400 bg-red-900/30',
};

function StatusBadge({ status }) {
    const cls = STATUS_STYLES[status] ?? 'text-zinc-400 bg-zinc-800';
    return (
        <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium ${cls}`}>
            {status === 'running' && (
                <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
            )}
            {status}
        </span>
    );
}

export default function Index({ orchestrations }) {
    const handleDelete = (id, name) => {
        if (confirm(`Delete "${name}"? This cannot be undone.`)) {
            router.delete(route('orchestrator.destroy', id));
        }
    };

    const handleRun = (id) => {
        router.post(route('orchestrator.run', id));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Orchestrator" />

            <div className="min-h-screen text-zinc-100 p-6 md:p-12">
                <div className="max-w-7xl mx-auto space-y-8">
                    <div className="flex flex-col md:flex-row justify-between items-end pb-8 border-b border-white/5 gap-6">
                        <div>
                            <Link href="/chat" className="text-xs font-mono text-zinc-500 hover:text-white mb-2 block uppercase tracking-wide">
                                &larr; Return to Bridge
                            </Link>
                            <h1 className="text-4xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-zinc-400">
                                Orchestrator
                            </h1>
                            <p className="text-zinc-500 mt-2">Automate sequences of AI conversations with the wizard.</p>
                        </div>

                        <Link
                            href={route('orchestrator.wizard')}
                            className="group flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium transition-all hover:shadow-[0_0_20px_rgba(99,102,241,0.3)]"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                            New Orchestration
                        </Link>
                    </div>

                    {orchestrations.data.length === 0 ? (
                        <div className="text-center py-24 text-zinc-500">
                            <svg className="mx-auto mb-4 opacity-30" xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                            <p className="text-lg font-medium text-zinc-400">No orchestrations yet</p>
                            <p className="text-sm mt-1">Use the wizard to create your first automated pipeline.</p>
                            <Link href={route('orchestrator.wizard')} className="mt-6 inline-block text-indigo-400 hover:text-indigo-300 text-sm font-medium">
                                Start wizard &rarr;
                            </Link>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {orchestrations.data.map((orchestration) => (
                                <div key={orchestration.id} className="glass-panel rounded-2xl p-6 border border-white/10 flex flex-col md:flex-row md:items-center gap-4">
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-3">
                                            <Link
                                                href={route('orchestrator.show', orchestration.id)}
                                                className="font-semibold text-white hover:text-indigo-300 transition-colors truncate"
                                            >
                                                {orchestration.name}
                                            </Link>
                                            <StatusBadge status={orchestration.status} />
                                            {orchestration.is_scheduled && (
                                                <span className="text-xs text-violet-400 bg-violet-900/30 px-2 py-0.5 rounded-full">
                                                    Scheduled
                                                </span>
                                            )}
                                        </div>
                                        {orchestration.description && (
                                            <p className="text-sm text-zinc-400 mt-1 truncate">{orchestration.description}</p>
                                        )}
                                        <div className="flex items-center gap-4 mt-2 text-xs text-zinc-500">
                                            <span>{orchestration.runs_count} run{orchestration.runs_count !== 1 ? 's' : ''}</span>
                                            {orchestration.latest_run && (
                                                <span>Last run: <StatusBadge status={orchestration.latest_run.status} /></span>
                                            )}
                                            {orchestration.is_scheduled && orchestration.next_run_at && (
                                                <span>Next: {new Date(orchestration.next_run_at).toLocaleString()}</span>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2 shrink-0">
                                        <button
                                            onClick={() => handleRun(orchestration.id)}
                                            disabled={orchestration.status === 'running'}
                                            className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-700/40 hover:bg-emerald-700/60 text-emerald-300 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                            Run
                                        </button>
                                        <Link
                                            href={route('orchestrator.show', orchestration.id)}
                                            className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-700/40 hover:bg-zinc-700/60 text-zinc-300 text-sm font-medium transition-colors"
                                        >
                                            View
                                        </Link>
                                        <button
                                            onClick={() => handleDelete(orchestration.id, orchestration.name)}
                                            className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-900/20 hover:bg-red-900/40 text-red-400 text-sm font-medium transition-colors"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {orchestrations.last_page > 1 && (
                        <div className="flex justify-center gap-2 pt-4">
                            {orchestrations.links.map((link, i) => (
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
