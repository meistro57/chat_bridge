import React, { useState, useRef, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import axios from 'axios';

const MarkdownContent = ({ content }) => (
    <ReactMarkdown
        remarkPlugins={[remarkGfm]}
        className="space-y-3"
        components={{
            p: ({ children }) => <p className="text-zinc-100">{children}</p>,
            ul: ({ children }) => <ul className="list-disc space-y-1 pl-5 text-zinc-100">{children}</ul>,
            ol: ({ children }) => <ol className="list-decimal space-y-1 pl-5 text-zinc-100">{children}</ol>,
            li: ({ children }) => <li>{children}</li>,
            code: ({ inline, children }) => {
                if (inline) {
                    return (
                        <code className="rounded bg-black/40 px-1.5 py-0.5 font-mono text-indigo-100">
                            {children}
                        </code>
                    );
                }
                return (
                    <pre className="overflow-x-auto rounded-xl border border-white/10 bg-black/40 p-4 text-sm text-zinc-100">
                        <code className="font-mono">{children}</code>
                    </pre>
                );
            },
            blockquote: ({ children }) => (
                <blockquote className="border-l-2 border-indigo-500/50 pl-4 text-zinc-200 italic">
                    {children}
                </blockquote>
            ),
        }}
    >
        {String(content ?? '')}
    </ReactMarkdown>
);

export default function TranscriptChat({ conversations }) {
    const [messages, setMessages] = useState([]);
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const [selectedConversation, setSelectedConversation] = useState('');
    const [error, setError] = useState(null);
    const bottomRef = useRef(null);
    const inputRef = useRef(null);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, loading]);

    const handleSubmit = async (e) => {
        e.preventDefault();

        const question = input.trim();
        if (!question || loading) return;

        setInput('');
        setError(null);
        setMessages(prev => [...prev, { role: 'user', content: question }]);
        setLoading(true);

        try {
            const response = await axios.post(route('transcript-chat.ask'), {
                question,
                conversation_id: selectedConversation || null,
            });

            const { answer, sources } = response.data;

            setMessages(prev => [...prev, {
                role: 'assistant',
                content: answer,
                sources,
            }]);
        } catch (err) {
            const message = err.response?.data?.message
                ?? err.response?.data?.errors?.question?.[0]
                ?? 'Something went wrong. Please try again.';

            setError(message);
            setMessages(prev => prev.slice(0, -1));
            setInput(question);
        } finally {
            setLoading(false);
            inputRef.current?.focus();
        }
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSubmit(e);
        }
    };

    const clearChat = () => {
        setMessages([]);
        setError(null);
        inputRef.current?.focus();
    };

    return (
        <AuthenticatedLayout>
            <Head title="Ask the Archive" />

            <div className="flex h-screen flex-col text-zinc-100">
                {/* Header */}
                <div className="flex-shrink-0 border-b border-white/10 bg-zinc-950/80 px-6 py-4 backdrop-blur-md">
                    <div className="mx-auto flex max-w-4xl items-center justify-between">
                        <div className="flex items-center gap-4">
                            <Link
                                href={route('chat.index')}
                                className="flex items-center gap-2 text-zinc-400 transition-colors hover:text-white"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="m15 18-6-6 6-6" />
                                </svg>
                                Back
                            </Link>
                            <div>
                                <h1 className="text-lg font-bold tracking-tight">Ask the Archive</h1>
                                <p className="text-xs text-zinc-500">AI-powered Q&amp;A over your chat transcripts</p>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            {/* Conversation filter */}
                            <select
                                value={selectedConversation}
                                onChange={(e) => setSelectedConversation(e.target.value)}
                                className="rounded-xl border border-white/10 bg-zinc-900/60 px-3 py-1.5 text-sm text-zinc-300 outline-none transition-colors focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                            >
                                <option value="">All conversations</option>
                                {conversations.map((conv) => {
                                    const label = conv.persona_a?.name && conv.persona_b?.name
                                        ? `${conv.persona_a.name} × ${conv.persona_b.name}`
                                        : `Conversation ${conv.id.slice(0, 8)}`;
                                    return (
                                        <option key={conv.id} value={conv.id}>
                                            {label}
                                        </option>
                                    );
                                })}
                            </select>

                            {messages.length > 0 && (
                                <button
                                    onClick={clearChat}
                                    className="rounded-xl border border-white/10 bg-zinc-900/40 px-3 py-1.5 text-xs text-zinc-400 transition-colors hover:border-white/20 hover:text-white"
                                >
                                    Clear
                                </button>
                            )}

                            <div className="flex items-center gap-1.5">
                                <div className="h-1.5 w-1.5 animate-pulse rounded-full bg-indigo-500" />
                                <span className="font-mono text-[10px] uppercase tracking-widest text-zinc-500">
                                    Embeddings Active
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Messages */}
                <div className="flex-1 overflow-y-auto px-4 py-6">
                    <div className="mx-auto max-w-4xl space-y-6">
                        {messages.length === 0 && (
                            <div className="flex flex-col items-center justify-center py-24 text-center">
                                <div className="mb-6 flex h-20 w-20 items-center justify-center rounded-full border border-indigo-500/30 bg-indigo-500/10">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" className="text-indigo-400">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                                    </svg>
                                </div>
                                <h2 className="mb-2 text-xl font-semibold text-zinc-200">Ask about your transcripts</h2>
                                <p className="max-w-sm text-sm text-zinc-500">
                                    Questions are matched against your chat history using semantic embeddings. The AI reads the most relevant excerpts and synthesises an answer.
                                </p>
                                <div className="mt-8 grid gap-2 sm:grid-cols-2">
                                    {[
                                        'What topics did the AI discuss most?',
                                        'Summarise the key decisions made.',
                                        'What questions were asked about deployment?',
                                        'Did anyone mention a specific problem?',
                                    ].map((suggestion) => (
                                        <button
                                            key={suggestion}
                                            onClick={() => { setInput(suggestion); inputRef.current?.focus(); }}
                                            className="rounded-xl border border-white/10 bg-zinc-900/40 px-4 py-2.5 text-left text-sm text-zinc-400 transition-all hover:border-indigo-500/40 hover:bg-indigo-500/10 hover:text-zinc-200"
                                        >
                                            {suggestion}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        {messages.map((msg, index) => (
                            <div key={index} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                                {msg.role === 'user' ? (
                                    <div className="max-w-xl rounded-2xl rounded-tr-sm bg-indigo-600/30 border border-indigo-500/30 px-5 py-3 text-zinc-100 shadow-lg">
                                        <p className="text-sm leading-relaxed">{msg.content}</p>
                                    </div>
                                ) : (
                                    <div className="max-w-3xl space-y-3">
                                        <div className="flex items-center gap-2 px-1">
                                            <div className="flex h-6 w-6 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 text-[10px] font-bold text-white">
                                                AI
                                            </div>
                                            <span className="text-xs font-semibold uppercase tracking-widest text-indigo-400">
                                                Archive Assistant
                                            </span>
                                        </div>

                                        <div className="rounded-2xl rounded-tl-sm border border-white/10 bg-zinc-900/50 px-5 py-4 shadow-lg backdrop-blur-md">
                                            <MarkdownContent content={msg.content} />
                                        </div>

                                        {msg.sources && msg.sources.length > 0 && (
                                            <details className="rounded-xl border border-white/10 bg-zinc-900/30">
                                                <summary className="cursor-pointer px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-zinc-500 transition-colors hover:text-zinc-300">
                                                    {msg.sources.length} source{msg.sources.length !== 1 ? 's' : ''} retrieved
                                                </summary>
                                                <div className="space-y-2 border-t border-white/10 p-3">
                                                    {msg.sources.map((source, si) => (
                                                        <div
                                                            key={si}
                                                            className="rounded-lg border border-white/10 bg-black/20 px-3 py-2"
                                                        >
                                                            <div className="mb-1 flex items-center gap-2">
                                                                <span className={`rounded px-1.5 py-0.5 font-mono text-[10px] uppercase ${source.role === 'assistant' ? 'bg-indigo-500/20 text-indigo-300' : 'bg-zinc-700/50 text-zinc-400'}`}>
                                                                    {source.persona_name ?? source.role}
                                                                </span>
                                                                <span className="text-[10px] text-zinc-600">{source.created_at}</span>
                                                                <span className="ml-auto font-mono text-[10px] text-emerald-400">
                                                                    {(source.score * 100).toFixed(0)}% match
                                                                </span>
                                                            </div>
                                                            <p className="text-xs leading-relaxed text-zinc-400">{source.content}</p>
                                                        </div>
                                                    ))}
                                                </div>
                                            </details>
                                        )}
                                    </div>
                                )}
                            </div>
                        ))}

                        {loading && (
                            <div className="flex justify-start">
                                <div className="max-w-3xl space-y-3">
                                    <div className="flex items-center gap-2 px-1">
                                        <div className="flex h-6 w-6 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 text-[10px] font-bold text-white">
                                            AI
                                        </div>
                                        <span className="text-xs font-semibold uppercase tracking-widest text-indigo-400">
                                            Searching archive…
                                        </span>
                                    </div>
                                    <div className="rounded-2xl rounded-tl-sm border border-white/10 bg-zinc-900/50 px-5 py-4">
                                        <div className="flex items-center gap-2">
                                            <div className="h-2 w-2 animate-bounce rounded-full bg-indigo-400" style={{ animationDelay: '0ms' }} />
                                            <div className="h-2 w-2 animate-bounce rounded-full bg-indigo-400" style={{ animationDelay: '150ms' }} />
                                            <div className="h-2 w-2 animate-bounce rounded-full bg-indigo-400" style={{ animationDelay: '300ms' }} />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        <div ref={bottomRef} />
                    </div>
                </div>

                {/* Error banner */}
                {error && (
                    <div className="mx-auto mb-2 w-full max-w-4xl px-4">
                        <div className="flex items-center justify-between rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-2.5 text-sm text-red-300">
                            <span>{error}</span>
                            <button onClick={() => setError(null)} className="ml-4 text-red-400 hover:text-red-200">✕</button>
                        </div>
                    </div>
                )}

                {/* Input area */}
                <div className="flex-shrink-0 border-t border-white/10 bg-zinc-950/80 px-4 py-4 backdrop-blur-md">
                    <form onSubmit={handleSubmit} className="mx-auto max-w-4xl">
                        <div className="relative flex items-end gap-3 rounded-2xl border border-white/10 bg-zinc-900/60 px-4 py-3 transition-all focus-within:border-indigo-500/40 focus-within:ring-2 focus-within:ring-indigo-500/20">
                            <textarea
                                ref={inputRef}
                                value={input}
                                onChange={(e) => setInput(e.target.value)}
                                onKeyDown={handleKeyDown}
                                placeholder="Ask anything about your chat transcripts…"
                                rows={1}
                                disabled={loading}
                                className="flex-1 resize-none bg-transparent text-sm text-zinc-100 outline-none placeholder:text-zinc-600 disabled:opacity-50"
                                style={{ maxHeight: '140px', overflowY: 'auto' }}
                                onInput={(e) => {
                                    e.target.style.height = 'auto';
                                    e.target.style.height = Math.min(e.target.scrollHeight, 140) + 'px';
                                }}
                            />
                            <button
                                type="submit"
                                disabled={!input.trim() || loading}
                                className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white transition-all hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                    <line x1="22" y1="2" x2="11" y2="13" />
                                    <polygon points="22 2 15 22 11 13 2 9 22 2" />
                                </svg>
                            </button>
                        </div>
                        <p className="mt-2 text-center font-mono text-[10px] text-zinc-600">
                            Enter to send · Shift+Enter for new line · Answers grounded in your transcript embeddings
                        </p>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
