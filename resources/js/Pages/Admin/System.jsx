import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';

export default function System({ systemInfo }) {
    const [output, setOutput] = useState('');
    const [loading, setLoading] = useState(false);
    const [activeAction, setActiveAction] = useState(null);
    const [openaiKey, setOpenaiKey] = useState('');
    const [openaiStatus, setOpenaiStatus] = useState({
        isSet: systemInfo.openai_key_set,
        last4: systemInfo.openai_key_last4,
    });
    const [savingKey, setSavingKey] = useState(false);
    const [testingKey, setTestingKey] = useState(false);
    const [clearingKey, setClearingKey] = useState(false);
    const [codexPrompt, setCodexPrompt] = useState('');
    const [invokingCodex, setInvokingCodex] = useState(false);

    const boostAgents = systemInfo.boost?.agents?.length
        ? systemInfo.boost.agents.join(', ')
        : 'None';
    const boostEditors = systemInfo.boost?.editors?.length
        ? systemInfo.boost.editors.join(', ')
        : 'None';
    const mcpDetails = systemInfo.mcp?.details ?? {};

    const runAction = async (action, label) => {
        setLoading(true);
        setActiveAction(action);
        setOutput(`Running ${label}...\n\n`);

        try {
            const response = await axios.post('/admin/system/diagnostic', { action });
            setOutput(response.data.output);
        } catch (error) {
            setOutput(`Error: ${error.response?.data?.message || error.message}`);
        } finally {
            setLoading(false);
            setActiveAction(null);
        }
    };

    const saveOpenAiKey = async (e) => {
        e.preventDefault();
        setSavingKey(true);
        setOutput('Saving OpenAI service key...\n\n');

        try {
            const response = await axios.post('/admin/system/openai-key', {
                openai_key: openaiKey,
            });
            setOpenaiStatus({
                isSet: response.data.openai_key_set,
                last4: response.data.openai_key_last4,
            });
            setOpenaiKey('');
            setOutput('✓ OpenAI service key updated.\n');
        } catch (error) {
            setOutput(`Error: ${error.response?.data?.message || error.message}`);
        } finally {
            setSavingKey(false);
        }
    };

    const testOpenAiKey = async () => {
        setTestingKey(true);
        setOutput('Testing OpenAI service key...\n\n');

        try {
            const response = await axios.post('/admin/system/openai-key/test', {
                openai_key: openaiKey.length ? openaiKey : null,
            });
            setOutput(`${response.data.message}\nResult: ${response.data.result}\n`);
        } catch (error) {
            setOutput(`Error: ${error.response?.data?.message || error.message}`);
        } finally {
            setTestingKey(false);
        }
    };

    const clearOpenAiKey = async () => {
        if (!confirm('Clear the OpenAI service key?')) {
            return;
        }

        setClearingKey(true);
        setOutput('Clearing OpenAI service key...\n\n');

        try {
            const response = await axios.post('/admin/system/openai-key/clear');
            setOpenaiStatus({
                isSet: response.data.openai_key_set,
                last4: null,
            });
            setOutput('✓ OpenAI service key cleared.\n');
        } catch (error) {
            setOutput(`Error: ${error.response?.data?.message || error.message}`);
        } finally {
            setClearingKey(false);
        }
    };

    const invokeCodex = async (e) => {
        e.preventDefault();
        setInvokingCodex(true);
        setOutput('Invoking Codex AI Agent...\n\n');

        try {
            const response = await axios.post('/admin/system/diagnostic', {
                action: 'invoke_codex',
                prompt: codexPrompt,
            });
            setOutput(response.data.output);
        } catch (error) {
            setOutput(`Error: ${error.response?.data?.message || error.message}`);
        } finally {
            setInvokingCodex(false);
        }
    };

    const actions = [
        { id: 'health_check', label: 'Health Check', icon: '🏥', color: 'blue' },
        { id: 'fix_permissions', label: 'Fix Permissions', icon: '🔐', color: 'purple' },
        { id: 'clear_cache', label: 'Clear All Caches', icon: '🗑️', color: 'orange' },
        { id: 'optimize', label: 'Optimize App', icon: '⚡', color: 'yellow' },
        { id: 'validate_ai', label: 'Validate AI Services', icon: '🤖', color: 'cyan' },
        { id: 'check_database', label: 'Check Database', icon: '🗄️', color: 'emerald' },
        { id: 'run_tests', label: 'Run Tests', icon: '🧪', color: 'pink' },
        { id: 'fix_code_style', label: 'Fix Code Style', icon: '✨', color: 'indigo' },
    ];

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-zinc-100">
                    System Diagnostics
                </h2>
            }
        >
            <Head title="System Diagnostics" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                    {/* System Information */}
                    <div className="relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-6 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden">
                        <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-blue-500/80 via-cyan-500/80 to-blue-400/80" />
                        <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />
                        <h3 className="relative text-lg font-bold text-zinc-100 mb-4">System Information</h3>
                        <div className="relative grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <InfoCard label="PHP Version" value={systemInfo.php_version} />
                            <InfoCard label="Laravel Version" value={systemInfo.laravel_version} />
                            <InfoCard label="Environment" value={systemInfo.environment} />
                            <InfoCard label="Cache Driver" value={systemInfo.cache_driver} />
                            <InfoCard label="Queue Driver" value={systemInfo.queue_driver} />
                            <InfoCard label="Database" value={systemInfo.database} />
                            <InfoCard label="Memory Limit" value={systemInfo.memory_limit} />
                            <InfoCard label="Max Execution" value={systemInfo.max_execution_time + 's'} />
                            <InfoCard
                                label="Disk Space"
                                value={`${systemInfo.disk_space.free} / ${systemInfo.disk_space.total}`}
                            />
                        </div>

                        <div className="relative mt-4 flex gap-4">
                            <StatusBadge
                                label="Storage"
                                status={systemInfo.storage_writable}
                            />
                            <StatusBadge
                                label="Bootstrap Cache"
                                status={systemInfo.cache_writable}
                            />
                            <StatusBadge
                                label="Debug Mode"
                                status={systemInfo.debug_mode}
                                warning={systemInfo.environment === 'production' && systemInfo.debug_mode}
                            />
                        </div>
                    </div>

                    {/* Codex + Boost Diagnostics */}
                    <div className="relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-6 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden">
                        <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-violet-500/80 via-purple-500/80 to-indigo-500/80" />
                        <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />
                        <h3 className="relative text-lg font-bold text-zinc-100 mb-4">Codex + Boost</h3>
                        <div className="relative grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <InfoCard label="MCP Mode" value={mcpDetails.mcp_mode ?? 'Unknown'} />
                            <InfoCard label="MCP Version" value={mcpDetails.version ?? 'Unknown'} />
                            <InfoCard
                                label="Vector Search"
                                value={mcpDetails.vector_search ? 'Enabled' : 'Unavailable'}
                            />
                            <InfoCard label="Boost Agents" value={boostAgents} />
                            <InfoCard label="Boost Editors" value={boostEditors} />
                            <InfoCard
                                label="Boost Config"
                                value={systemInfo.boost?.present ? 'Loaded' : 'Missing'}
                            />
                        </div>
                        <div className="relative mt-4 flex flex-wrap gap-4">
                            <StatusBadge
                                label="Boost Config"
                                status={systemInfo.boost?.present && !systemInfo.boost?.error}
                            />
                            <StatusBadge
                                label="MCP Health"
                                status={systemInfo.mcp?.ok}
                            />
                        </div>
                        {systemInfo.boost?.error && (
                            <div className="relative mt-4 text-sm text-red-400 font-mono">
                                Boost config error: {systemInfo.boost.error}
                            </div>
                        )}
                    </div>

                    {/* Codex Invocation */}
                    <div className="relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-6 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden">
                        <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-cyan-500/80 via-teal-500/80 to-emerald-500/80" />
                        <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />
                        <h3 className="relative text-lg font-bold text-zinc-100 mb-4">Invoke Codex Agent</h3>
                        <p className="relative text-sm text-zinc-500 mb-4">
                            Send a prompt to the Codex AI agent for diagnostics, analysis, or recommendations.
                        </p>
                        <form onSubmit={invokeCodex} className="relative space-y-4">
                            <textarea
                                value={codexPrompt}
                                onChange={(e) => setCodexPrompt(e.target.value)}
                                placeholder="Enter your prompt for Codex (e.g., 'Analyze the current system state and provide recommendations')..."
                                rows={3}
                                className="w-full bg-zinc-950/60 border border-white/10 rounded-xl px-4 py-3 text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:ring-2 focus:ring-cyan-500/40 resize-none"
                            />
                            <div className="flex flex-col sm:flex-row gap-3">
                                <button
                                    type="submit"
                                    disabled={invokingCodex || !openaiStatus.isSet}
                                    className="px-5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed transition-all flex items-center justify-center gap-2 shadow-[0_0_20px_rgba(6,182,212,0.2)]"
                                >
                                    {invokingCodex ? (
                                        <>
                                            <svg className="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Invoking Codex...
                                        </>
                                    ) : (
                                        <>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 2a10 10 0 1 0 10 10H12V2Z"/><path d="M12 12 4.5 19.5"/><path d="M21 21l-3-3"/></svg>
                                            Invoke Codex
                                        </>
                                    )}
                                </button>
                                {!openaiStatus.isSet && (
                                    <span className="text-sm text-yellow-400 flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                        Set an OpenAI key below to enable Codex
                                    </span>
                                )}
                            </div>
                        </form>
                    </div>

                    {/* Diagnostic Actions */}
                    <div className="relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-6 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden">
                        <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-emerald-500/80 via-teal-500/80 to-cyan-500/80" />
                        <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />
                        <h3 className="relative text-lg font-bold text-zinc-100 mb-4">Diagnostic Actions</h3>
                        <div className="relative grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            {actions.map((action) => (
                                <ActionButton
                                    key={action.id}
                                    action={action}
                                    onClick={() => runAction(action.id, action.label)}
                                    disabled={loading}
                                    active={activeAction === action.id}
                                />
                            ))}
                        </div>
                    </div>

                    {/* OpenAI Service Key */}
                    <div className="relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-6 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden">
                        <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-indigo-500/80 via-blue-500/80 to-cyan-500/80" />
                        <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />
                        <h3 className="relative text-lg font-bold text-zinc-100 mb-4">Codex/Boost Service Key</h3>
                        <p className="relative text-sm text-zinc-500 mb-4">
                            This single admin key is used for Codex/Boost diagnostics and repair tasks.
                        </p>
                        <div className="relative flex items-center gap-3 mb-4">
                            <StatusBadge
                                label="OpenAI Key"
                                status={openaiStatus.isSet}
                            />
                            {openaiStatus.isSet && openaiStatus.last4 && (
                                <div className="text-xs text-zinc-500 font-mono">
                                    Last 4: {openaiStatus.last4}
                                </div>
                            )}
                        </div>
                        <form onSubmit={saveOpenAiKey} className="relative flex flex-col md:flex-row gap-3">
                            <input
                                type="password"
                                value={openaiKey}
                                onChange={(event) => setOpenaiKey(event.target.value)}
                                placeholder="sk-..."
                                className="flex-1 bg-zinc-950/60 border border-white/10 rounded-xl px-4 py-2 text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                            />
                            <button
                                type="submit"
                                disabled={savingKey || openaiKey.length === 0}
                                className="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                {savingKey ? 'Saving...' : 'Save Key'}
                            </button>
                        </form>
                        <div className="relative mt-4 flex flex-col sm:flex-row gap-3">
                            <button
                                type="button"
                                onClick={testOpenAiKey}
                                disabled={testingKey || (!openaiStatus.isSet && openaiKey.length === 0)}
                                className="px-4 py-2 rounded-xl bg-emerald-600/90 hover:bg-emerald-500 text-white text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                {testingKey ? 'Testing...' : 'Test Key'}
                            </button>
                            <button
                                type="button"
                                onClick={clearOpenAiKey}
                                disabled={clearingKey || !openaiStatus.isSet}
                                className="px-4 py-2 rounded-xl bg-red-600/90 hover:bg-red-500 text-white text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                {clearingKey ? 'Clearing...' : 'Clear Key'}
                            </button>
                        </div>
                    </div>

                    {/* Output Console */}
                    {output && (
                        <div className="relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-6 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden">
                            <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-zinc-500/50 via-zinc-400/50 to-zinc-500/50" />
                            <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />
                            <h3 className="relative text-lg font-bold text-zinc-100 mb-4">Output</h3>
                            <pre className="relative bg-zinc-950/80 text-zinc-300 p-4 rounded-xl font-mono text-sm overflow-x-auto whitespace-pre-wrap scrollbar-dark border border-white/[0.05]">
                                {output}
                            </pre>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function InfoCard({ label, value }) {
    return (
        <div className="bg-zinc-900/40 backdrop-blur-sm rounded-xl p-4 border border-white/[0.06] transition-all hover:bg-zinc-900/50 hover:border-white/[0.1]">
            <div className="text-xs text-zinc-500 mb-1 font-medium uppercase tracking-wide">{label}</div>
            <div className="text-sm font-semibold text-zinc-200">{value}</div>
        </div>
    );
}

function StatusBadge({ label, status, warning = false }) {
    const color = warning
        ? 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20'
        : status
            ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
            : 'bg-red-500/10 text-red-400 border-red-500/20';

    return (
        <div className={`inline-flex items-center gap-2 px-3 py-1 rounded-full border text-xs font-medium ${color}`}>
            <span>{status ? '✓' : '✗'}</span>
            <span>{label}</span>
        </div>
    );
}

function ActionButton({ action, onClick, disabled, active }) {
    const colorClasses = {
        blue: 'after:from-blue-500/80 after:to-cyan-500/80 bg-blue-500/5 border-blue-500/20 hover:border-blue-500/40 hover:bg-blue-500/10',
        purple: 'after:from-purple-500/80 after:to-pink-500/80 bg-purple-500/5 border-purple-500/20 hover:border-purple-500/40 hover:bg-purple-500/10',
        orange: 'after:from-orange-500/80 after:to-red-500/80 bg-orange-500/5 border-orange-500/20 hover:border-orange-500/40 hover:bg-orange-500/10',
        yellow: 'after:from-yellow-500/80 after:to-orange-500/80 bg-yellow-500/5 border-yellow-500/20 hover:border-yellow-500/40 hover:bg-yellow-500/10',
        cyan: 'after:from-cyan-500/80 after:to-blue-500/80 bg-cyan-500/5 border-cyan-500/20 hover:border-cyan-500/40 hover:bg-cyan-500/10',
        emerald: 'after:from-emerald-500/80 after:to-teal-500/80 bg-emerald-500/5 border-emerald-500/20 hover:border-emerald-500/40 hover:bg-emerald-500/10',
        pink: 'after:from-pink-500/80 after:to-rose-500/80 bg-pink-500/5 border-pink-500/20 hover:border-pink-500/40 hover:bg-pink-500/10',
        indigo: 'after:from-indigo-500/80 after:to-purple-500/80 bg-indigo-500/5 border-indigo-500/20 hover:border-indigo-500/40 hover:bg-indigo-500/10',
    };

    return (
        <button
            onClick={onClick}
            disabled={disabled}
            className={`
                relative p-4 rounded-xl border backdrop-blur-sm transition-all duration-300 overflow-hidden
                after:absolute after:bottom-0 after:left-0 after:right-0 after:h-[2px] after:bg-gradient-to-r
                ${colorClasses[action.color]}
                ${active ? 'scale-95 opacity-50' : 'hover:scale-[1.02]'}
                ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}
                disabled:hover:scale-100
            `}
        >
            <div className="text-2xl mb-2">{action.icon}</div>
            <div className="text-sm font-semibold text-zinc-100">{action.label}</div>
            {active && <div className="text-xs text-zinc-500 mt-1">Running...</div>}
        </button>
    );
}
