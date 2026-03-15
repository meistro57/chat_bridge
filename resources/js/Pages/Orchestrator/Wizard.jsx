import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useRef, useState } from 'react';

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

export default function Wizard() {
    const [history, setHistory] = useState([]);
    const [input, setInput] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [draft, setDraft] = useState(null);
    const [isMaterializing, setIsMaterializing] = useState(false);
    const bottomRef = useRef(null);

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
        router.post(route('orchestrator.wizard.materialize'), { draft }, {
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
