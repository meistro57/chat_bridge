import { GlassCard } from '@/Components/ui/GlassCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function MetricCard({ label, value, tone = 'indigo' }) {
    const tones = {
        indigo: 'from-indigo-400 to-violet-400',
        emerald: 'from-emerald-400 to-teal-400',
        cyan: 'from-cyan-400 to-blue-400',
        violet: 'from-violet-400 to-fuchsia-400',
        amber: 'from-amber-400 to-orange-400',
        red: 'from-red-400 to-rose-400',
    };

    return (
        <div className="rounded-xl border border-white/10 bg-white/5 p-4">
            <div className="text-xs font-semibold uppercase tracking-wider text-zinc-400">{label}</div>
            <div className={`mt-2 bg-gradient-to-r bg-clip-text text-2xl font-bold text-transparent ${tones[tone] ?? tones.indigo}`}>
                {value}
            </div>
        </div>
    );
}

function formatMs(value) {
    return `${Number(value ?? 0).toFixed(2)} ms`;
}

export default function PerformanceMonitor({ snapshot: initialSnapshot }) {
    const [snapshot, setSnapshot] = useState(initialSnapshot);
    const [loading, setLoading] = useState(false);

    const refresh = async () => {
        setLoading(true);

        try {
            const response = await fetch(route('admin.performance.stats'));
            const data = await response.json();
            setSnapshot(data);
        } catch (error) {
            console.error('Failed to load performance stats', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const interval = setInterval(refresh, 10000);

        return () => clearInterval(interval);
    }, []);

    const windowStats = snapshot?.window ?? {};
    const queue = snapshot?.queue ?? {};
    const runtime = snapshot?.runtime ?? {};
    const routes = snapshot?.route_breakdown ?? [];
    const slowRequests = snapshot?.recent_slow_requests ?? [];
    const throughput = snapshot?.throughput ?? [];

    const throughputMax = Math.max(1, ...throughput.map((entry) => entry.count ?? 0));

    return (
        <AuthenticatedLayout>
            <Head title="Performance Monitor" />

            <div className="min-h-screen p-6 text-zinc-100 md:p-12">
                <div className="mx-auto max-w-7xl space-y-8">
                    <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                        <div>
                            <Link href={route('admin.boost.dashboard')} className="mb-2 block text-xs font-mono uppercase tracking-wide text-zinc-500 hover:text-white">
                                &larr; Back to Boost Dashboard
                            </Link>
                            <h1 className="bg-gradient-to-r from-white to-zinc-400 bg-clip-text text-4xl font-bold text-transparent">
                                Performance Monitor
                            </h1>
                            <p className="mt-2 text-zinc-500">Live request latency, DB query timing, throughput, error rates, and queue health.</p>
                        </div>

                        <button
                            type="button"
                            onClick={refresh}
                            disabled={loading}
                            className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {loading ? 'Refreshing...' : 'Refresh Now'}
                        </button>
                    </div>

                    <GlassCard accent="indigo" className="space-y-4">
                        <h2 className="text-lg font-semibold text-zinc-100">Request Window (Last 5 Minutes)</h2>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <MetricCard label="Req/Min" value={windowStats.requests_last_minute ?? 0} tone="cyan" />
                            <MetricCard label="Avg Response" value={formatMs(windowStats.avg_response_ms)} tone="indigo" />
                            <MetricCard label="P95 Response" value={formatMs(windowStats.p95_response_ms)} tone="amber" />
                            <MetricCard label="Error Rate" value={`${Number(windowStats.error_rate_percent ?? 0).toFixed(2)}%`} tone="red" />
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <MetricCard label="Avg DB Time" value={formatMs(windowStats.avg_db_total_query_ms)} tone="violet" />
                            <MetricCard label="P95 DB Time" value={formatMs(windowStats.p95_db_total_query_ms)} tone="amber" />
                            <MetricCard label="Avg Query Count" value={Number(windowStats.avg_db_query_count ?? 0).toFixed(2)} tone="cyan" />
                            <MetricCard label="Slow Query Req" value={`${Number(windowStats.slow_query_request_rate_percent ?? 0).toFixed(2)}%`} tone="red" />
                        </div>
                    </GlassCard>

                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <GlassCard accent="cyan" className="space-y-4">
                            <h2 className="text-lg font-semibold text-zinc-100">Throughput (15 min)</h2>
                            <div className="flex h-44 items-end gap-2 rounded-xl border border-white/10 bg-zinc-950/50 p-4">
                                {throughput.map((entry) => {
                                    const heightPercent = Math.max(6, Math.round(((entry.count ?? 0) / throughputMax) * 100));

                                    return (
                                        <div key={entry.minute} className="flex flex-1 flex-col items-center gap-1">
                                            <div className="text-[10px] text-zinc-500">{entry.count}</div>
                                            <div
                                                className="w-full rounded-t bg-gradient-to-t from-indigo-600/80 to-cyan-400/80"
                                                style={{ height: `${heightPercent}%` }}
                                            />
                                            <div className="text-[10px] text-zinc-500">{entry.minute}</div>
                                        </div>
                                    );
                                })}
                            </div>
                        </GlassCard>

                        <GlassCard accent="emerald" className="space-y-4">
                            <h2 className="text-lg font-semibold text-zinc-100">Queue Health</h2>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <MetricCard label="Queued Jobs" value={queue.queued_jobs ?? 0} tone="amber" />
                                <MetricCard label="Failed Jobs" value={queue.failed_jobs ?? 0} tone="red" />
                                <MetricCard label="DB Driver" value={runtime.db_connection ?? 'unknown'} tone="indigo" />
                                <MetricCard label="Cache Driver" value={runtime.cache_driver ?? 'unknown'} tone="cyan" />
                            </div>
                        </GlassCard>
                    </div>

                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <GlassCard accent="violet" className="space-y-4">
                            <h2 className="text-lg font-semibold text-zinc-100">Slowest Routes</h2>
                            <div className="space-y-3">
                                {routes.length > 0 ? routes.map((entry) => (
                                    <div key={entry.path} className="rounded-xl border border-white/10 bg-white/5 p-3">
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="font-mono text-xs text-indigo-200">{entry.path}</span>
                                            <span className="text-xs text-zinc-400">{entry.count} req</span>
                                        </div>
                                        <div className="mt-2 grid grid-cols-3 gap-2 text-xs text-zinc-400">
                                            <span>avg {formatMs(entry.avg_ms)}</span>
                                            <span>p95 {formatMs(entry.p95_ms)}</span>
                                            <span>max {formatMs(entry.max_ms)}</span>
                                        </div>
                                    </div>
                                )) : (
                                    <p className="text-sm text-zinc-500">No samples yet. Use the app, then refresh.</p>
                                )}
                            </div>
                        </GlassCard>

                        <GlassCard accent="orange" className="space-y-4">
                            <h2 className="text-lg font-semibold text-zinc-100">Recent Slow Requests (&gt; 1s)</h2>
                            <div className="space-y-3">
                                {slowRequests.length > 0 ? slowRequests.map((entry, index) => (
                                    <div key={`${entry.path}-${index}`} className="rounded-xl border border-white/10 bg-white/5 p-3">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="font-mono text-xs text-zinc-300">{entry.method} {entry.path}</span>
                                            <span className="text-xs font-semibold text-amber-300">{formatMs(entry.duration_ms)}</span>
                                        </div>
                                        <div className="mt-1 text-xs text-zinc-500">
                                            status {entry.status} • {new Date(entry.timestamp).toLocaleTimeString()}
                                        </div>
                                    </div>
                                )) : (
                                    <p className="text-sm text-zinc-500">No slow requests recorded in the current sample window.</p>
                                )}
                            </div>
                        </GlassCard>
                    </div>

                    <GlassCard accent="purple" className="space-y-4">
                        <h2 className="text-lg font-semibold text-zinc-100">Recent Slow Queries (&gt;= 100ms)</h2>
                        <div className="space-y-3">
                            {(snapshot?.recent_slow_queries ?? []).length > 0 ? (snapshot?.recent_slow_queries ?? []).map((entry, index) => (
                                <div key={`${entry.timestamp}-${index}`} className="rounded-xl border border-white/10 bg-white/5 p-3">
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="font-mono text-xs text-zinc-300">{entry.path}</span>
                                        <span className="text-xs font-semibold text-amber-300">{formatMs(entry.time_ms)}</span>
                                    </div>
                                    <div className="mt-2 overflow-x-auto rounded-md border border-white/10 bg-zinc-950/50 p-2 font-mono text-[11px] text-zinc-400">
                                        {entry.sql}
                                    </div>
                                </div>
                            )) : (
                                <p className="text-sm text-zinc-500">No slow queries recorded yet.</p>
                            )}
                        </div>
                    </GlassCard>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
