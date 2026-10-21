import type { Fixture } from '@/types/perfect10/fixture';
import type {
    FixturePrediction,
    PredictionDraft,
} from '@/types/perfect10/prediction';

type PredictionScoreProps = {
    fixture: Fixture;
    prediction?: FixturePrediction | null;
    draft?: PredictionDraft | null;
    editable?: boolean;
    onDraftChange?: (draft: PredictionDraft) => void;
};

export default function PredictionScore({
    fixture,
    prediction,
    draft,
    editable = false,
    onDraftChange,
}: PredictionScoreProps) {
    const homeValue =
        draft?.home ??
        prediction?.pred_home_goals?.toString() ??
        '';

    const awayValue =
        draft?.away ??
        prediction?.pred_away_goals?.toString() ??
        '';

    const isDirty =
        Boolean(draft) &&
        (
            Number(homeValue) !== prediction?.pred_home_goals ||
            Number(awayValue) !== prediction?.pred_away_goals
        );

    const inputColor = isDirty ? '#0000ff' : '#000000';

    const handleChange = (
        side: 'home' | 'away',
        value: string,
    ) => {
        if (!onDraftChange) return;

        onDraftChange({
            home: side === 'home' ? value : homeValue,
            away: side === 'away' ? value : awayValue,
        });
    };

    if (editable) {
        return (
            <div className="predictionRow">
                <input
                    type="number"
                    className="predInput home"
                    aria-label={`${fixture.home_team_name ?? 'Home'} predicted goals`}
                    min={0}
                    max={9}
                    step={1}
                    value={homeValue}
                    style={{ color: inputColor }}
                    onChange={event =>
                        handleChange('home', event.target.value)
                    }
                    disabled={!onDraftChange}
                />

                <div className="vs">V</div>

                <input
                    type="number"
                    className="predInput away"
                    aria-label={`${fixture.away_team_name ?? 'Away'} predicted goals`}
                    min={0}
                    max={9}
                    step={1}
                    value={awayValue}
                    style={{ color: inputColor }}
                    onChange={event =>
                        handleChange('away', event.target.value)
                    }
                    disabled={!onDraftChange}
                />
            </div>
        );
    }

    return (
        <div className="predictionRow">
            <div className="predDiv home">
                {prediction?.pred_home_goals ?? '–'}
            </div>

            <div className="vs vsPred">
                <span className="label">You:</span>
                V
            </div>

            <div className="predDiv away">
                {prediction?.pred_away_goals ?? '–'}
            </div>
        </div>
    );
}
