import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ personas }) {
    const revealClasses = [
        'butter-reveal',
        'butter-reveal butter-reveal-delay-1',
        'butter-reveal butter-reveal-delay-2',
        'butter-reveal butter-reveal-delay-3',
    ];
    const handleDelete = (id) => {
        if (confirm('Permanently delete this persona?')) {
            router.delete(`/personas/${id}`);
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Persona Registry" />
            
            <div className="min-h-screen text-zinc-100 p-6 md:p-12">
                <div className="max-w-7xl mx-auto space-y-8">
                {/* Header */}
                <div className="flex flex-col md:flex-row justify-between items-end pb-8 border-b border-white/5 gap-6">
                    <div>
                        <Link href="/chat" className="text-xs font-mono text-zinc-500 hover:text-white mb-2 block uppercase tracking-wide">&larr; Return to Bridge</Link>
                        <h1 className="text-4xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-zinc-400">Persona Registry</h1>
                        <p className="text-zinc-500 mt-2">Manage defined AI entities and behavioral configurations.</p>
                    </div>
                    
                    <Link 
                        href="/personas/create"
                        className="group flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium transition-all hover:shadow-[0_0_20px_rgba(99,102,241,0.3)]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                        Construct New Persona
                    </Link>
                </div>

                {/* Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {personas.map((persona, index) => (
                        <div
                            key={persona.id}
                            className={`group relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-6 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden glass-butter hover:bg-zinc-900/60 hover:border-white/[0.15] hover:shadow-[0_18px_50px_rgba(8,12,20,0.5)] ${revealClasses[index % revealClasses.length]}`}
                        >
                            {/* Accent border at bottom */}
                            <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-indigo-500/80 via-purple-500/80 to-pink-500/80" />
                            <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />

                            {/* Actions Overlay - Visible on Hover */}
                            <div className="absolute top-4 right-4 flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity z-10">
                                <Link
                                    href={`/personas/${persona.id}/edit`}
                                    className="p-2 rounded-lg bg-zinc-900/80 hover:bg-white text-zinc-400 hover:text-black transition-colors"
                                    title="Edit Configuration"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </Link>
                                <button
                                    onClick={() => handleDelete(persona.id)}
                                    className="p-2 rounded-lg bg-red-900/20 hover:bg-red-500 text-red-400 hover:text-white transition-colors"
                                    title="Delete Entity"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                </button>
                            </div>

                            <div className="relative space-y-4">
                                <div className="flex items-start gap-4">
                                    <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 border border-white/10 flex items-center justify-center text-indigo-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    </div>
                                    <div>
                                        <h3 className="font-bold text-lg text-zinc-100 group-hover:text-indigo-300 transition-colors">{persona.name}</h3>
                                        <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-white/5 text-zinc-400">
                                            Persona Template
                                        </span>
                                    </div>
                                </div>

                                <div className="space-y-2 border-t border-white/[0.06] pt-4">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-zinc-500">Creativity (Temp)</span>
                                        <div className="flex items-center gap-2">
                                            <div className="w-16 h-1 bg-zinc-800 rounded-full overflow-hidden">
                                                <div className="h-full bg-gradient-to-r from-indigo-500 to-purple-500" style={{ width: `${(persona.temperature / 2) * 100}%` }}></div>
                                            </div>
                                            <span className="text-zinc-300 font-mono text-xs">{persona.temperature}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                    
                    {/* Add New Card (Empty State) */}
                    <Link href="/personas/create" className="group border-2 border-dashed border-zinc-800 hover:border-zinc-700 rounded-2xl p-6 flex flex-col items-center justify-center gap-4 text-zinc-600 hover:text-zinc-400 transition-colors butter-reveal butter-reveal-delay-3">
                        <div className="w-12 h-12 rounded-full bg-zinc-900 border border-zinc-700 flex items-center justify-center group-hover:scale-110 transition-transform">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        </div>
                        <span className="font-medium">Define New Persona</span>
                    </Link>
                </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
