import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Form, Head, Link } from '@inertiajs/react';

export default function Query({ results, filters, personas }) {
    const activeFilters = filters ?? {};
    const data = results?.data ?? [];
    const links = results?.links ?? [];

    return (
        <AuthenticatedLayout>
            <Head title="Query Conversations" />

            <div className="min-h-screen text-zinc-100 p-6 md:p-12">
                <div className="mx-auto max-w-6xl space-y-8">
                    <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                        <div>
                            <Link href={route('analytics.index')} className="text-xs font-mono text-zinc-500 hover:text-white mb-2 block uppercase tracking-wide">
                                &larr; Analytics Overview
                            </Link>
                            <h1 className="text-4xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-zinc-400">
                                Search Conversations
                            </h1>
                            <p className="text-zinc-500 mt-2">Filter by keyword, persona, time range, and status.</p>
                        </div>
                        <Link
                            href={route('analytics.export', activeFilters)}
                            className="flex items-center gap-2 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-200 hover:bg-emerald-500/20"
                        >
                            Export CSV
                        </Link>
                    </div>

                    <Form action={route('analytics.query')} method="get" className="glass-panel glass-butter rounded-2xl border border-white/10 p-6">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-wider text-zinc-400">Keyword</label>
                                <input
                                    name="keyword"
                                    defaultValue={activeFilters.keyword ?? ''}
                                    className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 outline-none focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                    placeholder="Search message content"
                                />
                            </div>
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-wider text-zinc-400">Persona</label>
                                <select
                                    name="persona_id"
                                    defaultValue={activeFilters.persona_id ?? ''}
                                    className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 outline-none focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                >
                                    <option value="">All Personas</option>
                                    {personas.map((persona) => (
                                        <option key={persona.id} value={persona.id}>{persona.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-wider text-zinc-400">Role</label>
                                <select
                                    name="role"
                                    defaultValue={activeFilters.role ?? ''}
                                    className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 outline-none focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                >
                                    <option value="">Any Role</option>
                                    <option value="user">User</option>
                                    <option value="assistant">Assistant</option>
                                </select>
                            </div>
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-wider text-zinc-400">Status</label>
                                <select
                                    name="status"
                                    defaultValue={activeFilters.status ?? ''}
                                    className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 outline-none focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                >
                                    <option value="">Any Status</option>
                                    <option value="active">Active</option>
                                    <option value="completed">Completed</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-wider text-zinc-400">From</label>
                                <input
                                    type="date"
                                    name="date_from"
                                    defaultValue={activeFilters.date_from ?? ''}
                                    className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 outline-none focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                />
                            </div>
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-wider text-zinc-400">To</label>
                                <input
                                    type="date"
                                    name="date_to"
                                    defaultValue={activeFilters.date_to ?? ''}
                                    className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 outline-none focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                />
                            </div>
                        </div>
                        <div className="mt-4 flex flex-wrap items-center gap-3">
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-wider text-zinc-400">Sort</label>
                                <select
                                    name="sort_order"
                                    defaultValue={activeFilters.sort_order ?? 'desc'}
                                    className="rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 outline-none focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                >
                                    <option value="desc">Newest</option>
                                    <option value="asc">Oldest</option>
                                </select>
                            </div>
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-wider text-zinc-400">Per Page</label>
                                <select
                                    name="per_page"
                                    defaultValue={activeFilters.per_page ?? 20}
                                    className="rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 outline-none focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                >
                                    {[10, 20, 50, 100].map((size) => (
                                        <option key={size} value={size}>{size}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="ml-auto flex items-center gap-3">
                                <Link
                                    href={route('analytics.query')}
                                    className="rounded-xl border border-white/10 px-4 py-2 text-sm text-zinc-300 hover:border-white/30 hover:text-white"
                                >
                                    Clear
                                </Link>
                                <button
                                    type="submit"
                                    className="rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                                >
                                    Search
                                </button>
                            </div>
                        </div>
                    </Form>

                    <div className="glass-panel glass-butter rounded-2xl border border-white/10 p-6">
                        <div className="flex items-center justify-between border-b border-white/5 pb-4 text-sm text-zinc-400">
                            <span>{results?.total ?? 0} results</span>
                            {activeFilters.keyword && (
                                <span className="font-mono text-xs uppercase tracking-widest text-zinc-500">
                                    Keyword: "{activeFilters.keyword}"
                                </span>
                            )}
                        </div>

                        <div className="mt-6 space-y-4">
                            {data.map((message) => (
                                <div key={message.id} className="rounded-2xl border border-white/5 bg-zinc-900/60 p-5">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div className="flex items-center gap-3">
                                            <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs uppercase tracking-wider text-zinc-300">
                                                {message.persona?.name ?? message.role}
                                            </span>
                                            <span className="text-xs text-zinc-500">
                                                {new Date(message.created_at).toLocaleString()}
                                            </span>
                                        </div>
                                        <Link
                                            href={route('chat.show', message.conversation_id)}
                                            className="text-xs font-semibold uppercase tracking-wider text-indigo-400 hover:text-indigo-300"
                                        >
                                            View Conversation
                                        </Link>
                                    </div>
                                    <p className="mt-4 whitespace-pre-wrap text-sm text-zinc-200">
                                        {message.content}
                                    </p>
                                </div>
                            ))}

                            {data.length === 0 && (
                                <div className="py-16 text-center text-zinc-500">
                                    No conversations matched those filters.
                                </div>
                            )}
                        </div>

                        {links.length > 1 && (
                            <div className="mt-6 flex flex-wrap items-center gap-2">
                                {links.map((link) => (
                                    <Link
                                        key={link.label}
                                        href={link.url || '#'}
                                        preserveScroll
                                        className={`rounded-lg border px-3 py-1 text-xs ${
                                            link.active
                                                ? 'border-indigo-500/60 bg-indigo-500/20 text-indigo-200'
                                                : 'border-white/10 text-zinc-400 hover:border-white/30 hover:text-white'
                                        } ${link.url ? '' : 'pointer-events-none opacity-40'}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
