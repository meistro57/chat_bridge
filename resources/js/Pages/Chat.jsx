import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Chat({ personas, conversations, debug_info }) {
    const { auth } = usePage().props;
    
    return (
        <div className="min-h-screen text-zinc-100 p-6 md:p-12">
            {debug_info && <div className="bg-red-500 text-white p-2 text-center absolute top-0 left-0 w-full z-50">{debug_info}</div>}
            <Head title="Bridge Control" />
            
            <div className="max-w-7xl mx-auto space-y-12">
                {/* Header Section */}
                <div className="flex flex-col md:flex-row justify-between items-end border-b border-white/5 pb-8 gap-6">
                    <div>
                        <div className="flex items-center gap-3 mb-2">
                            <div className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                            <span className="text-xs font-mono text-zinc-400 tracking-[0.2em] uppercase">System Online</span>
                        </div>
                        <h1 className="text-5xl md:text-6xl font-black bg-clip-text text-transparent bg-gradient-to-r from-white via-zinc-200 to-zinc-500 tracking-tight">
                            Bridge Network.
                        </h1>
                    </div>
                    
                    <div className="flex items-center gap-4">
                        <Link 
                            href="/api-keys" 
                            className="px-4 py-3 rounded-xl font-medium text-zinc-400 hover:text-white hover:bg-white/5 transition-all duration-300"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                        </Link>
                        {auth.user.role === 'admin' && (
                            <Link 
                                href={route('admin.users.index')} 
                                className="px-6 py-3 rounded-xl font-medium text-zinc-400 hover:text-white hover:bg-white/5 transition-all duration-300"
                            >
                                Admin Panel
                            </Link>
                        )}
                        <Link 
                            href="/personas" 
                            className="px-6 py-3 rounded-xl font-medium text-zinc-400 hover:text-white hover:bg-white/5 transition-all duration-300"
                        >
                            Manage Personas
                        </Link>
                        <Link 
                            href="/chat/create" 
                            className="group relative px-8 py-3 bg-white text-black rounded-xl font-bold overflow-hidden transition-all hover:scale-105 hover:shadow-[0_0_40px_rgba(255,255,255,0.3)]"
                        >
                            <div className="absolute inset-0 bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-500 opacity-20 group-hover:opacity-40 transition-opacity"></div>
                            <span className="relative flex items-center gap-2">
                                Initialize New Bridge 
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="lucide lucide-arrow-right"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                            </span>
                        </Link>
                    </div>
                </div>

                {/* Dashboard Grid */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    {/* Left Column: Recent Sessions */}
                    <div className="lg:col-span-2 space-y-6">
                        <div className="flex justify-between items-center mb-4">
                            <h2 className="text-2xl font-light tracking-wide text-zinc-400">Recent Activity</h2>
                            <Link href="/chat/search" className="text-sm text-indigo-400 hover:text-indigo-300 transition-colors">Search Archives &rarr;</Link>
                        </div>
                        
                        <div className="grid gap-4">
                            {conversations.map((conv) => (
                                <Link 
                                    key={conv.id} 
                                    href={`/chat/${conv.id}`}
                                    className="group glass-panel rounded-2xl p-6 transition-all duration-300 hover:border-indigo-500/30 hover:shadow-[0_0_30px_rgba(99,102,241,0.1)] relative overflow-hidden"
                                >
                                    <div className="absolute top-0 right-0 p-6 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-indigo-400"><path d="M7 7h10v10"/><path d="M7 17 17 7"/></svg>
                                    </div>

                                    <div className="flex flex-col gap-4">
                                        <div className="flex items-center gap-3">
                                            <div className="flex -space-x-2">
                                                <div className="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center text-[10px] font-bold ring-2 ring-black">A</div>
                                                <div className="w-8 h-8 rounded-full bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center text-[10px] font-bold ring-2 ring-black">B</div>
                                            </div>
                                            <span className="font-semibold text-lg group-hover:text-indigo-300 transition-colors">
                                                {conv.provider_a} <span className="text-zinc-500 text-sm font-normal">vs</span> {conv.provider_b}
                                            </span>
                                            <span className={`px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wider font-bold ${
                                                conv.status === 'completed' ? 'bg-zinc-800 text-zinc-400' : 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                            }`}>
                                                {conv.status}
                                            </span>
                                        </div>
                                        
                                        <p className="text-zinc-400 text-sm line-clamp-2 leading-relaxed pl-11 border-l border-white/5">
                                            "{conv.starter_message}"
                                        </p>

                                        <div className="flex justify-between items-center pl-11 mt-2">
                                            <span className="text-xs text-zinc-600 font-mono">ID: {conv.id.substring(0,8)}...</span>
                                            <Link 
                                                href={route('chat.destroy', conv.id)} 
                                                method="delete"
                                                as="button"
                                                onClick={(e) => { e.stopPropagation(); if(!confirm('Delete this session?')) e.preventDefault(); }}
                                                className="text-xs text-zinc-600 hover:text-red-400 transition-colors z-10 p-2"
                                            >
                                                Delete
                                            </Link>
                                        </div>
                                    </div>
                                </Link>
                            ))}

                            {conversations.length === 0 && (
                                <div className="glass-panel p-12 text-center rounded-2xl border-dashed border-zinc-800">
                                    <p className="text-zinc-500 mb-4">No active bridges found.</p>
                                    <Link href="/chat/create" className="text-indigo-400 hover:text-indigo-300">Start a new session</Link>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Right Column: Persona Library */}
                    <div>
                        <h2 className="text-2xl font-light tracking-wide text-zinc-400 mb-6">Persona Index</h2>
                        <div className="glass-panel rounded-2xl p-1 h-[600px] overflow-hidden flex flex-col">
                            <div className="overflow-y-auto p-4 space-y-2 custom-scrollbar flex-1">
                                {personas.map((persona) => (
                                    <div key={persona.id} className="group flex items-center justify-between p-3 rounded-xl hover:bg-white/5 transition-colors cursor-default">
                                        <div className="flex items-center gap-3">
                                            <div className="w-8 h-8 rounded-lg bg-zinc-800 flex items-center justify-center text-zinc-400 group-hover:text-white transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                            </div>
                                            <div>
                                                <div className="text-zinc-200 font-medium">{persona.name}</div>
                                                <div className="text-[10px] uppercase tracking-wider text-zinc-500 group-hover:text-indigo-400 transition-colors">{persona.provider}</div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                                {personas.length === 0 && (
                                    <div className="text-center py-8 text-zinc-600 text-sm">No personas instantiated.</div>
                                )}
                            </div>
                            <div className="p-4 border-t border-white/5 bg-white/5 backdrop-blur-sm">
                                <Link 
                                    href="/personas" 
                                    className="block w-full text-center py-2 rounded-lg bg-zinc-800 text-zinc-300 text-sm hover:bg-zinc-700 transition-colors"
                                >
                                    Manage Registry
                                </Link>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    );
}
