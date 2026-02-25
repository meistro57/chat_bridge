import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Form, Head, Link } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';

function currentToken(value, cursorPosition) {
    const prefix = value.slice(0, cursorPosition);
    const match = prefix.match(/[A-Za-z0-9_.]+$/);

    return match ? match[0] : '';
}

function replaceCurrentToken(value, cursorPosition, replacement) {
    const prefix = value.slice(0, cursorPosition);
    const suffix = value.slice(cursorPosition);
    const match = prefix.match(/[A-Za-z0-9_.]+$/);

    if (!match) {
        return {
            nextValue: `${prefix}${replacement}${suffix}`,
            nextCursor: cursorPosition + replacement.length,
        };
    }

    const tokenStart = cursorPosition - match[0].length;
    const nextValue = `${value.slice(0, tokenStart)}${replacement}${suffix}`;

    return {
        nextValue,
        nextCursor: tokenStart + replacement.length,
    };
}

function hydrateTemplate(sql, userId) {
    return (sql ?? '').replaceAll('{{auth_user_id}}', String(userId ?? ''));
}

export default function Query({ results, filters, personas, sqlPlayground }) {
    const activeFilters = filters ?? {};
    const data = results?.data ?? [];
    const links = results?.links ?? [];
    const examples = sqlPlayground?.examples ?? [];
    const schema = sqlPlayground?.schema ?? [];
    const defaultLimit = sqlPlayground?.defaultLimit ?? 100;
    const currentUserId = sqlPlayground?.currentUserId ?? '';
    const editorRef = useRef(null);

    const [queryText, setQueryText] = useState(
        hydrateTemplate(examples[0]?.sql ?? 'SELECT * FROM conversations WHERE user_id = {{auth_user_id}} LIMIT 50', currentUserId),
    );
    const [rowLimit, setRowLimit] = useState(defaultLimit);
    const [sqlResult, setSqlResult] = useState(null);
    const [sqlError, setSqlError] = useState('');
    const [isExecuting, setIsExecuting] = useState(false);
    const [cursorPosition, setCursorPosition] = useState(0);

    const autocompleteValues = useMemo(() => {
        const tableNames = schema.map((table) => table.name);
        const columnNames = schema.flatMap((table) => table.columns.map((column) => column.name));
        const dottedNames = schema.flatMap((table) => table.columns.map((column) => `${table.name}.${column.name}`));
        const values = [...(sqlPlayground?.keywords ?? []), ...tableNames, ...columnNames, ...dottedNames];

        return [...new Set(values)]
            .filter((value) => value && typeof value === 'string')
            .sort((left, right) => left.localeCompare(right));
    }, [schema, sqlPlayground?.keywords]);

    const activeAutocompleteToken = useMemo(
        () => currentToken(queryText, cursorPosition).toLowerCase(),
        [queryText, cursorPosition],
    );

    const suggestions = useMemo(() => {
        if (activeAutocompleteToken.length < 2) {
            return [];
        }

        return autocompleteValues
            .filter((value) => value.toLowerCase().startsWith(activeAutocompleteToken) && value.toLowerCase() !== activeAutocompleteToken)
            .slice(0, 10);
    }, [autocompleteValues, activeAutocompleteToken]);

    const applySuggestion = (suggestion) => {
        if (!editorRef.current) {
            return;
        }

        const position = editorRef.current.selectionStart ?? cursorPosition;
        const { nextValue, nextCursor } = replaceCurrentToken(queryText, position, suggestion);

        setQueryText(nextValue);
        setCursorPosition(nextCursor);

        requestAnimationFrame(() => {
            if (!editorRef.current) {
                return;
            }

            editorRef.current.focus();
            editorRef.current.setSelectionRange(nextCursor, nextCursor);
        });
    };

    const runQuery = async () => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        setIsExecuting(true);
        setSqlError('');

        try {
            const response = await fetch(route('analytics.query.run-sql'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken ?? '',
                },
                body: JSON.stringify({
                    sql: queryText,
                    limit: Number(rowLimit) || defaultLimit,
                }),
            });

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload?.message ?? 'Unable to run SQL query.');
            }

            setSqlResult(payload);
        } catch (error) {
            setSqlResult(null);
            setSqlError(error.message);
        } finally {
            setIsExecuting(false);
        }
    };

    const handleEditorKeyDown = (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
            event.preventDefault();
            runQuery();

            return;
        }

        if ((event.key === 'Tab' || event.key === 'Enter') && suggestions.length > 0 && !event.shiftKey && !event.metaKey && !event.ctrlKey) {
            event.preventDefault();
            applySuggestion(suggestions[0]);
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Query Conversations" />

            <div className="min-h-screen p-6 text-zinc-100 md:p-12">
                <div className="mx-auto max-w-6xl space-y-8">
                    <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                        <div>
                            <Link href={route('analytics.index')} className="mb-2 block text-xs font-mono uppercase tracking-wide text-zinc-500 hover:text-white">
                                &larr; Analytics Overview
                            </Link>
                            <h1 className="bg-gradient-to-r from-white to-zinc-400 bg-clip-text text-4xl font-bold text-transparent">
                                Query Conversations
                            </h1>
                            <p className="mt-2 text-zinc-500">Run full SQL analytics with examples, autocomplete, and export-ready filters.</p>
                        </div>
                        <form method="post" action={route('analytics.export')} className="flex items-center gap-2">
                            <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content} />
                            {Object.entries(activeFilters).map(([key, value]) => (
                                value ? <input key={key} type="hidden" name={key} value={value} /> : null
                            ))}
                            <input type="hidden" name="format" value="csv" />
                            <button
                                type="submit"
                                className="flex items-center gap-2 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-200 hover:bg-emerald-500/20"
                            >
                                Export CSV
                            </button>
                        </form>
                    </div>

                    <div className="glass-panel glass-butter space-y-6 rounded-2xl border border-white/10 p-6">
                        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <h2 className="text-xl font-semibold text-white">SQL Playground</h2>
                            <div className="flex items-center gap-2 text-xs text-zinc-400">
                                <span>Rows</span>
                                <input
                                    type="number"
                                    min="1"
                                    max="500"
                                    value={rowLimit}
                                    onChange={(event) => setRowLimit(event.target.value)}
                                    className="w-24 rounded-lg border border-white/10 bg-zinc-900/70 px-3 py-1.5 text-sm text-zinc-100 outline-none focus:border-indigo-500/60 focus:ring-2 focus:ring-indigo-500/20"
                                />
                                <button
                                    type="button"
                                    onClick={runQuery}
                                    disabled={isExecuting}
                                    className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-70"
                                >
                                    {isExecuting ? 'Running...' : 'Run SQL'}
                                </button>
                            </div>
                        </div>

                        <div className="rounded-xl border border-white/10 bg-zinc-950/60 p-4">
                            <textarea
                                ref={editorRef}
                                value={queryText}
                                onChange={(event) => setQueryText(event.target.value)}
                                onKeyDown={handleEditorKeyDown}
                                onKeyUp={(event) => setCursorPosition(event.currentTarget.selectionStart ?? 0)}
                                onClick={(event) => setCursorPosition(event.currentTarget.selectionStart ?? 0)}
                                spellCheck={false}
                                className="h-56 w-full resize-y rounded-lg border border-white/10 bg-zinc-950/80 p-4 font-mono text-sm text-zinc-100 outline-none focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                            />
                            <div className="mt-3 flex flex-wrap items-center gap-2 text-xs">
                                <span className="text-zinc-500">Autocomplete</span>
                                <span className="text-zinc-500">Ctrl/Cmd+Enter runs query</span>
                                {suggestions.length > 0 ? (
                                    suggestions.map((suggestion) => (
                                        <button
                                            key={suggestion}
                                            type="button"
                                            onClick={() => applySuggestion(suggestion)}
                                            className="rounded-full border border-indigo-500/30 bg-indigo-500/10 px-2.5 py-1 font-mono text-indigo-200 hover:bg-indigo-500/20"
                                        >
                                            {suggestion}
                                        </button>
                                    ))
                                ) : (
                                    <span className="text-zinc-500">Type at least 2 characters to see suggestions.</span>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <div className="space-y-3 rounded-xl border border-white/10 bg-zinc-900/40 p-4">
                                <h3 className="text-sm font-semibold uppercase tracking-wider text-zinc-400">Examples</h3>
                                <div className="space-y-2">
                                    {examples.map((example) => (
                                        <button
                                            key={example.id}
                                            type="button"
                                            onClick={() => setQueryText(hydrateTemplate(example.sql, currentUserId))}
                                            className="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-left hover:border-indigo-500/40 hover:bg-indigo-500/10"
                                        >
                                            <div className="text-sm font-semibold text-zinc-200">{example.title}</div>
                                            <div className="text-xs text-zinc-500">{example.description}</div>
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <div className="space-y-3 rounded-xl border border-white/10 bg-zinc-900/40 p-4">
                                <h3 className="text-sm font-semibold uppercase tracking-wider text-zinc-400">Schema</h3>
                                <div className="max-h-64 space-y-2 overflow-y-auto pr-1">
                                    {schema.map((table) => (
                                        <details key={table.name} className="rounded-lg border border-white/10 bg-zinc-950/50 px-3 py-2">
                                            <summary className="cursor-pointer font-mono text-xs uppercase tracking-widest text-indigo-300">
                                                {table.name}
                                            </summary>
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                {table.columns.map((column) => (
                                                    <span key={`${table.name}.${column.name}`} className="rounded-full border border-white/10 bg-white/5 px-2 py-1 font-mono text-[11px] text-zinc-300">
                                                        {column.name} <span className="text-zinc-500">({column.type})</span>
                                                    </span>
                                                ))}
                                            </div>
                                        </details>
                                    ))}
                                </div>
                            </div>
                        </div>

                        {sqlError && (
                            <div className="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                                {sqlError}
                            </div>
                        )}

                        {sqlResult && (
                            <div className="space-y-3 rounded-xl border border-white/10 bg-zinc-950/60 p-4">
                                <div className="flex flex-wrap items-center gap-4 text-xs text-zinc-400">
                                    <span>{sqlResult.row_count} rows</span>
                                    <span>{sqlResult.execution_ms} ms</span>
                                    {sqlResult.truncated && <span className="text-amber-300">Showing first {sqlResult.limit} rows</span>}
                                </div>
                                <div className="overflow-x-auto rounded-lg border border-white/10">
                                    <table className="min-w-full border-collapse text-left text-sm">
                                        <thead className="bg-zinc-900/80 text-xs uppercase tracking-wider text-zinc-400">
                                            <tr>
                                                {sqlResult.columns.map((column) => (
                                                    <th key={column} className="border-b border-white/10 px-3 py-2 font-semibold">
                                                        {column}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {sqlResult.rows.length > 0 ? (
                                                sqlResult.rows.map((row, rowIndex) => (
                                                    <tr key={rowIndex} className="border-b border-white/5 text-zinc-200">
                                                        {sqlResult.columns.map((column) => (
                                                            <td key={`${rowIndex}-${column}`} className="max-w-[280px] px-3 py-2 align-top font-mono text-xs text-zinc-300">
                                                                {row[column] === null || row[column] === undefined ? 'NULL' : String(row[column])}
                                                            </td>
                                                        ))}
                                                    </tr>
                                                ))
                                            ) : (
                                                <tr>
                                                    <td colSpan={Math.max(sqlResult.columns.length, 1)} className="px-3 py-6 text-center text-sm text-zinc-500">
                                                        Query returned zero rows.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </div>

                    <Form action={route('analytics.query')} method="get" className="glass-panel glass-butter rounded-2xl border border-white/10 p-6">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-wider text-zinc-400">Keyword</label>
                                <input
                                    name="keyword"
                                    defaultValue={activeFilters.keyword ?? ''}
                                    className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 outline-none focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                    placeholder="Search message content"
                                />
                            </div>
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-wider text-zinc-400">Persona</label>
                                <select
                                    name="persona_id"
                                    defaultValue={activeFilters.persona_id ?? ''}
                                    className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 outline-none focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                >
                                    <option value="">All Personas</option>
                                    {personas.map((persona) => (
                                        <option key={persona.id} value={persona.id}>{persona.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-wider text-zinc-400">Role</label>
                                <select
                                    name="role"
                                    defaultValue={activeFilters.role ?? ''}
                                    className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 outline-none focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                >
                                    <option value="">Any Role</option>
                                    <option value="user">User</option>
                                    <option value="assistant">Assistant</option>
                                </select>
                            </div>
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-wider text-zinc-400">Status</label>
                                <select
                                    name="status"
                                    defaultValue={activeFilters.status ?? ''}
                                    className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 outline-none focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                >
                                    <option value="">Any Status</option>
                                    <option value="active">Active</option>
                                    <option value="completed">Completed</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-wider text-zinc-400">From</label>
                                <input
                                    type="date"
                                    name="date_from"
                                    defaultValue={activeFilters.date_from ?? ''}
                                    className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 outline-none focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                />
                            </div>
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-wider text-zinc-400">To</label>
                                <input
                                    type="date"
                                    name="date_to"
                                    defaultValue={activeFilters.date_to ?? ''}
                                    className="w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 outline-none focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                />
                            </div>
                        </div>
                        <div className="mt-4 flex flex-wrap items-center gap-3">
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-wider text-zinc-400">Sort</label>
                                <select
                                    name="sort_order"
                                    defaultValue={activeFilters.sort_order ?? 'desc'}
                                    className="rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 outline-none focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                >
                                    <option value="desc">Newest</option>
                                    <option value="asc">Oldest</option>
                                </select>
                            </div>
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-wider text-zinc-400">Per Page</label>
                                <select
                                    name="per_page"
                                    defaultValue={activeFilters.per_page ?? 20}
                                    className="rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 outline-none focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/20"
                                >
                                    {[10, 20, 50, 100].map((size) => (
                                        <option key={size} value={size}>{size}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="ml-auto flex items-center gap-3">
                                <Link
                                    href={route('analytics.query')}
                                    className="rounded-xl border border-white/10 px-4 py-2 text-sm text-zinc-300 hover:border-white/30 hover:text-white"
                                >
                                    Clear
                                </Link>
                                <button
                                    type="submit"
                                    className="rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                                >
                                    Search
                                </button>
                            </div>
                        </div>
                    </Form>

                    <div className="glass-panel glass-butter rounded-2xl border border-white/10 p-6">
                        <div className="flex items-center justify-between border-b border-white/5 pb-4 text-sm text-zinc-400">
                            <span>{results?.total ?? 0} results</span>
                            {activeFilters.keyword && (
                                <span className="font-mono text-xs uppercase tracking-widest text-zinc-500">
                                    Keyword: &quot;{activeFilters.keyword}&quot;
                                </span>
                            )}
                        </div>

                        <div className="mt-6 space-y-4">
                            {data.map((message) => (
                                <div key={message.id} className="rounded-2xl border border-white/5 bg-zinc-900/60 p-5">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div className="flex items-center gap-3">
                                            <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs uppercase tracking-wider text-zinc-300">
                                                {message.persona?.name ?? message.role}
                                            </span>
                                            <span className="text-xs text-zinc-500">
                                                {new Date(message.created_at).toLocaleString()}
                                            </span>
                                        </div>
                                        <Link
                                            href={route('chat.show', message.conversation_id)}
                                            className="text-xs font-semibold uppercase tracking-wider text-indigo-400 hover:text-indigo-300"
                                        >
                                            View Conversation
                                        </Link>
                                    </div>
                                    <p className="mt-4 whitespace-pre-wrap text-sm text-zinc-200">
                                        {message.content}
                                    </p>
                                </div>
                            ))}

                            {data.length === 0 && (
                                <div className="py-16 text-center text-zinc-500">
                                    No conversations matched those filters.
                                </div>
                            )}
                        </div>

                        {links.length > 1 && (
                            <div className="mt-6 flex flex-wrap items-center gap-2">
                                {links.map((link, index) => (
                                    <Link
                                        key={`${link.label}-${index}`}
                                        href={link.url || '#'}
                                        preserveScroll
                                        className={`rounded-lg border px-3 py-1 text-xs ${
                                            link.active
                                                ? 'border-indigo-500/60 bg-indigo-500/20 text-indigo-200'
                                                : 'border-white/10 text-zinc-400 hover:border-white/30 hover:text-white'
                                        } ${link.url ? '' : 'pointer-events-none opacity-40'}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
