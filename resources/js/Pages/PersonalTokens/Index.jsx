import { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function Index({ tokens, newToken }) {
    const [showNewToken, setShowNewToken] = useState(!!newToken);

    useEffect(() => {
        if (newToken) {
            setShowNewToken(true);
        }
    }, [newToken]);
    const [copied, setCopied] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ name: '' });

    const handleCreate = (e) => {
        e.preventDefault();
        post(route('personal-tokens.store'), { onSuccess: () => reset() });
    };

    const handleRevoke = (id) => {
        if (confirm('Revoke this token? Any integrations using it will stop working.')) {
            router.delete(route('personal-tokens.destroy', id));
        }
    };

    const handleCopy = () => {
        navigator.clipboard.writeText(newToken);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <AuthenticatedLayout>
            <Head title="API Tokens" />

            <div className="min-h-screen text-zinc-100 p-6 md:p-12">
                <div className="max-w-5xl mx-auto space-y-8">

                    {/* Header */}
                    <div className="flex flex-col md:flex-row justify-between items-end pb-8 border-b border-white/5 gap-6">
                        <div>
                            <Link href="/chat" className="text-xs font-mono text-zinc-500 hover:text-white mb-2 block uppercase tracking-wide">
                                &larr; Return to Bridge
                            </Link>
                            <h1 className="text-4xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-zinc-400">
                                API Tokens
                            </h1>
                            <p className="text-zinc-500 mt-2">
                                Generate tokens for programmatic access to the Chat Bridge API.
                            </p>
                        </div>
                    </div>

                    {/* One-time new token banner */}
                    {showNewToken && newToken && (
                        <div className="relative bg-emerald-950/60 border border-emerald-500/30 rounded-2xl p-6 space-y-3">
                            <div className="flex items-center gap-2 text-emerald-400 font-semibold">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                Token created — copy it now. You will not be able to see it again.
                            </div>
                            <div className="flex items-center gap-3">
                                <code className="flex-1 bg-black/40 rounded-lg px-4 py-3 text-sm font-mono text-emerald-300 break-all select-all">
                                    {newToken}
                                </code>
                                <button
                                    onClick={handleCopy}
                                    className="shrink-0 px-4 py-3 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-medium transition-colors"
                                >
                                    {copied ? 'Copied!' : 'Copy'}
                                </button>
                            </div>
                            <p className="text-xs text-zinc-500">
                                Use this token as a Bearer token: <code className="text-zinc-400">Authorization: Bearer {'<token>'}</code>
                            </p>
                            <button
                                onClick={() => setShowNewToken(false)}
                                className="absolute top-4 right-4 text-zinc-500 hover:text-white"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                    )}

                    {/* Create token form */}
                    <div className="bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-6 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)]">
                        <h2 className="text-lg font-semibold text-zinc-200 mb-4">Create New Token</h2>
                        <form onSubmit={handleCreate} className="flex gap-3">
                            <div className="flex-1">
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={e => setData('name', e.target.value)}
                                    placeholder="Token name (e.g. My Integration, Claude Desktop)"
                                    className="w-full bg-zinc-800 border border-white/10 rounded-xl px-4 py-2.5 text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-indigo-500/50 focus:ring-1 focus:ring-indigo-500/20"
                                />
                                {errors.name && (
                                    <p className="mt-1 text-xs text-red-400">{errors.name}</p>
                                )}
                            </div>
                            <button
                                type="submit"
                                disabled={processing}
                                className="shrink-0 flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white px-5 py-2.5 rounded-xl font-medium transition-all hover:shadow-[0_0_20px_rgba(99,102,241,0.3)]"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                                {processing ? 'Creating…' : 'Create Token'}
                            </button>
                        </form>
                    </div>

                    {/* Token list */}
                    <div className="grid gap-4">
                        {tokens.map((token, index) => (
                            <div
                                key={token.id}
                                className="group relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-6 flex flex-col md:flex-row justify-between items-center gap-4 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden hover:bg-zinc-900/60 hover:border-white/[0.15]"
                            >
                                <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-indigo-500/80 via-violet-500/80 to-purple-500/80" />
                                <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />

                                <div className="flex items-center gap-4 w-full md:w-auto flex-1 min-w-0">
                                    <div className="w-12 h-12 rounded-xl flex items-center justify-center border border-white/10 bg-indigo-500/10 text-indigo-400 shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    </div>
                                    <div className="min-w-0">
                                        <h3 className="font-bold text-lg text-zinc-100">{token.name}</h3>
                                        <div className="text-sm text-zinc-500 font-mono mt-1 flex items-center gap-2 flex-wrap">
                                            <span className="text-xs text-zinc-600">
                                                Created {new Date(token.created_at).toLocaleDateString()}
                                            </span>
                                            {token.last_used_at ? (
                                                <>
                                                    <span>•</span>
                                                    <span className="text-xs text-zinc-600">
                                                        Last used {new Date(token.last_used_at).toLocaleDateString()}
                                                    </span>
                                                </>
                                            ) : (
                                                <>
                                                    <span>•</span>
                                                    <span className="text-xs text-zinc-700">Never used</span>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <div className="flex gap-2 w-full md:w-auto justify-end shrink-0">
                                    <button
                                        onClick={() => handleRevoke(token.id)}
                                        className="px-4 py-2 rounded-lg bg-red-900/10 hover:bg-red-500 text-red-500 hover:text-white transition-colors text-sm font-medium border border-red-900/20 flex items-center gap-2"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                                        Revoke
                                    </button>
                                </div>
                            </div>
                        ))}

                        {tokens.length === 0 && (
                            <div className="text-center py-24 bg-zinc-900/50 backdrop-blur-2xl rounded-2xl border-2 border-dashed border-zinc-700">
                                <p className="text-zinc-500 mb-2">No API tokens yet.</p>
                                <p className="text-zinc-600 text-sm">Create one above to get programmatic access to Chat Bridge.</p>
                            </div>
                        )}
                    </div>

                    {/* Usage docs */}
                    <div className="bg-zinc-900/30 rounded-2xl p-6 border border-white/[0.05] space-y-3">
                        <h2 className="text-sm font-semibold text-zinc-400 uppercase tracking-wider">Usage</h2>
                        <div className="space-y-2 text-sm font-mono text-zinc-500">
                            <p className="text-zinc-400">Include your token as a Bearer token in the Authorization header:</p>
                            <code className="block bg-black/30 rounded-lg px-4 py-3 text-zinc-300">
                                Authorization: Bearer {'<your-token>'}
                            </code>
                            <p className="text-zinc-400 pt-1">Available endpoints:</p>
                            <code className="block bg-black/30 rounded-lg px-4 py-3 text-zinc-300 space-y-1">
                                <span className="block"><span className="text-indigo-400">POST</span>  /api/chat-bridge/respond</span>
                                <span className="block"><span className="text-emerald-400">GET</span>   /api/mcp/health</span>
                                <span className="block"><span className="text-emerald-400">GET</span>   /api/mcp/stats</span>
                                <span className="block"><span className="text-emerald-400">GET</span>   /api/mcp/recent-chats</span>
                                <span className="block"><span className="text-emerald-400">GET</span>   /api/mcp/search-chats</span>
                                <span className="block"><span className="text-emerald-400">GET</span>   /api/mcp/contextual-memory</span>
                            </code>
                        </div>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
