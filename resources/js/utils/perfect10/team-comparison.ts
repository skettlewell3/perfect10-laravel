import type { TeamComparisonData } from '@/types/perfect10/team-comparison';

export type ComparisonSummaryStats = {
    gf: number;
    ga: number;
    pts: number;
    swing: number;
    played: number;
    w: number;
    d: number;
    l: number;
};

export type ComparisonTeamSummary = {
    home: ComparisonSummaryStats;
    away: ComparisonSummaryStats;
    total: ComparisonSummaryStats;
};

export type ComparisonSummary = {
    t1: ComparisonTeamSummary;
    t2: ComparisonTeamSummary;
};

function emptyStats(): ComparisonSummaryStats {
    return {
        gf: 0,
        ga: 0,
        pts: 0,
        swing: 0,
        played: 0,
        w: 0,
        d: 0,
        l: 0,
    };
}

function emptySummary(): ComparisonSummary {
    return {
        t1: {
            home: emptyStats(),
            away: emptyStats(),
            total: emptyStats(),
        },
        t2: {
            home: emptyStats(),
            away: emptyStats(),
            total: emptyStats(),
        },
    };
}

function addResult(stats: ComparisonSummaryStats, points: number | null) {
    if (points === 3) {
        stats.w++;
    } else if (points === 1) {
        stats.d++;
    } else if (points === 0) {
        stats.l++;
    }
}

function addFixture(
    stats: ComparisonSummaryStats,
    gf: number | null,
    ga: number | null,
    points: number | null,
    swing: number | null = null,
) {
    stats.played++;
    stats.gf += gf ?? 0;
    stats.ga += ga ?? 0;
    stats.pts += points ?? 0;
    stats.swing += swing ?? 0;

    addResult(stats, points);
}

function calculateTotals(team: ComparisonTeamSummary) {
    const fields: (keyof ComparisonSummaryStats)[] = [
        'gf',
        'ga',
        'pts',
        'swing',
        'played',
        'w',
        'd',
        'l',
    ];

    for (const field of fields) {
        team.total[field] = team.home[field] + team.away[field];
    }
}

export function summariseGenericComparison(
    data: TeamComparisonData,
): ComparisonSummary {
    const summary = emptySummary();

    for (const row of data) {
        if (row.t1_home_gf !== null) {
            addFixture(
                summary.t1.home,
                row.t1_home_gf,
                row.t1_home_ga,
                row.t1_home_pts,
            );
        }

        if (row.t1_away_gf !== null) {
            addFixture(
                summary.t1.away,
                row.t1_away_gf,
                row.t1_away_ga,
                row.t1_away_pts,
            );
        }

        if (row.t2_home_gf !== null) {
            addFixture(
                summary.t2.home,
                row.t2_home_gf,
                row.t2_home_ga,
                row.t2_home_pts,
            );
        }

        if (row.t2_away_gf !== null) {
            addFixture(
                summary.t2.away,
                row.t2_away_gf,
                row.t2_away_ga,
                row.t2_away_pts,
            );
        }
    }

    calculateTotals(summary.t1);
    calculateTotals(summary.t2);

    return summary;
}

export function summariseDirectComparison(
    data: TeamComparisonData,
): ComparisonSummary {
    const summary = emptySummary();

    for (const row of data) {
        /*
         * Home results count only when both teams
         * have a comparable home result.
         */
        if (row.t1_home_swing !== null) {
            addFixture(
                summary.t1.home,
                row.t1_home_gf,
                row.t1_home_ga,
                row.t1_home_pts,
                row.t1_home_swing,
            );

            addFixture(
                summary.t2.home,
                row.t2_home_gf,
                row.t2_home_ga,
                row.t2_home_pts,
                row.t2_home_swing,
            );
        }

        /*
         * Away results are evaluated independently
         * from the home results.
         */
        if (row.t1_away_swing !== null) {
            addFixture(
                summary.t1.away,
                row.t1_away_gf,
                row.t1_away_ga,
                row.t1_away_pts,
                row.t1_away_swing,
            );

            addFixture(
                summary.t2.away,
                row.t2_away_gf,
                row.t2_away_ga,
                row.t2_away_pts,
                row.t2_away_swing,
            );
        }
    }

    calculateTotals(summary.t1);
    calculateTotals(summary.t2);

    return summary;
}
