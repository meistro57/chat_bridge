import React, { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';

const PROVIDERS = [
    { id: 'anthropic', name: 'Anthropic (Claude)' },
    { id: 'openai', name: 'OpenAI (GPT)' },
    { id: 'openrouter', name: 'OpenRouter' },
    { id: 'gemini', name: 'Google Gemini' },
    { id: 'deepseek', name: 'DeepSeek' },
    { id: 'ollama', name: 'Ollama (Local)' },
    { id: 'lmstudio', name: 'LM Studio (Local)' },
];

export default function Create({ personas }) {
    const { data, setData, post, processing, errors, transform } = useForm({
        persona_a_id: '',
        persona_b_id: '',
        provider_a: '',
        provider_b: '',
        model_a: '',
        model_b: '',
        temp_a: 0.7,
        temp_b: 0.7,
        starter_message: '',
        max_rounds: 10,
        stop_word_detection: false,
        stop_words: '',
        stop_word_threshold: 0.8,
        notifications_enabled: true,
    });

    transform((payload) => ({
        ...payload,
        notifications_enabled: Boolean(payload.notifications_enabled),
        stop_words: payload.stop_word_detection && payload.stop_words
            ? payload.stop_words.split(',').map((word) => word.trim()).filter((word) => word.length > 0)
            : [],
    }));

    const [modelsA, setModelsA] = useState([]);
    const [modelsB, setModelsB] = useState([]);
    const [loadingModelsA, setLoadingModelsA] = useState(false);
    const [loadingModelsB, setLoadingModelsB] = useState(false);

    const fetchModels = async (provider, setModels, setLoading) => {
        if (!provider) {
            setModels([]);
            return;
        }

        setLoading(true);
        try {
            const response = await fetch(`/api/providers/models?provider=${provider}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                console.error(`HTTP error! status: ${response.status}`);
                const errorText = await response.text();
                console.error('Error response:', errorText);
                setModels([]);
                return;
            }

            const result = await response.json();
            console.log(`Fetched models for ${provider}:`, result);
            setModels(result.models || []);
        } catch (error) {
            console.error('Error fetching models:', error);
            setModels([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchModels(data.provider_a, setModelsA, setLoadingModelsA);
    }, [data.provider_a]);

    useEffect(() => {
        fetchModels(data.provider_b, setModelsB, setLoadingModelsB);
    }, [data.provider_b]);

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('chat.store'));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Initialize Protocol" />

            <div className="relative min-h-screen text-zinc-100 p-4 md:p-8 overflow-hidden">
                <div className="pointer-events-none absolute inset-0 -z-10">
                    <div className="absolute -top-24 -left-24 h-96 w-96 rounded-full bg-[radial-gradient(circle_at_center,rgba(99,102,241,0.16),transparent_60%)] blur-2xl"></div>
                    <div className="absolute top-20 right-[-8rem] h-[26rem] w-[26rem] rounded-full bg-[radial-gradient(circle_at_center,rgba(16,185,129,0.14),transparent_60%)] blur-2xl"></div>
                    <div className="absolute bottom-[-10rem] left-1/3 h-[30rem] w-[30rem] rounded-full bg-[radial-gradient(circle_at_center,rgba(168,85,247,0.12),transparent_60%)] blur-2xl"></div>
                </div>
                <div className="max-w-6xl mx-auto">
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-end pb-8 border-b border-white/5 gap-6 butter-reveal">
                        <div>
                            <Link href="/chat" className="text-xs font-mono text-zinc-500 hover:text-white mb-2 block uppercase tracking-wide">
                                &larr; Return to Bridge
                            </Link>
                            <h1 className="text-3xl font-bold tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-white to-zinc-400">
                                Initialize Bridge
                            </h1>
                            <p className="text-zinc-500 text-sm mt-1">
                                Configure parameters for new neural handshake.
                            </p>
                        </div>
                        <Link
                            href="/chat"
                            className="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-zinc-900/50 px-4 py-2 text-xs font-semibold text-zinc-300 transition-all hover:border-white/20 hover:bg-zinc-900/70 hover:text-white"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <path d="m15 18-6-6 6-6"/>
                            </svg>
                            Back to Sessions
                        </Link>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-8">
                    {/* Agent Configuration Grid */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Agent A */}
                        <div className="glass-panel glass-butter rounded-2xl p-6 space-y-4 butter-reveal">
                            <h2 className="text-lg font-bold text-indigo-400 uppercase tracking-wider mb-4">Agent Concept A</h2>

                            <div className="space-y-2">
                                <label className="text-xs font-bold uppercase tracking-wider text-zinc-400 ml-1">Persona</label>
                                <select
                                    value={data.persona_a_id}
                                    onChange={e => setData('persona_a_id', e.target.value)}
                                    className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-3 text-zinc-100 focus:ring-2 focus:ring-indigo-500/50 outline-none"
                                >
                                    <option value="">Select Persona...</option>
                                    {personas.map(p => (
                                        <option key={p.id} value={p.id}>{p.name}</option>
                                    ))}
                                </select>
                                {errors.persona_a_id && <div className="text-red-400 text-sm">{errors.persona_a_id}</div>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-xs font-bold uppercase tracking-wider text-zinc-400 ml-1">Provider</label>
                                <select
                                    value={data.provider_a}
                                    onChange={e => {
                                        setData('provider_a', e.target.value);
                                        setData('model_a', '');
                                    }}
                                    className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-3 text-zinc-100 focus:ring-2 focus:ring-indigo-500/50 outline-none"
                                >
                                    <option value="">Select Provider...</option>
                                    {PROVIDERS.map(p => (
                                        <option key={p.id} value={p.id}>{p.name}</option>
                                    ))}
                                </select>
                                {errors.provider_a && <div className="text-red-400 text-sm">{errors.provider_a}</div>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-xs font-bold uppercase tracking-wider text-zinc-400 ml-1">Model</label>
                                <select
                                    value={data.model_a}
                                    onChange={e => setData('model_a', e.target.value)}
                                    disabled={!data.provider_a || loadingModelsA}
                                    className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-3 text-zinc-100 focus:ring-2 focus:ring-indigo-500/50 outline-none disabled:opacity-50"
                                >
                                    <option value="">{loadingModelsA ? 'Loading models...' : 'Select Model...'}</option>
                                    {modelsA.map(m => (
                                        <option key={m.id} value={m.id}>
                                            {m.name}{m.cost ? ` - ${m.cost}` : ''}
                                        </option>
                                    ))}
                                </select>
                                {errors.model_a && <div className="text-red-400 text-sm">{errors.model_a}</div>}
                                <p className="text-xs text-zinc-600 ml-1">Cost shown as input/output per 1M tokens</p>
                            </div>

                            <div className="space-y-2">
                                <label className="text-xs font-bold uppercase tracking-wider text-zinc-400 ml-1">Temperature: {data.temp_a}</label>
                                <input
                                    type="range"
                                    min="0"
                                    max="2"
                                    step="0.1"
                                    value={data.temp_a}
                                    onChange={e => setData('temp_a', parseFloat(e.target.value))}
                                    className="w-full"
                                />
                                <div className="flex justify-between text-xs text-zinc-600">
                                    <span>Deterministic</span>
                                    <span>Creative</span>
                                </div>
                            </div>
                        </div>

                        {/* Agent B */}
                        <div className="glass-panel glass-butter rounded-2xl p-6 space-y-4 butter-reveal butter-reveal-delay-1">
                            <h2 className="text-lg font-bold text-purple-400 uppercase tracking-wider mb-4">Agent Concept B</h2>

                            <div className="space-y-2">
                                <label className="text-xs font-bold uppercase tracking-wider text-zinc-400 ml-1">Persona</label>
                                <select
                                    value={data.persona_b_id}
                                    onChange={e => setData('persona_b_id', e.target.value)}
                                    className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-3 text-zinc-100 focus:ring-2 focus:ring-purple-500/50 outline-none"
                                >
                                    <option value="">Select Persona...</option>
                                    {personas.map(p => (
                                        <option key={p.id} value={p.id}>{p.name}</option>
                                    ))}
                                </select>
                                {errors.persona_b_id && <div className="text-red-400 text-sm">{errors.persona_b_id}</div>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-xs font-bold uppercase tracking-wider text-zinc-400 ml-1">Provider</label>
                                <select
                                    value={data.provider_b}
                                    onChange={e => {
                                        setData('provider_b', e.target.value);
                                        setData('model_b', '');
                                    }}
                                    className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-3 text-zinc-100 focus:ring-2 focus:ring-purple-500/50 outline-none"
                                >
                                    <option value="">Select Provider...</option>
                                    {PROVIDERS.map(p => (
                                        <option key={p.id} value={p.id}>{p.name}</option>
                                    ))}
                                </select>
                                {errors.provider_b && <div className="text-red-400 text-sm">{errors.provider_b}</div>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-xs font-bold uppercase tracking-wider text-zinc-400 ml-1">Model</label>
                                <select
                                    value={data.model_b}
                                    onChange={e => setData('model_b', e.target.value)}
                                    disabled={!data.provider_b || loadingModelsB}
                                    className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-3 text-zinc-100 focus:ring-2 focus:ring-purple-500/50 outline-none disabled:opacity-50"
                                >
                                    <option value="">{loadingModelsB ? 'Loading models...' : 'Select Model...'}</option>
                                    {modelsB.map(m => (
                                        <option key={m.id} value={m.id}>
                                            {m.name}{m.cost ? ` - ${m.cost}` : ''}
                                        </option>
                                    ))}
                                </select>
                                {errors.model_b && <div className="text-red-400 text-sm">{errors.model_b}</div>}
                                <p className="text-xs text-zinc-600 ml-1">Cost shown as input/output per 1M tokens</p>
                            </div>

                            <div className="space-y-2">
                                <label className="text-xs font-bold uppercase tracking-wider text-zinc-400 ml-1">Temperature: {data.temp_b}</label>
                                <input
                                    type="range"
                                    min="0"
                                    max="2"
                                    step="0.1"
                                    value={data.temp_b}
                                    onChange={e => setData('temp_b', parseFloat(e.target.value))}
                                    className="w-full"
                                />
                                <div className="flex justify-between text-xs text-zinc-600">
                                    <span>Deterministic</span>
                                    <span>Creative</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Initial Prompt */}
                    <div className="glass-panel glass-butter rounded-2xl p-6 space-y-2 butter-reveal butter-reveal-delay-2">
                        <label className="text-xs font-bold uppercase tracking-wider text-emerald-400 ml-1">Initial Stimulus (Prompt)</label>
                        <textarea
                            value={data.starter_message}
                            onChange={e => setData('starter_message', e.target.value)}
                            className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-4 text-zinc-100 focus:ring-2 focus:ring-emerald-500/50 outline-none min-h-[120px] resize-none"
                            placeholder="Construct the initial scenario or query for the agents..."
                        ></textarea>
                        {errors.starter_message && <div className="text-red-400 text-sm">{errors.starter_message}</div>}
                    </div>

                    {/* Chat Control Settings */}
                    <div className="glass-panel glass-butter rounded-2xl p-6 space-y-4 butter-reveal butter-reveal-delay-3">
                        <h2 className="text-lg font-bold text-yellow-400 uppercase tracking-wider mb-4">Chat Control Settings</h2>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="space-y-2">
                                <label className="text-xs font-bold uppercase tracking-wider text-zinc-400 ml-1">Max Rounds</label>
                                <input
                                    type="number"
                                    min="1"
                                    max="100"
                                    value={data.max_rounds}
                                    onChange={e => setData('max_rounds', parseInt(e.target.value))}
                                    className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-3 text-zinc-100 focus:ring-2 focus:ring-yellow-500/50 outline-none"
                                />
                                <p className="text-xs text-zinc-600">Maximum number of conversation turns</p>
                            </div>

                            <div className="space-y-2">
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={data.stop_word_detection}
                                        onChange={e => setData('stop_word_detection', e.target.checked)}
                                        className="w-5 h-5 rounded bg-zinc-900/50 border-white/10"
                                    />
                                    <span className="text-xs font-bold uppercase tracking-wider text-zinc-400">Enable Stop Word Detection</span>
                                </label>
                                <p className="text-xs text-zinc-600 ml-7">Automatically stop when specific words are detected</p>
                            </div>

                            <div className="space-y-2">
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={data.notifications_enabled}
                                        onChange={e => setData('notifications_enabled', e.target.checked)}
                                        className="w-5 h-5 rounded bg-zinc-900/50 border-white/10"
                                    />
                                    <span className="text-xs font-bold uppercase tracking-wider text-zinc-400">Email Notifications</span>
                                </label>
                                <p className="text-xs text-zinc-600 ml-7">Send email alerts when the conversation completes or fails</p>
                            </div>
                        </div>

                        {data.stop_word_detection && (
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-white/5">
                                <div className="space-y-2">
                                    <label className="text-xs font-bold uppercase tracking-wider text-zinc-400 ml-1">Stop Words (comma separated)</label>
                                    <input
                                        type="text"
                                        value={data.stop_words}
                                        onChange={e => setData('stop_words', e.target.value)}
                                        placeholder="goodbye, farewell, end"
                                        className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-3 text-zinc-100 focus:ring-2 focus:ring-yellow-500/50 outline-none"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <label className="text-xs font-bold uppercase tracking-wider text-zinc-400 ml-1">Detection Threshold: {data.stop_word_threshold}</label>
                                    <input
                                        type="range"
                                        min="0.1"
                                        max="1"
                                        step="0.1"
                                        value={data.stop_word_threshold}
                                        onChange={e => setData('stop_word_threshold', parseFloat(e.target.value))}
                                        className="w-full"
                                    />
                                    <div className="flex justify-between text-xs text-zinc-600">
                                        <span>Loose</span>
                                        <span>Strict</span>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Submit Button */}
                    <div className="pt-4">
                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full group relative bg-white text-black rounded-xl py-4 font-bold overflow-hidden transition-all hover:scale-[1.01] hover:shadow-[0_0_40px_rgba(255,255,255,0.2)] disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <div className="absolute inset-0 bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-500 opacity-20 group-hover:opacity-40 transition-opacity"></div>
                            <span className="relative flex items-center justify-center gap-2">
                                {processing ? (
                                    <>
                                        <svg className="animate-spin h-5 w-5 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
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
        </AuthenticatedLayout>
    );
}
