import React, { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import { Head, useForm, usePage, Link } from '@inertiajs/react';

const PROVIDERS = [
    { id: 'anthropic', name: 'Anthropic (Claude)' },
    { id: 'openai', name: 'OpenAI (GPT)' },
    { id: 'openrouter', name: 'OpenRouter' },
    { id: 'gemini', name: 'Google Gemini' },
    { id: 'deepseek', name: 'DeepSeek' },
    { id: 'ollama', name: 'Ollama (Local)' },
    { id: 'lmstudio', name: 'LM Studio (Local)' },
];

const FALLBACK_MODELS_BY_PROVIDER = {
    openrouter: [
        { id: 'openai/gpt-4o-mini', name: 'GPT-4o Mini', cost: '$0.15/$0.60' },
        { id: 'openai/gpt-4o', name: 'GPT-4o', cost: '$2.50/$10.00' },
        { id: 'anthropic/claude-sonnet-4-5-20250929', name: 'Claude Sonnet 4.5', cost: '$3.00/$15.00' },
        { id: 'deepseek/deepseek-chat', name: 'DeepSeek Chat', cost: '$0.14/$0.28' },
    ],
};

export default function Create({ personas, template, openRouterModels = [], discordDefaults = {} }) {
    const { flash } = usePage().props;
    const selectedPersonaA = personas.find((persona) => persona.id === (template?.persona_a_id ?? ''));
    const selectedPersonaB = personas.find((persona) => persona.id === (template?.persona_b_id ?? ''));
    const { data, setData, post, processing, errors, transform } = useForm({
        persona_a_id: template?.persona_a_id ?? '',
        persona_b_id: template?.persona_b_id ?? '',
        provider_a: '',
        provider_b: '',
        model_a: '',
        model_b: '',
        temp_a: selectedPersonaA?.temperature ?? 0.7,
        temp_b: selectedPersonaB?.temperature ?? 0.7,
        starter_message: template?.starter_message ?? '',
        max_rounds: template?.max_rounds ?? 10,
        stop_word_detection: false,
        stop_words: '',
        stop_word_threshold: 0.8,
        notifications_enabled: false,
        discord_streaming_enabled: Boolean(discordDefaults.enabled ?? false),
        discord_webhook_url: discordDefaults.webhook_url ?? '',
    });

    transform((payload) => ({
        ...payload,
        notifications_enabled: Boolean(payload.notifications_enabled),
        discord_streaming_enabled: Boolean(payload.discord_streaming_enabled),
        stop_words: payload.stop_word_detection && payload.stop_words
            ? payload.stop_words.split(',').map((word) => word.trim()).filter((word) => word.length > 0)
            : [],
    }));

    const [modelsA, setModelsA] = useState([]);
    const [modelsB, setModelsB] = useState([]);
    const [loadingModelsA, setLoadingModelsA] = useState(false);
    const [loadingModelsB, setLoadingModelsB] = useState(false);
    const [showTemplateModal, setShowTemplateModal] = useState(false);
    const fallbackModelsByProvider = {
        ...FALLBACK_MODELS_BY_PROVIDER,
        openrouter: openRouterModels.length > 0 ? openRouterModels : FALLBACK_MODELS_BY_PROVIDER.openrouter,
    };

    const visibleModelsA = modelsA.length > 0
        ? modelsA
        : (data.provider_a ? (fallbackModelsByProvider[data.provider_a] || []) : []);
    const visibleModelsB = modelsB.length > 0
        ? modelsB
        : (data.provider_b ? (fallbackModelsByProvider[data.provider_b] || []) : []);

    const templateForm = useForm({
        name: '',
        description: '',
        category: '',
        is_public: false,
        persona_a_id: data.persona_a_id,
        persona_b_id: data.persona_b_id,
        starter_message: data.starter_message,
        max_rounds: data.max_rounds,
    });

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
            const models = result.models || [];
            if (models.length === 0 && fallbackModelsByProvider[provider]) {
                console.warn(`No models returned for ${provider}, using fallback list.`);
                setModels(fallbackModelsByProvider[provider]);
            } else {
                setModels(models);
            }
        } catch (error) {
            console.error('Error fetching models:', error);
            setModels(fallbackModelsByProvider[provider] || []);
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

    useEffect(() => {
        if (data.provider_a && !data.model_a && visibleModelsA.length > 0) {
            setData('model_a', visibleModelsA[0].id);
        }
    }, [data.provider_a, data.model_a, visibleModelsA, setData]);

    useEffect(() => {
        const personaA = personas.find((persona) => persona.id === data.persona_a_id);
        if (personaA) {
            setData('temp_a', personaA.temperature ?? 0.7);
        }
    }, [data.persona_a_id, personas, setData]);

    useEffect(() => {
        if (data.provider_b && !data.model_b && visibleModelsB.length > 0) {
            setData('model_b', visibleModelsB[0].id);
        }
    }, [data.provider_b, data.model_b, visibleModelsB, setData]);

    useEffect(() => {
        const personaB = personas.find((persona) => persona.id === data.persona_b_id);
        if (personaB) {
            setData('temp_b', personaB.temperature ?? 0.7);
        }
    }, [data.persona_b_id, personas, setData]);

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('chat.store'), {
            onError: () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
        });
    };

    const hasStopWords = data.stop_words
        .split(',')
        .map((word) => word.trim())
        .filter((word) => word.length > 0)
        .length > 0;

    const isValidRoundCount = Number.isInteger(Number(data.max_rounds)) && Number(data.max_rounds) >= 1 && Number(data.max_rounds) <= 100;

    const canSubmit = Boolean(
        data.persona_a_id &&
        data.persona_b_id &&
        data.provider_a &&
        data.provider_b &&
        data.model_a &&
        data.model_b &&
        data.starter_message.trim().length > 0 &&
        isValidRoundCount &&
        (!data.stop_word_detection || (hasStopWords && data.stop_word_threshold >= 0.1 && data.stop_word_threshold <= 1))
    );

    const openTemplateModal = () => {
        templateForm.setData({
            name: '',
            description: '',
            category: '',
            is_public: false,
            persona_a_id: data.persona_a_id,
            persona_b_id: data.persona_b_id,
            starter_message: data.starter_message,
            max_rounds: data.max_rounds,
        });
        setShowTemplateModal(true);
    };

    const handleSaveTemplate = (e) => {
        e.preventDefault();
        templateForm.post(route('templates.storeFromChat'), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => setShowTemplateModal(false),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Create Session" />

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
                                &larr; Back to Sessions
                            </Link>
                            <h1 className="text-3xl font-bold tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-white to-zinc-400">
                                Create Session
                            </h1>
                            <p className="text-zinc-500 text-sm mt-1">
                                Configure both agents, prompt, and safety settings.
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

                    {flash?.success && (
                        <div className="mt-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-3 text-sm text-emerald-300 butter-reveal">
                            {flash.success}
                        </div>
                    )}
                    {Object.keys(errors).length > 0 && (
                        <div className="mt-6 rounded-xl border border-red-500/30 bg-red-500/10 px-5 py-3 text-sm text-red-300 butter-reveal">
                            <p className="font-semibold">Please review the form errors and try again:</p>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                {Object.entries(errors).map(([field, message]) => (
                                    <li key={field}>
                                        {message}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-8">
                    {personas.length === 0 && (
                        <div className="glass-panel glass-butter rounded-2xl border border-amber-400/30 bg-amber-500/10 p-5 text-amber-100 butter-reveal">
                            <p className="text-sm font-semibold">No personas available yet.</p>
                            <p className="mt-1 text-xs text-amber-200/80">Create at least two personas before starting a session.</p>
                            <Link
                                href={route('personas.create')}
                                className="mt-3 inline-flex items-center gap-2 rounded-xl border border-amber-300/30 bg-amber-500/10 px-4 py-2 text-xs font-semibold text-amber-100 transition-all hover:bg-amber-500/20"
                            >
                                Create Persona
                            </Link>
                        </div>
                    )}
                    {template && (
                        <div className="glass-panel glass-butter rounded-2xl p-5 border border-white/10 flex flex-col md:flex-row md:items-center md:justify-between gap-4 butter-reveal">
                            <div>
                                <p className="text-xs uppercase tracking-widest text-zinc-400">Template Loaded</p>
                                <h3 className="text-lg font-semibold text-zinc-100">{template.name}</h3>
                                {template.description && (
                                    <p className="text-sm text-zinc-400 mt-1">{template.description}</p>
                                )}
                            </div>
                            <Link
                                href={route('templates.index')}
                                className="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-zinc-900/50 px-4 py-2 text-xs font-semibold text-zinc-300 transition-all hover:border-white/20 hover:bg-zinc-900/70 hover:text-white"
                            >
                                Browse Templates
                            </Link>
                        </div>
                    )}

                    {!template && (
                        <div className="flex justify-end">
                            <Link
                                href={route('templates.index')}
                                className="inline-flex items-center gap-2 rounded-xl border border-indigo-500/30 bg-indigo-500/10 px-4 py-2 text-xs font-semibold text-indigo-200 transition-all hover:bg-indigo-500/20"
                            >
                                Start from Template
                            </Link>
                        </div>
                    )}
                    {/* Agent Configuration Grid */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Agent A */}
                        <div className="glass-panel glass-butter rounded-2xl p-6 space-y-4 butter-reveal">
                            <h2 className="text-lg font-bold text-indigo-400 uppercase tracking-wider mb-4">Agent A</h2>

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
                                    {visibleModelsA.map(m => (
                                        <option key={m.id} value={m.id}>
                                            {m.name}{m.cost ? ` - ${m.cost}` : ''}
                                        </option>
                                    ))}
                                </select>
                                {errors.model_a && <div className="text-red-400 text-sm">{errors.model_a}</div>}
                                <p className="text-xs text-zinc-600 ml-1">Cost shown as input/output per 1M tokens</p>
                            </div>

                        </div>

                        {/* Agent B */}
                        <div className="glass-panel glass-butter rounded-2xl p-6 space-y-4 butter-reveal butter-reveal-delay-1">
                            <h2 className="text-lg font-bold text-purple-400 uppercase tracking-wider mb-4">Agent B</h2>

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
                                    {visibleModelsB.map(m => (
                                        <option key={m.id} value={m.id}>
                                            {m.name}{m.cost ? ` - ${m.cost}` : ''}
                                        </option>
                                    ))}
                                </select>
                                {errors.model_b && <div className="text-red-400 text-sm">{errors.model_b}</div>}
                                <p className="text-xs text-zinc-600 ml-1">Cost shown as input/output per 1M tokens</p>
                            </div>

                        </div>
                    </div>

                    {/* Initial Prompt */}
                    <div className="glass-panel glass-butter rounded-2xl p-6 space-y-2 butter-reveal butter-reveal-delay-2">
                        <label className="text-xs font-bold uppercase tracking-wider text-emerald-400 ml-1">Starter Message</label>
                        <textarea
                            value={data.starter_message}
                            onChange={e => setData('starter_message', e.target.value)}
                            className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-4 text-zinc-100 focus:ring-2 focus:ring-emerald-500/50 outline-none min-h-[120px] resize-none"
                            placeholder="Write the opening message that starts the conversation..."
                        ></textarea>
                        {errors.starter_message && <div className="text-red-400 text-sm">{errors.starter_message}</div>}
                    </div>

                    {/* Chat Control Settings */}
                    <div className="glass-panel glass-butter rounded-2xl p-6 space-y-4 butter-reveal butter-reveal-delay-3">
                        <h2 className="text-lg font-bold text-yellow-400 uppercase tracking-wider mb-4">Session Settings</h2>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="space-y-2">
                                <label className="text-xs font-bold uppercase tracking-wider text-zinc-400 ml-1">Max Rounds</label>
                                <input
                                    type="number"
                                    min="1"
                                    max="100"
                                    value={data.max_rounds}
                                    onChange={e => {
                                        const value = e.target.value;
                                        setData('max_rounds', value === '' ? '' : parseInt(value, 10));
                                    }}
                                    onBlur={() => {
                                        if (!isValidRoundCount) {
                                            setData('max_rounds', 10);
                                        }
                                    }}
                                    className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-3 text-zinc-100 focus:ring-2 focus:ring-yellow-500/50 outline-none"
                                />
                                {errors.max_rounds && <div className="text-red-400 text-sm">{errors.max_rounds}</div>}
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

                            <div className="space-y-2">
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={data.discord_streaming_enabled}
                                        onChange={e => setData('discord_streaming_enabled', e.target.checked)}
                                        className="w-5 h-5 rounded bg-zinc-900/50 border-white/10"
                                    />
                                    <span className="text-xs font-bold uppercase tracking-wider text-zinc-400">Discord Broadcast</span>
                                </label>
                                <p className="text-xs text-zinc-600 ml-7">Broadcast conversation updates to Discord when enabled.</p>
                            </div>
                        </div>

                        {data.discord_streaming_enabled && (
                            <div className="pt-4 border-t border-white/5 space-y-2">
                                <label className="text-xs font-bold uppercase tracking-wider text-zinc-400 ml-1">Discord Webhook URL (optional override)</label>
                                <input
                                    type="url"
                                    value={data.discord_webhook_url}
                                    onChange={e => setData('discord_webhook_url', e.target.value)}
                                    placeholder="https://discord.com/api/webhooks/..."
                                    className="w-full bg-zinc-900/50 border border-white/10 rounded-xl p-3 text-zinc-100 focus:ring-2 focus:ring-yellow-500/50 outline-none"
                                />
                                {errors.discord_webhook_url && <div className="text-red-400 text-sm">{errors.discord_webhook_url}</div>}
                                <p className="text-xs text-zinc-600">Leave blank to use your profile default or system webhook.</p>
                            </div>
                        )}

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
                                    {errors.stop_words && <div className="text-red-400 text-sm">{errors.stop_words}</div>}
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
                                    {errors.stop_word_threshold && <div className="text-red-400 text-sm">{errors.stop_word_threshold}</div>}
                                    <div className="flex justify-between text-xs text-zinc-600">
                                        <span>Loose</span>
                                        <span>Strict</span>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Submit Buttons */}
                    <div className="pt-4 flex flex-col gap-3">
                        <button
                            type="submit"
                            disabled={processing || !canSubmit}
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
                                        Creating session...
                                    </>
                                ) : (
                                    <>
                                        Start Session
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                                    </>
                                )}
                            </span>
                        </button>
                        {!canSubmit && (
                            <p className="text-xs text-zinc-500 text-center">
                                Complete persona, provider, model, prompt, and round settings to start.
                            </p>
                        )}

                        <button
                            type="button"
                            onClick={openTemplateModal}
                            className="w-full rounded-xl border border-white/10 bg-zinc-900/50 py-3 text-sm font-semibold text-zinc-300 transition-all hover:border-white/20 hover:bg-zinc-900/70 hover:text-white"
                        >
                            Save as Template
                        </button>
                    </div>
                    </form>
                </div>
            </div>

            <Modal show={showTemplateModal} onClose={() => setShowTemplateModal(false)} maxWidth="md">
                <form onSubmit={handleSaveTemplate} className="p-6 space-y-5">
                    <h2 className="text-lg font-bold text-zinc-100">Save as Template</h2>

                    <div className="space-y-2">
                        <label className="text-xs font-bold uppercase tracking-wider text-zinc-400">Name <span className="text-red-400">*</span></label>
                        <input
                            type="text"
                            value={templateForm.data.name}
                            onChange={e => templateForm.setData('name', e.target.value)}
                            className="w-full rounded-xl border border-white/10 bg-zinc-900/80 p-3 text-zinc-100 focus:ring-2 focus:ring-indigo-500/50 outline-none"
                            placeholder="Template name..."
                        />
                        {templateForm.errors.name && <div className="text-red-400 text-sm">{templateForm.errors.name}</div>}
                    </div>

                    <div className="space-y-2">
                        <label className="text-xs font-bold uppercase tracking-wider text-zinc-400">Description</label>
                        <textarea
                            value={templateForm.data.description}
                            onChange={e => templateForm.setData('description', e.target.value)}
                            className="w-full rounded-xl border border-white/10 bg-zinc-900/80 p-3 text-zinc-100 focus:ring-2 focus:ring-indigo-500/50 outline-none resize-none"
                            rows={3}
                            placeholder="Optional description..."
                        />
                        {templateForm.errors.description && <div className="text-red-400 text-sm">{templateForm.errors.description}</div>}
                    </div>

                    <div className="space-y-2">
                        <label className="text-xs font-bold uppercase tracking-wider text-zinc-400">Category</label>
                        <input
                            type="text"
                            value={templateForm.data.category}
                            onChange={e => templateForm.setData('category', e.target.value)}
                            className="w-full rounded-xl border border-white/10 bg-zinc-900/80 p-3 text-zinc-100 focus:ring-2 focus:ring-indigo-500/50 outline-none"
                            placeholder="e.g. Debate, Interview..."
                        />
                        {templateForm.errors.category && <div className="text-red-400 text-sm">{templateForm.errors.category}</div>}
                    </div>

                    <div>
                        <label className="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={templateForm.data.is_public}
                                onChange={e => templateForm.setData('is_public', e.target.checked)}
                                className="w-5 h-5 rounded bg-zinc-900/50 border-white/10"
                            />
                            <span className="text-sm text-zinc-300">Make this template public</span>
                        </label>
                    </div>

                    <div className="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            onClick={() => setShowTemplateModal(false)}
                            className="rounded-xl border border-white/10 bg-zinc-900/50 px-5 py-2 text-sm font-semibold text-zinc-300 transition-all hover:bg-zinc-900/70 hover:text-white"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={templateForm.processing}
                            className="rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white transition-all hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {templateForm.processing ? 'Saving...' : 'Save Template'}
                        </button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
