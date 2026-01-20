import { Head, Link } from '@inertiajs/react';
import { BarChart, Bar, LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';

export default function Index({ stats, recentActivity, personaStats }) {
    const COLORS = ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#06b6d4', '#ef4444', '#f97316'];

    return (
        <div className="min-h-screen text-zinc-100 p-6 md:p-12">
            <Head title="Analytics & Query" />

            <div className="max-w-7xl mx-auto space-y-8">
                {/* Header */}
                <div className="flex flex-col md:flex-row justify-between items-end pb-8 border-b border-white/5 gap-6">
                    <div>
                        <Link href="/dashboard" className="text-xs font-mono text-zinc-500 hover:text-white mb-2 block uppercase tracking-wide">&larr; Dashboard</Link>
                        <h1 className="text-4xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-zinc-400">Analytics & Data Query</h1>
                        <p className="text-zinc-500 mt-2">Analyze your conversation history and query past chats.</p>
                    </div>

                    <Link
                        href="/analytics/query"
                        className="group flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium transition-all hover:shadow-[0_0_20px_rgba(99,102,241,0.3)]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        Query Data
                    </Link>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div className="glass-panel rounded-2xl p-6 border border-blue-500/20">
                        <div className="text-sm text-zinc-500 mb-1">Total Conversations</div>
                        <div className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-cyan-400">
                            {stats.total_conversations}
                        </div>
                    </div>

                    <div className="glass-panel rounded-2xl p-6 border border-purple-500/20">
                        <div className="text-sm text-zinc-500 mb-1">Total Messages</div>
                        <div className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-pink-400">
                            {stats.total_messages}
                        </div>
                    </div>

                    <div className="glass-panel rounded-2xl p-6 border border-emerald-500/20">
                        <div className="text-sm text-zinc-500 mb-1">Active</div>
                        <div className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-teal-400">
                            {stats.active_conversations}
                        </div>
                    </div>

                    <div className="glass-panel rounded-2xl p-6 border border-orange-500/20">
                        <div className="text-sm text-zinc-500 mb-1">Completed</div>
                        <div className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-orange-400 to-red-400">
                            {stats.completed_conversations}
                        </div>
                    </div>
                </div>

                {/* Charts */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Recent Activity */}
                    <div className="glass-panel rounded-2xl p-6 border border-white/5">
                        <h2 className="text-xl font-bold text-zinc-100 mb-4">Activity (Last 7 Days)</h2>
                        <ResponsiveContainer width="100%" height={300}>
                            <LineChart data={recentActivity}>
                                <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
                                <XAxis dataKey="date" stroke="#9ca3af" />
                                <YAxis stroke="#9ca3af" />
                                <Tooltip contentStyle={{ backgroundColor: '#1f2937', border: '1px solid #374151' }} />
                                <Line type="monotone" dataKey="count" stroke="#6366f1" strokeWidth={2} />
                            </LineChart>
                        </ResponsiveContainer>
                    </div>

                    {/* Persona Usage */}
                    <div className="glass-panel rounded-2xl p-6 border border-white/5">
                        <h2 className="text-xl font-bold text-zinc-100 mb-4">Top Personas</h2>
                        <ResponsiveContainer width="100%" height={300}>
                            <BarChart data={personaStats}>
                                <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
                                <XAxis dataKey="persona_name" stroke="#9ca3af" angle={-45} textAnchor="end" height={100} />
                                <YAxis stroke="#9ca3af" />
                                <Tooltip contentStyle={{ backgroundColor: '#1f2937', border: '1px solid #374151' }} />
                                <Bar dataKey="count" fill="#8b5cf6" />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </div>

                {/* Quick Links */}
                <div className="glass-panel rounded-2xl p-8 border border-white/5">
                    <h2 className="text-xl font-bold text-zinc-100 mb-4">Quick Actions</h2>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <Link
                            href="/analytics/query"
                            className="p-4 rounded-xl bg-zinc-900/50 hover:bg-zinc-800 border border-white/5 hover:border-indigo-500/30 transition-all group"
                        >
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-lg bg-indigo-500/10 flex items-center justify-center group-hover:bg-indigo-500/20 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-indigo-400"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                                </div>
                                <div>
                                    <div className="font-medium text-zinc-100">Search Conversations</div>
                                    <div className="text-sm text-zinc-500">Query your chat history</div>
                                </div>
                            </div>
                        </Link>

                        <Link
                            href="/chat"
                            className="p-4 rounded-xl bg-zinc-900/50 hover:bg-zinc-800 border border-white/5 hover:border-purple-500/30 transition-all group"
                        >
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-lg bg-purple-500/10 flex items-center justify-center group-hover:bg-purple-500/20 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-purple-400"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                </div>
                                <div>
                                    <div className="font-medium text-zinc-100">View All Chats</div>
                                    <div className="text-sm text-zinc-500">Browse conversations</div>
                                </div>
                            </div>
                        </Link>

                        <Link
                            href="/personas"
                            className="p-4 rounded-xl bg-zinc-900/50 hover:bg-zinc-800 border border-white/5 hover:border-emerald-500/30 transition-all group"
                        >
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-lg bg-emerald-500/10 flex items-center justify-center group-hover:bg-emerald-500/20 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-emerald-400"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                </div>
                                <div>
                                    <div className="font-medium text-zinc-100">Manage Personas</div>
                                    <div className="text-sm text-zinc-500">Configure AI agents</div>
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
