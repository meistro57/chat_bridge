import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';

export default function Edit({ apiKey }) {
    const { data, setData, put, processing, errors } = useForm({
        provider: apiKey.provider,
        label: apiKey.label || '',
        key: '', // Empty by default
        is_active: apiKey.is_active,
    });

    const providers = ['openai', 'anthropic', 'gemini', 'deepseek', 'openrouter', 'ollama', 'lmstudio'];

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/api-keys/${apiKey.id}`);
    };

    return (
        <div className="min-h-screen text-zinc-100 flex justify-center py-12 px-4 sm:px-6 lg:px-8">
            <Head title="Edit API Key" />
            
            <div className="w-full max-w-2xl space-y-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-zinc-400">Modify Credential</h1>
                        <p className="mt-2 text-sm text-zinc-500">Update parameters for: <span className="text-white font-mono">{apiKey.provider.toUpperCase()}</span></p>
                    </div>
                </div>

                <div className="glass-panel p-8 rounded-2xl relative overflow-hidden">
                    <form onSubmit={handleSubmit} className="space-y-6 relative z-10">
                        
                        <div className="space-y-2">
                            <label className="text-xs font-bold uppercase tracking-wider text-purple-400 ml-1">Provider Service</label>
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

                        <div className="space-y-2">
                            <label className="text-xs font-bold uppercase tracking-wider text-zinc-500 ml-1">Label</label>
                            <input 
                                type="text"
                                value={data.label}
                                onChange={e => setData('label', e.target.value)}
                                className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-3 text-zinc-100 focus:border-white/30 outline-none transition-all"
                            />
                        </div>

                        <div className="space-y-2">
                            <label className="text-xs font-bold uppercase tracking-wider text-emerald-400 ml-1">Secret Key</label>
                            <input 
                                type="password"
                                value={data.key}
                                onChange={e => setData('key', e.target.value)}
                                className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-3 text-zinc-100 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 outline-none transition-all placeholder:text-zinc-600"
                                placeholder="Leave blank to keep current key"
                                autoComplete="new-password"
                            />
                        </div>

                        <div className="flex items-center gap-3 p-4 bg-white/5 rounded-xl border border-white/5">
                            <input 
                                type="checkbox"
                                id="is_active"
                                checked={data.is_active}
                                onChange={e => setData('is_active', e.target.checked)}
                                className="w-5 h-5 rounded border-zinc-600 text-indigo-600 focus:ring-indigo-500 bg-zinc-800"
                            />
                            <label htmlFor="is_active" className="text-sm font-medium text-zinc-300 cursor-pointer select-none">
                                Enable this key for API calls
                            </label>
                        </div>

                        <div className="h-px bg-gradient-to-r from-transparent via-white/10 to-transparent my-6"></div>

                        <div className="flex items-center justify-end gap-4">
                            <Link href="/api-keys" className="px-6 py-2 rounded-xl text-zinc-400 hover:text-white hover:bg-white/5 transition-colors text-sm font-medium">
                                Cancel
                            </Link>
                            <button 
                                type="submit" 
                                disabled={processing}
                                className="bg-white text-black px-8 py-2.5 rounded-xl font-bold hover:scale-105 transition-transform shadow-[0_0_20px_rgba(255,255,255,0.2)] disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {processing ? 'Updating...' : 'Save Changes'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
