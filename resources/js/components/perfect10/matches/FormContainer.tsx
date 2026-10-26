import FormRow from './FormRow';

import type { FixtureTeam } from '@/components/perfect10/fixtures/FixtureCard';
import type { Fixture } from '@/types/perfect10/fixture';

export type TeamForm = {
    past: Fixture[];
    future: Fixture[];
};

export type MatchForm = {
    home: TeamForm;
    away: TeamForm;
};

type FormContainerProps = {
    form: MatchForm;
    currentFixture: Fixture;
    teamsMap: Record<number, FixtureTeam>;
};

export default function FormContainer({
    form,
    currentFixture,
    teamsMap,
}: FormContainerProps) {
    const homeTeamId = currentFixture.home_team_id;
    const awayTeamId = currentFixture.away_team_id;

    if (homeTeamId === null || awayTeamId === null) {
        return null;
    }

    const formMaxRows = Math.max(form.home.past.length, form.away.past.length);

    const upcomingMaxRows = Math.max(
        form.home.future.length,
        form.away.future.length,
    );

    const sizeMap = [1.1, 1.05, 1, 0.95, 0.9];

    return (
        <div className="formContainer">
            {Array.from({ length: upcomingMaxRows }).map((_, index) => (
                <FormRow
                    key={`future-${index}`}
                    fixture1={form.home.future[index] ?? null}
                    fixture2={form.away.future[index] ?? null}
                    homeTeamId={homeTeamId}
                    awayTeamId={awayTeamId}
                    scale={1}
                    teamsMap={teamsMap}
                />
            ))}

            {Array.from({ length: formMaxRows }).map((_, index) => (
                <FormRow
                    key={`past-${index}`}
                    fixture1={form.home.past[index] ?? null}
                    fixture2={form.away.past[index] ?? null}
                    homeTeamId={homeTeamId}
                    awayTeamId={awayTeamId}
                    scale={sizeMap[index] ?? 0.9}
                    teamsMap={teamsMap}
                />
            ))}
        </div>
    );
}
