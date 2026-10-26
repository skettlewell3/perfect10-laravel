import FormBlock from './FormBlock';

import type { FixtureTeam } from '@/components/perfect10/fixtures/FixtureCard';
import type { Fixture } from '@/types/perfect10/fixture';

type FormRowProps = {
    fixture1: Fixture | null;
    fixture2: Fixture | null;
    homeTeamId: number;
    awayTeamId: number;
    scale: number;
    teamsMap: Record<number, FixtureTeam>;
};

export default function FormRow({
    fixture1,
    fixture2,
    homeTeamId,
    awayTeamId,
    scale,
    teamsMap,
}: FormRowProps) {
    return (
        <div className="formRow">
            <FormBlock
                side="left"
                fixture={fixture1}
                teamId={homeTeamId}
                scale={scale}
                teamsMap={teamsMap}
            />

            <FormBlock
                side="right"
                fixture={fixture2}
                teamId={awayTeamId}
                scale={scale}
                teamsMap={teamsMap}
            />
        </div>
    );
}
