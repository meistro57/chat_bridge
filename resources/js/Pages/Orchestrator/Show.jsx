import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

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

function StepRow({ step, index }) {
    return (
        <div className="flex items-start gap-4 p-4 rounded-xl border border-white/5 bg-zinc-900/40">
            <div className="w-8 h-8 rounded-full bg-indigo-700/40 border border-indigo-500/30 flex items-center justify-center text-indigo-300 text-sm font-bold shrink-0">
                {step.step_number}
            </div>
            <div className="flex-1 min-w-0">
                <p className="font-medium text-white">{step.label || `Step ${step.step_number}`}</p>
                <div className="flex flex-wrap gap-x-4 gap-y-1 mt-1 text-xs text-zinc-500">
                    {step.template_id && <span>Template #{step.template_id}</span>}
                    {step.provider_a && <span>Provider A: {step.provider_a}</span>}
                    {step.provider_b && <span>Provider B: {step.provider_b}</span>}
                    <span>Input: {step.input_source}</span>
                    <span>Output: {step.output_action}</span>
                    {step.pause_before_run && <span className="text-amber-400">⏸ Pauses before run</span>}
                </div>
                {step.input_value && (
                    <p className="mt-2 text-xs text-zinc-400 truncate">{step.input_value}</p>
                )}
            </div>
        </div>
    );
}

function RunRow({ run }) {
    const duration = run.started_at && run.completed_at
        ? Math.round((new Date(run.completed_at) - new Date(run.started_at)) / 1000)
        : null;

    return (
        <div className="flex items-center gap-4 px-4 py-3 rounded-xl border border-white/5 bg-zinc-900/40">
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
}

export default function Show({ orchestration, runs }) {
    const [streamToDiscord, setStreamToDiscord] = useState(Boolean(orchestration.metadata?.discord_streaming_enabled ?? false));
    const [streamToDiscourse, setStreamToDiscourse] = useState(Boolean(orchestration.metadata?.discourse_streaming_enabled ?? false));

    useEffect(() => {
        setStreamToDiscord(Boolean(orchestration.metadata?.discord_streaming_enabled ?? false));
        setStreamToDiscourse(Boolean(orchestration.metadata?.discourse_streaming_enabled ?? false));
    }, [orchestration.metadata]);

    const updateStreamingFlags = (discordEnabled, discourseEnabled) => {
        router.put(route('orchestrator.update', orchestration.id), {
            discord_streaming_enabled: discordEnabled,
            discourse_streaming_enabled: discourseEnabled,
        }, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const handleRun = () => router.post(route('orchestrator.run', orchestration.id));
    const handlePause = () => router.post(route('orchestrator.pause', orchestration.id));
    const handleDelete = () => {
        if (confirm(`Delete "${orchestration.name}"?`)) {
            router.delete(route('orchestrator.destroy', orchestration.id));
        }
    };

    const isRunning = orchestration.status === 'running';

    return (
        <AuthenticatedLayout>
            <Head title={orchestration.name} />

            <div className="min-h-screen text-zinc-100 p-6 md:p-12">
                <div className="max-w-5xl mx-auto space-y-8">
                    <div className="pb-6 border-b border-white/5">
                        <Link href={route('orchestrator.index')} className="text-xs font-mono text-zinc-500 hover:text-white mb-2 block uppercase tracking-wide">
                            &larr; Back to Orchestrator
                        </Link>
                        <div className="flex flex-col md:flex-row md:items-start gap-4">
                            <div className="flex-1">
                                <div className="flex items-center gap-3">
                                    <h1 className="text-3xl font-bold text-white">{orchestration.name}</h1>
                                    <StatusBadge status={orchestration.status} />
                                    {orchestration.is_scheduled && (
                                        <span className="text-xs text-violet-400 bg-violet-900/30 px-2 py-0.5 rounded-full">Scheduled</span>
                                    )}
                                </div>
                                {orchestration.description && (
                                    <p className="text-zinc-400 mt-2">{orchestration.description}</p>
                                )}
                                {orchestration.goal && (
                                    <p className="text-zinc-500 text-sm mt-1 italic">{orchestration.goal}</p>
                                )}
                            </div>
                            <div className="flex items-center gap-2 shrink-0">
                                {isRunning ? (
                                    <button onClick={handlePause} className="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-amber-700/40 hover:bg-amber-700/60 text-amber-300 text-sm font-medium transition-colors">
                                        Pause
                                    </button>
                                ) : (
                                    <button onClick={handleRun} className="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-700/40 hover:bg-emerald-700/60 text-emerald-300 text-sm font-medium transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                        Run Now
                                    </button>
                                )}
                                <button onClick={handleDelete} className="px-4 py-2 rounded-xl bg-red-900/20 hover:bg-red-900/40 text-red-400 text-sm font-medium transition-colors">
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <h2 className="text-lg font-semibold text-white">Streaming</h2>
                        <div className="space-y-2 rounded-xl border border-white/10 bg-zinc-900/40 p-4">
                            <label className="flex items-center gap-2 text-sm text-zinc-300">
                                <input
                                    type="checkbox"
                                    checked={streamToDiscord}
                                    onChange={(event) => {
                                        const nextValue = event.target.checked;
                                        setStreamToDiscord(nextValue);
                                        updateStreamingFlags(nextValue, streamToDiscourse);
                                    }}
                                    className="h-4 w-4 rounded border-white/20 bg-zinc-900 text-indigo-500 focus:ring-indigo-500/50"
                                />
                                Stream each step chat to Discord
                            </label>
                            <label className="flex items-center gap-2 text-sm text-zinc-300">
                                <input
                                    type="checkbox"
                                    checked={streamToDiscourse}
                                    onChange={(event) => {
                                        const nextValue = event.target.checked;
                                        setStreamToDiscourse(nextValue);
                                        updateStreamingFlags(streamToDiscord, nextValue);
                                    }}
                                    className="h-4 w-4 rounded border-white/20 bg-zinc-900 text-indigo-500 focus:ring-indigo-500/50"
                                />
                                Stream each step chat to Discourse
                            </label>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <h2 className="text-lg font-semibold text-white">Steps</h2>
                        {orchestration.steps?.length === 0 ? (
                            <p className="text-zinc-500 text-sm">No steps configured.</p>
                        ) : (
                            <div className="space-y-3">
                                {orchestration.steps?.map((step, i) => (
                                    <StepRow key={step.id} step={step} index={i} />
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-semibold text-white">Run History</h2>
                            <Link href={route('orchestrator.runs.index', orchestration.id)} className="text-xs text-indigo-400 hover:text-indigo-300">
                                All runs →
                            </Link>
                        </div>
                        {runs.data.length === 0 ? (
                            <p className="text-zinc-500 text-sm">No runs yet.</p>
                        ) : (
                            <div className="space-y-2">
                                {runs.data.map((run) => <RunRow key={run.id} run={run} />)}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
