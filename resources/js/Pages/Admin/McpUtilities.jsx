import { GlassCard } from '@/Components/ui/GlassCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useState } from 'react';

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

export default function McpUtilities({ health, stats, endpoints, traffic, ollamaToolsSupported = null }) {
    const healthPayload = health?.payload ?? {};
    const statsPayload = stats?.payload ?? {};
    const [audit, setAudit] = useState({
        messages_count: Number(statsPayload.messages_count ?? 0),
        embeddings_count: Number(statsPayload.embeddings_count ?? 0),
        missing_embeddings_count: Math.max(
            Number(statsPayload.messages_count ?? 0) - Number(statsPayload.embeddings_count ?? 0),
            0,
        ),
        coverage_percent: Number(statsPayload.messages_count ?? 0) > 0
            ? Number((((Number(statsPayload.embeddings_count ?? 0) / Number(statsPayload.messages_count ?? 0)) * 100).toFixed(2)))
            : 100,
        checked_at: null,
    });
    const [compareLoading, setCompareLoading] = useState(false);
    const [populateLoading, setPopulateLoading] = useState(false);
    const [populateLimit, setPopulateLimit] = useState(100);
    const [error, setError] = useState('');
    const [populateSummary, setPopulateSummary] = useState(null);
    const [trafficEvents, setTrafficEvents] = useState(Array.isArray(traffic?.events) ? traffic.events : []);
    const [trafficProvider, setTrafficProvider] = useState('ollama');
    const [trafficLimit, setTrafficLimit] = useState(40);
    const [trafficLoading, setTrafficLoading] = useState(false);
    const [trafficError, setTrafficError] = useState('');
    const [flushLoading, setFlushLoading] = useState(false);
    const [flushSummary, setFlushSummary] = useState(null);
    const [flushError, setFlushError] = useState('');

    const loadTraffic = async (provider = trafficProvider, limit = trafficLimit) => {
        setTrafficLoading(true);
        setTrafficError('');
        try {
            const response = await axios.get(route('admin.mcp.utilities.traffic'), {
                params: {
                    provider: provider || undefined,
                    limit,
                },
            });
            setTrafficEvents(Array.isArray(response.data?.events) ? response.data.events : []);
        } catch (requestError) {
            setTrafficError(requestError.response?.data?.message || requestError.message || 'Failed to load MCP traffic.');
        } finally {
            setTrafficLoading(false);
        }
    };

    useEffect(() => {
        loadTraffic();
        const timer = setInterval(() => {
            loadTraffic();
        }, 3000);

        return () => clearInterval(timer);
    }, [trafficProvider, trafficLimit]);

    const compareEmbeddings = async () => {
        setCompareLoading(true);
        setError('');
        setPopulateSummary(null);

        try {
            const response = await axios.get(route('admin.mcp.utilities.embeddings.compare'));
            setAudit(response.data.audit);
        } catch (requestError) {
            setError(requestError.response?.data?.message || requestError.message || 'Failed to compare embeddings.');
        } finally {
            setCompareLoading(false);
        }
    };

    const populateEmbeddings = async () => {
        if (audit.missing_embeddings_count <= 0) {
            return;
        }

        setPopulateLoading(true);
        setError('');
        setPopulateSummary(null);

        try {
            const response = await axios.post(route('admin.mcp.utilities.embeddings.populate'), {
                limit: populateLimit,
            });
            setAudit(response.data.audit);
            setPopulateSummary(response.data.summary);
        } catch (requestError) {
            setError(requestError.response?.data?.message || requestError.message || 'Failed to populate embeddings.');
        } finally {
            setPopulateLoading(false);
        }
    };

    const flushQueueState = async () => {
        setFlushLoading(true);
        setFlushError('');
        setFlushSummary(null);

        try {
            const response = await axios.post(route('admin.mcp.utilities.flush'));
            setFlushSummary(response.data?.summary ?? null);
        } catch (requestError) {
            const responseErrors = requestError.response?.data?.errors;
            if (Array.isArray(responseErrors) && responseErrors.length > 0) {
                setFlushError(responseErrors.join(' | '));
            } else {
                setFlushError(requestError.response?.data?.message || requestError.message || 'Failed to flush queue state.');
            }
        } finally {
            setFlushLoading(false);
        }
    };

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
                            <StatItem label="Messages" value={audit.messages_count} />
                            <StatItem label="Embeddings" value={audit.embeddings_count} />
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <StatItem label="Missing Embeddings" value={audit.missing_embeddings_count} />
                            <StatItem label="Coverage %" value={audit.coverage_percent} />
                        </div>

                        <div className="rounded-xl border border-white/10 bg-white/5 p-4">
                            <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                                <div className="space-y-2">
                                    <div className="text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                        Embedding Controls
                                    </div>
                                    <div className="text-sm text-zinc-400">
                                        Compare totals first, then populate missing embeddings in batches.
                                    </div>
                                    {audit.checked_at && (
                                        <div className="text-xs text-zinc-500">
                                            Last compared: {new Date(audit.checked_at).toLocaleString()}
                                        </div>
                                    )}
                                </div>
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                            Populate Limit
                                        </label>
                                        <input
                                            type="number"
                                            min="1"
                                            max="1000"
                                            value={populateLimit}
                                            onChange={(event) => setPopulateLimit(Math.max(1, Math.min(1000, Number(event.target.value || 1))))}
                                            className="w-28 rounded-xl border border-white/10 bg-zinc-900/60 px-3 py-2 text-sm text-zinc-200 outline-none focus:border-cyan-500/50"
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        onClick={compareEmbeddings}
                                        disabled={compareLoading || populateLoading}
                                        className="rounded-xl border border-white/10 bg-zinc-900/50 px-4 py-2 text-sm text-zinc-200 transition-colors hover:border-white/20 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {compareLoading ? 'Comparing...' : 'Compare Missing'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={populateEmbeddings}
                                        disabled={populateLoading || compareLoading || audit.missing_embeddings_count <= 0}
                                        className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-200 transition-colors hover:bg-emerald-500/20 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {populateLoading ? 'Populating...' : 'Populate Missing'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={flushQueueState}
                                        disabled={flushLoading || populateLoading || compareLoading}
                                        className="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-2 text-sm text-amber-200 transition-colors hover:bg-amber-500/20 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {flushLoading ? 'Flushing...' : 'Flush Queue State'}
                                    </button>
                                </div>
                            </div>

                            {populateSummary && (
                                <div className="mt-4 grid grid-cols-2 gap-3 text-sm md:grid-cols-5">
                                    <StatItem label="Processed" value={populateSummary.processed} />
                                    <StatItem label="Updated" value={populateSummary.updated} />
                                    <StatItem label="Failed" value={populateSummary.failed} />
                                    <StatItem label="Requested" value={populateSummary.requested_limit} />
                                    <StatItem label="Remaining" value={populateSummary.remaining_missing} />
                                </div>
                            )}

                            {error && (
                                <div className="mt-3 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-200">
                                    {error}
                                </div>
                            )}

                            {flushSummary && (
                                <div className="mt-3 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-100">
                                    Flushed {flushSummary.failed_jobs_flushed} failed jobs and cleared {flushSummary.cleared_lock_keys} lock keys.
                                </div>
                            )}

                            {flushError && (
                                <div className="mt-3 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-200">
                                    {flushError}
                                </div>
                            )}
                        </div>
                    </GlassCard>

                    <GlassCard accent="indigo" className="space-y-5">
                        <div className="space-y-1">
                            <h2 className="text-lg font-semibold text-zinc-100">MCP Traffic Watch</h2>
                            <p className="text-sm text-zinc-400">
                                Live MCP tool call events captured during chat runs.
                            </p>
                        </div>

                        {ollamaToolsSupported === false && (
                            <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                                Ollama tool calling is currently unsupported in this app, so Ollama-filtered MCP traffic will stay empty.
                            </div>
                        )}

                        <div className="rounded-xl border border-white/10 bg-white/5 p-4">
                            <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                            Provider Filter
                                        </label>
                                        <select
                                            value={trafficProvider}
                                            onChange={(event) => setTrafficProvider(event.target.value)}
                                            className="rounded-xl border border-white/10 bg-zinc-900/60 px-3 py-2 text-sm text-zinc-200 outline-none focus:border-indigo-500/50"
                                        >
                                            <option value="">All</option>
                                            <option value="ollama">Ollama</option>
                                            <option value="openai">OpenAI</option>
                                            <option value="anthropic">Anthropic</option>
                                            <option value="openrouter">OpenRouter</option>
                                            <option value="gemini">Gemini</option>
                                            <option value="bedrock">Bedrock</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                            Event Limit
                                        </label>
                                        <input
                                            type="number"
                                            min="1"
                                            max="250"
                                            value={trafficLimit}
                                            onChange={(event) => setTrafficLimit(Math.max(1, Math.min(250, Number(event.target.value || 1))))}
                                            className="w-28 rounded-xl border border-white/10 bg-zinc-900/60 px-3 py-2 text-sm text-zinc-200 outline-none focus:border-indigo-500/50"
                                        />
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => loadTraffic()}
                                    disabled={trafficLoading}
                                    className="rounded-xl border border-white/10 bg-zinc-900/50 px-4 py-2 text-sm text-zinc-200 transition-colors hover:border-white/20 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {trafficLoading ? 'Refreshing...' : 'Refresh Traffic'}
                                </button>
                            </div>

                            {trafficError && (
                                <div className="mt-3 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-200">
                                    {trafficError}
                                </div>
                            )}

                            <div className="mt-4 max-h-96 space-y-2 overflow-y-auto">
                                {trafficEvents.length === 0 && (
                                    <div className="rounded-lg border border-white/10 bg-black/20 px-3 py-2 text-sm text-zinc-400">
                                        No MCP traffic for this filter yet.
                                    </div>
                                )}
                                {trafficEvents.map((event) => (
                                    <div key={event.id} className="rounded-lg border border-white/10 bg-black/20 p-3">
                                        <div className="flex flex-wrap items-center gap-2 text-xs">
                                            <span className="rounded border border-white/10 bg-white/5 px-2 py-0.5 font-semibold text-zinc-200">
                                                {event.tool_name}
                                            </span>
                                            <span className="font-mono text-zinc-500">{new Date(event.at).toLocaleTimeString()}</span>
                                            <span className="rounded border border-white/10 px-2 py-0.5 text-zinc-300">
                                                {event.provider || 'unknown provider'}
                                            </span>
                                            <span className="rounded border border-white/10 px-2 py-0.5 text-zinc-300">
                                                {event.model || 'unknown model'}
                                            </span>
                                            <span className="rounded border border-white/10 px-2 py-0.5 text-zinc-300">
                                                {event.duration_ms}ms
                                            </span>
                                        </div>
                                        {event.error ? (
                                            <div className="mt-2 rounded border border-red-500/30 bg-red-500/10 px-2 py-1 text-xs text-red-200">
                                                {event.error}
                                            </div>
                                        ) : (
                                            <div className="mt-2 rounded border border-emerald-500/20 bg-emerald-500/10 px-2 py-1 text-xs text-emerald-200">
                                                {event.result_preview}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
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
