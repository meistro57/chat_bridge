import React, { useRef, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';

const PERSONA_TEMPLATE = {
    name: 'Example Persona',
    system_prompt: 'You are a helpful assistant with expertise in...',
    guidelines: ['Be concise and clear', 'Cite sources when relevant'],
    temperature: 0.7,
    notes: 'Optional internal notes or tags',
};

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        system_prompt: '',
        guidelines: [],
        temperature: 0.7,
        notes: '',
    });

    const fileInputRef = useRef(null);
    const [showCreator, setShowCreator] = useState(false);
    const [creatorInput, setCreatorInput] = useState({
        idea: '',
        tone: '',
        audience: '',
        style: '',
        constraints: '',
    });
    const [creatorError, setCreatorError] = useState('');
    const [isGenerating, setIsGenerating] = useState(false);

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/personas');
    };

    const handleDownloadTemplate = () => {
        const blob = new Blob([JSON.stringify(PERSONA_TEMPLATE, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'persona-template.json';
        a.click();
        URL.revokeObjectURL(url);
    };

    const handleImport = (e) => {
        const file = e.target.files?.[0];
        if (!file) {
            return;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
            try {
                const json = JSON.parse(event.target.result);
                setData({
                    name: json.name ?? data.name,
                    system_prompt: json.system_prompt ?? data.system_prompt,
                    guidelines: Array.isArray(json.guidelines) ? json.guidelines : data.guidelines,
                    temperature: json.temperature !== undefined ? parseFloat(json.temperature) : data.temperature,
                    notes: json.notes ?? data.notes,
                });
            } catch {
                alert('Invalid JSON file. Please use a valid persona template.');
            }
        };
        reader.readAsText(file);
        e.target.value = '';
    };

    const generatePersona = async (e) => {
        e.preventDefault();
        setCreatorError('');
        setIsGenerating(true);

        try {
            const response = await fetch(route('personas.generate'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(creatorInput),
            });

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload?.message ?? 'Failed to generate persona.');
            }

            setData('name', payload.name);
            setData('system_prompt', payload.system_prompt);
            setShowCreator(false);
        } catch (error) {
            setCreatorError(error?.message ?? 'Failed to generate persona.');
        } finally {
            setIsGenerating(false);
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Define Persona" />

            <div className="min-h-screen text-zinc-100 flex justify-center py-12 px-4 sm:px-6 lg:px-8">
                <div className="w-full max-w-3xl space-y-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-zinc-400">Define New Persona</h1>
                        <p className="mt-2 text-sm text-zinc-500">Create a reusable persona template (provider/model selected per conversation)</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={handleDownloadTemplate}
                            className="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-zinc-900 border border-zinc-800 text-zinc-400 hover:text-white hover:border-zinc-700 transition-colors text-xs font-medium"
                            title="Download a sample JSON template"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                            Template
                        </button>
                        <button
                            type="button"
                            onClick={() => setShowCreator(true)}
                            className="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 hover:text-white hover:bg-indigo-500/20 transition-colors text-xs font-semibold"
                            title="Generate a persona with AI"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3l1.9 3.8L18 8.7l-3 2.9.7 4.1-3.7-1.9L8.3 15.7 9 11.6 6 8.7l4.1-.9L12 3z"/><path d="M19 15l.9 1.8L22 17.2l-1.5 1.5.4 2.1-1.9-1-1.9 1 .4-2.1-1.5-1.5 2.1-.4L19 15z"/></svg>
                            AI Creator
                        </button>
                        <button
                            type="button"
                            onClick={() => fileInputRef.current?.click()}
                            className="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-zinc-900 border border-zinc-800 text-zinc-400 hover:text-white hover:border-zinc-700 transition-colors text-xs font-medium"
                            title="Import persona from a JSON file"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                            Import JSON
                        </button>
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".json,application/json"
                            onChange={handleImport}
                            className="hidden"
                        />
                        <Link href="/personas" className="p-2 rounded-lg bg-zinc-900 border border-zinc-800 text-zinc-400 hover:text-white transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </Link>
                    </div>
                </div>

                <div className="glass-panel glass-butter p-8 rounded-2xl butter-reveal">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Name */}
                        <div className="space-y-2">
                            <label className="text-xs font-bold uppercase tracking-wider text-indigo-400 ml-1">Persona Name</label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={e => setData('name', e.target.value)}
                                className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-3 text-zinc-100 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition-all"
                                placeholder="e.g. Technical Expert, Creative Writer, Debate Champion"
                            />
                            {errors.name && <div className="text-red-400 text-xs ml-1">{errors.name}</div>}
                        </div>

                        {/* System Prompt */}
                        <div className="space-y-2">
                            <label className="text-xs font-bold uppercase tracking-wider text-emerald-400 ml-1">System Prompt</label>
                            <textarea
                                value={data.system_prompt}
                                onChange={e => setData('system_prompt', e.target.value)}
                                className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-4 text-zinc-300 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 outline-none transition-all min-h-[200px] leading-relaxed resize-y"
                                placeholder="Define the personality, role, constraints, and behavior of this persona..."
                            ></textarea>
                            {errors.system_prompt && <div className="text-red-400 text-xs ml-1">{errors.system_prompt}</div>}
                        </div>

                        {/* Temperature */}
                        <div className="space-y-2">
                            <div className="flex justify-between">
                                <label className="text-xs font-bold uppercase tracking-wider text-purple-400 ml-1">Default Temperature</label>
                                <span className="text-xs font-mono text-zinc-400">{data.temperature}</span>
                            </div>
                            <input
                                type="range"
                                step="0.1"
                                min="0"
                                max="2"
                                value={data.temperature}
                                onChange={e => setData('temperature', parseFloat(e.target.value))}
                                className="w-full"
                            />
                            <div className="flex justify-between text-xs text-zinc-600">
                                <span>Deterministic</span>
                                <span>Creative</span>
                            </div>
                            <p className="text-xs text-zinc-600 ml-1">Can be overridden per conversation</p>
                        </div>

                        {/* Notes */}
                        <div className="space-y-2">
                            <label className="text-xs font-bold uppercase tracking-wider text-zinc-500 ml-1">Notes (Optional)</label>
                            <input
                                type="text"
                                value={data.notes}
                                onChange={e => setData('notes', e.target.value)}
                                className="w-full bg-zinc-900/30 border border-white/5 rounded-xl p-3 text-zinc-400 focus:border-white/20 outline-none transition-all"
                                placeholder="Internal notes or tags..."
                            />
                        </div>

                        {/* Divider */}
                        <div className="h-px bg-gradient-to-r from-transparent via-white/10 to-transparent my-6"></div>

                        {/* Actions */}
                        <div className="flex items-center justify-end gap-4">
                            <Link
                                href="/personas"
                                className="px-6 py-2 rounded-xl text-zinc-400 hover:text-white hover:bg-white/5 transition-colors text-sm font-medium"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className="bg-white text-black px-8 py-2.5 rounded-xl font-bold hover:scale-105 transition-transform shadow-[0_0_20px_rgba(255,255,255,0.2)] disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {processing ? 'Creating...' : 'Create Persona'}
                            </button>
                        </div>
                    </form>
                </div>
                </div>
            </div>

            {showCreator && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <button
                        type="button"
                        aria-label="Close AI Creator"
                        onClick={() => setShowCreator(false)}
                        className="absolute inset-0 bg-black/70 backdrop-blur-sm"
                    />
                    <div className="relative z-10 w-full max-w-2xl rounded-2xl border border-white/10 bg-zinc-950/90 p-6 shadow-[0_28px_90px_rgba(0,0,0,0.7)]">
                        <div className="mb-5 flex items-start justify-between gap-3">
                            <div>
                                <h2 className="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-indigo-300 to-cyan-300">
                                    Persona Creator Bot
                                </h2>
                                <p className="mt-1 text-sm text-zinc-400">
                                    Describe the persona and AI will fill in both name and system prompt.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowCreator(false)}
                                className="rounded-lg border border-white/10 p-2 text-zinc-400 hover:text-white hover:border-white/30"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
                            </button>
                        </div>

                        <form onSubmit={generatePersona} className="space-y-4">
                            <div className="space-y-2">
                                <label className="text-xs font-semibold uppercase tracking-wider text-indigo-300">Persona Idea</label>
                                <textarea
                                    value={creatorInput.idea}
                                    onChange={(e) => setCreatorInput((previous) => ({ ...previous, idea: e.target.value }))}
                                    className="min-h-[100px] w-full rounded-xl border border-white/10 bg-zinc-900/70 p-3 text-sm text-zinc-100 outline-none focus:border-indigo-500/60 focus:ring-2 focus:ring-indigo-500/20"
                                    placeholder="Example: A skeptical forensic investigator who debates claims step-by-step and demands evidence."
                                    required
                                />
                            </div>

                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div className="space-y-2">
                                    <label className="text-xs font-semibold uppercase tracking-wider text-zinc-400">Tone</label>
                                    <input
                                        type="text"
                                        value={creatorInput.tone}
                                        onChange={(e) => setCreatorInput((previous) => ({ ...previous, tone: e.target.value }))}
                                        className="w-full rounded-xl border border-white/10 bg-zinc-900/70 p-3 text-sm text-zinc-100 outline-none focus:border-indigo-500/60 focus:ring-2 focus:ring-indigo-500/20"
                                        placeholder="Calm, direct, witty"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <label className="text-xs font-semibold uppercase tracking-wider text-zinc-400">Audience</label>
                                    <input
                                        type="text"
                                        value={creatorInput.audience}
                                        onChange={(e) => setCreatorInput((previous) => ({ ...previous, audience: e.target.value }))}
                                        className="w-full rounded-xl border border-white/10 bg-zinc-900/70 p-3 text-sm text-zinc-100 outline-none focus:border-indigo-500/60 focus:ring-2 focus:ring-indigo-500/20"
                                        placeholder="Developers, founders, students"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <label className="text-xs font-semibold uppercase tracking-wider text-zinc-400">Response Style</label>
                                    <input
                                        type="text"
                                        value={creatorInput.style}
                                        onChange={(e) => setCreatorInput((previous) => ({ ...previous, style: e.target.value }))}
                                        className="w-full rounded-xl border border-white/10 bg-zinc-900/70 p-3 text-sm text-zinc-100 outline-none focus:border-indigo-500/60 focus:ring-2 focus:ring-indigo-500/20"
                                        placeholder="Bulleted, analytical, compact"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <label className="text-xs font-semibold uppercase tracking-wider text-zinc-400">Constraints</label>
                                    <input
                                        type="text"
                                        value={creatorInput.constraints}
                                        onChange={(e) => setCreatorInput((previous) => ({ ...previous, constraints: e.target.value }))}
                                        className="w-full rounded-xl border border-white/10 bg-zinc-900/70 p-3 text-sm text-zinc-100 outline-none focus:border-indigo-500/60 focus:ring-2 focus:ring-indigo-500/20"
                                        placeholder="No fluff, always include risks"
                                    />
                                </div>
                            </div>

                            {creatorError && (
                                <div className="rounded-xl border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-200">
                                    {creatorError}
                                </div>
                            )}

                            <div className="flex items-center justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setShowCreator(false)}
                                    className="rounded-xl border border-white/15 px-4 py-2 text-sm text-zinc-300 hover:border-white/30 hover:text-white"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={isGenerating}
                                    className="rounded-xl bg-gradient-to-r from-indigo-500 to-cyan-500 px-5 py-2 text-sm font-semibold text-white shadow-[0_0_20px_rgba(56,189,248,0.3)] hover:brightness-110 disabled:opacity-60"
                                >
                                    {isGenerating ? 'Generating...' : 'Generate Persona'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
