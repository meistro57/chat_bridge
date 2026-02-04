import React, { useEffect, useState, useRef } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';

const MarkdownContent = ({ content }) => {
    return (
        <ReactMarkdown
            remarkPlugins={[remarkGfm]}
            className="space-y-4"
            components={{
                h1: ({ children }) => <h1 className="text-xl font-semibold text-zinc-100">{children}</h1>,
                h2: ({ children }) => <h2 className="text-lg font-semibold text-zinc-100">{children}</h2>,
                h3: ({ children }) => <h3 className="text-base font-semibold text-zinc-100">{children}</h3>,
                h4: ({ children }) => <h4 className="text-base font-semibold text-zinc-100">{children}</h4>,
                h5: ({ children }) => <h5 className="text-base font-semibold text-zinc-100">{children}</h5>,
                h6: ({ children }) => <h6 className="text-base font-semibold text-zinc-100">{children}</h6>,
                p: ({ children }) => <p className="text-zinc-100">{children}</p>,
                ul: ({ children }) => <ul className="list-disc space-y-2 pl-5 text-zinc-100">{children}</ul>,
                ol: ({ children }) => <ol className="list-decimal space-y-2 pl-5 text-zinc-100">{children}</ol>,
                li: ({ children }) => <li>{children}</li>,
                a: ({ children, href }) => (
                    <a
                        href={href}
                        target="_blank"
                        rel="noreferrer"
                        className="text-indigo-300 underline decoration-dotted underline-offset-4 hover:text-indigo-200"
                    >
                        {children}
                    </a>
                ),
                code: ({ inline, className, children }) => {
                    if (inline) {
                        return (
                            <code className="rounded bg-black/40 px-1.5 py-0.5 text-[0.95em] font-mono text-indigo-100">
                                {children}
                            </code>
                        );
                    }

                    const language = className?.replace('language-', '') ?? '';

                    return (
                        <pre className="overflow-x-auto rounded-xl border border-white/10 bg-black/40 p-4 text-sm text-zinc-100">
                            {language && (
                                <div className="mb-2 text-[10px] uppercase tracking-widest text-indigo-300">
                                    {language}
                                </div>
                            )}
                            <code className="font-mono">{children}</code>
                        </pre>
                    );
                },
                blockquote: ({ children }) => (
                    <blockquote className="border-l-2 border-indigo-500/50 pl-4 text-zinc-200 italic">
                        {children}
                    </blockquote>
                ),
                hr: () => <hr className="border-white/10" />,
                table: ({ children }) => (
                    <div className="overflow-x-auto rounded-xl border border-white/10">
                        <table className="min-w-full text-left text-sm text-zinc-100">{children}</table>
                    </div>
                ),
                th: ({ children }) => (
                    <th className="bg-white/5 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-zinc-300">
                        {children}
                    </th>
                ),
                td: ({ children }) => <td className="border-t border-white/10 px-3 py-2">{children}</td>,
            }}
        >
            {String(content ?? '')}
        </ReactMarkdown>
    );
};

const PlainTextContent = ({ content }) => (
    <p className="text-zinc-100 whitespace-pre-wrap">{String(content ?? '')}</p>
);

