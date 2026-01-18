import React from 'react';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ apiKeys }) {
    const handleDelete = (id) => {
        if (confirm('Delete this API Key?')) {
            router.delete(`/api-keys/${id}`);
        }
    };

    return (
        <div className="min-h-screen text-zinc-100 p-6 md:p-12">
            <Head title="API Keys" />
            
            <div className="max-w-5xl mx-auto space-y-8">
                {/* Header */}
                <div className="flex flex-col md:flex-row justify-between items-end pb-8 border-b border-white/5 gap-6">
                    <div>
                        <Link href="/chat" className="text-xs font-mono text-zinc-500 hover:text-white mb-2 block uppercase tracking-wide">&larr; Return to Bridge</Link>
                        <h1 className="text-4xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-zinc-400">Credential Vault</h1>
                        <p className="text-zinc-500 mt-2">Manage provider authentication keys securely.</p>
                    </div>
                    
                    <Link 
                        href="/api-keys/create"
                        className="group flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium transition-all hover:shadow-[0_0_20px_rgba(99,102,241,0.3)]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                        Add New Key
                    </Link>
                </div>

                {/* List */}
                <div className="grid gap-4">
                    {apiKeys.map(key => (
                        <div key={key.id} className="group glass-panel rounded-2xl p-6 flex flex-col md:flex-row justify-between items-center gap-4 hover:border-indigo-500/30 transition-all">
                            <div className="flex items-center gap-4 w-full md:w-auto">
                                <div className={`w-12 h-12 rounded-xl flex items-center justify-center border border-white/10 ${key.is_active ? 'bg-indigo-500/10 text-indigo-400' : 'bg-red-500/10 text-red-500'}`}>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                                </div>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h3 className="font-bold text-lg text-zinc-100">{key.label || key.provider.toUpperCase()}</h3>
                                        <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider ${key.is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-zinc-800 text-zinc-500'}`}>
                                            {key.is_active ? 'Active' : 'Revoked'}
                                        </span>
                                    </div>
                                    <div className="text-sm text-zinc-500 font-mono mt-1 flex items-center gap-2">
                                        <span className="uppercase text-xs tracking-wider text-zinc-600">{key.provider}</span>
                                        <span>•</span>
                                        <span className="opacity-50">{key.masked_key}</span>
                                    </div>
                                </div>
                            </div>

                            <div className="flex gap-2 w-full md:w-auto justify-end">
                                <Link 
                                    href={`/api-keys/${key.id}/edit`} 
                                    className="px-4 py-2 rounded-lg bg-zinc-900/50 hover:bg-white text-zinc-400 hover:text-black transition-colors text-sm font-medium border border-white/5"
                                >
                                    Configure
                                </Link>
                                <button 
                                    onClick={() => handleDelete(key.id)} 
                                    className="p-2 rounded-lg bg-red-900/10 hover:bg-red-500 text-red-500 hover:text-white transition-colors border border-red-900/20"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                                </button>
                            </div>
                        </div>
                    ))}

                    {apiKeys.length === 0 && (
                        <div className="text-center py-24 glass-panel rounded-3xl border-dashed border-zinc-800">
                            <p className="text-zinc-500 mb-4">No credentials stored locally.</p>
                            <Link href="/api-keys/create" className="text-indigo-400 hover:text-indigo-300">Register a Provider Key</Link>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
