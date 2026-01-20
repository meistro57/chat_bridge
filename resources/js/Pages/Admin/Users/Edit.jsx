import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';

export default function Edit({ auth, user }) {
    const { data, setData, put, processing, errors } = useForm({
        name: user.name,
        email: user.email,
        password: '',
        role: user.role,
        is_active: user.is_active,
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.users.update', user.id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-zinc-100 leading-tight">Edit User: {user.name}</h2>}
        >
            <Head title={`Edit ${user.name}`} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-zinc-900 overflow-hidden shadow-xl sm:rounded-lg border border-zinc-700/50">
                        <div className="p-6 text-zinc-100">
                            <form onSubmit={submit}>
                                <div>
                                    <InputLabel htmlFor="name" value="Name" className="text-zinc-300" />
                                    <TextInput
                                        id="name"
                                        className="mt-1 block w-full bg-zinc-800 border-zinc-700 text-white focus:border-indigo-500 focus:ring-indigo-500"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        required
                                        isFocused
                                        autoComplete="name"
                                    />
                                    <InputError className="mt-2" message={errors.name} />
                                </div>

                                <div className="mt-4">
                                    <InputLabel htmlFor="email" value="Email" className="text-zinc-300" />
                                    <TextInput
                                        id="email"
                                        type="email"
                                        className="mt-1 block w-full bg-zinc-800 border-zinc-700 text-white focus:border-indigo-500 focus:ring-indigo-500"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        required
                                        autoComplete="username"
                                    />
                                    <InputError className="mt-2" message={errors.email} />
                                </div>

                                <div className="mt-4">
                                    <InputLabel htmlFor="password" value="Password (leave blank to keep current)" className="text-zinc-300" />
                                    <TextInput
                                        id="password"
                                        type="password"
                                        className="mt-1 block w-full bg-zinc-800 border-zinc-700 text-white focus:border-indigo-500 focus:ring-indigo-500"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        autoComplete="new-password"
                                    />
                                    <InputError className="mt-2" message={errors.password} />
                                </div>

                                <div className="mt-4">
                                    <InputLabel htmlFor="role" value="Role" className="text-zinc-300" />
                                    <select
                                        id="role"
                                        className="mt-1 block w-full bg-zinc-800 border-zinc-700 text-white rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={data.role}
                                        onChange={(e) => setData('role', e.target.value)}
                                    >
                                        <option value="user">User</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    <InputError className="mt-2" message={errors.role} />
                                </div>

                                <div className="flex items-center justify-end mt-6">
                                    <Link
                                        href={route('admin.users.index')}
                                        className="text-sm text-zinc-400 hover:text-zinc-200 underline mr-4"
                                    >
                                        Cancel
                                    </Link>
                                    <PrimaryButton className="bg-indigo-600 hover:bg-indigo-500" disabled={processing}>
                                        Update User
                                    </PrimaryButton>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
