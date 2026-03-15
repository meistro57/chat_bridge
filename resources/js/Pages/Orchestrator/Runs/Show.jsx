import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const STATUS_STYLES = {
    idle: 'text-zinc-400 bg-zinc-800',
    running: 'text-emerald-400 bg-emerald-900/30',
    paused: 'text-amber-400 bg-amber-900/30',
    completed: 'text-sky-400 bg-sky-900/30',
    failed: 'text-red-400 bg-red-900/30',
    queued: 'text-violet-400 bg-violet-900/30',
    skipped: 'text-zinc-500 bg-zinc-800',
    pending: 'text-zinc-400 bg-zinc-800',
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

function StepRunRow({ stepRun }) {
    const duration = stepRun.started_at && stepRun.completed_at
        ? Math.round((new Date(stepRun.completed_at) - new Date(stepRun.started_at)) / 1000)
        : null;

    return (
        <div className="rounded-xl border border-white/5 bg-zinc-900/40 p-4 space-y-2">
            <div className="flex items-center gap-3">
                <div className="w-7 h-7 rounded-full bg-indigo-700/40 border border-indigo-500/30 flex items-center justify-center text-indigo-300 text-xs font-bold shrink-0">
                    {stepRun.step?.step_number ?? '?'}
                </div>
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                        <span className="font-medium text-white text-sm">
                            {stepRun.step?.label || `Step ${stepRun.step?.step_number}`}
                        </span>
                        <StatusBadge status={stepRun.status} />
                        {stepRun.condition_passed === false && (
                            <span className="text-xs text-zinc-500">condition not met</span>
                        )}
                    </div>
                    <div className="flex items-center gap-4 mt-0.5 text-xs text-zinc-500">
                        {stepRun.started_at && <span>Started: {new Date(stepRun.started_at).toLocaleString()}</span>}
                        {duration !== null && <span>{duration}s</span>}
                    </div>
                </div>
                {stepRun.conversation && (
                    <Link
                        href={route('chat.show', stepRun.conversation.id)}
                        className="text-xs text-indigo-400 hover:text-indigo-300 shrink-0"
                    >
                        View conversation →
                    </Link>
                )}
            </div>

            {stepRun.output_summary && (
                <div className="ml-10 bg-zinc-800/60 rounded-lg p-3">
                    <p className="text-xs font-mono text-zinc-300 whitespace-pre-wrap line-clamp-5">{stepRun.output_summary}</p>
                </div>
            )}

            {stepRun.error_message && (
                <div className="ml-10 bg-red-900/20 border border-red-500/20 rounded-lg p-3">
                    <p className="text-xs text-red-400">{stepRun.error_message}</p>
                </div>
            )}
        </div>
    );
}

export default function RunShow({ run }) {
    const duration = run.started_at && run.completed_at
        ? Math.round((new Date(run.completed_at) - new Date(run.started_at)) / 1000)
        : null;

    return (
        <AuthenticatedLayout>
            <Head title={`Run — ${run.orchestration?.name}`} />

            <div className="min-h-screen text-zinc-100 p-6 md:p-12">
                <div className="max-w-4xl mx-auto space-y-8">
                    <div className="pb-6 border-b border-white/5">
                        <Link href={route('orchestrator.show', run.orchestration_id)} className="text-xs font-mono text-zinc-500 hover:text-white mb-2 block uppercase tracking-wide">
                            &larr; Back to {run.orchestration?.name}
                        </Link>
                        <div className="flex items-start gap-4">
                            <div className="flex-1">
                                <div className="flex items-center gap-3">
                                    <h1 className="text-2xl font-bold text-white">Run Details</h1>
                                    <StatusBadge status={run.status} />
                                </div>
                                <div className="flex flex-wrap gap-x-6 gap-y-1 mt-2 text-sm text-zinc-500">
                                    <span>Triggered by: <span className="text-zinc-400">{run.triggered_by}</span></span>
                                    {run.started_at && <span>Started: {new Date(run.started_at).toLocaleString()}</span>}
                                    {duration !== null && <span>Duration: {duration}s</span>}
                                </div>
                            </div>
                        </div>
                        {run.error_message && (
                            <div className="mt-4 bg-red-900/20 border border-red-500/20 rounded-xl p-4">
                                <p className="text-sm text-red-400">{run.error_message}</p>
                            </div>
                        )}
                    </div>

                    <div className="space-y-3">
                        <h2 className="text-lg font-semibold text-white">
                            Step Runs <span className="text-zinc-500 font-normal text-sm">({run.step_runs?.length ?? 0})</span>
                        </h2>
                        {(run.step_runs?.length ?? 0) === 0 ? (
                            <p className="text-zinc-500 text-sm">No steps ran yet.</p>
                        ) : (
                            run.step_runs.map((stepRun) => (
                                <StepRunRow key={stepRun.id} stepRun={stepRun} />
                            ))
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
