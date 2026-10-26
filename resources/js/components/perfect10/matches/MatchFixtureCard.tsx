import MatchFixtureRow from './MatchFixtureRow';

import type { FixtureTeam } from '@/components/perfect10/fixtures/FixtureCard';
import type { LeagueTableRowData } from '@/components/perfect10/tables/LeagueTable';
import type { Fixture } from '@/types/perfect10/fixture';
import type {
    FixturePrediction,
    PredictionDraft,
} from '@/types/perfect10/prediction';

type MatchFixtureCardProps = {
    fixture: Fixture;
    prediction?: FixturePrediction | null;
    draft?: PredictionDraft | null;
    teamsMap: Record<number, FixtureTeam>;
    actualTable: LeagueTableRowData[];
    predictionWindowOpen: boolean;
    onDraftChange?: (draft: PredictionDraft) => void;
};

const statusMap: Record<string, { label: string; color: string }> = {
    upcoming: { label: 'Upcoming', color: 'amber' },
    live_90: { label: 'Live', color: 'blue' },
    live_et: { label: 'Live', color: 'blue' },
    finished: { label: 'Finished', color: 'grey' },
    postponed: { label: 'Postponed', color: 'grey' },
};

export default function MatchFixtureCard({
    fixture,
    prediction,
    draft,
    teamsMap,
    actualTable,
    predictionWindowOpen,
    onDraftChange,
}: MatchFixtureCardProps) {
    const statusMeta = statusMap[fixture.fixture_status];

    const kickoff = new Date(fixture.kickoff_at);

    const day = kickoff.toLocaleDateString('en-GB', {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
    });

    const ko = kickoff.toLocaleTimeString('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
    });

    return (
        <div className="mfCard matchCard">
            <div className="mfCardHeader">
                <div className="fcHeaderCenter">
                    <div className="day">{day}</div>
                    <div className="ko">{ko}</div>
                </div>

                {statusMeta && (
                    <div className={`fixtureStatus ${statusMeta.color}`}>
                        <span className="dot" />
                        {statusMeta.label}
                    </div>
                )}
            </div>

            <MatchFixtureRow
                fixture={fixture}
                prediction={prediction}
                draft={draft}
                teamsMap={teamsMap}
                actualTable={actualTable}
                predictionWindowOpen={predictionWindowOpen}
                onDraftChange={onDraftChange}
            />
        </div>
    );
}
