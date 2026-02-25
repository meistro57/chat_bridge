import { GlassCard } from '@/Components/ui/GlassCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';

function StatusPill({ present, error }) {
    const healthy = present && !error;
    const classes = healthy
        ? 'border-emerald-500/30 bg-emerald-500/15 text-emerald-200'
        : 'border-red-500/30 bg-red-500/15 text-red-200';

    return (
        <span className={`inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wider ${classes}`}>
            {healthy ? 'Loaded' : 'Issue'}
        </span>
    );
}

function StatItem({ label, value, loading }) {
    return (
        <div className="rounded-xl border border-white/10 bg-white/5 p-4 transition-all duration-300 hover:bg-white/10">
            <div className="text-xs font-semibold uppercase tracking-wider text-zinc-400">
                {label}
            </div>
            <div className={`mt-2 text-2xl font-bold transition-opacity ${loading ? 'opacity-50' : 'opacity-100'}`}>
                {loading ? (
                    <div className="h-8 w-16 animate-pulse rounded bg-white/10" />
                ) : (
                    <span className="text-zinc-100">{value ?? '—'}</span>
                )}
            </div>
        </div>
    );
}

function LiveStatItem({ label, value, gradient, loading }) {
    return (
        <div className="rounded-xl border border-white/10 bg-white/5 p-5 transition-all duration-300 hover:bg-white/10 hover:border-white/20">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-3">
                        {label}
                    </div>
                    {loading ? (
                        <div className="h-9 w-20 animate-pulse rounded bg-white/10" />
                    ) : (
                        <div className={`text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-br ${gradient}`}>
                            {value?.toLocaleString() ?? '—'}
                        </div>
                    )}
                </div>
                <div className={`w-2 h-2 rounded-full ${loading ? 'bg-yellow-400 animate-pulse' : 'bg-emerald-400'} shadow-lg`}
                     style={{ boxShadow: loading ? '0 0 10px rgba(250, 204, 21, 0.5)' : '0 0 10px rgba(52, 211, 153, 0.5)' }}
                />
            </div>
        </div>
    );
}

function ListCard({ title, items, accent }) {
    return (
        <GlassCard accent={accent} className="space-y-4">
            <div className="space-y-1">
                <h3 className="text-lg font-semibold text-zinc-100">{title}</h3>
                <p className="text-sm text-zinc-400">
                    Registered in <span className="font-mono text-xs text-zinc-300">boost.json</span>.
                </p>
            </div>

            {items.length === 0 ? (
                <p className="text-sm text-zinc-400">None configured.</p>
            ) : (
                <div className="flex flex-wrap gap-2">
                    {items.map((item) => (
                        <span
                            key={item}
                            className="rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-zinc-200"
                        >
                            {item}
                        </span>
                    ))}
                </div>
            )}
        </GlassCard>
    );
}

