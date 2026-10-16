
import type { Fixture } from '@/types/perfect10/fixture';
import type {
    FixturePrediction,
    PredictionDraft,
} from '@/types/perfect10/prediction';

import MatchScore from './MatchScore';
import PredictionScore from './PredictionScore';


type FixtureScorePanelProps = {
    fixture: Fixture;
    prediction?: FixturePrediction | null;
    draft?: PredictionDraft | null;
    canEdit?: boolean;
    onDraftChange?: (draft: PredictionDraft) => void;
};

export default function FixtureScorePanel({
    fixture,
    prediction,
    draft,
    canEdit = false,
    onDraftChange,
}: FixtureScorePanelProps) {
    const predictionOpen =
        fixture.predictions_open &&
        fixture.fixture_status === 'upcoming';

    const editable = predictionOpen && canEdit;

    return (
        <div className="scorePanel">
            {!editable && (
                <MatchScore fixture={fixture} />
            )}

            <PredictionScore
                fixture={fixture}
                prediction={prediction}
                draft={draft}
                editable={editable}
                onDraftChange={onDraftChange}
            />
        </div>
    );
}
