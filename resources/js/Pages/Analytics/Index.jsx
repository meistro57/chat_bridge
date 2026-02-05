import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Line,
    LineChart,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

export default function Index({
    overview,
    metrics,
    tokenUsageByProvider,
    providerUsage,
    personaStats,
    trendData,
    recentConversations,
    costByProvider,
}) {
    const COLORS = ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#06b6d4', '#ef4444', '#f97316'];

    const formatNumber = (value) => new Intl.NumberFormat().format(value ?? 0);
    const formatCurrency = (value) => `$${(value ?? 0).toFixed(2)}`;

    const completionRate = metrics?.completion_rate ? (metrics.completion_rate * 100).toFixed(1) : '0.0';
    const averageLength = metrics?.average_length ?? 0;

    const trends = trendData ?? [];
    const providerTokens = tokenUsageByProvider ?? [];
    const providerCounts = providerUsage ?? [];
    const personas = personaStats ?? [];
    const recent = recentConversations ?? [];

    return (
        <AuthenticatedLayout>
            <Head title="Conversation Analytics" />

            <div className="min-h-screen text-zinc-100 p-6 md:p-12">
                <div className="max-w-7xl mx-auto space-y-8">
                    <div className="flex flex-col md:flex-row justify-between items-end pb-8 border-b border-white/5 gap-6">
                        <div>
                            <Link href="/dashboard" className="text-xs font-mono text-zinc-500 hover:text-white mb-2 block uppercase tracking-wide">&larr; Dashboard</Link>
                            <h1 className="text-4xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-zinc-400">Conversation Analytics</h1>
                            <p className="text-zinc-500 mt-2">Track usage, trends, and performance across your AI conversations.</p>
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            <Link
                                href="/analytics/query"
                                className="group flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium transition-all hover:shadow-[0_0_20px_rgba(99,102,241,0.3)]"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                                Query Data
                            </Link>
                            <form method="post" action={route('analytics.export')} className="flex">
                                <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content} />
                                <input type="hidden" name="format" value="csv" />
                                <button
                                    type="submit"
                                    className="group flex items-center gap-2 border border-emerald-500/30 bg-emerald-500/10 text-emerald-200 hover:bg-emerald-500/20 px-4 py-2.5 rounded-xl font-medium"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    Export CSV
                                </button>
                            </form>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div className="relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-6 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden glass-butter butter-reveal">
                            <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-blue-500/80 via-cyan-500/80 to-blue-400/80" />
                            <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />
                            <div className="relative text-sm text-zinc-500 mb-1">Total Conversations</div>
                            <div className="relative text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-cyan-400">
                                {formatNumber(overview?.total_conversations)}
                            </div>
                            <div className="relative mt-2 text-xs text-zinc-500">Avg length: {averageLength} msgs</div>
                        </div>

                        <div className="relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-6 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden glass-butter butter-reveal butter-reveal-delay-1">
                            <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-purple-500/80 via-pink-500/80 to-fuchsia-500/80" />
                            <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />
                            <div className="relative text-sm text-zinc-500 mb-1">Total Messages</div>
                            <div className="relative text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-pink-400">
                                {formatNumber(overview?.total_messages)}
                            </div>
                            <div className="relative mt-2 text-xs text-zinc-500">Completion rate: {completionRate}%</div>
                        </div>

                        <div className="relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-6 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden glass-butter butter-reveal butter-reveal-delay-2">
                            <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-emerald-500/80 via-teal-500/80 to-cyan-500/80" />
                            <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />
                            <div className="relative text-sm text-zinc-500 mb-1">Total Tokens</div>
                            <div className="relative text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-teal-400">
                                {formatNumber(overview?.total_tokens)}
                            </div>
                            <div className="relative mt-2 text-xs text-zinc-500">All providers</div>
                        </div>

                        <div className="relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-6 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden glass-butter butter-reveal butter-reveal-delay-3">
                            <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-orange-500/80 via-amber-500/80 to-yellow-500/80" />
                            <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />
                            <div className="relative text-sm text-zinc-500 mb-1">Estimated Cost</div>
                            <div className="relative text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-orange-400 to-red-400">
                                {formatCurrency(overview?.total_cost)}
                            </div>
                            <div className="relative mt-2 text-xs text-zinc-500">Based on pricing config</div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div className="relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-6 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden glass-butter butter-reveal-strong butter-reveal-delay-1">
                            <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-indigo-500/80 via-blue-500/80 to-cyan-500/80" />
                            <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />
                            <h2 className="relative text-xl font-bold text-zinc-100 mb-4">Conversations Over Time</h2>
                            <div className="relative">
                                {trends.length > 0 ? (
                                    <ResponsiveContainer width="100%" height={300}>
                                        <LineChart data={trends}>
                                            <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
                                            <XAxis dataKey="date" stroke="#9ca3af" />
                                            <YAxis stroke="#9ca3af" />
                                            <Tooltip contentStyle={{ backgroundColor: '#1f2937', border: '1px solid #374151', borderRadius: '8px' }} />
                                            <Line type="monotone" dataKey="count" stroke="#6366f1" strokeWidth={2} />
                                        </LineChart>
                                    </ResponsiveContainer>
                                ) : (
                                    <div className="text-sm text-zinc-500">No trend data available.</div>
                                )}
                            </div>
                        </div>

                        <div className="relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-6 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden glass-butter butter-reveal-strong butter-reveal-delay-2">
                            <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-purple-500/80 via-pink-500/80 to-fuchsia-500/80" />
                            <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />
                            <h2 className="relative text-xl font-bold text-zinc-100 mb-4">Top Personas</h2>
                            <div className="relative">
                                {personas.length > 0 ? (
                                    <ResponsiveContainer width="100%" height={300}>
                                        <BarChart data={personas}>
                                            <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
                                            <XAxis dataKey="persona_name" stroke="#9ca3af" angle={-45} textAnchor="end" height={100} />
                                            <YAxis stroke="#9ca3af" />
                                            <Tooltip contentStyle={{ backgroundColor: '#1f2937', border: '1px solid #374151', borderRadius: '8px' }} />
                                            <Bar dataKey="count" fill="#8b5cf6" />
                                        </BarChart>
                                    </ResponsiveContainer>
                                ) : (
                                    <div className="text-sm text-zinc-500">No persona usage yet.</div>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div className="relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-6 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden glass-butter butter-reveal-strong butter-reveal-delay-3">
                            <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-emerald-500/80 via-teal-500/80 to-cyan-500/80" />
                            <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />
                            <h2 className="relative text-xl font-bold text-zinc-100 mb-4">Provider Usage</h2>
                            <div className="relative">
                                {providerCounts.length > 0 ? (
                                    <ResponsiveContainer width="100%" height={300}>
                                        <PieChart>
                                            <Pie data={providerCounts} dataKey="count" nameKey="provider" outerRadius={110}>
                                                {providerCounts.map((entry, index) => (
                                                    <Cell key={`cell-${entry.provider}`} fill={COLORS[index % COLORS.length]} />
                                                ))}
                                            </Pie>
                                            <Tooltip contentStyle={{ backgroundColor: '#1f2937', border: '1px solid #374151', borderRadius: '8px' }} />
                                        </PieChart>
                                    </ResponsiveContainer>
                                ) : (
                                    <div className="text-sm text-zinc-500">No provider data available.</div>
                                )}
                            </div>
                        </div>

                        <div className="relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-6 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden glass-butter butter-reveal-strong butter-reveal-delay-4">
                            <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-yellow-500/80 via-orange-500/80 to-red-500/80" />
                            <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />
                            <h2 className="relative text-xl font-bold text-zinc-100 mb-4">Tokens by Provider</h2>
                            <div className="relative">
                                {providerTokens.length > 0 ? (
                                    <ResponsiveContainer width="100%" height={300}>
                                        <BarChart data={providerTokens}>
                                            <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
                                            <XAxis dataKey="provider" stroke="#9ca3af" />
                                            <YAxis stroke="#9ca3af" />
                                            <Tooltip contentStyle={{ backgroundColor: '#1f2937', border: '1px solid #374151', borderRadius: '8px' }} />
                                            <Bar dataKey="tokens" fill="#f59e0b" />
                                        </BarChart>
                                    </ResponsiveContainer>
                                ) : (
                                    <div className="text-sm text-zinc-500">No token usage data.</div>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div className="lg:col-span-2 relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-6 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden glass-butter butter-reveal-strong butter-reveal-delay-5">
                            <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-cyan-500/80 via-blue-500/80 to-indigo-500/80" />
                            <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />
                            <div className="relative flex items-center justify-between mb-4">
                                <h2 className="text-xl font-bold text-zinc-100">Recent Conversations</h2>
                                <Link href="/chat" className="text-xs uppercase tracking-widest text-indigo-300 hover:text-white">View All</Link>
                            </div>
                            <div className="relative overflow-x-auto">
                                {recent.length > 0 ? (
                                    <table className="min-w-full text-sm">
                                        <thead className="text-zinc-500 uppercase text-xs">
                                            <tr>
                                                <th className="text-left py-3 px-2">ID</th>
                                                <th className="text-left py-3 px-2">Status</th>
                                                <th className="text-left py-3 px-2">Providers</th>
                                                <th className="text-right py-3 px-2">Messages</th>
                                                <th className="text-right py-3 px-2">Tokens</th>
                                                <th className="text-right py-3 px-2">Created</th>
                                            </tr>
                                        </thead>
                                        <tbody className="text-zinc-200">
                                            {recent.map((conversation) => (
                                                <tr key={conversation.id} className="border-t border-white/5 hover:bg-white/5">
                                                    <td className="py-3 px-2 font-mono text-xs text-zinc-400">
                                                        <Link href={route('chat.show', conversation.id)} className="text-indigo-300 hover:text-indigo-200">
                                                            {conversation.id.slice(0, 8)}
                                                        </Link>
                                                    </td>
                                                    <td className="py-3 px-2">
                                                        <span className={`rounded-full px-2.5 py-1 text-xs uppercase tracking-widest ${conversation.status === 'completed' ? 'bg-emerald-500/15 text-emerald-200' : conversation.status === 'active' ? 'bg-indigo-500/15 text-indigo-200' : 'bg-red-500/15 text-red-200'}`}>
                                                            {conversation.status}
                                                        </span>
                                                    </td>
                                                    <td className="py-3 px-2 text-xs text-zinc-400">
                                                        {conversation.provider_a} / {conversation.provider_b}
                                                    </td>
                                                    <td className="py-3 px-2 text-right text-zinc-300">
                                                        {formatNumber(conversation.message_count)}
                                                    </td>
                                                    <td className="py-3 px-2 text-right text-zinc-300">
                                                        {formatNumber(conversation.total_tokens)}
                                                    </td>
                                                    <td className="py-3 px-2 text-right text-xs text-zinc-500">
                                                        {new Date(conversation.created_at).toLocaleDateString()}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                ) : (
                                    <div className="text-sm text-zinc-500">No recent conversations found.</div>
                                )}
                            </div>
                        </div>

                        <div className="relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-6 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden glass-butter butter-reveal-strong butter-reveal-delay-6">
                            <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-pink-500/80 via-fuchsia-500/80 to-purple-500/80" />
                            <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />
                            <h2 className="relative text-xl font-bold text-zinc-100 mb-4">Cost by Provider</h2>
                            <div className="relative space-y-3">
                                {costByProvider?.length > 0 ? (
                                    costByProvider.map((entry) => (
                                        <div key={entry.provider} className="flex items-center justify-between rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-3">
                                            <span className="text-sm text-zinc-300">{entry.provider}</span>
                                            <span className="text-sm font-semibold text-emerald-200">{formatCurrency(entry.cost)}</span>
                                        </div>
                                    ))
                                ) : (
                                    <div className="text-sm text-zinc-500">No billable usage yet.</div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
