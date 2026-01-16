import React from 'react';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ personas }) {
    const handleDelete = (id) => {
        if (confirm('Delete this persona?')) {
            router.delete(`/personas/${id}`);
        }
    };

    return (
        <div className="min-h-screen p-8 font-['VT323'] bg-[#c0c0c0]">
            <Head title="Persona Library" />
            
            <div className="max-w-5xl mx-auto border-2 border-[#ffffff] shadow-[2px_2px_0px_0px_#000000]">
                <div className="bg-[#000080] text-white p-1 flex justify-between items-center px-2">
                    <span className="font-bold">PERSONA_LIBRARY.EXE</span>
                    <Link href="/" className="bg-[#c0c0c0] text-black px-2 text-sm border-b border-r border-black shadow-[1px_1px_0px_0px_#ffffff_inset]">X</Link>
                </div>

                <div className="p-6">
                    <div className="flex justify-between items-center mb-6">
                        <h1 className="text-3xl font-bold uppercase">Registered Agents</h1>
                        <Link 
                            href="/personas/create"
                            className="bg-[#c0c0c0] px-4 py-1 text-xl shadow-[2px_2px_0px_0px_#ffffff_inset,-2px_-2px_0px_0px_#808080_inset]"
                        >
                            + ADD_NEW_PERSONA
                        </Link>
                    </div>

                    <div className="bg-white border-2 border-[#808080] overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead className="bg-[#c0c0c0] border-b-2 border-[#808080]">
                                <tr>
                                    <th className="p-2 border-r border-[#808080]">NAME</th>
                                    <th className="p-2 border-r border-[#808080]">PROVIDER</th>
                                    <th className="p-2 border-r border-[#808080]">MODEL</th>
                                    <th className="p-2 border-r border-[#808080]">TEMP</th>
                                    <th className="p-2">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                {personas.map(persona => (
                                    <tr key={persona.id} className="border-b border-[#c0c0c0] hover:bg-[#ffffcc]">
                                        <td className="p-2 border-r border-[#c0c0c0] font-bold">{persona.name}</td>
                                        <td className="p-2 border-r border-[#c0c0c0]">{persona.provider}</td>
                                        <td className="p-2 border-r border-[#c0c0c0] text-sm">{persona.model || 'DEFAULT'}</td>
                                        <td className="p-2 border-r border-[#c0c0c0]">{persona.temperature}</td>
                                        <td className="p-2 flex gap-4">
                                            <Link href={`/personas/${persona.id}/edit`} className="text-blue-800 underline">EDIT</Link>
                                            <button onClick={() => handleDelete(persona.id)} className="text-red-600 underline">DELETE</button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    
                    <div className="mt-6">
                        <Link href="/" className="text-xl underline">{"<-- RETURN_TO_DASHBOARD"}</Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
