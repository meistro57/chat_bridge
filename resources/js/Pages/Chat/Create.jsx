import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';

export default function Create({ personas }) {
    const { data, setData, post, processing, errors } = useForm({
        persona_a_id: '',
        persona_b_id: '',
        starter_message: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/chat/store');
    };

    return (
        <div className="min-h-screen text-zinc-100 flex items-center justify-center p-4">
            <Head title="Initialize Protocol" />
            
            <div className="w-full max-w-2xl glass-panel p-8 md:p-12 rounded-3xl relative overflow-hidden">
                {/* Decorative Elements */}
                <div className="absolute top-0 right-0 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl -mr-32 -mt-32 pointer-events-none"></div>
                <div className="absolute bottom-0 left-0 w-64 h-64 bg-purple-500/10 rounded-full blur-3xl -ml-32 -mb-32 pointer-events-none"></div>

                <div className="flex justify-between items-center mb-8 relative z-10">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-white to-zinc-400">Initialize Bridge</h1>
                        <p className="text-zinc-500 text-sm mt-1">Configure parameters for new neural handshake.</p>
                    </div>
                    <Link href="/chat" className="p-2 rounded-full hover:bg-white/5 text-zinc-400 hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </Link>
                </div>

                <form onSubmit={handleSubmit} className="space-y-8 relative z-10">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="space-y-2">
                            <label className="text-xs font-bold uppercase tracking-wider text-indigo-400 ml-1">Agent Concept A</label>
                            <div className="relative">
                                <select 
                                    value={data.persona_a_id}
                                    onChange={e => setData('persona_a_id', e.target.value)}
                                    className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-4 text-zinc-100 appearance-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all outline-none"
                                >
                                    <option value="">Select Persona Entity...</option>
                                    {personas.map(p => (
                                        <option key={p.id} value={p.id}>{p.name} ({p.provider})</option>
                                    ))}
                                </select>
                                <div className="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-zinc-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                </div>
                            </div>
                            {errors.persona_a_id && <div className="text-red-400 text-sm pl-1">{errors.persona_a_id}</div>}
                        </div>

                        <div className="space-y-2">
                            <label className="text-xs font-bold uppercase tracking-wider text-purple-400 ml-1">Agent Concept B</label>
                            <div className="relative">
                                <select 
                                    value={data.persona_b_id}
                                    onChange={e => setData('persona_b_id', e.target.value)}
                                    className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-4 text-zinc-100 appearance-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 transition-all outline-none"
                                >
                                    <option value="">Select Persona Entity...</option>
                                    {personas.map(p => (
                                        <option key={p.id} value={p.id}>{p.name} ({p.provider})</option>
                                    ))}
                                </select>
                                <div className="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-zinc-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                </div>
                            </div>
                            {errors.persona_b_id && <div className="text-red-400 text-sm pl-1">{errors.persona_b_id}</div>}
                        </div>
                    </div>

                    <div className="space-y-2">
                        <label className="text-xs font-bold uppercase tracking-wider text-emerald-400 ml-1">Initial Stimulus (Prompt)</label>
                        <textarea 
                            value={data.starter_message}
                            onChange={e => setData('starter_message', e.target.value)}
                            className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-4 text-zinc-100 focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition-all outline-none min-h-[160px] resize-none leading-relaxed"
                            placeholder="Construct the initial scenario or query for the agents..."
                        ></textarea>
                        {errors.starter_message && <div className="text-red-400 text-sm pl-1">{errors.starter_message}</div>}
                    </div>

                    <div className="pt-4">
                        <button 
                            type="submit" 
                            disabled={processing}
                            className="w-full group relative bg-white text-black rounded-xl py-4 font-bold overflow-hidden transition-all hover:scale-[1.01] hover:shadow-[0_0_40px_rgba(255,255,255,0.2)] disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <div className="absolute inset-0 bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-500 opacity-20 group-hover:opacity-40 transition-opacity animate-gradient-x"></div>
                            <span className="relative flex items-center justify-center gap-2">
                                {processing ? (
                                    <>
                                        <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Establishing Uplink...
                                    </>
                                ) : (
                                    <>
                                        Begin Simulation 
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                                    </>
                                )}
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
