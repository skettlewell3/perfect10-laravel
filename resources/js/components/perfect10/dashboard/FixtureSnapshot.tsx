import type { Fixture } from '@/types/perfect10';

type Props = {
    fixture: Fixture;
};

export default function FixtureSnapshot({ fixture }: Props) {
    const status = fixture.fixture_status;

    const isLive = status === 'live_90' || status === 'live_et';

    const isFinished = status === 'finished';

    const labels: Record<Fixture['fixture_status'], string> = {
        upcoming: 'FIXTURE',
        live_90: 'LIVE',
        live_et: 'EXTRA TIME',
        finished: 'RESULT',
        postponed: 'POSTPONED',
    };

    const kickoff = new Date(fixture.kickoff_at);

    const snapDate = new Intl.DateTimeFormat('en-GB', {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
    }).format(kickoff);

    const snapKO = new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).format(kickoff);

    const homeScore =
        isLive || isFinished ? (fixture.final_home_goals ?? '-') : '-';

    const awayScore =
        isLive || isFinished ? (fixture.final_away_goals ?? '-') : '-';

    return (
        <div className="fixtureSnapshot">
            <div
                className={[
                    'snapLabel',
                    `snapStatus-${status}`,
                    isLive ? 'blink' : '',
                ]
                    .filter(Boolean)
                    .join(' ')}
            >
                {labels[status]}
            </div>

            <div className="snapFixture">
                <div className="snapTeamRow H">
                    <div className="snapTeamName">
                        {fixture.home_team_name ?? 'TBC'}
                    </div>

                    <div className="snapScore">{homeScore}</div>
                </div>

                <div className="snapTeamRow A">
                    <div className="snapTeamName">
                        {fixture.away_team_name ?? 'TBC'}
                    </div>

                    <div className="snapScore">{awayScore}</div>
                </div>
            </div>

            <div className="snapDetail">
                <p>{fixture.venue_name}</p>
                <p>
                    {snapDate} · {snapKO}
                </p>
            </div>
        </div>
    );
}
