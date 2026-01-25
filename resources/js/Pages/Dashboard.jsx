import { Head, Link } from '@inertiajs/react';

export default function Dashboard({ user }) {
    const modules = [
        {
            name: 'Chat Bridge',
            description: 'Start AI conversations between different personas',
            href: '/chat',
            icon: (
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
            ),
            color: 'from-blue-500 to-cyan-500',
            accent: 'blue',
        },
        {
            name: 'Personas',
            description: 'Manage AI personas and their configurations',
            href: '/personas',
            icon: (
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            ),
            color: 'from-purple-500 to-pink-500',
            accent: 'purple',
        },
        {
            name: 'API Keys',
            description: 'Manage and validate your AI provider credentials',
            href: '/api-keys',
            icon: (
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
                </svg>
            ),
            color: 'from-emerald-500 to-teal-500',
            accent: 'emerald',
        },
        {
            name: 'Analytics',
            description: 'View statistics and query conversation history',
            href: '/analytics',
            icon: (
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                </svg>
            ),
            color: 'from-cyan-500 to-blue-500',
            accent: 'cyan',
        },
        {
            name: 'Profile',
            description: 'Update your account settings and preferences',
            href: '/profile',
            icon: (
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            ),
            color: 'from-orange-500 to-red-500',
            accent: 'orange',
        },
    ];

    // Add admin modules if user is admin
    if (user?.role === 'admin') {
        modules.push(
            {
                name: 'User Management',
                description: 'Manage users and permissions',
                href: '/admin/users',
                icon: (
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                ),
                color: 'from-red-500 to-pink-500',
                accent: 'red',
            },
            {
                name: 'System Diagnostics',
                description: 'Run health checks and maintenance tasks',
                href: '/admin/system',
                icon: (
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                ),
                color: 'from-violet-500 to-purple-500',
                accent: 'violet',
            },
            {
                name: 'Telescope',
                description: 'Debug and monitor application activity',
                href: '/telescope',
                icon: (
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M12 2a10 10 0 1 0 10 10H12V2Z"/>
                        <path d="M12 12 4.5 19.5"/>
                        <path d="M15 15 3.5 20.5"/>
                    </svg>
                ),
                color: 'from-fuchsia-500 to-pink-500',
                accent: 'pink',
            }
        );
    }

    return (
        <div className="min-h-screen text-zinc-100 p-6 md:p-12">
            <Head title="Dashboard" />

            <div className="max-w-7xl mx-auto space-y-8">
                {/* Header */}
                <div className="pb-8">
                    <h1 className="text-5xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-zinc-400 mb-2">
                        Chat Bridge
                    </h1>
                    <p className="text-zinc-500 text-lg">
                        Welcome back, {user?.name}. Choose a module to get started.
                    </p>
                </div>

                {/* Module Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {modules.map((module) => (
                        <Link
                            key={module.name}
                            href={module.href}
                            className="group relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-8 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden transition-all duration-300 hover:bg-zinc-900/60 hover:border-white/[0.15] hover:shadow-[0_12px_40px_rgba(0,0,0,0.5)] hover:scale-[1.02]"
                        >
                            {/* Accent border at bottom */}
                            <div className={`absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r ${module.color} opacity-80`} />

                            {/* Inner glow effect */}
                            <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />

                            <div className="relative mb-6">
                                <div className={`relative h-14 w-14 rounded-xl bg-gradient-to-br ${module.color} p-[1px] shadow-[0_0_20px_rgba(255,255,255,0.06)] transition-transform group-hover:scale-110`}>
                                    <div className="flex h-full w-full items-center justify-center rounded-xl bg-zinc-900/80 backdrop-blur-xl">
                                        <div className={`text-white/90`}>
                                            {module.icon}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h3 className="relative text-xl font-bold text-zinc-100 mb-2 group-hover:text-white transition-colors">
                                {module.name}
                            </h3>

                            <p className="relative text-sm text-zinc-500 group-hover:text-zinc-400 transition-colors">
                                {module.description}
                            </p>

                            {/* Arrow indicator */}
                            <div className="relative mt-4 flex items-center text-zinc-600 group-hover:text-zinc-400 transition-colors">
                                <span className="text-sm font-medium mr-2">Open</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="group-hover:translate-x-1 transition-transform">
                                    <line x1="5" y1="12" x2="19" y2="12"/>
                                    <polyline points="12 5 19 12 12 19"/>
                                </svg>
                            </div>
                        </Link>
                    ))}
                </div>

                {/* Quick Stats */}
                <div className="relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl p-8 border border-white/[0.08] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden mt-12">
                    {/* Accent border at bottom */}
                    <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-cyan-500/80 via-teal-500/80 to-emerald-500/80" />

                    {/* Inner glow effect */}
                    <div className="absolute inset-0 bg-gradient-to-br from-white/[0.02] to-transparent pointer-events-none" />

                    <h2 className="relative text-xl font-bold text-zinc-100 mb-6">System Status</h2>
                    <div className="relative grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div className="text-center p-4 rounded-xl bg-zinc-900/30 border border-white/[0.05]">
                            <div className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-cyan-400 mb-1">
                                Active
                            </div>
                            <div className="text-sm text-zinc-500">Application Status</div>
                        </div>
                        <div className="text-center p-4 rounded-xl bg-zinc-900/30 border border-white/[0.05]">
                            <div className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-pink-400 mb-1">
                                {user?.role || 'User'}
                            </div>
                            <div className="text-sm text-zinc-500">Your Role</div>
                        </div>
                        <div className="text-center p-4 rounded-xl bg-zinc-900/30 border border-white/[0.05]">
                            <div className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-teal-400 mb-1">
                                Laravel 12
                            </div>
                            <div className="text-sm text-zinc-500">Framework Version</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
