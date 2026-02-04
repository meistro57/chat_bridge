import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { GlassCard } from '@/Components/ui/GlassCard';
import { Head, Link, usePage, router } from '@inertiajs/react';
import { useState } from 'react';

function StatCard({ label, value, accent }) {
    const gradients = {
        blue: 'from-blue-500 to-cyan-500',
        emerald: 'from-emerald-500 to-teal-500',
        purple: 'from-purple-500 to-pink-500',
        orange: 'from-orange-500 to-amber-500',
    };

    return (
        <GlassCard accent={accent} hover>
            <div className="text-sm text-zinc-400">{label}</div>
            <div className={`text-3xl font-bold mt-1 bg-gradient-to-r ${gradients[accent] || gradients.blue} bg-clip-text text-transparent`}>
                {value}
            </div>
        </GlassCard>
    );
}

export default function Index({ auth, users, stats, filters }) {
    const { flash = {} } = usePage().props;
    const [search, setSearch] = useState(filters?.search || '');
    const [role, setRole] = useState(filters?.role || '');
    const [status, setStatus] = useState(filters?.status || '');

    const applyFilters = () => {
        const params = {};
        if (search) params.search = search;
        if (role) params.role = role;
        if (status) params.status = status;

        router.get(route('admin.users.index'), params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const clearFilters = () => {
        setSearch('');
        setRole('');
        setStatus('');
        router.get(route('admin.users.index'), {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const hasFilters = search || role || status;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-zinc-100 leading-tight">User Management</h2>}
        >
            <Head title="Users" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                    {flash.success && (
                        <div className="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-lg" role="alert">
                            <span className="block sm:inline">{flash.success}</span>
                        </div>
                    )}

                    {/* Stats Dashboard */}
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <StatCard label="Total Users" value={stats.total_users} accent="blue" />
                        <StatCard label="Active Users" value={stats.active_users} accent="emerald" />
                        <StatCard label="Admins" value={stats.admin_users} accent="purple" />
                        <StatCard label="New This Week" value={stats.recent_users} accent="orange" />
                    </div>

                    {/* Search & Filters */}
                    <GlassCard accent="indigo">
                        <div className="flex flex-col md:flex-row gap-4 items-end">
                            <div className="flex-1">
                                <label className="block text-xs font-medium text-zinc-400 mb-1">Search</label>
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                    placeholder="Search by name or email..."
                                    className="w-full bg-zinc-800/50 border border-zinc-700/50 rounded-lg px-3 py-2 text-sm text-zinc-100 placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500/50"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-zinc-400 mb-1">Role</label>
                                <select
                                    value={role}
                                    onChange={(e) => setRole(e.target.value)}
                                    className="bg-zinc-800/50 border border-zinc-700/50 rounded-lg px-3 py-2 text-sm text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500/50"
                                >
                                    <option value="">All Roles</option>
                                    <option value="admin">Admin</option>
                                    <option value="user">User</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-zinc-400 mb-1">Status</label>
                                <select
                                    value={status}
                                    onChange={(e) => setStatus(e.target.value)}
                                    className="bg-zinc-800/50 border border-zinc-700/50 rounded-lg px-3 py-2 text-sm text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500/50"
                                >
                                    <option value="">All Statuses</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div className="flex gap-2">
                                <button
                                    onClick={applyFilters}
                                    className="bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2 px-4 rounded-lg text-sm transition duration-150"
                                >
                                    Filter
                                </button>
                                {hasFilters && (
                                    <button
                                        onClick={clearFilters}
                                        className="bg-zinc-700 hover:bg-zinc-600 text-zinc-300 font-medium py-2 px-4 rounded-lg text-sm transition duration-150"
                                    >
                                        Clear
                                    </button>
                                )}
                            </div>
                        </div>
                    </GlassCard>

                    {/* Actions Bar */}
                    <div className="flex justify-between items-center">
                        <p className="text-sm text-zinc-400">
                            Showing {users.length} user{users.length !== 1 ? 's' : ''}
                        </p>
                        <Link
                            href={route('admin.users.create')}
                            className="bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-2 px-4 rounded-lg shadow-lg transition duration-150 ease-in-out"
                        >
                            Create New User
                        </Link>
                    </div>

                    {/* Users Table */}
                    <div className="relative bg-zinc-900/50 backdrop-blur-2xl overflow-hidden rounded-2xl border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)]">
                        <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-red-500/80 via-pink-500/80 to-purple-500/80" />
                        <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />
                        <div className="relative p-6 text-zinc-100">
                            {users.length === 0 ? (
                                <div className="text-center py-12 text-zinc-500">
                                    No users found matching your filters.
                                </div>
                            ) : (
                                <table className="min-w-full divide-y divide-zinc-700/50">
                                    <thead>
                                        <tr>
                                            <th className="px-6 py-3 bg-zinc-800/50 backdrop-blur-sm text-left text-xs leading-4 font-medium text-zinc-400 uppercase tracking-wider rounded-tl-lg">Name</th>
                                            <th className="px-6 py-3 bg-zinc-800/50 backdrop-blur-sm text-left text-xs leading-4 font-medium text-zinc-400 uppercase tracking-wider">Email</th>
                                            <th className="px-6 py-3 bg-zinc-800/50 backdrop-blur-sm text-left text-xs leading-4 font-medium text-zinc-400 uppercase tracking-wider">Role</th>
                                            <th className="px-6 py-3 bg-zinc-800/50 backdrop-blur-sm text-left text-xs leading-4 font-medium text-zinc-400 uppercase tracking-wider">Status</th>
                                            <th className="px-6 py-3 bg-zinc-800/50 backdrop-blur-sm text-left text-xs leading-4 font-medium text-zinc-400 uppercase tracking-wider">Stats</th>
                                            <th className="px-6 py-3 bg-zinc-800/50 backdrop-blur-sm text-left text-xs leading-4 font-medium text-zinc-400 uppercase tracking-wider rounded-tr-lg">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-transparent divide-y divide-zinc-800/50">
                                        {users.map((user) => (
                                            <tr key={user.id} className="hover:bg-white/[0.02] transition-colors">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <Link
                                                        href={route('admin.users.show', user.id)}
                                                        className="text-sm font-medium text-white hover:text-indigo-400 transition-colors"
                                                    >
                                                        {user.name}
                                                    </Link>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm text-zinc-400">{user.email}</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                                        user.role === 'admin'
                                                        ? 'bg-purple-900/60 text-purple-200 border border-purple-500/30'
                                                        : 'bg-zinc-700/60 text-zinc-300 border border-zinc-600/30'
                                                    }`}>
                                                        {user.role}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                                        user.is_active
                                                        ? 'bg-emerald-900/60 text-emerald-200 border border-emerald-500/30'
                                                        : 'bg-red-900/60 text-red-200 border border-red-500/30'
                                                    }`}>
                                                        {user.is_active ? 'Active' : 'Inactive'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-zinc-400">
                                                    <div className="flex gap-3">
                                                        <span title="Personas">{user.personas_count} personas</span>
                                                        <span title="Conversations">{user.conversations_count} chats</span>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <div className="flex gap-3">
                                                        <Link
                                                            href={route('admin.users.show', user.id)}
                                                            className="text-cyan-400 hover:text-cyan-300 transition-colors"
                                                        >
                                                            View
                                                        </Link>
                                                        <Link
                                                            href={route('admin.users.edit', user.id)}
                                                            className="text-indigo-400 hover:text-indigo-300 transition-colors"
                                                        >
                                                            Edit
                                                        </Link>
                                                        {user.id !== auth.user.id && (
                                                            <Link
                                                                href={route('admin.users.destroy', user.id)}
                                                                method="delete"
                                                                as="button"
                                                                className="text-red-400 hover:text-red-300 transition-colors"
                                                            >
                                                                Delete
                                                            </Link>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
