import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Create({ personas, categories }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        category: '',
        starter_message: '',
        max_rounds: 10,
        persona_a_id: '',
        persona_b_id: '',
        is_public: false,
        rag_enabled: false,
        rag_source_limit: 6,
        rag_score_threshold: 0.3,
        rag_system_prompt: '',
        rag_files: [],
        rag_files_to_delete: [],
    });

    const handleSubmit = (event) => {
        event.preventDefault();
        post(route('templates.store'), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Create Template" />

            <div className="min-h-screen text-zinc-100 p-6 md:p-12">
                <div className="max-w-4xl mx-auto space-y-8">
                    <div className="flex flex-col md:flex-row justify-between items-end pb-8 border-b border-white/5 gap-6">
                        <div>
                            <Link href={route('templates.index')} className="text-xs font-mono text-zinc-500 hover:text-white mb-2 block uppercase tracking-wide">
                                &larr; Back to Templates
                            </Link>
                            <h1 className="text-4xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-zinc-400">
                                New Conversation Template
                            </h1>
                            <p className="text-zinc-500 mt-2">Design a repeatable starting point for your sessions.</p>
                        </div>
                    </div>

                    <form onSubmit={handleSubmit} className="glass-panel glass-butter rounded-2xl p-6 md:p-8 border border-white/10 space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-widest text-zinc-400">Template Name</label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(event) => setData('name', event.target.value)}
                                    className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                />
                                {errors.name && <p className="text-xs text-red-400">{errors.name}</p>}
                            </div>
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-widest text-zinc-400">Category</label>
                                <input
                                    type="text"
                                    list="template-categories"
                                    value={data.category}
                                    onChange={(event) => setData('category', event.target.value)}
                                    className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                />
                                <datalist id="template-categories">
                                    {categories.map((category) => (
                                        <option key={category} value={category} />
                                    ))}
                                </datalist>
                                {errors.category && <p className="text-xs text-red-400">{errors.category}</p>}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <label className="text-xs uppercase tracking-widest text-zinc-400">Description</label>
                            <textarea
                                value={data.description}
                                onChange={(event) => setData('description', event.target.value)}
                                rows={3}
                                className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                            />
                            {errors.description && <p className="text-xs text-red-400">{errors.description}</p>}
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-widest text-zinc-400">Persona A</label>
                                <select
                                    value={data.persona_a_id}
                                    onChange={(event) => setData('persona_a_id', event.target.value)}
                                    className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                >
                                    <option value="">Select Persona</option>
                                    {personas.map((persona) => (
                                        <option key={persona.id} value={persona.id}>{persona.is_favorite ? `★ ${persona.name}` : persona.name}</option>
                                    ))}
                                </select>
                                {errors.persona_a_id && <p className="text-xs text-red-400">{errors.persona_a_id}</p>}
                            </div>
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-widest text-zinc-400">Persona B</label>
                                <select
                                    value={data.persona_b_id}
                                    onChange={(event) => setData('persona_b_id', event.target.value)}
                                    className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                >
                                    <option value="">Select Persona</option>
                                    {personas.map((persona) => (
                                        <option key={persona.id} value={persona.id}>{persona.is_favorite ? `★ ${persona.name}` : persona.name}</option>
                                    ))}
                                </select>
                                {errors.persona_b_id && <p className="text-xs text-red-400">{errors.persona_b_id}</p>}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <label className="text-xs uppercase tracking-widest text-zinc-400">Starter Message</label>
                            <textarea
                                value={data.starter_message}
                                onChange={(event) => setData('starter_message', event.target.value)}
                                rows={5}
                                className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                            />
                            {errors.starter_message && <p className="text-xs text-red-400">{errors.starter_message}</p>}
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-widest text-zinc-400">Max Rounds</label>
                                <input
                                    type="number"
                                    min="1"
                                    max="500"
                                    value={data.max_rounds}
                                    onChange={(event) => setData('max_rounds', event.target.value)}
                                    className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                />
                                {errors.max_rounds && <p className="text-xs text-red-400">{errors.max_rounds}</p>}
                            </div>
                            <div className="flex items-center gap-3 pt-6">
                                <input
                                    id="is_public"
                                    type="checkbox"
                                    checked={data.is_public}
                                    onChange={(event) => setData('is_public', event.target.checked)}
                                    className="h-4 w-4 rounded border-white/20 bg-zinc-900/60 text-indigo-500 focus:ring-indigo-500"
                                />
                                <label htmlFor="is_public" className="text-sm text-zinc-300">Make template public</label>
                            </div>
                        </div>

                        <div className="rounded-2xl border border-white/10 bg-zinc-900/30 p-5 space-y-5">
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <p className="text-xs uppercase tracking-widest text-zinc-400">Template RAG</p>
                                    <p className="text-sm text-zinc-500 mt-1">Attach source files and retrieval settings for agents.</p>
                                </div>
                                <label className="flex items-center gap-2 text-sm text-zinc-300">
                                    <input
                                        type="checkbox"
                                        checked={Boolean(data.rag_enabled)}
                                        onChange={(event) => setData('rag_enabled', event.target.checked)}
                                        className="h-4 w-4 rounded border-white/20 bg-zinc-900/60 text-indigo-500 focus:ring-indigo-500"
                                    />
                                    Enable RAG
                                </label>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div className="space-y-2">
                                    <label className="text-xs uppercase tracking-widest text-zinc-400">Source Limit</label>
                                    <input
                                        type="number"
                                        min="1"
                                        max="20"
                                        value={data.rag_source_limit}
                                        onChange={(event) => setData('rag_source_limit', event.target.value)}
                                        className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                    />
                                    {errors.rag_source_limit && <p className="text-xs text-red-400">{errors.rag_source_limit}</p>}
                                </div>

                                <div className="space-y-2">
                                    <label className="text-xs uppercase tracking-widest text-zinc-400">Score Threshold (0-1)</label>
                                    <input
                                        type="number"
                                        min="0"
                                        max="1"
                                        step="0.05"
                                        value={data.rag_score_threshold}
                                        onChange={(event) => setData('rag_score_threshold', event.target.value)}
                                        className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                    />
                                    {errors.rag_score_threshold && <p className="text-xs text-red-400">{errors.rag_score_threshold}</p>}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-widest text-zinc-400">RAG System Prompt</label>
                                <textarea
                                    value={data.rag_system_prompt}
                                    onChange={(event) => setData('rag_system_prompt', event.target.value)}
                                    rows={4}
                                    className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                    placeholder="Optional instructions for how the agent should use retrieved context."
                                />
                                {errors.rag_system_prompt && <p className="text-xs text-red-400">{errors.rag_system_prompt}</p>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-widest text-zinc-400">Attach RAG Files</label>
                                <input
                                    type="file"
                                    multiple
                                    onChange={(event) => setData('rag_files', Array.from(event.target.files ?? []))}
                                    className="w-full rounded-xl border border-dashed border-white/20 bg-zinc-900/50 px-4 py-3 text-sm text-zinc-300 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-500/20 file:px-3 file:py-2 file:text-xs file:font-semibold file:uppercase file:tracking-widest file:text-indigo-200 hover:file:bg-indigo-500/30"
                                />
                                <p className="text-xs text-zinc-500">Accepted: .txt, .md, .pdf, .doc, .docx, .csv, .json. Max 10 files, 10MB each.</p>
                                {errors.rag_files && <p className="text-xs text-red-400">{errors.rag_files}</p>}
                                {Array.isArray(data.rag_files) && data.rag_files.length > 0 && (
                                    <div className="rounded-xl border border-white/10 bg-black/20 p-3">
                                        <p className="text-[11px] uppercase tracking-widest text-zinc-400">Queued files</p>
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {data.rag_files.map((file) => (
                                                <span key={`${file.name}-${file.size}`} className="rounded-lg border border-white/10 bg-white/5 px-2 py-1 text-xs text-zinc-300">
                                                    {file.name}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="flex items-center justify-end gap-3">
                            <Link
                                href={route('templates.index')}
                                className="rounded-xl border border-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-zinc-300 hover:border-white/30"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-xl bg-indigo-600 px-5 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-500"
                            >
                                {processing ? 'Saving...' : 'Create Template'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
