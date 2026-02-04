import { GlassCard } from '@/Components/ui/GlassCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import NotificationPreferencesForm from './Partials/NotificationPreferencesForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import UsageStatsCard from './Partials/UsageStatsCard';

export default function Edit({ mustVerifyEmail, status, stats, notificationPreferences }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-zinc-100">
                    Profile
                </h2>
            }
        >
            <Head title="Profile" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {/* Usage Statistics */}
                    <UsageStatsCard stats={stats} />

                    <GlassCard accent="indigo" className="sm:p-8">
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            className="max-w-xl"
                        />
                    </GlassCard>

                    <GlassCard accent="cyan" className="sm:p-8">
                        <NotificationPreferencesForm
                            preferences={notificationPreferences}
                            className="max-w-xl"
                        />
                    </GlassCard>

                    <GlassCard accent="violet" className="sm:p-8">
                        <UpdatePasswordForm className="max-w-xl" />
                    </GlassCard>

                    {/* Quick Links */}
                    <GlassCard accent="emerald" className="sm:p-8">
                        <section className="max-w-xl">
                            <header>
                                <h2 className="text-lg font-medium text-zinc-100">
                                    Quick Links
                                </h2>
                                <p className="mt-1 text-sm text-zinc-400">
                                    Manage your resources from here.
                                </p>
                            </header>
                            <div className="mt-6 flex flex-wrap gap-3">
                                <Link
                                    href={route('api-keys.index')}
                                    className="inline-flex items-center gap-2 px-4 py-2 bg-zinc-800/50 border border-zinc-700/50 rounded-lg text-sm text-zinc-300 hover:bg-zinc-700/50 hover:text-white transition-colors"
                                >
                                    API Keys
                                </Link>
                                <Link
                                    href={route('personas.index')}
                                    className="inline-flex items-center gap-2 px-4 py-2 bg-zinc-800/50 border border-zinc-700/50 rounded-lg text-sm text-zinc-300 hover:bg-zinc-700/50 hover:text-white transition-colors"
                                >
                                    My Personas
                                </Link>
                                <Link
                                    href={route('chat.index')}
                                    className="inline-flex items-center gap-2 px-4 py-2 bg-zinc-800/50 border border-zinc-700/50 rounded-lg text-sm text-zinc-300 hover:bg-zinc-700/50 hover:text-white transition-colors"
                                >
                                    Conversations
                                </Link>
                                <Link
                                    href={route('analytics.index')}
                                    className="inline-flex items-center gap-2 px-4 py-2 bg-zinc-800/50 border border-zinc-700/50 rounded-lg text-sm text-zinc-300 hover:bg-zinc-700/50 hover:text-white transition-colors"
                                >
                                    Analytics
                                </Link>
                            </div>
                        </section>
                    </GlassCard>

                    <GlassCard accent="red" className="sm:p-8">
                        <DeleteUserForm className="max-w-xl" />
                    </GlassCard>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
