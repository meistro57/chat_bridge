import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';

export default function Edit({ persona }) {
    const { data, setData, put, processing, errors } = useForm({
        name: persona.name,
        provider: persona.provider,
        model: persona.model || '',
        system_prompt: persona.system_prompt,
        guidelines: persona.guidelines || [],
        temperature: persona.temperature,
        notes: persona.notes || '',
    });

    const providers = ['openai', 'anthropic', 'gemini', 'deepseek', 'openrouter', 'ollama', 'lmstudio'];

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/personas/${persona.id}`);
    };

    return (
        <div className="min-h-screen p-8 font-['VT323'] bg-[#c0c0c0]">
            <Head title="Edit Persona" />
            
            <div className="max-w-3xl mx-auto border-2 border-[#ffffff] shadow-[2px_2px_0px_0px_#000000]">
                <div className="bg-[#000080] text-white p-1 flex justify-between items-center px-2">
                    <span className="font-bold">PERSONA_EDITOR.PRO - {persona.name}</span>
                    <Link href="/personas" className="bg-[#c0c0c0] text-black px-2 text-sm border-b border-r border-black shadow-[1px_1px_0px_0px_#ffffff_inset]">X</Link>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-1">
                            <label className="block text-lg">AGENT_NAME:</label>
                            <input 
                                type="text"
                                value={data.name}
                                onChange={e => setData('name', e.target.value)}
                                className="w-full bg-white border-2 border-[#808080] p-1 shadow-[1px_1px_0px_0px_#000000_inset]"
                            />
                            {errors.name && <div className="text-red-600 text-sm">{errors.name}</div>}
                        </div>
                        <div className="space-y-1">
                            <label className="block text-lg">PROVIDER:</label>
                            <select 
                                value={data.provider}
                                onChange={e => setData('provider', e.target.value)}
                                className="w-full bg-white border-2 border-[#808080] p-1 shadow-[1px_1px_0px_0px_#000000_inset]"
                            >
                                {providers.map(p => <option key={p} value={p}>{p.toUpperCase()}</option>)}
                            </select>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-1">
                            <label className="block text-lg">MODEL_OVERRIDE:</label>
                            <input 
                                type="text"
                                value={data.model}
                                onChange={e => setData('model', e.target.value)}
                                className="w-full bg-white border-2 border-[#808080] p-1 shadow-[1px_1px_0px_0px_#000000_inset]"
                            />
                        </div>
                        <div className="space-y-1">
                            <label className="block text-lg">TEMPERATURE:</label>
                            <input 
                                type="number"
                                step="0.1"
                                min="0"
                                max="2"
                                value={data.temperature}
                                onChange={e => setData('temperature', e.target.value)}
                                className="w-full bg-white border-2 border-[#808080] p-1 shadow-[1px_1px_0px_0px_#000000_inset]"
                            />
                        </div>
                    </div>

                    <div className="space-y-1">
                        <label className="block text-lg">SYSTEM_PROMPT:</label>
                        <textarea 
                            value={data.system_prompt}
                            onChange={e => setData('system_prompt', e.target.value)}
                            className="w-full bg-white border-2 border-[#808080] p-2 h-40 shadow-[1px_1px_0px_0px_#000000_inset]"
                        ></textarea>
                        {errors.system_prompt && <div className="text-red-600 text-sm">{errors.system_prompt}</div>}
                    </div>

                    <div className="space-y-1">
                        <label className="block text-lg">ADDITIONAL_NOTES:</label>
                        <input 
                            type="text"
                            value={data.notes}
                            onChange={e => setData('notes', e.target.value)}
                            className="w-full bg-white border-2 border-[#808080] p-1 shadow-[1px_1px_0px_0px_#000000_inset]"
                        />
                    </div>

                    <div className="flex justify-between pt-4">
                        <Link href="/personas" className="bg-[#c0c0c0] px-4 py-2 border-2 border-[#ffffff] shadow-[1px_1px_0px_0px_#000000]">CANCEL_CHANGES</Link>
                        <button 
                            type="submit" 
                            disabled={processing}
                            className="bg-[#c0c0c0] px-8 py-2 text-xl font-bold border-2 border-[#ffffff] shadow-[1px_1px_0px_0px_#000000] active:shadow-[-1px_-1px_0px_0px_#ffffff_inset]"
                        >
                            {processing ? 'UPDATING...' : 'COMMIT_CHANGES'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
