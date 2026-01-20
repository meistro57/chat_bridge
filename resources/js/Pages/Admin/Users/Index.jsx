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

                    <div className="bg-zinc-900 overflow-hidden shadow-xl sm:rounded-lg border border-zinc-700/50">
                        <div className="p-6 text-zinc-100">
                            <table className="min-w-full divide-y divide-zinc-700">
                                <thead>
                                    <tr>
                                        <th className="px-6 py-3 bg-zinc-800 text-left text-xs leading-4 font-medium text-zinc-400 uppercase tracking-wider">Name</th>
                                        <th className="px-6 py-3 bg-zinc-800 text-left text-xs leading-4 font-medium text-zinc-400 uppercase tracking-wider">Email</th>
                                        <th className="px-6 py-3 bg-zinc-800 text-left text-xs leading-4 font-medium text-zinc-400 uppercase tracking-wider">Role</th>
                                        <th className="px-6 py-3 bg-zinc-800 text-left text-xs leading-4 font-medium text-zinc-400 uppercase tracking-wider">Stats</th>
                                        <th className="px-6 py-3 bg-zinc-800 text-left text-xs leading-4 font-medium text-zinc-400 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-zinc-900 divide-y divide-zinc-800">
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
