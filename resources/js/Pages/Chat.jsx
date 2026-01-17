import React from 'react';
import { Head, Link } from '@inertiajs/react';

export default function Chat({ personas, conversations }) {
    return (
        <div className="min-h-screen p-8 font-['VT323']">
            <Head title="Dashboard" />
            
            <div className="max-w-4xl mx-auto">
                <div className="bg-[#000080] text-white p-2 flex justify-between items-center shadow-[2px_2px_0px_0px_#ffffff_inset,-2px_-2px_0px_0px_#808080_inset]">
                    <span className="font-bold uppercase tracking-wider text-xl">Bridge_Chat_Network.exe</span>
                    <div className="flex gap-1">
                        <button className="bg-[#c0c0c0] text-black px-2 py-0.5 text-xs shadow-[1px_1px_0px_0px_#ffffff_inset,-1px_-1px_0px_0px_#808080_inset]">_</button>
                        <button className="bg-[#c0c0c0] text-black px-2 py-0.5 text-xs shadow-[1px_1px_0px_0px_#ffffff_inset,-1px_-1px_0px_0px_#808080_inset]">X</button>
                    </div>
                </div>

                <div className="bg-[#c0c0c0] p-6 shadow-[2px_2px_0px_0px_#ffffff_inset,-2px_-2px_0px_0px_#808080_inset] border-t-0">
                    <div className="flex justify-between items-center mb-8">
                        <h1 className="text-4xl text-black">MAIN CONTROL PANEL</h1>
                        <div className="flex gap-4">
                            <Link 
                                href="/chat/search" 
                                className="bg-[#c0c0c0] text-black px-4 py-2 text-xl shadow-[2px_2px_0px_0px_#ffffff_inset,-2px_-2px_0px_0px_#808080_inset] active:shadow-[-2px_-2px_0px_0px_#ffffff_inset,2px_2px_0px_0px_#808080_inset]"
                            >
                                SEARCH_ARCHIVES
                            </Link>
                            <Link 
                                href="/personas" 
                                className="bg-[#c0c0c0] text-black px-4 py-2 text-xl shadow-[2px_2px_0px_0px_#ffffff_inset,-2px_-2px_0px_0px_#808080_inset] active:shadow-[-2px_-2px_0px_0px_#ffffff_inset,2px_2px_0px_0px_#808080_inset]"
                            >
                                MANAGE_PERSONAS
                            </Link>
                            <Link 
                                href="/chat/create" 
                                className="bg-[#000080] text-white px-6 py-2 text-2xl shadow-[2px_2px_0px_0px_#ffffff_inset,-2px_-2px_0px_0px_#808080_inset] active:shadow-[-2px_-2px_0px_0px_#ffffff_inset,2px_2px_0px_0px_#808080_inset]"
                            >
                                INIT_NEW_BRIDGE
                            </Link>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <h2 className="text-2xl mb-4 text-[#000080] border-b-2 border-[#000080]">RECENT_SESSIONS</h2>
                            <div className="space-y-4">
                                {conversations.map((conv) => (
                                        <div key={conv.id} className="bg-white p-3 border-2 border-[#808080] hover:bg-[#ffffcc] group">
                                            <div className="flex justify-between items-start">
                                                <Link href={`/chat/${conv.id}`} className="flex-1">
                                                    <div className="flex justify-between text-xl font-bold">
                                                        <span>{conv.provider_a} vs {conv.provider_b}</span>
                                                        <span className="text-sm opacity-60 uppercase">{conv.status}</span>
                                                    </div>
                                                    <p className="truncate text-lg opacity-80 mt-1">{conv.starter_message}</p>
                                                </Link>
                                                <Link 
                                                    href={route('chat.destroy', conv.id)} 
                                                    method="delete" 
                                                    as="button"
                                                    className="ml-2 text-red-600 opacity-0 group-hover:opacity-100 hover:font-bold"
                                                    onClick={(e) => !confirm('Are you sure?') && e.preventDefault()}
                                                >
                                                    [X]
                                                </Link>
                                            </div>
                                        </div>
                                ))}
                                {conversations.length === 0 && <p className="text-xl opacity-60">NO_DATA_FOUND</p>}
                            </div>
                        </div>

                        <div>
                            <h2 className="text-2xl mb-4 text-[#000080] border-b-2 border-[#000080]">PERSONA_LIBRARY</h2>
                            <div className="bg-white p-4 border-2 border-[#808080] h-[400px] overflow-y-auto">
                                <ul className="space-y-2 text-xl">
                                    {personas.map((persona) => (
                                        <li key={persona.id} className="flex justify-between">
                                            <span>{persona.name}</span>
                                            <span className="text-sm uppercase opacity-60">[{persona.provider}]</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
