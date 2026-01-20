import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ apiKeys }) {
    const [testing, setTesting] = useState({});

    const handleDelete = (id) => {
        if (confirm('Delete this API Key?')) {
            router.delete(`/api-keys/${id}`);
        }
    };

    const handleTest = async (id) => {
        setTesting(prev => ({ ...prev, [id]: true }));

        try {
            const response = await fetch(`/api-keys/${id}/test`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });

            const data = await response.json();

            if (response.ok) {
                router.reload({ only: ['apiKeys'] });
            } else {
                alert(`Validation failed: ${data.error || data.message}`);
                router.reload({ only: ['apiKeys'] });
            }
        } catch (error) {
            alert(`Error testing API key: ${error.message}`);
        } finally {
            setTesting(prev => ({ ...prev, [id]: false }));
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
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <h3 className="font-bold text-lg text-zinc-100">{key.label || key.provider.toUpperCase()}</h3>
                                        <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider ${key.is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-zinc-800 text-zinc-500'}`}>
                                            {key.is_active ? 'Active' : 'Revoked'}
                                        </span>
                                        {key.is_validated && (
                                            <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-green-500/10 text-green-400 flex items-center gap-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                Validated
                                            </span>
                                        )}
                                        {!key.is_validated && key.last_validated_at && (
                                            <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-red-500/10 text-red-400 flex items-center gap-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                                                Invalid
                                            </span>
                                        )}
                                    </div>
                                    <div className="text-sm text-zinc-500 font-mono mt-1 flex items-center gap-2 flex-wrap">
                                        <span className="uppercase text-xs tracking-wider text-zinc-600">{key.provider}</span>
                                        <span>•</span>
                                        <span className="opacity-50">{key.masked_key}</span>
                                        {key.last_validated_at && (
                                            <>
                                                <span>•</span>
                                                <span className="text-[10px] text-zinc-600">
                                                    Tested {new Date(key.last_validated_at).toLocaleDateString()}
                                                </span>
                                            </>
                                        )}
                                    </div>
                                    {key.validation_error && (
                                        <div className="mt-2 text-xs text-red-400 bg-red-500/5 border border-red-500/10 rounded px-2 py-1">
                                            {key.validation_error}
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="flex gap-2 w-full md:w-auto justify-end">
                                <button
                                    onClick={() => handleTest(key.id)}
                                    disabled={testing[key.id]}
                                    className="px-4 py-2 rounded-lg bg-blue-900/10 hover:bg-blue-500 text-blue-400 hover:text-white transition-colors text-sm font-medium border border-blue-900/20 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                                >
                                    {testing[key.id] ? (
                                        <>
                                            <svg className="animate-spin" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg>
                                            Testing...
                                        </>
                                    ) : (
                                        <>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                                            Test Key
                                        </>
                                    )}
                                </button>
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