export default function Show({ conversation, stopSignal }) {
    const [messages, setMessages] = useState(conversation.messages || []);
    const [streamingContent, setStreamingContent] = useState('');
    const [streamingSpeaker, setStreamingSpeaker] = useState(null);
    const [status, setStatus] = useState(conversation.status);
    const [isStopping, setIsStopping] = useState(stopSignal);
    const scrollRef = useRef(null);
    const streamingContentRef = useRef('');
    const streamingSpeakerRef = useRef(null);

    useEffect(() => {
        const channel = window.Echo.private(`conversation.${conversation.id}`);
        
        channel.listen('.message.chunk', (e) => {
            setStreamingSpeaker(e.personaName);
            streamingSpeakerRef.current = e.personaName;
            setStreamingContent(prev => prev + e.chunk);
            streamingContentRef.current = `${streamingContentRef.current}${e.chunk}`;
        });

        channel.listen('.message.completed', (e) => {
            const content = e?.message?.content ?? streamingContentRef.current;
            const personaName = e?.personaName ?? streamingSpeakerRef.current ?? null;

            setMessages(prev => {
                // Check if message already exists to prevent duplicates
                const messageExists = prev.some(msg => msg.id === e.message.id);
                if (messageExists) {
                    return prev;
                }

                return [
                    ...prev,
                    {
                        ...e.message,
                        content,
                        // Use persona from message data, or construct from personaName as fallback
                        persona: e.message?.persona ?? (personaName ? { name: personaName } : null),
                    },
                ];
            });
            setStreamingContent('');
            setStreamingSpeaker(null);
            streamingContentRef.current = '';
            streamingSpeakerRef.current = null;
        });

        channel.listen('.conversation.status.updated', (e) => {
            setStatus(e.conversation.status);
            if (e.conversation.status === 'completed') {
                setIsStopping(false);
            }
        });

        return () => {
            window.Echo.leave(`conversation.${conversation.id}`);
        };
    }, [conversation.id]);

    useEffect(() => {
        if (scrollRef.current) {
            scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
        }
    }, [messages, streamingContent]);

    const handleStop = () => {
        if (confirm('Initiate emergency halt sequence?')) {
            router.post(`/chat/${conversation.id}/stop`, {}, {
                onSuccess: () => setIsStopping(true)
            });
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Session ${conversation.id.substring(0,8)}`} />
            <div className="min-h-screen text-zinc-200 flex flex-col h-screen overflow-hidden">
            
            {/* Glass Header */}
            <div className="glass-panel glass-butter border-b border-white/5 p-4 z-10 sticky top-0 bg-[#09090b]/80 backdrop-blur-xl butter-reveal">
                <div className="max-w-5xl mx-auto flex justify-between items-center">
                    <div className="flex items-center gap-4">
                        <Link href="/chat" className="p-2 rounded-lg hover:bg-white/5 transition-colors text-zinc-400 hover:text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        </Link>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="font-bold text-lg tracking-tight">Session Transcript</h1>
                                <span className="px-2 py-0.5 rounded text-[10px] bg-zinc-800 text-zinc-400 font-mono">{conversation.id.substring(0,8)}</span>
                            </div>
                            <div className="text-xs text-zinc-500 flex items-center gap-2">
                                <span>{conversation.provider_a}</span>
                                <span className="w-1 h-1 rounded-full bg-zinc-600"></span>
                                <span>{conversation.provider_b}</span>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        <div className={`flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium border ${
                            (conversation.metadata?.notifications_enabled ?? true)
                                ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400'
                                : 'bg-zinc-800 border-zinc-700 text-zinc-400'
                        }`}>
                            {(conversation.metadata?.notifications_enabled ?? true) ? 'Email Alerts On' : 'Email Alerts Off'}
                        </div>
                        <div className={`flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium border ${
                            status === 'active' ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-zinc-800 border-zinc-700 text-zinc-400'
                        }`}>
                            <div className={`w-1.5 h-1.5 rounded-full ${status === 'active' ? 'bg-emerald-500 animate-pulse' : 'bg-zinc-500'}`}></div>
                            {status.toUpperCase()}
                        </div>

                        {status === 'active' && !isStopping && (
                            <button 
                                onClick={handleStop}
                                className="bg-red-500/10 text-red-500 hover:bg-red-500 hover:text-white border border-red-500/20 px-3 py-1.5 rounded-lg text-xs font-bold transition-all duration-300 flex items-center gap-2"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/></svg>
                                HALT
                            </button>
                        )}
                        {isStopping && (
                            <span className="text-red-400 text-xs font-mono animate-pulse">STOPPING...</span>
                        )}
                        <a 
                            href={`/chat/${conversation.id}/transcript`} 
                            className="text-zinc-400 hover:text-white p-2"
                            title="Download Transcript"
                            download
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                        </a>
                    </div>
                </div>
            </div>

            {/* Chat Area */}
            <div className="flex-1 overflow-y-auto custom-scrollbar p-6" ref={scrollRef}>
                <div className="max-w-3xl mx-auto space-y-8 pb-12">
                    
                    {/* Starter Message */}
                    <div className="flex justify-center mb-12">
                        <div className="glass-panel glass-butter px-6 py-4 rounded-2xl max-w-lg text-center butter-reveal">
                            <div className="text-[10px] uppercase tracking-widest text-indigo-400 mb-2 font-bold">Initial Prompt</div>
                            <p className="text-zinc-300 text-lg font-light leading-relaxed">"{conversation.starter_message}"</p>
                        </div>
                    </div>

                    {/* Messages */}
                    {messages.map((msg, idx) => {
                        const isUser = msg.role === 'user';
                        const isPersonaA = msg.persona_id === conversation.persona_a_id;
                        const isPersonaB = msg.persona_id === conversation.persona_b_id;

                        // Calculate response time (seconds from previous message)
                        const responseTime = idx > 0 && msg.created_at && messages[idx - 1].created_at
                            ? Math.abs(new Date(msg.created_at) - new Date(messages[idx - 1].created_at)) / 1000
                            : null;

                        // Determine colors and metadata based on persona
                        let colorClasses = '';
                        let labelColor = '';
                        let badgeColor = '';
                        let agent = '';
                        let provider = '';

                        if (isUser) {
                            colorClasses = 'bg-gradient-to-br from-indigo-900/40 to-blue-900/40 border border-indigo-500/20 rounded-tr-sm text-indigo-100 backdrop-blur-md';
                            labelColor = 'text-indigo-400';
                            badgeColor = 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30';
                            agent = 'User';
                        } else if (isPersonaA) {
                            colorClasses = 'bg-gradient-to-br from-purple-900/30 to-violet-900/30 border border-purple-500/20 rounded-tl-sm text-purple-50 backdrop-blur-md';
                            labelColor = 'text-purple-400';
                            badgeColor = 'bg-purple-500/20 text-purple-300 border-purple-500/30';
                            agent = 'Agent A';
                            provider = conversation.provider_a;
                        } else if (isPersonaB) {
                            colorClasses = 'bg-gradient-to-br from-emerald-900/30 to-teal-900/30 border border-emerald-500/20 rounded-tl-sm text-emerald-50 backdrop-blur-md';
                            labelColor = 'text-emerald-400';
                            badgeColor = 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30';
                            agent = 'Agent B';
                            provider = conversation.provider_b;
                        } else {
                            colorClasses = 'bg-zinc-800/40 border border-white/5 rounded-tl-sm text-zinc-100 backdrop-blur-md';
                            labelColor = 'text-zinc-400';
                            badgeColor = 'bg-zinc-700/20 text-zinc-400 border-zinc-600/30';
                            agent = 'Assistant';
                        }

                        const personaName = msg.persona?.name || agent;

                        return (
                            <div key={msg.id || idx} className={`flex flex-col ${isUser ? 'items-end' : 'items-start'} group`}>
                                {/* Card Header */}
                                <div className="flex flex-col gap-1 mb-2 px-1 max-w-2xl w-full">
                                    <div className="flex items-center justify-between gap-3">
                                        <div className="flex items-center gap-2">
                                            <span className={`text-sm font-bold uppercase tracking-wider ${labelColor}`}>
                                                {agent}
                                            </span>
                                            <span className={`text-xs font-medium ${labelColor} opacity-80`}>
                                                {personaName}
                                            </span>
                                        </div>
                                        <span className="text-[10px] text-zinc-500 font-mono opacity-0 group-hover:opacity-100 transition-opacity">
                                            {new Date(msg.created_at).toLocaleTimeString()}
                                        </span>
                                    </div>

                                    {/* Metadata Badges */}
                                    <div className="flex items-center gap-2 flex-wrap">
                                        {provider && (
                                            <span className={`px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase tracking-wide border ${badgeColor}`}>
                                                {provider}
                                            </span>
                                        )}
                                        {responseTime !== null && (
                                            <span className="px-2 py-0.5 rounded-md text-[10px] font-mono bg-zinc-800/50 text-zinc-400 border border-zinc-700/50">
                                                {responseTime < 60
                                                    ? `${responseTime.toFixed(1)}s`
                                                    : `${Math.floor(responseTime / 60)}m ${Math.floor(responseTime % 60)}s`}
                                            </span>
                                        )}
                                        {msg.tokens_used && (
                                            <span className="px-2 py-0.5 rounded-md text-[10px] font-mono bg-zinc-800/50 text-zinc-400 border border-zinc-700/50">
                                                {msg.tokens_used.toLocaleString()} tokens
                                            </span>
                                        )}
                                        <span className="px-2 py-0.5 rounded-md text-[10px] font-mono bg-zinc-800/50 text-zinc-400 border border-zinc-700/50">
                                            {msg.content.length.toLocaleString()} chars
                                        </span>
                                    </div>
                                </div>

                                {/* Card Content */}
                                <div className={`max-w-2xl p-6 rounded-2xl text-lg leading-relaxed shadow-lg ${colorClasses}`}>
                                    <MarkdownContent content={msg.content} />
                                </div>
                            </div>
                        );
                    })}

                    {/* Streaming Indicator */}
                    {streamingSpeaker && (
                        <div className="flex flex-col items-start animate-pulse">
                            <div className="flex flex-col gap-1 mb-2 px-1 max-w-2xl w-full">
                                <div className="flex items-center gap-2">
                                    <span className="text-sm font-bold uppercase tracking-wider text-cyan-400">
                                        Streaming
                                    </span>
                                    <span className="text-xs font-medium text-cyan-400 opacity-80">
                                        {streamingSpeaker}
                                    </span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase tracking-wide border bg-cyan-500/20 text-cyan-300 border-cyan-500/30">
                                        Live
                                    </span>
                                    <span className="px-2 py-0.5 rounded-md text-[10px] font-mono bg-zinc-800/50 text-zinc-400 border border-zinc-700/50">
                                        {streamingContent.length.toLocaleString()} chars
                                    </span>
                                </div>
                            </div>
                            <div className="max-w-2xl p-6 rounded-2xl rounded-tl-sm bg-gradient-to-br from-cyan-900/30 to-blue-900/30 border border-cyan-500/30 text-cyan-50 text-lg leading-relaxed shadow-[0_0_20px_rgba(6,182,212,0.15)] backdrop-blur-md">
                                <MarkdownContent content={streamingContent} />
                                <span className="inline-block w-2 h-5 bg-cyan-400 ml-1 animate-blink">|</span>
                            </div>
                        </div>
                    )}
                </div>
            </div>
            </div>
        </AuthenticatedLayout>
    );
}
