import StageCardHeader from './StageCardHeader';
import StageFixtureRow from './StageFixtureRow';

import type { Fixture } from '@/types/perfect10/fixture';
import type {
    PredictionsMap,
    PredictionDraft,
} from '@/types/perfect10/prediction';
import type { FixtureTeam } from './FixtureCard';

type StageFixturesCardProps = {
    fixtures: Fixture[];
    teamsMap: Record<number, FixtureTeam>;
    predictionsMap: PredictionsMap;
    flavourCode: string;

    predictionOpenAt?: string | null;
    predictionCloseAt?: string | null;
    predictionWindowOpen: boolean;

    predictionDrafts?: Record<number, PredictionDraft>;
    onDraftChange?: (fixtureId: number, draft: PredictionDraft) => void;
};

export default function StageFixturesCard({
    fixtures,
    teamsMap,
    predictionsMap,
    flavourCode,
    predictionOpenAt,
    predictionCloseAt,
    predictionWindowOpen,
    predictionDrafts = {},
    onDraftChange,
}: StageFixturesCardProps) {
    if (fixtures.length === 0) {
        return null;
    }

    const firstFixture = fixtures[0];

    const stageClass =
        firstFixture.gameweek_number !== null
            ? `gwStage-${firstFixture.gameweek_number % 4}`
            : 'specialStage';

    return (
        <div className={`fixtureCard groupedFixturesCard ${stageClass}`}>
            <StageCardHeader
                fixtures={fixtures}
                predictionOpenAt={predictionOpenAt}
                predictionCloseAt={predictionCloseAt}
            />

            {fixtures.map((fixture) => (
                <StageFixtureRow
                    key={fixture.fixture_id}
                    fixture={fixture}
                    teamsMap={teamsMap}
                    prediction={predictionsMap[fixture.fixture_id] ?? null}
                    draft={predictionDrafts[fixture.fixture_id] ?? null}
                    flavourCode={flavourCode}
                    predictionWindowOpen={predictionWindowOpen}
                    onDraftChange={
                        onDraftChange
                            ? (draft) =>
                                  onDraftChange(fixture.fixture_id, draft)
                            : undefined
                    }
                />
            ))}
        </div>
    );
}
