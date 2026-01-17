import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';

export default function Create({ personas }) {
    const { data, setData, post, processing, errors } = useForm({
        persona_a_id: '',
        persona_b_id: '',
        starter_message: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/chat/store');
    };

    return (
        <div className="min-h-screen p-8 font-['VT323'] bg-[#c0c0c0]">
            <Head title="Init New Bridge" />
            
            <div className="max-w-2xl mx-auto border-2 border-[#ffffff] shadow-[2px_2px_0px_0px_#000000]">
                <div className="bg-[#000080] text-white p-1 flex justify-between items-center px-2">
                    <span className="font-bold">NEW_BRIDGE_SETUP.EXE</span>
                    <Link href="/" className="bg-[#c0c0c0] text-black px-2 text-sm border-b border-r border-black shadow-[1px_1px_0px_0px_#ffffff_inset]">X</Link>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-6">
                    <div className="space-y-2">
                        <label className="block text-xl">SELECT AGENT A PERSONA:</label>
                        <select 
                            value={data.persona_a_id}
                            onChange={e => setData('persona_a_id', e.target.value)}
                            className="w-full bg-white border-2 border-[#808080] p-2 text-xl"
                        >
                            <option value="">-- SELECT --</option>
                            {personas.map(p => (
                                <option key={p.id} value={p.id}>{p.name} ({p.provider})</option>
                            ))}
                        </select>
                        {errors.persona_a_id && <div className="text-red-600">{errors.persona_a_id}</div>}
                    </div>

                    <div className="space-y-2">
                        <label className="block text-xl">SELECT AGENT B PERSONA:</label>
                        <select 
                            value={data.persona_b_id}
                            onChange={e => setData('persona_b_id', e.target.value)}
                            className="w-full bg-white border-2 border-[#808080] p-2 text-xl"
                        >
                            <option value="">-- SELECT --</option>
                            {personas.map(p => (
                                <option key={p.id} value={p.id}>{p.name} ({p.provider})</option>
                            ))}
                        </select>
                        {errors.persona_b_id && <div className="text-red-600">{errors.persona_b_id}</div>}
                    </div>

                    <div className="space-y-2">
                        <label className="block text-xl">CONVERSATION STARTER:</label>
                        <textarea 
                            value={data.starter_message}
                            onChange={e => setData('starter_message', e.target.value)}
                            className="w-full bg-white border-2 border-[#808080] p-2 text-xl h-32"
                            placeholder="Type the initial prompt..."
                        ></textarea>
                        {errors.starter_message && <div className="text-red-600">{errors.starter_message}</div>}
                    </div>

                    <div className="flex justify-end pt-4">
                        <button 
                            type="submit" 
                            disabled={processing}
                            className="bg-[#c0c0c0] px-8 py-2 text-2xl shadow-[2px_2px_0px_0px_#ffffff_inset,-2px_-2px_0px_0px_#808080_inset] active:shadow-[-2px_-2px_0px_0px_#ffffff_inset,2px_2px_0px_0px_#808080_inset]"
                        >
                            {processing ? 'INITIALIZING...' : 'START_CONVERSATION'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
