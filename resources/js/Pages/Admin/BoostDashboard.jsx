import { GlassCard } from '@/Components/ui/GlassCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

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

function StatItem({ label, value }) {
    return (
        <div className="rounded-xl border border-white/10 bg-white/5 p-4">
            <div className="text-xs font-semibold uppercase tracking-wider text-zinc-400">
                {label}
            </div>
            <div className="mt-2 text-2xl font-bold text-zinc-100">
                {value ?? '—'}
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

export default function BoostDashboard({ boost }) {
    const agents = boost?.agents ?? [];
    const editors = boost?.editors ?? [];

    return (
        <AuthenticatedLayout>
            <Head title="Boost Dashboard" />

            <div className="min-h-screen p-6 text-zinc-100 md:p-12">
                <div className="mx-auto max-w-6xl space-y-8">
                    <div className="space-y-2">
                        <h1 className="text-4xl font-bold text-zinc-100">Boost Dashboard</h1>
                        <p className="text-zinc-400">
                            Showcase Laravel Boost status, version, and the exact agent/editor surface it exposes.
                        </p>
                    </div>

                    <GlassCard accent="violet" className="space-y-6">
                        <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div className="space-y-1">
                                <h2 className="text-lg font-semibold text-zinc-100">Boost Overview</h2>
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
                        <ListCard title="Editors" items={editors} accent="cyan" />
                    </div>

                    <GlassCard accent="orange" className="space-y-4">
                        <h2 className="text-lg font-semibold text-zinc-100">How To Showcase Boost</h2>
                        <ul className="grid grid-cols-1 gap-3 text-sm text-zinc-300 md:grid-cols-2">
                            <li className="rounded-xl border border-white/10 bg-white/5 p-4">
                                Point MCP clients at the MCP endpoints on the MCP Utilities page.
                            </li>
                            <li className="rounded-xl border border-white/10 bg-white/5 p-4">
                                Confirm the Codex agent appears in the Agents list here.
                            </li>
                            <li className="rounded-xl border border-white/10 bg-white/5 p-4">
                                Use System Diagnostics to run the Boost and MCP checks together.
                            </li>
                            <li className="rounded-xl border border-white/10 bg-white/5 p-4">
                                Keep <span className="font-mono text-xs text-zinc-200">boost.json</span> aligned with installed packages and services.
                            </li>
                        </ul>
                    </GlassCard>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
