import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

async function fetchModelsForProvider(provider) {
    if (!provider) {
        return [];
    }
    const baseProvider = provider.includes(':') ? provider.split(':')[0] : provider;
    try {
        const response = await fetch(`/api/providers/models?provider=${baseProvider}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) {
            return [];
        }
        const result = await response.json();
        return result.models || [];
    } catch {
        return [];
    }
}

function UserBubble({ text }) {
    return (
        <div className="flex justify-end">
            <div className="max-w-[75%] bg-indigo-600/80 text-white rounded-2xl rounded-tr-sm px-4 py-3 text-sm leading-relaxed">
                {text}
            </div>
        </div>
    );
}

function AssistantBubble({ text }) {
    const parts = text.split(/(<orchestration>[\s\S]*?<\/orchestration>)/g);

    return (
        <div className="flex justify-start">
            <div className="max-w-[75%] bg-zinc-800/80 border border-white/10 text-zinc-100 rounded-2xl rounded-tl-sm px-4 py-3 text-sm leading-relaxed space-y-2">
                {parts.map((part, i) =>
                    part.startsWith('<orchestration>') ? (
                        <div key={i} className="bg-emerald-900/30 border border-emerald-500/30 rounded-xl p-3 text-xs font-mono text-emerald-300">
                            Draft ready — click "Save & Create" to continue.
                        </div>
                    ) : (
                        <p key={i} className="whitespace-pre-wrap">{part}</p>
                    )
                )}
            </div>
        </div>
    );
}

function TypingIndicator() {
    return (
        <div className="flex justify-start">
            <div className="bg-zinc-800/80 border border-white/10 rounded-2xl rounded-tl-sm px-4 py-3 flex gap-1.5 items-center">
                <span className="w-2 h-2 rounded-full bg-zinc-400 animate-bounce" style={{ animationDelay: '0ms' }} />
                <span className="w-2 h-2 rounded-full bg-zinc-400 animate-bounce" style={{ animationDelay: '150ms' }} />
                <span className="w-2 h-2 rounded-full bg-zinc-400 animate-bounce" style={{ animationDelay: '300ms' }} />
            </div>
        </div>
    );
}

function StepEditor({ stepConfigs, setStepConfigs, configuredProviders, toolCapableProviders }) {
    const [modelsCache, setModelsCache] = useState({});
    const [loadingCache, setLoadingCache] = useState({});

    const loadModels = async (provider) => {
        if (!provider || modelsCache[provider] !== undefined) {
            return;
        }
        setLoadingCache((prev) => ({ ...prev, [provider]: true }));
        const models = await fetchModelsForProvider(provider);
        setModelsCache((prev) => ({ ...prev, [provider]: models }));
        setLoadingCache((prev) => ({ ...prev, [provider]: false }));
    };

    useEffect(() => {
        stepConfigs.forEach((step) => {
            if (step.provider_a) loadModels(step.provider_a);
            if (step.provider_b) loadModels(step.provider_b);
        });
    }, []);

    const handleProviderChange = async (index, side, value) => {
        setStepConfigs((prev) => {
            const updated = [...prev];
            updated[index] = { ...updated[index], [`provider_${side}`]: value, [`model_${side}`]: '' };
            return updated;
        });
        if (value) {
            await loadModels(value);
        }
    };

    const handleModelChange = (index, side, value) => {
        setStepConfigs((prev) => {
            const updated = [...prev];
            updated[index] = { ...updated[index], [`model_${side}`]: value };
            return updated;
        });
    };

    const selectClass = 'w-full rounded-lg border border-white/10 bg-zinc-900 px-2 py-1.5 text-xs text-zinc-100 focus:border-indigo-500/50 focus:ring-1 focus:ring-indigo-500/20 focus:outline-none';

    return (
        <div className="space-y-2">
            {stepConfigs.map((step, index) => (
                <div key={index} className="rounded-xl border border-white/10 bg-zinc-900/60 p-3 space-y-2">
                    <p className="text-xs font-medium text-zinc-300">
                        Step {index + 1}{step.label ? ` — ${step.label}` : ''}
                    </p>
                    <div className="grid grid-cols-2 gap-3">
                        {['a', 'b'].map((side) => {
                            const provider = step[`provider_${side}`] || '';
                            const model = step[`model_${side}`] || '';
                            const models = modelsCache[provider] || [];
                            const isLoading = loadingCache[provider] || false;

                            return (
                                <div key={side} className="space-y-1.5">
                                    <p className="text-xs text-zinc-500 font-medium">Agent {side.toUpperCase()}</p>
                                    <select
                                        value={provider}
                                        onChange={(e) => handleProviderChange(index, side, e.target.value)}
                                        className={selectClass}
                                    >
                                        <option value="">— provider —</option>
                                        {(toolCapableProviders ?? configuredProviders).map((p) => (
                                            <option key={p.id} value={p.id}>{p.name}</option>
                                        ))}
                                    </select>
                                    {isLoading ? (
                                        <div className="flex items-center gap-1.5 px-2 py-1.5">
                                            <span className="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-bounce" style={{ animationDelay: '0ms' }} />
                                            <span className="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-bounce" style={{ animationDelay: '150ms' }} />
                                            <span className="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-bounce" style={{ animationDelay: '300ms' }} />
                                        </div>
                                    ) : (
                                        <select
                                            value={model}
                                            onChange={(e) => handleModelChange(index, side, e.target.value)}
                                            disabled={!provider || models.length === 0}
                                            className={selectClass}
                                        >
                                            <option value="">— model —</option>
                                            {models.map((m) => (
                                                <option key={m.id} value={m.id}>{m.name || m.id}</option>
                                            ))}
                                        </select>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            ))}
        </div>
    );
}

export default function Wizard() {
    const [history, setHistory] = useState([]);
    const [input, setInput] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [draft, setDraft] = useState(null);
    const [stepConfigs, setStepConfigs] = useState([]);
    const [streamToDiscord, setStreamToDiscord] = useState(false);
    const [streamToDiscourse, setStreamToDiscourse] = useState(false);
    const [isMaterializing, setIsMaterializing] = useState(false);
    const [configuredProviders, setConfiguredProviders] = useState([]);
    const toolCapableProviders = configuredProviders.filter((p) => p.supports_tools !== false);
    const bottomRef = useRef(null);

    useEffect(() => {
        fetch('/api/providers/configured', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((r) => r.ok ? r.json() : null)
            .then((d) => { if (d?.providers) { setConfiguredProviders(d.providers); } })
            .catch(() => {});
    }, []);

    const scrollToBottom = () => {
        setTimeout(() => bottomRef.current?.scrollIntoView({ behavior: 'smooth' }), 50);
    };

    const sendMessage = async () => {
        if (!input.trim() || isLoading) {
            return;
        }

        const userMessage = input.trim();
        setInput('');
        setHistory((prev) => [...prev, { role: 'user', content: userMessage }]);
        setIsLoading(true);
        scrollToBottom();

        try {
            const response = await fetch(route('orchestrator.wizard.chat'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ message: userMessage, history }),
            });

            const data = await response.json();

            setHistory((prev) => [...prev, { role: 'assistant', content: data.reply }]);

            if (data.done && data.orchestration_draft) {
                setDraft(data.orchestration_draft);
                setStepConfigs(
                    (data.orchestration_draft.steps ?? []).map((s) => ({
                        provider_a: s.provider_a ?? '',
                        model_a: s.model_a ?? '',
                        provider_b: s.provider_b ?? '',
                        model_b: s.model_b ?? '',
                    }))
                );
                setStreamToDiscord(Boolean(data.orchestration_draft.discord_streaming_enabled ?? false));
                setStreamToDiscourse(Boolean(data.orchestration_draft.discourse_streaming_enabled ?? false));
            }
        } catch {
            setHistory((prev) => [...prev, { role: 'assistant', content: 'Something went wrong. Please try again.' }]);
        } finally {
            setIsLoading(false);
            scrollToBottom();
        }
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    };

    const handleMaterialize = () => {
        if (!draft) {
            return;
        }
        setIsMaterializing(true);
        const mergedSteps = (draft.steps ?? []).map((step, index) => ({
            ...step,
            ...(stepConfigs[index] ?? {}),
        }));
        router.post(route('orchestrator.wizard.materialize'), {
            draft: {
                ...draft,
                steps: mergedSteps,
                discord_streaming_enabled: streamToDiscord,
                discourse_streaming_enabled: streamToDiscourse,
            },
        }, {
            onError: () => setIsMaterializing(false),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Orchestration Wizard" />

            <div className="min-h-screen text-zinc-100 flex flex-col">
                <div className="max-w-3xl mx-auto w-full flex flex-col flex-1 p-4 md:p-8 space-y-4">
                    <div className="pb-4 border-b border-white/5">
                        <p className="text-xs font-mono text-zinc-500 uppercase tracking-wide mb-1">
                            &larr; <a href={route('orchestrator.index')} className="hover:text-white">Back to Orchestrator</a>
                        </p>
                        <h1 className="text-2xl font-bold text-white">Orchestration Wizard</h1>
                        <p className="text-zinc-500 text-sm mt-1">Describe your goal and I'll help you design a pipeline.</p>
                    </div>

                    <div className="flex-1 space-y-4 overflow-y-auto max-h-[55vh] pr-1">
                        {history.length === 0 && (
                            <div className="text-center py-12 text-zinc-600">
                                <p className="text-sm">Start by describing what you want to automate.</p>
                                <p className="text-xs mt-2 text-zinc-700">e.g. "Run a debate between a scientist and a philosopher about consciousness, then summarize it."</p>
                            </div>
                        )}

                        {history.map((msg, i) =>
                            msg.role === 'user' ? (
                                <UserBubble key={i} text={msg.content} />
                            ) : (
                                <AssistantBubble key={i} text={msg.content} />
                            )
                        )}

                        {isLoading && <TypingIndicator />}
                        <div ref={bottomRef} />
                    </div>

                    {draft && (
                        <div className="glass-panel rounded-2xl p-5 border border-emerald-500/30 bg-emerald-900/10 space-y-3">
                            <div className="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-emerald-400"><polyline points="20 6 9 17 4 12"/></svg>
                                <span className="text-emerald-300 font-medium text-sm">Draft ready</span>
                            </div>
                            <div>
                                <p className="text-white font-semibold">{draft.name}</p>
                                {draft.goal && <p className="text-zinc-400 text-sm mt-1">{draft.goal}</p>}
                                <p className="text-zinc-500 text-xs mt-2">{draft.steps?.length ?? 0} step{draft.steps?.length !== 1 ? 's' : ''}</p>
                            </div>
                            <StepEditor stepConfigs={stepConfigs} setStepConfigs={setStepConfigs} configuredProviders={configuredProviders} toolCapableProviders={toolCapableProviders} />
                            <div className="space-y-2 rounded-xl border border-white/10 bg-zinc-900/40 p-3">
                                <label className="flex items-center gap-2 text-xs text-zinc-300">
                                    <input
                                        type="checkbox"
                                        checked={streamToDiscord}
                                        onChange={(e) => setStreamToDiscord(e.target.checked)}
                                        className="h-4 w-4 rounded border-white/20 bg-zinc-900 text-indigo-500 focus:ring-indigo-500/50"
                                    />
                                    Stream each step chat to Discord
                                </label>
                                <label className="flex items-center gap-2 text-xs text-zinc-300">
                                    <input
                                        type="checkbox"
                                        checked={streamToDiscourse}
                                        onChange={(e) => setStreamToDiscourse(e.target.checked)}
                                        className="h-4 w-4 rounded border-white/20 bg-zinc-900 text-indigo-500 focus:ring-indigo-500/50"
                                    />
                                    Stream each step chat to Discourse
                                </label>
                            </div>
                            <div className="flex gap-3">
                                <button
                                    onClick={handleMaterialize}
                                    disabled={isMaterializing}
                                    className="flex-1 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white py-2 rounded-xl text-sm font-medium transition-colors"
                                >
                                    {isMaterializing ? 'Creating…' : 'Save & Create'}
                                </button>
                                <button
                                    onClick={() => setDraft(null)}
                                    className="px-4 py-2 rounded-xl text-sm text-zinc-400 hover:text-white bg-zinc-800 transition-colors"
                                >
                                    Keep Editing
                                </button>
                            </div>
                        </div>
                    )}

                    <div className="flex gap-3 pt-2">
                        <textarea
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            onKeyDown={handleKeyDown}
                            rows={2}
                            placeholder="Describe your goal or answer the question above…"
                            disabled={isLoading}
                            className="flex-1 resize-none rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-3 text-sm text-zinc-100 placeholder-zinc-600 focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none disabled:opacity-50"
                        />
                        <button
                            onClick={sendMessage}
                            disabled={!input.trim() || isLoading}
                            className="px-5 rounded-xl bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed text-white font-medium transition-colors"
                        >
                            Send
                        </button>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
