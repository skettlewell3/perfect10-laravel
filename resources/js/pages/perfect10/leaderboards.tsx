import { Head, usePage } from '@inertiajs/react';

import ContentBanner from '@/components/perfect10/app/ContentBanner';
import LeaderboardFilters from '@/components/perfect10/leaderboard/LeaderboardFilters';
import LeaderboardHeader from '@/components/perfect10/leaderboard/LeaderboardHeader';
import LeaderboardRow from '@/components/perfect10/leaderboard/LeaderboardRow';
import type { Perfect10PageProps } from '@/types/perfect10';

interface LeaderboardRowData {
    rank: number;
    profile_id: string;
    display_name: string;
    profile_type: string;
    predicted: number;
    correct_results: number;
    correct_home_goals: number;
    correct_away_goals: number;
    correct_goal_difference: number;
    correct_total_goals: number;
    perfect_10s: number;
    points_total: number;
}

interface Stage {
    stage_id: number;
    stage_name: string;
}

interface Filters {
    scopeType: string;
    scopeId: number | null;
    population: string;
}

interface LeaderboardsPageProps extends Perfect10PageProps {
    leaderboard: LeaderboardRowData[];
    leaderboardStages: Stage[];
    filters: Filters;
}

export default function LeaderboardsPage() {
    const { flavour, leaderboard, leaderboardStages, filters } =
        usePage<LeaderboardsPageProps>().props;

    return (
        <>
            <Head title="Leaderboards" />

            <div className="pageShell">
                <ContentBanner title="Leaderboards" />

                <LeaderboardFilters
                    leaderboardStages={leaderboardStages}
                    filters={filters}
                    flavourCode={flavour.flavour_code}
                />

                <LeaderboardHeader />

                <div className="scrollArea boardList">
                    {leaderboard.map((row) => (
                        <LeaderboardRow key={row.profile_id} row={row} />
                    ))}
                </div>
            </div>
        </>
    );
}
