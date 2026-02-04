import PrimaryButton from '@/Components/PrimaryButton';
import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';

export default function NotificationPreferencesForm({
    preferences,
    className = '',
}) {
    const { data, setData, patch, processing, recentlySuccessful } = useForm({
        conversation_completed: preferences?.conversation_completed ?? true,
        conversation_failed: preferences?.conversation_failed ?? true,
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.notifications'));
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-zinc-100">
                    Notification Preferences
                </h2>

                <p className="mt-1 text-sm text-zinc-400">
                    Choose which email notifications you want to receive.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-4">
                <label className="flex items-center gap-3 cursor-pointer group">
                    <input
                        type="checkbox"
                        checked={data.conversation_completed}
                        onChange={(e) => setData('conversation_completed', e.target.checked)}
                        className="w-4 h-4 rounded border-zinc-600 bg-zinc-800 text-indigo-500 focus:ring-indigo-500/50 focus:ring-offset-0"
                    />
                    <div>
                        <div className="text-sm text-zinc-200 group-hover:text-white transition-colors">
                            Conversation completed
                        </div>
                        <div className="text-xs text-zinc-500">
                            Get notified when a conversation finishes successfully.
                        </div>
                    </div>
                </label>

                <label className="flex items-center gap-3 cursor-pointer group">
                    <input
                        type="checkbox"
                        checked={data.conversation_failed}
                        onChange={(e) => setData('conversation_failed', e.target.checked)}
                        className="w-4 h-4 rounded border-zinc-600 bg-zinc-800 text-indigo-500 focus:ring-indigo-500/50 focus:ring-offset-0"
                    />
                    <div>
                        <div className="text-sm text-zinc-200 group-hover:text-white transition-colors">
                            Conversation failed
                        </div>
                        <div className="text-xs text-zinc-500">
                            Get notified when a conversation encounters an error.
                        </div>
                    </div>
                </label>

                <div className="flex items-center gap-4 pt-2">
                    <PrimaryButton disabled={processing}>
                        Save Preferences
                    </PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-zinc-400">Saved.</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
