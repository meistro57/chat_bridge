import { GlassCard } from '@/Components/ui/GlassCard';

function StatItem({ label, value, gradient }) {
    return (
        <div className="text-center">
            <div className={`text-2xl font-bold bg-gradient-to-r ${gradient} bg-clip-text text-transparent`}>
                {typeof value === 'number' ? value.toLocaleString() : value}
            </div>
            <div className="text-xs text-zinc-400 mt-1">{label}</div>
        </div>
    );
}

export default function UsageStatsCard({ stats }) {
    const resolvedStats = {
        total_conversations: 0,
        completed_conversations: 0,
        total_messages: 0,
        total_tokens: 0,
        total_personas: 0,
        total_api_keys: 0,
        ...(stats ?? {}),
    };

    return (
        <GlassCard accent="blue">
            <section>
                <header>
                    <h2 className="text-lg font-medium text-zinc-100">
                        Usage Statistics
                    </h2>
                    <p className="mt-1 text-sm text-zinc-400">
                        Your activity overview across the platform.
                    </p>
                </header>

                <div className="mt-6 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
                    <StatItem
                        label="Conversations"
                        value={resolvedStats.total_conversations}
                        gradient="from-blue-500 to-cyan-500"
                    />
                    <StatItem
                        label="Completed"
                        value={resolvedStats.completed_conversations}
                        gradient="from-emerald-500 to-teal-500"
                    />
                    <StatItem
                        label="Messages"
                        value={resolvedStats.total_messages}
                        gradient="from-purple-500 to-pink-500"
                    />
                    <StatItem
                        label="Tokens Used"
                        value={resolvedStats.total_tokens}
                        gradient="from-orange-500 to-amber-500"
                    />
                    <StatItem
                        label="Personas"
                        value={resolvedStats.total_personas}
                        gradient="from-violet-500 to-purple-500"
                    />
                    <StatItem
                        label="API Keys"
                        value={resolvedStats.total_api_keys}
                        gradient="from-cyan-500 to-blue-500"
                    />
                </div>
            </section>
        </GlassCard>
    );
}
