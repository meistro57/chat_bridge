import { GlassCard } from '@/Components/ui/GlassCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function Metric({ label, value, tone = 'indigo' }) {
    const tones = {
        indigo: 'from-indigo-400 to-violet-400',
        emerald: 'from-emerald-400 to-teal-400',
        cyan: 'from-cyan-400 to-blue-400',
        amber: 'from-amber-400 to-orange-400',
        red: 'from-red-400 to-rose-400',
    };

    return (
        <div className="rounded-xl border border-white/10 bg-white/5 p-4">
            <div className="text-xs font-semibold uppercase tracking-wider text-zinc-400">{label}</div>
            <div className={`mt-2 bg-gradient-to-r bg-clip-text text-2xl font-bold text-transparent ${tones[tone] ?? tones.indigo}`}>
                {value ?? '—'}
            </div>
        </div>
    );
}

export default function Index({ snapshot: initialSnapshot }) {
    const [snapshot, setSnapshot] = useState(initialSnapshot);
    const [loading, setLoading] = useState(false);

    const refresh = async () => {
        setLoading(true);
        try {
            const response = await fetch(route('admin.redis.stats'));
            const data = await response.json();
            setSnapshot(data);
        } catch (error) {
            console.error('Failed to refresh redis stats', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const interval = setInterval(refresh, 10000);
        return () => clearInterval(interval);
    }, []);

    const redis = snapshot ?? {};
    const isConnected = Boolean(redis.connected);
    const statusClass = isConnected
        ? 'border-emerald-500/30 bg-emerald-500/15 text-emerald-200'
        : 'border-red-500/30 bg-red-500/15 text-red-200';

    return (
        <AuthenticatedLayout>
            <Head title="Redis Dashboard" />

            <div className="min-h-screen p-6 text-zinc-100 md:p-12">
                <div className="mx-auto max-w-7xl space-y-8">
                    <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                        <div>
                            <Link href={route('admin.boost.dashboard')} className="mb-2 block text-xs font-mono uppercase tracking-wide text-zinc-500 hover:text-white">
                                &larr; Back to Boost Dashboard
                            </Link>
                            <h1 className="bg-gradient-to-r from-white to-zinc-400 bg-clip-text text-4xl font-bold text-transparent">
                                Redis Dashboard
                            </h1>
                            <p className="mt-2 text-zinc-500">Live Redis health, memory, traffic, and keyspace visibility.</p>
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
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h2 className="text-lg font-semibold text-zinc-100">Connection</h2>
                            <span className={`inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wider ${statusClass}`}>
                                {isConnected ? 'Connected' : 'Unavailable'}
                            </span>
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Metric label="Client" value={redis.client ?? 'unknown'} tone="cyan" />
                            <Metric label="Host" value={`${redis.host ?? 'n/a'}:${redis.port ?? 'n/a'}`} tone="indigo" />
                            <Metric label="Database" value={redis.database ?? 'n/a'} tone="indigo" />
                            <Metric label="Ping" value={redis.ping_ms != null ? `${redis.ping_ms} ms` : 'n/a'} tone="emerald" />
                        </div>
                        {redis.error && (
                            <div className="rounded-xl border border-red-500/20 bg-red-500/10 p-3 text-sm text-red-200">
                                {redis.error}
                            </div>
                        )}
                    </GlassCard>

                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <GlassCard accent="emerald" className="space-y-4">
                            <h2 className="text-lg font-semibold text-zinc-100">Memory</h2>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <Metric label="Used" value={redis.memory?.used ?? 'n/a'} tone="emerald" />
                                <Metric label="Peak" value={redis.memory?.peak ?? 'n/a'} tone="amber" />
                                <Metric label="Fragmentation" value={redis.memory?.fragmentation ?? 'n/a'} tone="cyan" />
                            </div>
                        </GlassCard>

                        <GlassCard accent="violet" className="space-y-4">
                            <h2 className="text-lg font-semibold text-zinc-100">Cache Efficiency</h2>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <Metric label="Hits" value={redis.cache?.hits ?? 0} tone="emerald" />
                                <Metric label="Misses" value={redis.cache?.misses ?? 0} tone="red" />
                                <Metric label="Hit Rate" value={`${Number(redis.cache?.hit_rate_percent ?? 0).toFixed(2)}%`} tone="indigo" />
                            </div>
                        </GlassCard>
                    </div>

                    <GlassCard accent="cyan" className="space-y-4">
                        <h2 className="text-lg font-semibold text-zinc-100">Traffic</h2>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <Metric label="Ops/Sec" value={redis.traffic?.ops_per_sec ?? 0} tone="cyan" />
                            <Metric label="Commands Processed" value={redis.traffic?.total_commands_processed ?? 0} tone="indigo" />
                            <Metric label="Connections Received" value={redis.traffic?.total_connections_received ?? 0} tone="emerald" />
                            <Metric label="Expired Keys" value={redis.traffic?.expired_keys ?? 0} tone="amber" />
                            <Metric label="Evicted Keys" value={redis.traffic?.evicted_keys ?? 0} tone="red" />
                            <Metric label="Rejected Connections" value={redis.traffic?.rejected_connections ?? 0} tone="red" />
                        </div>
                    </GlassCard>

                    <GlassCard accent="orange" className="space-y-4">
                        <h2 className="text-lg font-semibold text-zinc-100">Keyspace</h2>
                        {Array.isArray(redis.keyspace) && redis.keyspace.length > 0 ? (
                            <div className="overflow-hidden rounded-xl border border-white/10">
                                <table className="min-w-full divide-y divide-white/10 text-sm">
                                    <thead className="bg-white/5 text-left text-xs uppercase tracking-wider text-zinc-400">
                                        <tr>
                                            <th className="px-4 py-3">DB</th>
                                            <th className="px-4 py-3">Keys</th>
                                            <th className="px-4 py-3">Expiring</th>
                                            <th className="px-4 py-3">Avg TTL (ms)</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-white/5">
                                        {redis.keyspace.map((row) => (
                                            <tr key={row.db} className="bg-zinc-950/40">
                                                <td className="px-4 py-3 font-mono text-zinc-200">{row.db}</td>
                                                <td className="px-4 py-3 text-zinc-300">{row.keys}</td>
                                                <td className="px-4 py-3 text-zinc-300">{row.expires}</td>
                                                <td className="px-4 py-3 text-zinc-400">{row.avg_ttl_ms}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-zinc-400">No keyspace data available yet.</p>
                        )}
                    </GlassCard>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
