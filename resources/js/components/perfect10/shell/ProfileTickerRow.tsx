import { usePage } from '@inertiajs/react';

import type { Perfect10PageProps } from '@/types/perfect10';

export default function ProfileTickerRow() {
    const { flavour, perfect10Shell } = usePage<Perfect10PageProps>().props;

    const ticker = perfect10Shell?.profileTicker;

    const tickerItems = [
        {
            label: '🌍',
            rank: ticker?.global?.rank_position,
            points: ticker?.global?.total_points,
        },
        {
            label: flavour.flavour_code,
            rank: ticker?.flavour?.rank_position,
            points: ticker?.flavour?.total_points,
        },
        {
            label: flavour.active_campaign_label,
            rank: ticker?.campaign?.rank_position,
            points: ticker?.campaign?.total_points,
        },
        {
            label: ticker?.tickerStageLabel ?? '-',
            rank: ticker?.stage?.rank_position,
            points: ticker?.stage?.total_points,
        },
    ];

    return (
        <div className="profileTickerRow">
            <div className="tickerTrack">
                {tickerItems.map((item) => (
                    <div key={item.label} className="tickerStat">
                        <span>{item.label}:</span>

                        <strong>{item.points ?? 0} PTS</strong>

                        <p>#{item.rank ?? '-'}</p>
                    </div>
                ))}
            </div>
        </div>
    );
}
