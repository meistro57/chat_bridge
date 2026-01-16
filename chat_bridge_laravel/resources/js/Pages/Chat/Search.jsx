import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';

export default function Search({ results, query }) {
    const [searchTerm, setSearchTerm] = useState(query || '');

    const handleSearch = (e) => {
        e.preventDefault();
        router.get('/chat/search', { q: searchTerm }, { preserveState: true });
    };

    return (
        <div className="min-h-screen p-8 font-['VT323'] bg-[#c0c0c0]">
            <Head title="Search Conversations" />
            
            <div className="max-w-4xl mx-auto border-2 border-[#ffffff] shadow-[2px_2px_0px_0px_#000000]">
                <div className="bg-[#000080] text-white p-1 flex justify-between items-center px-2">
                    <span className="font-bold">CONVERSATION_SEARCH_QUERY.EXE</span>
                    <Link href="/" className="bg-[#c0c0c0] text-black px-2 text-sm border-b border-r border-black shadow-[1px_1px_0px_0px_#ffffff_inset]">X</Link>
                </div>

                <div className="p-6">
                    <form onSubmit={handleSearch} className="mb-8">
                        <label className="block text-2xl mb-2 uppercase">Input Search Parameters:</label>
                        <div className="flex gap-2">
                            <input 
                                type="text"
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="flex-1 bg-white border-2 border-[#808080] p-2 text-2xl shadow-[1px_1px_0px_0px_#000000_inset]"
                                placeholder="Keyword lookup..."
                            />
                            <button 
                                type="submit"
                                className="bg-[#c0c0c0] px-6 py-2 text-2xl border-2 border-[#ffffff] shadow-[1px_1px_0px_0px_#000000] active:shadow-[-1px_-1px_0px_0px_#ffffff_inset]"
                            >
                                EXECUTE
                            </button>
                        </div>
                    </form>

                    <div className="space-y-6">
                        <h2 className="text-2xl border-b-2 border-[#000080] text-[#000080] mb-4 uppercase">
                            Query Results: {results.length} found
                        </h2>

                        {results.map((msg) => (
                            <div key={msg.id} className="bg-white p-4 border-2 border-[#808080] hover:bg-[#ffffcc]">
                                <div className="flex justify-between items-center mb-2">
                                    <span className="font-bold text-xl text-[#000080]">
                                        {msg.persona?.name || 'SYSTEM'}:
                                    </span>
                                    <Link 
                                        href={`/chat/${msg.conversation_id}`} 
                                        className="text-sm underline hover:font-bold"
                                    >
                                        VIEW_FULL_CONTEXT [{msg.conversation_id.substring(0,8)}]
                                    </Link>
                                </div>
                                <p className="text-xl leading-relaxed italic">
                                    "{msg.content.substring(0, 200)}{msg.content.length > 200 ? '...' : ''}"
                                </p>
                                <div className="mt-2 text-sm opacity-60">
                                    TIMESTAMP: {new Date(msg.created_at).toLocaleString()} | 
                                    AGENTS: {msg.conversation.provider_a} vs {msg.conversation.provider_b}
                                </div>
                            </div>
                        ))}

                        {results.length === 0 && searchTerm && (
                            <p className="text-3xl text-center opacity-40 py-20 uppercase">NO_MATCHING_RECORDS_IN_DATABASE</p>
                        )}
                        
                        {!searchTerm && (
                            <p className="text-2xl text-center opacity-40 py-20 uppercase">AWAITING_QUERY_INPUT</p>
                        )}
                    </div>

                    <div className="mt-8">
                        <Link href="/" className="text-xl underline">{"<-- RETURN_TO_DASHBOARD"}</Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
