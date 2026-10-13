import LeagueTableRow from './LeagueTableRow';

export interface LeagueTableRowData {
    team_id: number;
    team_name: string;
    short_code: string | null;
    table_type: string;
    pos: number;
    played: number;
    won: number;
    drawn: number;
    lost: number;
    gf: number;
    ga: number;
    gd: number;
    points: number;
}

type Props = {
    data: LeagueTableRowData[];
};

export default function LeagueTable({ data }: Props) {
    return (
        <div className="leagueTableScroll">
            <table className="leagueTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Team</th>
                        <th>MP</th>
                        <th>W</th>
                        <th>D</th>
                        <th>L</th>
                        <th>GF</th>
                        <th>GA</th>
                        <th>GD</th>
                        <th>Pts</th>
                    </tr>
                </thead>

                <tbody>
                    {data.map((row) => (
                        <LeagueTableRow key={row.team_id} row={row} />
                    ))}
                </tbody>
            </table>
        </div>
    );
}
