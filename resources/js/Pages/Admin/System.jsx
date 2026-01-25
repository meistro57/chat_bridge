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
                    <div className="glass-panel rounded-xl p-6 border border-white/5">
                        <h3 className="text-lg font-bold text-zinc-100 mb-4">System Information</h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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

                        <div className="mt-4 flex gap-4">
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
                    <div className="glass-panel rounded-xl p-6 border border-white/5">
                        <h3 className="text-lg font-bold text-zinc-100 mb-4">Codex + Boost</h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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
                        <div className="mt-4 flex flex-wrap gap-4">
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
                            <div className="mt-4 text-sm text-red-400 font-mono">
                                Boost config error: {systemInfo.boost.error}
                            </div>
                        )}
                    </div>

                    {/* Diagnostic Actions */}
                    <div className="glass-panel rounded-xl p-6 border border-white/5">
                        <h3 className="text-lg font-bold text-zinc-100 mb-4">Diagnostic Actions</h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
                    <div className="glass-panel rounded-xl p-6 border border-white/5">
                        <h3 className="text-lg font-bold text-zinc-100 mb-4">Codex/Boost Service Key</h3>
                        <p className="text-sm text-zinc-500 mb-4">
                            This single admin key is used for Codex/Boost diagnostics and repair tasks.
                        </p>
                        <div className="flex items-center gap-3 mb-4">
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
                        <form onSubmit={saveOpenAiKey} className="flex flex-col md:flex-row gap-3">
                            <input
                                type="password"
                                value={openaiKey}
                                onChange={(event) => setOpenaiKey(event.target.value)}
                                placeholder="sk-..."
                                className="flex-1 bg-zinc-950/60 border border-white/10 rounded-lg px-4 py-2 text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                            />
                            <button
                                type="submit"
                                disabled={savingKey || openaiKey.length === 0}
                                className="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {savingKey ? 'Saving...' : 'Save Key'}
                            </button>
                        </form>
                        <div className="mt-4 flex flex-col sm:flex-row gap-3">
                            <button
                                type="button"
                                onClick={testOpenAiKey}
                                disabled={testingKey || (!openaiStatus.isSet && openaiKey.length === 0)}
                                className="px-4 py-2 rounded-lg bg-emerald-600/90 hover:bg-emerald-500 text-white text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {testingKey ? 'Testing...' : 'Test Key'}
                            </button>
                            <button
                                type="button"
                                onClick={clearOpenAiKey}
                                disabled={clearingKey || !openaiStatus.isSet}
                                className="px-4 py-2 rounded-lg bg-red-600/90 hover:bg-red-500 text-white text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {clearingKey ? 'Clearing...' : 'Clear Key'}
                            </button>
                        </div>
                    </div>

                    {/* Output Console */}
                    {output && (
                        <div className="glass-panel rounded-xl p-6 border border-white/5">
                            <h3 className="text-lg font-bold text-zinc-100 mb-4">Output</h3>
                            <pre className="bg-zinc-950 text-zinc-300 p-4 rounded-lg font-mono text-sm overflow-x-auto whitespace-pre-wrap scrollbar-dark">
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
        <div className="bg-zinc-900/30 rounded-lg p-3 border border-white/5">
            <div className="text-xs text-zinc-500 mb-1">{label}</div>
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
        blue: 'from-blue-500 to-cyan-500 bg-blue-500/10 border-blue-500/20 hover:border-blue-500/50',
        purple: 'from-purple-500 to-pink-500 bg-purple-500/10 border-purple-500/20 hover:border-purple-500/50',
        orange: 'from-orange-500 to-red-500 bg-orange-500/10 border-orange-500/20 hover:border-orange-500/50',
        yellow: 'from-yellow-500 to-orange-500 bg-yellow-500/10 border-yellow-500/20 hover:border-yellow-500/50',
        cyan: 'from-cyan-500 to-blue-500 bg-cyan-500/10 border-cyan-500/20 hover:border-cyan-500/50',
        emerald: 'from-emerald-500 to-teal-500 bg-emerald-500/10 border-emerald-500/20 hover:border-emerald-500/50',
        pink: 'from-pink-500 to-rose-500 bg-pink-500/10 border-pink-500/20 hover:border-pink-500/50',
        indigo: 'from-indigo-500 to-purple-500 bg-indigo-500/10 border-indigo-500/20 hover:border-indigo-500/50',
    };

    return (
        <button
            onClick={onClick}
            disabled={disabled}
            className={`
                p-4 rounded-xl border transition-all
                ${colorClasses[action.color]}
                ${active ? 'scale-95 opacity-50' : 'hover:scale-105'}
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
