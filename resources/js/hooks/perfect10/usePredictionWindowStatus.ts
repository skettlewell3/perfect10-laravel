import { useEffect, useState } from 'react';

export type PredictionWindowMode =
    | 'blank'
    | 'preOpen'
    | 'open'
    | 'closingSoon'
    | 'closed';

export type PredictionWindowStatus = {
    mode: PredictionWindowMode;
    label: string;
    color: string;
    countdown: string | null;
    flip: boolean;
};

const HOUR = 60 * 60 * 1000;
const MINUTE = 60 * 1000;

function getWindowStatus(
    openAt: string | null,
    closeAt: string | null,
    now: number,
): Omit<PredictionWindowStatus, 'flip'> {
    const blank = {
        mode: 'blank' as const,
        label: '',
        color: '',
        countdown: null,
    };

    if (!openAt || !closeAt) return blank;

    const open = new Date(openAt).getTime();
    const close = new Date(closeAt).getTime();

    if (
        !Number.isFinite(open) ||
        !Number.isFinite(close) ||
        close <= open
    ) {
        return blank;
    }

    let target: number | null = null;
    let mode: PredictionWindowMode;
    let label: string;
    let color: string;

    if (now < open) {
        if (open - now > 24 * HOUR) return blank;

        mode = 'preOpen';
        label = 'OPEN SOON';
        color = 'grey';
        target = open;
    } else if (now < close) {
        const closingSoon = close - now < 12 * HOUR;

        mode = closingSoon ? 'closingSoon' : 'open';
        label = closingSoon ? 'Closes soon' : 'OPEN';
        color = closingSoon ? 'amber' : 'green';
        target = close;
    } else {
        mode = 'closed';
        label = 'CLOSED';
        color = 'grey';
    }

    let countdown: string | null = null;

    if (target !== null) {
        const remaining = Math.max(0, target - now);

        const hours = Math.floor(remaining / HOUR);
        const minutes = Math.floor(
            (remaining % HOUR) / MINUTE,
        );

        countdown = `${hours}h ${minutes}m`;
    }

    return {
        mode,
        label,
        color,
        countdown,
    };
}

export default function usePredictionWindowStatus(
    openAt: string | null,
    closeAt: string | null,
): PredictionWindowStatus {
    const [now, setNow] = useState(() => Date.now());
    const [flip, setFlip] = useState(false);

    useEffect(() => {
        const interval = window.setInterval(() => {
            setNow(Date.now());
            setFlip(previous => !previous);
        }, 3000);

        return () => window.clearInterval(interval);
    }, []);

    return {
        ...getWindowStatus(openAt, closeAt, now),
        flip,
    };
}