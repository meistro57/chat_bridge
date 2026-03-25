import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';

export default function Index({ templates, categories, filters }) {
    const user = usePage().props.auth.user;
    const activeCategory = filters?.category ?? '';
    const [showFavoritesOnly, setShowFavoritesOnly] = useState(false);

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

    const handleToggleFavorite = (id) => {
        router.patch(route('templates.favorite', id), {}, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                router.reload({
                    only: ['templates'],
                    preserveScroll: true,
                });
            },
        });
    };

    const handleClearFavorites = async () => {
        if (!confirm('Clear all favorites?')) {
            return;
        }

        const favoriteTemplates = templates.filter((t) => t.is_favorite && t.user_id === user.id);

        if (favoriteTemplates.length === 0) {
            return;
        }

        try {
            await Promise.all(
                favoriteTemplates.map((t) => axios.patch(route('templates.favorite', t.id))),
            );

            if (showFavoritesOnly) {
                setShowFavoritesOnly(false);
            }

            router.reload({
                only: ['templates'],
                preserveScroll: true,
            });
        } catch {
            router.reload({
                only: ['templates'],
                preserveScroll: true,
            });
        }
    };

    const hasFavorites = templates.some((t) => t.is_favorite && t.user_id === user.id);
    const filteredTemplates = showFavoritesOnly
        ? templates.filter((t) => t.is_favorite)
        : templates;

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

                        <div className="flex flex-wrap items-center justify-end gap-3">
                            <button
                                onClick={() => setShowFavoritesOnly((current) => !current)}
                                className={`group flex items-center gap-2 rounded-xl border px-5 py-2.5 font-medium transition-all ${showFavoritesOnly ? 'border-indigo-400/50 bg-indigo-500/20 text-indigo-200 hover:bg-indigo-500/30' : 'border-zinc-700 bg-zinc-900/70 text-zinc-300 hover:border-zinc-600 hover:bg-zinc-900'}`}
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill={showFavoritesOnly ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                {showFavoritesOnly ? 'Show All' : 'Favorites Only'}
                            </button>
                            <button
                                onClick={handleClearFavorites}
                                disabled={!hasFavorites}
                                className="group flex items-center gap-2 rounded-xl border border-amber-500/40 bg-amber-500/10 px-5 py-2.5 font-medium text-amber-200 transition-all hover:bg-amber-500/20 disabled:cursor-not-allowed disabled:border-zinc-700/60 disabled:bg-zinc-900/60 disabled:text-zinc-500"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="4" y1="4" x2="20" y2="20"/><path d="M12 17.77 5.82 21.02 7 14.14 2 9.27l6.91-1.01L12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88z"/></svg>
                                Clear Favorites
                            </button>
                            <Link
                                href={route('templates.create')}
                                className="group flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium transition-all hover:shadow-[0_0_20px_rgba(99,102,241,0.3)]"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                                New Template
                            </Link>
                        </div>
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
                        {filteredTemplates.map((template, index) => {
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
                                                {template.is_favorite && (
                                                    <span className="inline-flex items-center rounded-full border border-amber-500/40 bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-200">
                                                        Favorite
                                                    </span>
                                                )}
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
                                                    onClick={() => handleToggleFavorite(template.id)}
                                                    className={`p-2 rounded-lg transition-colors ${template.is_favorite ? 'bg-amber-500/20 text-amber-300 hover:bg-amber-500/30' : 'bg-zinc-900/80 text-zinc-400 hover:bg-zinc-700 hover:text-amber-300'}`}
                                                    title={template.is_favorite ? 'Remove Favorite' : 'Mark Favorite'}
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill={template.is_favorite ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                                </button>
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

                        {filteredTemplates.length === 0 && (
                            <div className="col-span-full text-center text-zinc-500 py-12">
                                {showFavoritesOnly ? 'No favorite templates yet.' : 'No templates found for this category.'}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
