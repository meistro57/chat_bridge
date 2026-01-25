import React from 'react';
import { cn } from '@/lib/utils';

const accentColors = {
    cyan: 'after:bg-gradient-to-r after:from-cyan-500/80 after:via-teal-500/80 after:to-emerald-500/80',
    blue: 'after:bg-gradient-to-r after:from-blue-500/80 after:via-cyan-500/80 after:to-blue-400/80',
    purple: 'after:bg-gradient-to-r after:from-purple-500/80 after:via-pink-500/80 after:to-fuchsia-500/80',
    emerald: 'after:bg-gradient-to-r after:from-emerald-500/80 after:via-teal-500/80 after:to-cyan-500/80',
    orange: 'after:bg-gradient-to-r after:from-orange-500/80 after:via-amber-500/80 after:to-yellow-500/80',
    red: 'after:bg-gradient-to-r after:from-red-500/80 after:via-rose-500/80 after:to-pink-500/80',
    violet: 'after:bg-gradient-to-r after:from-violet-500/80 after:via-purple-500/80 after:to-indigo-500/80',
    pink: 'after:bg-gradient-to-r after:from-pink-500/80 after:via-rose-500/80 after:to-red-500/80',
    indigo: 'after:bg-gradient-to-r after:from-indigo-500/80 after:via-blue-500/80 after:to-cyan-500/80',
};

const GlassCard = React.forwardRef(({
    className,
    children,
    accent,
    hover = false,
    glow = false,
    padding = true,
    ...props
}, ref) => {
    const baseClasses = cn(
        'relative bg-zinc-900/50 backdrop-blur-2xl rounded-2xl border border-white/[0.08]',
        'shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden',
        padding && 'p-6',
        hover && 'transition-all duration-300 hover:bg-zinc-900/60 hover:border-white/[0.12] hover:shadow-[0_12px_40px_rgba(0,0,0,0.5)] hover:scale-[1.01]',
        glow && 'ring-1 ring-white/[0.05] shadow-[0_8px_32px_rgba(0,0,0,0.5),inset_0_1px_0_rgba(255,255,255,0.05)]',
        accent && 'after:absolute after:bottom-0 after:left-0 after:right-0 after:h-[2px]',
        accent && accentColors[accent],
        className
    );

    return (
        <div ref={ref} className={baseClasses} {...props}>
            {children}
        </div>
    );
});
GlassCard.displayName = 'GlassCard';

const GlassCardHeader = React.forwardRef(({ className, ...props }, ref) => (
    <div
        ref={ref}
        className={cn('flex flex-col space-y-1.5 pb-4', className)}
        {...props}
    />
));
GlassCardHeader.displayName = 'GlassCardHeader';

const GlassCardTitle = React.forwardRef(({ className, ...props }, ref) => (
    <h3
        ref={ref}
        className={cn('text-lg font-bold text-zinc-100', className)}
        {...props}
    />
));
GlassCardTitle.displayName = 'GlassCardTitle';

const GlassCardDescription = React.forwardRef(({ className, ...props }, ref) => (
    <p
        ref={ref}
        className={cn('text-sm text-zinc-500', className)}
        {...props}
    />
));
GlassCardDescription.displayName = 'GlassCardDescription';

const GlassCardContent = React.forwardRef(({ className, ...props }, ref) => (
    <div ref={ref} className={cn('', className)} {...props} />
));
GlassCardContent.displayName = 'GlassCardContent';

const GlassCardFooter = React.forwardRef(({ className, ...props }, ref) => (
    <div
        ref={ref}
        className={cn('flex items-center pt-4', className)}
        {...props}
    />
));
GlassCardFooter.displayName = 'GlassCardFooter';

export {
    GlassCard,
    GlassCardHeader,
    GlassCardTitle,
    GlassCardDescription,
    GlassCardContent,
    GlassCardFooter
};
