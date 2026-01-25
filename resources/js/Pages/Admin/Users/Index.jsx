import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Index({ auth, users }) {
    const { flash } = usePage().props;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-zinc-100 leading-tight">User Management</h2>}
        >
            <Head title="Users" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    
                    {flash.success && (
                        <div className="mb-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded relative" role="alert">
                            <span className="block sm:inline">{flash.success}</span>
                        </div>
                    )}

                    <div className="flex justify-end mb-6">
                        <Link
                            href={route('admin.users.create')}
                            className="bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-2 px-4 rounded shadow-lg transition duration-150 ease-in-out"
                        >
                            Create New User
                        </Link>
                    </div>

                    <div className="relative bg-zinc-900/50 backdrop-blur-2xl overflow-hidden rounded-2xl border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)]">
                        <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-red-500/80 via-pink-500/80 to-purple-500/80" />
                        <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />
                        <div className="relative p-6 text-zinc-100">
                            <table className="min-w-full divide-y divide-zinc-700/50">
                                <thead>
                                    <tr>
                                        <th className="px-6 py-3 bg-zinc-800/50 backdrop-blur-sm text-left text-xs leading-4 font-medium text-zinc-400 uppercase tracking-wider rounded-tl-lg">Name</th>
                                        <th className="px-6 py-3 bg-zinc-800/50 backdrop-blur-sm text-left text-xs leading-4 font-medium text-zinc-400 uppercase tracking-wider">Email</th>
                                        <th className="px-6 py-3 bg-zinc-800/50 backdrop-blur-sm text-left text-xs leading-4 font-medium text-zinc-400 uppercase tracking-wider">Role</th>
                                        <th className="px-6 py-3 bg-zinc-800/50 backdrop-blur-sm text-left text-xs leading-4 font-medium text-zinc-400 uppercase tracking-wider">Stats</th>
                                        <th className="px-6 py-3 bg-zinc-800/50 backdrop-blur-sm text-left text-xs leading-4 font-medium text-zinc-400 uppercase tracking-wider rounded-tr-lg">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-transparent divide-y divide-zinc-800/50">
                                    {users.map((user) => (
                                        <tr key={user.id}>
                                            <td className="px-6 py-4 whitespace-no-wrap">
                                                <div className="text-sm leading-5 font-medium text-white">{user.name}</div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-no-wrap">
                                                <div className="text-sm leading-5 text-zinc-400">{user.email}</div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-no-wrap">
                                                <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                                    user.role === 'admin' 
                                                    ? 'bg-purple-900 text-purple-200' 
                                                    : 'bg-zinc-700 text-zinc-300'
                                                }`}>
                                                    {user.role}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-zinc-400">
                                                <div>Personas: {user.personas_count}</div>
                                                <div>Chats: {user.conversations_count}</div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-no-wrap text-sm leading-5 font-medium">
                                                <Link
                                                    href={route('admin.users.edit', user.id)}
                                                    className="text-indigo-400 hover:text-indigo-300 mr-4"
                                                >
                                                    Edit
                                                </Link>
                                                {user.id !== auth.user.id && (
                                                    <Link
                                                        href={route('admin.users.destroy', user.id)}
                                                        method="delete"
                                                        as="button"
                                                        className="text-red-400 hover:text-red-300"
                                                    >
                                                        Delete
                                                    </Link>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
