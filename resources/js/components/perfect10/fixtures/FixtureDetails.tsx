import type { Fixture } from '@/types/perfect10/fixture';

type FixtureDetailsProps = {
    fixture: Fixture;
};

function formatTime(date: Date, timeZone?: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
        ...(timeZone ? { timeZone } : {}),
    }).format(date);
}

export default function FixtureDetails({ fixture }: FixtureDetailsProps) {
    const kickoff = new Date(fixture.kickoff_at);

    const localKO = formatTime(kickoff);

    const venueKO = fixture.timezone
        ? formatTime(kickoff, fixture.timezone)
        : localKO;

    const showVenueTime = venueKO !== localKO;

    const location = [fixture.city, fixture.country].filter(Boolean).join(', ');

    return (
        <div className="fixtureDetails perFixtureDetails">
            <div className="fixtureLocation">
                {showVenueTime && <p>{venueKO}</p>}

                <p>{fixture.venue_name}</p>

                <p>{location}</p>
            </div>
        </div>
    );
}
