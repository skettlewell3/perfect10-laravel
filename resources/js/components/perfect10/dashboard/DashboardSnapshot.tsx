import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';

type Props = {
    screens: ReactNode[];
    interval?: number;
    loopFrom?: number;
};

export default function DashboardSnapshot({
    screens,
    interval = 5000,
    loopFrom = 0,
}: Props) {
    const [activeIndex, setActiveIndex] = useState(0);

    useEffect(() => {
        if (screens.length <= 1) {
            return;
        }

        const timer = window.setTimeout(() => {
            setActiveIndex((current) => {
                const nextIndex = current + 1;

                return nextIndex < screens.length ? nextIndex : loopFrom;
            });
        }, interval);

        return () => window.clearTimeout(timer);
    }, [activeIndex, screens.length, interval, loopFrom]);

    if (!screens.length) {
        return null;
    }

    return (
        <div className="dashboardSnapshot">
            {screens[Math.min(activeIndex, screens.length - 1)]}
        </div>
    );
}
