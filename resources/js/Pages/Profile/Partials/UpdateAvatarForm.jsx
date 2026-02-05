import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import { Transition } from '@headlessui/react';
import { useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

export default function UpdateAvatarForm({ className = '' }) {
    const user = usePage().props.auth.user;
    const [previewUrl, setPreviewUrl] = useState(user.avatar_url);

    const { data, setData, patch, processing, errors, reset, recentlySuccessful } = useForm({
        avatar: null,
    });

    const deleteForm = useForm({});

    const initials = useMemo(() => {
        if (!user?.name) {
            return 'U';
        }

        return user.name
            .split(' ')
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0].toUpperCase())
            .join('');
    }, [user?.name]);

    useEffect(() => {
        if (!data.avatar) {
            setPreviewUrl(user.avatar_url);
            return undefined;
        }

        const objectUrl = URL.createObjectURL(data.avatar);
        setPreviewUrl(objectUrl);

        return () => URL.revokeObjectURL(objectUrl);
    }, [data.avatar, user.avatar_url]);

    const submit = (event) => {
        event.preventDefault();

        patch(route('profile.avatar.update'), {
            forceFormData: true,
            onSuccess: () => reset('avatar'),
        });
    };

    const removeAvatar = () => {
        deleteForm.delete(route('profile.avatar.destroy'));
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-zinc-100">Avatar</h2>
                <p className="mt-1 text-sm text-zinc-400">
                    Upload a square image (max 2MB) to personalize your profile.
                </p>
            </header>

            <div className="mt-6 flex flex-col gap-6 md:flex-row md:items-center">
                <div className="flex items-center gap-4">
                    {previewUrl ? (
                        <img
                            src={previewUrl}
                            alt="Avatar preview"
                            className="h-20 w-20 rounded-2xl object-cover border border-white/10"
                        />
                    ) : (
                        <div className="h-20 w-20 rounded-2xl border border-white/10 bg-zinc-800/50 flex items-center justify-center text-lg font-semibold text-zinc-300">
                            {initials}
                        </div>
                    )}
                    <div>
                        <p className="text-sm text-zinc-300">Current avatar</p>
                        <p className="text-xs text-zinc-500">PNG, JPG, or WebP</p>
                    </div>
                </div>

                <form onSubmit={submit} className="flex-1 space-y-4">
                    <div>
                        <label className="text-xs uppercase tracking-wider text-zinc-400">Upload new</label>
                        <input
                            type="file"
                            accept="image/*"
                            className="mt-2 block w-full rounded-xl border border-white/10 bg-zinc-900/60 px-4 py-2 text-sm text-zinc-100 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-500/20 file:px-3 file:py-1 file:text-xs file:uppercase file:tracking-widest file:text-indigo-200"
                            onChange={(event) => setData('avatar', event.target.files[0])}
                        />
                        <InputError className="mt-2" message={errors.avatar} />
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <PrimaryButton disabled={processing || deleteForm.processing}>
                            {processing ? 'Uploading...' : 'Save Avatar'}
                        </PrimaryButton>
                        <button
                            type="button"
                            onClick={removeAvatar}
                            disabled={deleteForm.processing || !user.avatar_url}
                            className="rounded-xl border border-red-500/40 px-4 py-2 text-sm text-red-200 hover:bg-red-500/10 disabled:opacity-40"
                        >
                            {deleteForm.processing ? 'Removing...' : 'Remove'}
                        </button>

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
            </div>
        </section>
    );
}
