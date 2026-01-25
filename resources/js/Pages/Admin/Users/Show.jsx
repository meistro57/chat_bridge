import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Show({ auth, user }) {
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-zinc-100 leading-tight">User Details: {user.name}</h2>}
        >
            <Head title={`User ${user.name}`} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    
                    {/* User Profile Info */}
                    <div className="bg-zinc-900 overflow-hidden shadow-xl sm:rounded-lg border border-zinc-700/50">
                        <div className="p-6 text-zinc-100">
                            <h3 className="text-lg font-medium text-zinc-200 mb-4">Profile Information</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label className="block text-sm font-medium text-zinc-400">Name</label>
                                    <div className="mt-1 text-zinc-100">{user.name}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-zinc-400">Email</label>
                                    <div className="mt-1 text-zinc-100">{user.email}</div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-zinc-400">Role</label>
                                    <div className="mt-1">
                                        <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                            user.role === 'admin' 
                                            ? 'bg-purple-900 text-purple-200' 
                                            : 'bg-zinc-700 text-zinc-300'
                                        }`}>
                                            {user.role}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-zinc-400">Joined</label>
                                    <div className="mt-1 text-zinc-100">{new Date(user.created_at).toLocaleDateString()}</div>
                                </div>
                            </div>

                            <div className="mt-6">
                                <Link
                                    href={route('admin.users.edit', user.id)}
                                    className="bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-2 px-4 rounded shadow mr-2"
                                >
                                    Edit User
                                </Link>
                                <Link
                                    href={route('admin.users.index')}
                                    className="text-zinc-400 hover:text-zinc-200 underline"
                                >
                                    Back to List
                                </Link>
                            </div>
                        </div>
                    </div>

                    {/* Recent Conversations */}
                    <div className="bg-zinc-900 overflow-hidden shadow-xl sm:rounded-lg border border-zinc-700/50">
                        <div className="p-6 text-zinc-100">
                            <h3 className="text-lg font-medium text-zinc-200 mb-4">Personas</h3>
                            {user.personas && user.personas.length > 0 ? (
                                <ul className="divide-y divide-zinc-800">
                                    {user.personas.map((persona) => (
                                        <li key={persona.id} className="py-3">
                                            <div className="flex justify-between items-center">
                                                <div>
                                                    <div className="font-medium text-zinc-200">{persona.name}</div>
                                                    <div className="text-sm text-zinc-500">Temp {persona.temperature}</div>
                                                </div>
                                                <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                                    persona.is_active 
                                                    ? 'bg-emerald-900 text-emerald-200' 
                                                    : 'bg-zinc-700 text-zinc-300'
                                                }`}>
                                                    {persona.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <div className="text-zinc-500 italic">No personas found.</div>
                            )}
                        </div>
                    </div>

                    {/* Recent Conversations */}
                    <div className="bg-zinc-900 overflow-hidden shadow-xl sm:rounded-lg border border-zinc-700/50">
                        <div className="p-6 text-zinc-100">
                            <h3 className="text-lg font-medium text-zinc-200 mb-4">Recent Conversations</h3>
                            {user.conversations && user.conversations.length > 0 ? (
                                <ul className="divide-y divide-zinc-800">
                                    {user.conversations.map((conv) => (
                                        <li key={conv.id} className="py-3">
                                            <div className="flex justify-between">
                                                <span className="text-zinc-300">Conversation #{conv.id}</span>
                                                <span className="text-zinc-500 text-sm">{new Date(conv.created_at).toLocaleDateString()}</span>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <div className="text-zinc-500 italic">No conversations found.</div>
                            )}
                        </div>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
