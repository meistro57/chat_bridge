import React, { useEffect, useState, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';

export default function Show({ conversation, stopSignal }) {
    const [messages, setMessages] = useState(conversation.messages || []);
    const [streamingContent, setStreamingContent] = useState('');
    const [streamingSpeaker, setStreamingSpeaker] = useState(null);
    const [status, setStatus] = useState(conversation.status);
    const [isStopping, setIsStopping] = useState(stopSignal);
    const [theme, setTheme] = useState('matrix'); // 'matrix', 'retro', 'cyber'
    const scrollRef = useRef(null);

    const getThemeClasses = () => {
        switch (theme) {
            case 'retro':
                return {
                    container: 'bg-retro-bg text-retro-text font-serif',
                    window: 'border-2 border-retro-border-light shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]',
                    titleBar: 'bg-retro-window-title text-white',
                    content: 'bg-white border-2 border-retro-border-dark',
                    bubble: 'border-l-4 border-retro-border-dark pl-4',
                    userText: 'text-blue-800',
                    aiText: 'text-black',
                };
            case 'cyber':
                return {
                    container: 'bg-cyber-bg text-cyber-text font-mono',
                    window: 'border-2 border-cyber-border shadow-[0_0_15px_rgba(255,0,255,0.5)]',
                    titleBar: 'bg-cyber-border text-black',
                    content: 'bg-[#0a0a0a] border-y border-cyber-border',
                    bubble: 'border-l-2 border-cyber-accent pl-4',
                    userText: 'text-pink-400',
                    aiText: 'text-cyber-text',
                };
            default: // matrix
                return {
                    container: 'bg-black text-matrix-text font-mono',
                    window: 'border-2 border-matrix-border shadow-[0_0_20px_rgba(0,128,0,0.5)]',
                    titleBar: 'bg-matrix-border text-black',
                    content: 'bg-[#050505]',
                    bubble: 'border-l-2 border-matrix-border pl-4',
                    userText: 'text-blue-500',
                    aiText: 'text-matrix-text',
                };
        }
    };

    const t = getThemeClasses();

    useEffect(() => {
        const channel = window.Echo.private(`conversation.${conversation.id}`);
        
        channel.listen('.message.chunk', (e) => {
            setStreamingSpeaker(e.personaName);
            setStreamingContent(prev => prev + e.chunk);
        });

        channel.listen('.message.completed', (e) => {
            setMessages(prev => [...prev, e.message]);
            setStreamingContent('');
            setStreamingSpeaker(null);
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
        if (confirm('Send interrupt signal to agents?')) {
            router.post(`/chat/${conversation.id}/stop`, {}, {
                onSuccess: () => setIsStopping(true)
            });
        }
    };

    return (
        <div className={`min-h-screen p-4 md:p-8 ${t.container}`}>
            <Head title={`Conversation ${conversation.id}`} />
            
            <div className={`max-w-5xl mx-auto h-[90vh] flex flex-col ${t.window}`}>
                {/* Theme Switcher and Title Bar */}
                <div className={`${t.titleBar} p-2 flex justify-between items-center px-4`}>
                    <div className="flex items-center gap-4">
                        <span className="font-bold tracking-widest text-xl">SESSION_TRANSCRIPT::{conversation.id.substring(0,8)}</span>
                        <div className="flex bg-black/20 p-1 rounded gap-1 ml-4">
                            <button onClick={() => setTheme('matrix')} className={`px-2 text-xs ${theme === 'matrix' ? 'bg-white/20' : ''}`}>MTRX</button>
                            <button onClick={() => setTheme('retro')} className={`px-2 text-xs ${theme === 'retro' ? 'bg-white/20' : ''}`}>95</button>
                            <button onClick={() => setTheme('cyber')} className={`px-2 text-xs ${theme === 'cyber' ? 'bg-white/20' : ''}`}>CYBR</button>
                        </div>
                        {status === 'active' && !isStopping && (
                            <button 
                                onClick={handleStop}
                                className="ml-4 bg-red-600 text-white px-3 py-0.5 text-xs font-bold animate-pulse hover:bg-red-700"
                            >
                                HALT_EXECUTION
                            </button>
                        )}
                        {isStopping && (
                            <span className="ml-4 text-red-500 text-xs font-bold font-mono">STOP_SIGNAL_PENDING...</span>
                        )}
                    </div>
                    <div className="flex gap-4">
                        <Link href="/chat" className="hover:opacity-70 px-2">EXIT_TO_DASHBOARD</Link>
                    </div>
                </div>

                {/* Main Content */}
                <div ref={scrollRef} className={`flex-1 overflow-y-auto p-6 space-y-6 ${t.content}`}>
                    {/* Starter Message */}
                    <div className="border-b border-white/10 pb-4">
                        <span className={`${theme === 'retro' ? 'bg-retro-window-title text-white' : 'bg-matrix-border text-black'} px-2 py-0.5 mr-2`}>ORIGIN_INPUT</span>
                        <span className="text-xl opacity-80">{conversation.starter_message}</span>
                    </div>

                    {/* Chat History */}
                    {messages.map((msg, idx) => (
                        <div key={msg.id || idx} className="space-y-1">
                            <div className="flex items-center gap-2">
                                <span className="text-xs opacity-50">[{new Date(msg.created_at).toLocaleTimeString()}]</span>
                                <span className={`font-bold text-2xl ${msg.role === 'user' ? t.userText : t.aiText}`}>
                                    {msg.persona?.name || msg.role.toUpperCase()}:
                                </span>
                            </div>
                            <div className={`text-2xl leading-relaxed ${t.bubble}`}>
                                {msg.content}
                            </div>
                        </div>
                    ))}

                    {/* Streaming Content */}
                    {streamingSpeaker && (
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <span className="text-xs opacity-50">[LIVE_STREAM]</span>
                                <span className="font-bold text-2xl text-yellow-500 animate-pulse">
                                    {streamingSpeaker}:
                                </span>
                            </div>
                            <div className={`text-2xl leading-relaxed ${t.bubble} border-yellow-900/50`}>
                                {streamingContent}
                                <span className="inline-block w-3 h-6 bg-yellow-500 ml-1 animate-ping"></span>
                            </div>
                        </div>
                    )}
                </div>

                {/* Status Bar */}
                <div className={`${t.titleBar} p-2 text-sm flex justify-between px-4 opacity-90`}>
                    <div className="flex gap-4">
                        <div>PROVIDER_A: {conversation.provider_a}</div>
                        <div>PROVIDER_B: {conversation.provider_b}</div>
                    </div>
                    <div className="flex gap-4 items-center">
                        <a 
                            href={`/chat/${conversation.id}/transcript`} 
                            className="bg-black/20 px-2 hover:bg-black/40"
                            download
                        >
                            DOWNLOAD_TRANSCRIPT
                        </a>
                        <div className="animate-pulse">SYSTEM_STABLE: 100%</div>
                    </div>
                </div>
            </div>
        </div>
    );
}
