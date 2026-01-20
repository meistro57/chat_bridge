import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';

export default function Edit({ persona }) {
    const { data, setData, put, processing, errors } = useForm({
        name: persona.name,
        provider: persona.provider,
        model: persona.model || '',
        system_prompt: persona.system_prompt,
        guidelines: persona.guidelines || [],
        temperature: persona.temperature,
        notes: persona.notes || '',
    });

    const providers = ['openai', 'anthropic', 'gemini', 'deepseek', 'openrouter', 'ollama', 'lmstudio'];

    const modelsByProvider = {
        openai: ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-4', 'gpt-3.5-turbo', 'o1', 'o1-mini', 'o1-preview'],
        anthropic: ['claude-3-5-sonnet-20241022', 'claude-3-5-haiku-20241022', 'claude-3-opus-20240229', 'claude-3-sonnet-20240229', 'claude-3-haiku-20240307'],
        gemini: ['gemini-2.0-flash-exp', 'gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-1.0-pro'],
        deepseek: ['deepseek-chat', 'deepseek-reasoner'],
        openrouter: ['auto'],
        ollama: ['llama3.2', 'llama3.1', 'mistral', 'phi3', 'qwen2.5'],
        lmstudio: ['local-model']
    };

    const availableModels = modelsByProvider[data.provider] || [];

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/personas/${persona.id}`);
    };

    return (
        <div className="min-h-screen text-zinc-100 flex justify-center py-12 px-4 sm:px-6 lg:px-8">
            <Head title={`Edit ${persona.name}`} />
            
            <div className="w-full max-w-3xl space-y-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-zinc-400">Modify Configuration</h1>
                        <p className="mt-2 text-sm text-zinc-500">Update parameters for entity: <span className="text-white font-mono">{persona.name}</span></p>
                    </div>
                    <Link href="/personas" className="p-2 rounded-lg bg-zinc-900 border border-zinc-800 text-zinc-400 hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </Link>
                </div>

                <div className="glass-panel p-8 rounded-2xl relative overflow-hidden">
                    <form onSubmit={handleSubmit} className="space-y-6 relative z-10">
                        {/* Identity Section */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="space-y-2">
                                <label className="text-xs font-bold uppercase tracking-wider text-indigo-400 ml-1">Designation (Name)</label>
                                <input 
                                    type="text"
                                    value={data.name}
                                    onChange={e => setData('name', e.target.value)}
                                    className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-3 text-zinc-100 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition-all"
                                />
                                {errors.name && <div className="text-red-400 text-xs ml-1">{errors.name}</div>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-xs font-bold uppercase tracking-wider text-purple-400 ml-1">Inference Engine</label>
                                <div className="relative">
                                    <select 
                                        value={data.provider}
                                        onChange={e => setData('provider', e.target.value)}
                                        className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-3 text-zinc-100 appearance-none outline-none focus:border-purple-500 transition-all uppercase"
                                    >
                                        {providers.map(p => <option key={p} value={p}>{p}</option>)}
                                    </select>
                                    <div className="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-zinc-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Technical Specs */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="space-y-2">
                                <label className="text-xs font-bold uppercase tracking-wider text-cyan-400 ml-1">Model Selection</label>
                                <div className="relative">
                                    <select
                                        value={data.model}
                                        onChange={e => setData('model', e.target.value)}
                                        className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-3 text-zinc-100 appearance-none outline-none focus:border-cyan-500 transition-all"
                                    >
                                        <option value="">Default / Auto</option>
                                        {availableModels.map(model => (
                                            <option key={model} value={model}>{model}</option>
                                        ))}
                                    </select>
                                    <div className="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-zinc-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                    </div>
                                </div>
                                <p className="text-xs text-zinc-600 ml-1">Models available for {data.provider}</p>
                            </div>
                            <div className="space-y-2">
                                <div className="flex justify-between">
                                    <label className="text-xs font-bold uppercase tracking-wider text-zinc-500 ml-1">Creativity Index</label>
                                    <span className="text-xs font-mono text-zinc-400">{data.temperature}</span>
                                </div>
                                <input 
                                    type="range"
                                    step="0.1"
                                    min="0"
                                    max="2"
                                    value={data.temperature}
                                    onChange={e => setData('temperature', e.target.value)}
                                    className="w-full h-2 bg-zinc-800 rounded-lg appearance-none cursor-pointer accent-indigo-500"
                                />
                            </div>
                        </div>

                        {/* Core Logic */}
                        <div className="space-y-2">
                            <label className="text-xs font-bold uppercase tracking-wider text-emerald-400 ml-1">System Prompt / Directive</label>
                            <textarea 
                                value={data.system_prompt}
                                onChange={e => setData('system_prompt', e.target.value)}
                                className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-4 text-zinc-300 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 outline-none transition-all min-h-[200px] leading-relaxed resize-y"
                            ></textarea>
                            {errors.system_prompt && <div className="text-red-400 text-xs ml-1">{errors.system_prompt}</div>}
                        </div>

                        {/* Meta */}
                        <div className="space-y-2">
                            <label className="text-xs font-bold uppercase tracking-wider text-zinc-600 ml-1">Administrative Notes</label>
                            <input 
                                type="text"
                                value={data.notes}
                                onChange={e => setData('notes', e.target.value)}
                                className="w-full bg-zinc-900/30 border border-white/5 rounded-xl p-3 text-zinc-400 focus:border-white/20 outline-none transition-all"
                            />
                        </div>

                         {/* Formatting Line */}
                         <div className="h-px bg-gradient-to-r from-transparent via-white/10 to-transparent my-6"></div>

                        {/* Actions */}
                        <div className="flex items-center justify-end gap-4">
                            <Link 
                                href="/personas" 
                                className="px-6 py-2 rounded-xl text-zinc-400 hover:text-white hover:bg-white/5 transition-colors text-sm font-medium"
                            >
                                Discard Changes
                            </Link>
                            <button 
                                type="submit" 
                                disabled={processing}
                                className="bg-white text-black px-8 py-2.5 rounded-xl font-bold hover:scale-105 transition-transform shadow-[0_0_20px_rgba(255,255,255,0.2)] disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {processing ? 'Updating...' : 'Commit Updates'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
