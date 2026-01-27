import { GlassCard } from '@/Components/ui/GlassCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

function StatusPill({ ok }) {
    const classes = ok
        ? 'border-emerald-500/30 bg-emerald-500/15 text-emerald-200'
        : 'border-red-500/30 bg-red-500/15 text-red-200';

    return (
        <span className={`inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wider ${classes}`}>
            {ok ? 'Healthy' : 'Error'}
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

export default function McpUtilities({ health, stats, endpoints }) {
    const healthPayload = health?.payload ?? {};
    const statsPayload = stats?.payload ?? {};

    return (
        <AuthenticatedLayout>
            <Head title="MCP Utilities" />

            <div className="min-h-screen p-6 text-zinc-100 md:p-12">
                <div className="mx-auto max-w-6xl space-y-8">
                    <div className="space-y-2">
                        <h1 className="text-4xl font-bold text-zinc-100">MCP Utilities</h1>
                        <p className="text-zinc-400">
                            Explore MCP health, runtime stats, and the exact endpoints available inside this app.
                        </p>
                    </div>

                    <GlassCard accent="violet" className="space-y-6">
                        <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div className="space-y-1">
                                <h2 className="text-lg font-semibold text-zinc-100">MCP Health</h2>
                                <p className="text-sm text-zinc-400">
                                    This uses the same internal MCP health payload the API returns.
                                </p>
                            </div>
                            <StatusPill ok={Boolean(health?.ok)} />
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <StatItem label="Status" value={healthPayload.status ?? 'unknown'} />
                            <StatItem label="Mode" value={healthPayload.mcp_mode ?? 'unknown'} />
                            <StatItem label="Version" value={healthPayload.version ?? 'unknown'} />
                            <StatItem
                                label="Vector Search"
                                value={healthPayload.vector_search ? 'enabled' : 'unavailable'}
                            />
                        </div>
                    </GlassCard>

                    <GlassCard accent="cyan" className="space-y-6">
                        <div className="space-y-1">
                            <h2 className="text-lg font-semibold text-zinc-100">MCP Stats</h2>
                            <p className="text-sm text-zinc-400">
                                Live counts for conversations, messages, and stored embeddings.
                            </p>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <StatItem label="Conversations" value={statsPayload.conversations_count} />
                            <StatItem label="Messages" value={statsPayload.messages_count} />
                            <StatItem label="Embeddings" value={statsPayload.embeddings_count} />
                        </div>
                    </GlassCard>

                    <GlassCard accent="indigo" className="space-y-5">
                        <div className="space-y-1">
                            <h2 className="text-lg font-semibold text-zinc-100">Available MCP Endpoints</h2>
                            <p className="text-sm text-zinc-400">
                                These are ready to call from MCP clients or curl on the host machine.
                            </p>
                        </div>

                        <div className="overflow-hidden rounded-xl border border-white/10">
                            <table className="min-w-full divide-y divide-white/10 text-sm">
                                <thead className="bg-white/5 text-left text-xs uppercase tracking-wider text-zinc-400">
                                    <tr>
                                        <th className="px-4 py-3">Method</th>
                                        <th className="px-4 py-3">Path</th>
                                        <th className="px-4 py-3">Description</th>
                                        <th className="px-4 py-3 text-right">Example</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-white/5">
                                    {endpoints.map((endpoint) => (
                                        <tr key={`${endpoint.method}-${endpoint.path}`} className="bg-zinc-950/30">
                                            <td className="px-4 py-3">
                                                <span className="rounded-md border border-white/10 bg-white/5 px-2 py-1 text-xs font-semibold text-zinc-200">
                                                    {endpoint.method}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs text-zinc-200">
                                                {endpoint.path}
                                            </td>
                                            <td className="px-4 py-3 text-zinc-300">{endpoint.description}</td>
                                            <td className="px-4 py-3 text-right">
                                                <code className="rounded-lg border border-white/10 bg-zinc-950/80 px-3 py-2 text-xs text-zinc-200">
                                                    curl {endpoint.url}
                                                </code>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </GlassCard>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
