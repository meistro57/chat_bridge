import { createContext, useContext, useEffect, useState } from 'react';

const LiveStatusContext = createContext({ active_count: 0, items: [], error: false });

export function LiveStatusProvider({ children }) {
    const [liveStatus, setLiveStatus] = useState({ active_count: 0, items: [] });
    const [liveStatusError, setLiveStatusError] = useState(false);

    useEffect(() => {
        let isMounted = true;

        const load = async () => {
            try {
                const response = await fetch(route('chat.live-status'), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const payload = await response.json();
                if (isMounted) {
                    setLiveStatus({
                        active_count: Number(payload.active_count ?? 0),
                        items: Array.isArray(payload.items) ? payload.items : [],
                    });
                    setLiveStatusError(false);
                }
            } catch {
                if (isMounted) setLiveStatusError(true);
            }
        };

        load();
        const interval = setInterval(load, 10000);
        return () => { isMounted = false; clearInterval(interval); };
    }, []);

    return (
        <LiveStatusContext.Provider value={{ ...liveStatus, error: liveStatusError }}>
            {children}
        </LiveStatusContext.Provider>
    );
}

export function useLiveStatus() {
    return useContext(LiveStatusContext);
}