export default function BoostDashboard({ boost, liveStats: initialLiveStats }) {
    const agents = boost?.agents ?? [];
    const editors = boost?.editors ?? [];
    const [liveStats, setLiveStats] = useState(initialLiveStats);
    const [loading, setLoading] = useState(false);
    const [lastUpdated, setLastUpdated] = useState(new Date(initialLiveStats?.timestamp));

    const refreshStats = async () => {
        setLoading(true);
        try {
            const response = await fetch(route('boost.stats'));
            const data = await response.json();
            setLiveStats(data);
            setLastUpdated(new Date(data.timestamp));
        } catch (error) {
            console.error('Failed to refresh stats:', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        // Auto-refresh every 30 seconds
        const interval = setInterval(refreshStats, 30000);
        return () => clearInterval(interval);
    }, []);

    const formatTimestamp = (date) => {
        const now = new Date();
        const diffMs = now - date;
        const diffSecs = Math.floor(diffMs / 1000);

        if (diffSecs < 60) return `${diffSecs}s ago`;
        const diffMins = Math.floor(diffSecs / 60);
        if (diffMins < 60) return `${diffMins}m ago`;
        const diffHours = Math.floor(diffMins / 60);
        return `${diffHours}h ago`;
    };

    return (
        <AuthenticatedLayout>
            <Head title="Boost Dashboard" />

            <div className="min-h-screen p-6 text-zinc-100 md:p-12">
                <div className="mx-auto max-w-6xl space-y-8">
                    <div className="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                        <div className="space-y-2">
                            <h1 className="text-4xl font-bold text-zinc-100">Boost Dashboard</h1>
                            <p className="text-zinc-400">
                                Live stats, Laravel Boost status, and MCP configuration.
                            </p>
                        </div>
                        <div className="flex items-center gap-3">
                            <span className="text-xs text-zinc-500">
                                Updated {formatTimestamp(lastUpdated)}
                            </span>
                            <button
                                onClick={refreshStats}
                                disabled={loading}
                                className="group flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 disabled:bg-indigo-800 text-white px-4 py-2.5 rounded-xl font-medium transition-all hover:shadow-[0_0_20px_rgba(99,102,241,0.3)] disabled:cursor-not-allowed"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="16"
                                    height="16"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    className={loading ? 'animate-spin' : 'group-hover:rotate-180 transition-transform duration-500'}
                                >
                                    <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
                                </svg>
                                {loading ? 'Refreshing...' : 'Refresh'}
                            </button>
                        </div>
                    </div>

                    <GlassCard accent="violet" className="space-y-6">
                        <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div className="space-y-1">
                                <h2 className="text-lg font-semibold text-zinc-100">Live System Stats</h2>
                                <p className="text-sm text-zinc-400">
                                    Real-time database counts and MCP health status.
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className={`w-2 h-2 rounded-full ${liveStats?.mcp_health?.ok ? 'bg-emerald-400' : 'bg-red-400'} animate-pulse`} />
                                <span className="text-xs font-semibold uppercase tracking-wider text-zinc-300">
                                    MCP {liveStats?.mcp_health?.ok ? 'Healthy' : 'Down'}
                                </span>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                            <LiveStatItem
                                label="Conversations"
                                value={liveStats?.conversations}
                                gradient="from-blue-400 to-cyan-400"
                                loading={loading}
                            />
                            <LiveStatItem
                                label="Messages"
                                value={liveStats?.messages}
                                gradient="from-purple-400 to-pink-400"
                                loading={loading}
                            />
                            <LiveStatItem
                                label="Personas"
                                value={liveStats?.personas}
                                gradient="from-emerald-400 to-teal-400"
                                loading={loading}
                            />
                            <LiveStatItem
                                label="Users"
                                value={liveStats?.users}
                                gradient="from-orange-400 to-red-400"
                                loading={loading}
                            />
                            <LiveStatItem
                                label="Embeddings"
                                value={liveStats?.embeddings}
                                gradient="from-indigo-400 to-violet-400"
                                loading={loading}
                            />
                        </div>
                    </GlassCard>

                    <GlassCard accent="cyan" className="space-y-6">
                        <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div className="space-y-1">
                                <h2 className="text-lg font-semibold text-zinc-100">Boost Configuration</h2>
                                <p className="text-sm text-zinc-400">
                                    Derived from the installed package plus <span className="font-mono text-xs text-zinc-300">boost.json</span>.
                                </p>
                            </div>
                            <StatusPill present={Boolean(boost?.present)} error={boost?.error} />
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <StatItem label="Boost Version" value={boost?.version} />
                            <StatItem label="MCP Mode" value={boost?.mcp_mode ?? 'unknown'} />
                            <StatItem label="Vector Search" value={boost?.vector_search ? 'enabled' : 'unavailable'} />
                            <StatItem label="boost.json" value={boost?.present ? 'present' : 'missing'} />
                        </div>

                        {boost?.error && (
                            <div className="rounded-xl border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-200">
                                {boost.error}
                            </div>
                        )}
                    </GlassCard>

                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <ListCard title="Agents" items={agents} accent="indigo" />
                        <ListCard title="Editors" items={editors} accent="purple" />
                    </div>

                    <GlassCard accent="orange" className="space-y-4">
                        <h2 className="text-lg font-semibold text-zinc-100">Quick Actions</h2>
                        <ul className="grid grid-cols-1 gap-3 text-sm text-zinc-300 md:grid-cols-2">
                            <li className="rounded-xl border border-white/10 bg-white/5 p-4 hover:bg-white/10 transition-colors">
                                <a href="/admin/mcp-utilities" className="flex items-center justify-between group">
                                    <span>View MCP Endpoints</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="group-hover:translate-x-1 transition-transform">
                                        <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                                    </svg>
                                </a>
                            </li>
                            <li className="rounded-xl border border-white/10 bg-white/5 p-4 hover:bg-white/10 transition-colors">
                                <a href="/admin/system" className="flex items-center justify-between group">
                                    <span>System Diagnostics</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="group-hover:translate-x-1 transition-transform">
                                        <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                                    </svg>
                                </a>
                            </li>
                            <li className="rounded-xl border border-white/10 bg-white/5 p-4 hover:bg-white/10 transition-colors">
                                <a href="/analytics" className="flex items-center justify-between group">
                                    <span>View Analytics</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="group-hover:translate-x-1 transition-transform">
                                        <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                                    </svg>
                                </a>
                            </li>
                            <li className="rounded-xl border border-white/10 bg-white/5 p-4 hover:bg-white/10 transition-colors">
                                <a href="/admin/performance" className="flex items-center justify-between group">
                                    <span>Performance Monitor</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="group-hover:translate-x-1 transition-transform">
                                        <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                                    </svg>
                                </a>
                            </li>
                            <li className="rounded-xl border border-white/10 bg-white/5 p-4 hover:bg-white/10 transition-colors">
                                <button onClick={refreshStats} className="w-full flex items-center justify-between group">
                                    <span>Refresh All Stats</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="group-hover:rotate-180 transition-transform duration-500">
                                        <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
                                    </svg>
                                </button>
                            </li>
                        </ul>
                    </GlassCard>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
