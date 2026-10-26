import TeamComparisonCore from './TeamComparisonCore';

import type { FixtureTeam } from '@/components/perfect10/fixtures/FixtureCard';
import type {
    TeamComparisonData,
    TeamComparisonTeams,
} from '@/types/perfect10/team-comparison';

type Props = {
    data: TeamComparisonData;
    teams: TeamComparisonTeams;
    teamsMap: Record<number, FixtureTeam>;
    team1ShortCode: string | null;
    team2ShortCode: string | null;
};

export default function TeamComparison({
    data,
    teams,
    teamsMap,
    team1ShortCode,
    team2ShortCode,
}: Props) {
    return (
        <div className="teamComparisonContainer">
            <TeamComparisonCore
                data={data}
                teams={teams}
                teamsMap={teamsMap}
                team1ShortCode={team1ShortCode}
                team2ShortCode={team2ShortCode}
            />
        </div>
    );
}
