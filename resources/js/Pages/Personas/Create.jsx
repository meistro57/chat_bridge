import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        system_prompt: '',
        guidelines: [],
        temperature: 0.7,
        notes: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/personas');
    };

    return (
        <div className="min-h-screen text-zinc-100 flex justify-center py-12 px-4 sm:px-6 lg:px-8">
            <Head title="Define Persona" />

            <div className="w-full max-w-3xl space-y-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-zinc-400">Define New Persona</h1>
                        <p className="mt-2 text-sm text-zinc-500">Create a reusable persona template (provider/model selected per conversation)</p>
                    </div>
                    <Link href="/personas" className="p-2 rounded-lg bg-zinc-900 border border-zinc-800 text-zinc-400 hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </Link>
                </div>

                <div className="glass-panel p-8 rounded-2xl">
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
    );
}
