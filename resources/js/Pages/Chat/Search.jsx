import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

export default function Search({ results, query }) {
    const [searchTerm, setSearchTerm] = useState(query || '');
    const revealClasses = [
        'butter-reveal',
        'butter-reveal butter-reveal-delay-1',
        'butter-reveal butter-reveal-delay-2',
        'butter-reveal butter-reveal-delay-3',
    ];

    const handleSearch = (e) => {
        e.preventDefault();
        router.get('/chat/search', { q: searchTerm }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Search Archives" />
            
            <div className="min-h-screen text-zinc-100 p-6 md:p-12">
                <div className="max-w-4xl mx-auto space-y-12">
                {/* Header & Search Form */}
                <div className="space-y-8 butter-reveal">
                    <div className="flex justify-between items-center">
                        <Link href="/chat" className="flex items-center gap-2 text-zinc-400 hover:text-white transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                            Back to Dashboard
                        </Link>
                        <div className="flex items-center gap-2">
                            <div className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                            <span className="text-[10px] font-mono uppercase tracking-widest text-zinc-500">Archive Link Active</span>
                        </div>
                    </div>

                    <div className="text-center space-y-4">
                        <h1 className="text-4xl font-bold tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-white to-zinc-500">Archive Search</h1>
                        <p className="text-zinc-500 max-w-lg mx-auto">Retrieve specific neural interactions by keyword, timestamp, or entity reference.</p>
                    </div>

                    <form onSubmit={handleSearch} className="relative max-w-2xl mx-auto group">
                        <div className="absolute inset-0 bg-gradient-to-r from-indigo-500/20 to-purple-500/20 rounded-2xl blur-xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div className="relative flex items-center">
                            <div className="absolute left-6 text-zinc-400 pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                            </div>
                            <input 
                                type="text"
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="w-full glass-panel pl-16 pr-32 py-5 rounded-2xl text-xl bg-zinc-900/40 border-white/10 focus:border-indigo-500/50 focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all placeholder:text-zinc-600"
                                placeholder="Search transcript data..."
                                autoFocus
                            />
                            <button 
                                type="submit"
                                className="absolute right-3 px-6 py-2.5 bg-zinc-100 text-zinc-900 font-bold rounded-xl hover:bg-white hover:scale-105 transition-all text-sm"
                            >
                                Search
                            </button>
                        </div>
                    </form>
                </div>

                {/* Results Area */}
                <div className="space-y-2">
                    {query && (
                        <div className="flex items-center justify-between text-zinc-500 text-sm border-b border-white/5 pb-4">
                            <span>Found {results.length} matching records</span>
                            <span className="font-mono text-xs opacity-50">QUERY: "{query}"</span>
                        </div>
                    )}

                    <div className="grid gap-4">
                        {results.map((msg, index) => (
                            <div key={msg.id} className={`group glass-panel glass-butter p-6 rounded-2xl hover:border-indigo-500/30 ${revealClasses[index % revealClasses.length]}`}>
                                <div className="flex flex-col gap-3">
                                    <div className="flex justify-between items-start">
                                        <div className="flex items-center gap-3">
                                            <div className={`w-8 h-8 rounded-lg flex items-center justify-center text-[10px] font-bold border border-white/5 ${
                                                msg.persona ? 'bg-indigo-500/10 text-indigo-400' : 'bg-purple-500/10 text-purple-400'
                                            }`}>
                                                {msg.persona ? 'P' : 'S'}
                                            </div>
                                            <div>
                                                <div className="font-bold text-zinc-200">{msg.persona?.name || 'SYSTEM'}</div>
                                                <div className="text-[10px] uppercase text-zinc-500 flex items-center gap-1">
                                                    in session 
                                                    <Link href={`/chat/${msg.conversation_id}`} className="hover:text-white transition-colors border-b border-dotted border-zinc-600 hover:border-white">
                                                        {msg.conversation_id.substring(0,8)}
                                                    </Link>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="text-xs text-zinc-600 font-mono">
                                            {new Date(msg.created_at).toLocaleString()}
                                        </div>
                                    </div>
                                    
                                    <p className="pl-11 text-zinc-300 leading-relaxed border-l-2 border-white/5 py-1">
                                        "{msg.content.substring(0, 300)}{msg.content.length > 300 ? '...' : ''}"
                                    </p>
                                    
                                    <div className="pl-11 pt-2 flex items-center gap-4">
                                        <Link 
                                            href={`/chat/${msg.conversation_id}`} 
                                            className="text-xs font-bold text-indigo-400 hover:text-indigo-300 flex items-center gap-1"
                                        >
                                            View Full Context <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        ))}

                        {results.length === 0 && query && (
                            <div className="text-center py-24 glass-panel glass-butter rounded-3xl border-dashed border-zinc-800 butter-reveal">
                                <div className="inline-flex p-4 rounded-full bg-zinc-800/50 mb-4 text-zinc-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="m8 8 6 6"/><path d="m14 8-6 6"/></svg>
                                </div>
                                <h3 className="text-lg font-medium text-zinc-400">No matches located</h3>
                                <p className="text-zinc-600">Try adjusting your search parameters.</p>
                            </div>
                        )}
                        
                        {!query && (
                            <div className="text-center py-32 opacity-20">
                                <h3 className="text-4xl font-bold tracking-tight text-zinc-500">AWAITING INPUT</h3>
                            </div>
                        )}
                    </div>
                </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
