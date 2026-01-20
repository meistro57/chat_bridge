import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-zinc-950 pt-6 sm:justify-center sm:pt-0">
            <div>
                <Link href="/">
                    <ApplicationLogo className="h-20 w-20 fill-current text-white" />
                </Link>
            </div>

            <div className="mt-6 w-full overflow-hidden bg-zinc-900/50 border border-white/5 px-6 py-4 shadow-xl backdrop-blur-xl sm:max-w-md sm:rounded-lg">
                {children}
            </div>
        </div>
    );
}
