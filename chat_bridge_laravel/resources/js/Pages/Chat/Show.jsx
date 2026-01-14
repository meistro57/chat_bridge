import React, { useEffect, useState, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';

export default function Show({ conversation }) {
    const [messages, setMessages] = useState(conversation.messages || []);
    const [streamingContent, setStreamingContent] = useState('');
    const [streamingSpeaker, setStreamingSpeaker] = useState(null);
    const [status, setStatus] = useState(conversation.status);
    const scrollRef = useRef(null);

    useEffect(() => {
        const channel = window.Echo.channel(`conversation.${conversation.id}`);
        
        channel.listen('.message.chunk', (e) => {
            setStreamingSpeaker(e.personaName);
            setStreamingContent(prev => prev + e.chunk);
        });

        channel.listen('.message.completed', (e) => {
            setMessages(prev => [...prev, e.message]);
            setStreamingContent('');
            setStreamingSpeaker(null);
            
            // Check if conversation completed in background
            if (e.message.role === 'assistant' && status === 'active') {
                // We'd ideally have a status update event, but we can infer or wait for refresh
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

    return (
        <div className="min-h-screen p-4 md:p-8 font-['VT323'] bg-black text-green-500">
            <Head title={`Conversation ${conversation.id}`} />
            
            <div className="max-w-5xl mx-auto h-[90vh] flex flex-col border-2 border-green-900 shadow-[0_0_20px_rgba(0,128,0,0.5)]">
                {/* Vintage Terminal Title Bar */}
                <div className="bg-green-900 text-black p-2 flex justify-between items-center px-4">
                    <span className="font-bold tracking-widest text-xl">SESSION_TRANSCRIPT::{conversation.id.substring(0,8)}</span>
                    <div className="flex gap-4">
                        <Link href="/chat" className="hover:bg-green-400 px-2">EXIT_TO_DASHBOARD</Link>
                    </div>
                </div>

                {/* Main Content */}
                <div ref={scrollRef} className="flex-1 overflow-y-auto p-6 space-y-6 bg-[#050505]">
                    {/* Starter Message */}
                    <div className="border-b border-green-900 pb-4">
                        <span className="bg-green-900 text-black px-2 py-0.5 mr-2">ORIGIN_INPUT</span>
                        <span className="text-xl text-green-300">{conversation.starter_message}</span>
                    </div>

                    {/* Chat History */}
                    {messages.map((msg, idx) => (
                        <div key={msg.id || idx} className="space-y-1">
                            <div className="flex items-center gap-2">
                                <span className="text-green-800">[{msg.created_at}]</span>
                                <span className={`font-bold text-2xl ${msg.role === 'user' ? 'text-blue-500' : 'text-green-400'}`}>
                                    {msg.persona?.name || msg.role.toUpperCase()}:
                                </span>
                            </div>
                            <div className="text-2xl leading-relaxed pl-4 border-l-2 border-green-900">
                                {msg.content}
                            </div>
                        </div>
                    ))}

                    {/* Streaming Content */}
                    {streamingSpeaker && (
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <span className="text-green-800">[LIVE_STREAM]</span>
                                <span className="font-bold text-2xl text-yellow-500 animate-pulse">
                                    {streamingSpeaker}:
                                </span>
                            </div>
                            <div className="text-2xl leading-relaxed pl-4 border-l-2 border-yellow-900">
                                {streamingContent}
                                <span className="inline-block w-3 h-6 bg-yellow-500 ml-1 animate-ping"></span>
                            </div>
                        </div>
                    )}
                </div>

                {/* Status Bar */}
                <div className="bg-green-950 p-2 text-sm flex justify-between px-4 border-t border-green-900">
                    <div className="flex gap-4">
                        <div>PROVIDER_A: {conversation.provider_a} [{conversation.model_a}]</div>
                        <div>PROVIDER_B: {conversation.provider_b} [{conversation.model_b}]</div>
                    </div>
                    <div className="flex gap-4 items-center">
                        <a 
                            href={`/chat/${conversation.id}/transcript`} 
                            className="bg-green-900 text-black px-2 hover:bg-green-400"
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
