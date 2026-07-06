import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { GlassCard } from '@/Components/ui/GlassCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

const CLEAR_CHATS_CONFIRMATION_PHRASE = 'DELETE ALL CHATS';

export default function Backup({ backups = [], conversationsCount = 0 }) {
    const [confirmingClearChats, setConfirmingClearChats] = useState(false);

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        confirmation: '',
    });

    const confirmClearChats = () => {
        setConfirmingClearChats(true);
    };

    const closeClearChatsModal = () => {
        setConfirmingClearChats(false);

        clearErrors();
        reset();
    };

    const clearChats = (e) => {
        e.preventDefault();

        destroy(route('admin.database.chats.clear'), {
            preserveScroll: true,
            onSuccess: () => closeClearChatsModal(),
            onFinish: () => reset(),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Database Backup" />

            <div className="min-h-screen p-6 text-zinc-100 md:p-12">
                <div className="mx-auto max-w-5xl space-y-8">
                    <div className="space-y-2">
                        <h1 className="text-4xl font-bold text-zinc-100">
                            Database Backup
                        </h1>
                        <p className="text-zinc-400">
                            Use these commands to create a PostgreSQL backup
                            from the running Docker services.
                        </p>
                    </div>

                    <GlassCard accent="emerald" className="space-y-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <h2 className="text-lg font-semibold text-zinc-100">
                                Backup Command
                            </h2>
                            <form method="post" action={route('admin.database.backup.run')}>
                                <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content} />
                                <button
                                    type="submit"
                                    className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm font-medium text-emerald-200 transition hover:bg-emerald-500/20"
                                >
                                    Run Backup Now
                                </button>
                            </form>
                        </div>
                        <p className="text-sm text-zinc-400">
                            Run this from the project root on the host machine.
                        </p>
                        <pre className="overflow-x-auto rounded-xl border border-white/10 bg-zinc-950/80 p-4 text-sm text-zinc-200">
                            <code>
                                docker compose exec postgres pg_dump -U
                                chatbridge chatbridge &gt;
                                storage/app/backups/backup.sql
                            </code>
                        </pre>
                    </GlassCard>

                    <GlassCard accent="indigo" className="space-y-4">
                        <h2 className="text-lg font-semibold text-zinc-100">
                            Existing Backups
                        </h2>
                        {backups.length === 0 ? (
                            <p className="text-sm text-zinc-400">
                                No backups found in
                                <span className="mx-1 rounded bg-white/5 px-1.5 py-0.5 font-mono text-xs text-zinc-200">
                                    storage/app/backups
                                </span>
                                .
                            </p>
                        ) : (
                            <div className="overflow-hidden rounded-xl border border-white/10">
                                <table className="min-w-full divide-y divide-white/10 text-sm">
                                    <thead className="bg-white/5 text-left text-xs uppercase tracking-wider text-zinc-400">
                                        <tr>
                                            <th className="px-4 py-3">Filename</th>
                                            <th className="px-4 py-3">Size</th>
                                            <th className="px-4 py-3">Modified</th>
                                            <th className="px-4 py-3 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-white/5">
                                        {backups.map((backup) => (
                                            <tr key={backup.filename} className="bg-zinc-950/40">
                                                <td className="px-4 py-3 font-mono text-xs text-zinc-200">
                                                    {backup.filename}
                                                </td>
                                                <td className="px-4 py-3 text-zinc-300">
                                                    {backup.size_human}
                                                </td>
                                                <td className="px-4 py-3 text-zinc-400">
                                                    {new Date(backup.modified_at).toLocaleString()}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <a
                                                        href={route('admin.database.backups.download', backup.filename)}
                                                        className="rounded-lg border border-indigo-500/30 bg-indigo-500/10 px-3 py-1.5 text-xs font-medium text-indigo-200 transition hover:bg-indigo-500/20"
                                                    >
                                                        Download
                                                    </a>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </GlassCard>

                    <GlassCard accent="cyan" className="space-y-4">
                        <h2 className="text-lg font-semibold text-zinc-100">
                            Notes
                        </h2>
                        <ul className="list-disc space-y-2 pl-5 text-sm text-zinc-300">
                            <li>
                                This creates a plain SQL dump inside
                                <span className="mx-1 rounded bg-white/5 px-1.5 py-0.5 font-mono text-xs text-zinc-200">
                                    storage/app/backups
                                </span>
                                that will appear here and on the restore page.
                            </li>
                            <li>
                                Consider storing backups outside the repository
                                directory.
                            </li>
                        </ul>
                    </GlassCard>

                    <GlassCard accent="red" className="space-y-4">
                        <h2 className="text-lg font-semibold text-zinc-100">
                            Danger Zone
                        </h2>
                        <p className="text-sm text-zinc-400">
                            Permanently delete all conversations and messages
                            from the database (
                            {conversationsCount}{' '}
                            {conversationsCount === 1
                                ? 'conversation'
                                : 'conversations'}{' '}
                            currently stored). This cannot be undone. Take a
                            backup first if you may need this data later.
                        </p>
                        <DangerButton onClick={confirmClearChats}>
                            Clear All Chats
                        </DangerButton>
                    </GlassCard>
                </div>
            </div>

            <Modal show={confirmingClearChats} onClose={closeClearChatsModal}>
                <form onSubmit={clearChats} className="p-6">
                    <h2 className="text-lg font-medium text-zinc-100">
                        Are you sure you want to clear all chats?
                    </h2>

                    <p className="mt-1 text-sm text-zinc-400">
                        This will permanently delete all{' '}
                        {conversationsCount}{' '}
                        {conversationsCount === 1
                            ? 'conversation'
                            : 'conversations'}{' '}
                        and their messages. This action cannot be undone.
                        Type{' '}
                        <span className="font-mono text-zinc-200">
                            {CLEAR_CHATS_CONFIRMATION_PHRASE}
                        </span>{' '}
                        below to confirm.
                    </p>

                    <div className="mt-6">
                        <TextInput
                            id="confirmation"
                            type="text"
                            name="confirmation"
                            value={data.confirmation}
                            onChange={(e) =>
                                setData('confirmation', e.target.value)
                            }
                            className="mt-1 block w-full"
                            isFocused
                            placeholder={CLEAR_CHATS_CONFIRMATION_PHRASE}
                            autoComplete="off"
                        />

                        <InputError
                            message={errors.confirmation}
                            className="mt-2"
                        />
                    </div>

                    <div className="mt-6 flex justify-end">
                        <SecondaryButton onClick={closeClearChatsModal}>
                            Cancel
                        </SecondaryButton>

                        <DangerButton
                            className="ms-3"
                            disabled={
                                processing ||
                                data.confirmation !==
                                    CLEAR_CHATS_CONFIRMATION_PHRASE
                            }
                        >
                            Clear All Chats
                        </DangerButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
