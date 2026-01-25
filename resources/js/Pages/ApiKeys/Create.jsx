import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        provider: 'openai',
        key: '',
        label: '',
        is_active: true, // not natively supported by controller create but useful if added later, ignoring for now as default database is true
    });

    const providers = ['openai', 'anthropic', 'gemini', 'deepseek', 'openrouter', 'ollama', 'lmstudio'];

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/api-keys');
    };

    return (
        <AuthenticatedLayout>
            <Head title="Add API Key" />
            
            <div className="min-h-screen text-zinc-100 flex justify-center py-12 px-4 sm:px-6 lg:px-8">
                <div className="w-full max-w-2xl space-y-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-zinc-400">Secure Storage</h1>
                        <p className="mt-2 text-sm text-zinc-500">Encrypted credential provisioning for AI providers.</p>
                    </div>
                    <Link href="/api-keys" className="p-2 rounded-lg bg-zinc-900 border border-zinc-800 text-zinc-400 hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </Link>
                </div>

                <div className="glass-panel glass-butter p-8 rounded-2xl relative overflow-hidden butter-reveal">
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
                            {errors.provider && <div className="text-red-400 text-xs ml-1">{errors.provider}</div>}
                        </div>

                        <div className="space-y-2">
                            <label className="text-xs font-bold uppercase tracking-wider text-zinc-500 ml-1">Label (Optional)</label>
                            <input 
                                type="text"
                                value={data.label}
                                onChange={e => setData('label', e.target.value)}
                                className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-3 text-zinc-100 focus:border-white/30 outline-none transition-all placeholder:text-zinc-700"
                                placeholder="e.g. My Personal Pro Key"
                            />
                        </div>

                        <div className="space-y-2">
                            <label className="text-xs font-bold uppercase tracking-wider text-emerald-400 ml-1">Secret Key</label>
                            <input 
                                type="password"
                                value={data.key}
                                onChange={e => setData('key', e.target.value)}
                                className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-3 text-zinc-100 focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 outline-none transition-all"
                                placeholder="sk-..."
                                autoComplete="new-password"
                            />
                            {errors.key && <div className="text-red-400 text-xs ml-1">{errors.key}</div>}
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
                                {processing ? 'Encrypting...' : 'Save & Encrypt'}
                            </button>
                        </div>
                    </form>
                </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
