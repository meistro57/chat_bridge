import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function Index({ templates, categories, filters }) {
    const user = usePage().props.auth.user;
    const activeCategory = filters?.category ?? '';

    const handleDelete = (templateId) => {
        if (confirm('Delete this template?')) {
            router.delete(route('templates.destroy', templateId));
        }
    };

    const handleUse = (templateId) => {
        router.post(route('templates.use', templateId));
    };

    const handleClone = (templateId) => {
        router.post(route('templates.clone', templateId));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Conversation Templates" />

            <div className="min-h-screen text-zinc-100 p-6 md:p-12">
                <div className="max-w-7xl mx-auto space-y-8">
                    <div className="flex flex-col md:flex-row justify-between items-end pb-8 border-b border-white/5 gap-6">
                        <div>
                            <Link href="/chat" className="text-xs font-mono text-zinc-500 hover:text-white mb-2 block uppercase tracking-wide">
                                &larr; Return to Bridge
                            </Link>
                            <h1 className="text-4xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-zinc-400">
                                Conversation Templates
                            </h1>
                            <p className="text-zinc-500 mt-2">Build reusable starting points for high-impact sessions.</p>
                        </div>

                        <Link
                            href={route('templates.create')}
                            className="group flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium transition-all hover:shadow-[0_0_20px_rgba(99,102,241,0.3)]"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                            New Template
                        </Link>
                    </div>

                    <div className="glass-panel glass-butter rounded-2xl p-5 border border-white/10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <p className="text-xs uppercase tracking-widest text-zinc-400">Library Filters</p>
                            <p className="text-sm text-zinc-500">Browse public templates and your private collection.</p>
                        </div>
                        <form method="get" action={route('templates.index')} className="flex items-center gap-3">
                            <select
                                name="category"
                                defaultValue={activeCategory}
                                className="rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                            >
                                <option value="">All Categories</option>
                                {categories.map((category) => (
                                    <option key={category} value={category}>{category}</option>
                                ))}
                            </select>
                            <button
                                type="submit"
                                className="rounded-xl bg-zinc-800/70 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-zinc-200 hover:bg-zinc-700/70"
                            >
                                Apply
                            </button>
                        </form>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        {templates.map((template, index) => {
                            const isOwner = template.user_id === user.id;

                            return (
                                <div
                                    key={template.id}
                                    className={`group relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-6 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden glass-butter hover:bg-zinc-900/60 hover:border-white/[0.15] ${index % 3 === 0 ? 'butter-reveal' : index % 3 === 1 ? 'butter-reveal butter-reveal-delay-1' : 'butter-reveal butter-reveal-delay-2'}`}
                                >
                                    <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-indigo-500/80 via-purple-500/80 to-pink-500/80" />
                                    <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />

                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <h3 className="text-lg font-semibold text-zinc-100 group-hover:text-indigo-300 transition-colors">
                                                {template.name}
                                            </h3>
                                            <div className="mt-2 flex flex-wrap items-center gap-2">
                                                {template.category && (
                                                    <span className="rounded-full bg-white/5 px-3 py-1 text-[10px] font-semibold uppercase tracking-widest text-zinc-400">
                                                        {template.category}
                                                    </span>
                                                )}
                                                <span className={`rounded-full px-3 py-1 text-[10px] font-semibold uppercase tracking-widest ${template.is_public ? 'bg-emerald-500/15 text-emerald-200' : 'bg-white/5 text-zinc-400'}`}>
                                                    {template.is_public ? 'Public' : 'Private'}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                            {isOwner && (
                                                <Link
                                                    href={route('templates.edit', template.id)}
                                                    className="p-2 rounded-lg bg-zinc-900/80 hover:bg-white text-zinc-400 hover:text-black transition-colors"
                                                    title="Edit Template"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                </Link>
                                            )}
                                            {isOwner && (
                                                <button
                                                    type="button"
                                                    onClick={() => handleDelete(template.id)}
                                                    className="p-2 rounded-lg bg-red-900/20 hover:bg-red-500 text-red-400 hover:text-white transition-colors"
                                                    title="Delete Template"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                                </button>
                                            )}
                                        </div>
                                    </div>

                                    {template.description && (
                                        <p className="mt-3 text-sm text-zinc-400">
                                            {template.description}
                                        </p>
                                    )}

                                    <div className="mt-4 space-y-2 text-xs text-zinc-500">
                                        <div className="flex items-center justify-between">
                                            <span>Persona A</span>
                                            <span className="text-zinc-300">{template.persona_a?.name ?? 'Unassigned'}</span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span>Persona B</span>
                                            <span className="text-zinc-300">{template.persona_b?.name ?? 'Unassigned'}</span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span>Max Rounds</span>
                                            <span className="text-zinc-300">{template.max_rounds ?? '—'}</span>
                                        </div>
                                    </div>

                                    <div className="mt-6 flex flex-wrap items-center gap-3">
                                        <button
                                            type="button"
                                            onClick={() => handleUse(template.id)}
                                            className="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-500"
                                        >
                                            Use Template
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => handleClone(template.id)}
                                            className="rounded-xl border border-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-zinc-300 hover:border-white/30 hover:text-white"
                                        >
                                            Clone
                                        </button>
                                    </div>
                                </div>
                            );
                        })}

                        {templates.length === 0 && (
                            <div className="col-span-full text-center text-zinc-500 py-12">
                                No templates found for this category.
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
