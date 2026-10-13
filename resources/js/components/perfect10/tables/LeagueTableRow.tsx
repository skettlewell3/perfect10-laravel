import type { LeagueTableRowData } from './LeagueTable';

type Props = {
    row: LeagueTableRowData;
};

export default function LeagueTableRow({ row }: Props) {
    const { pos, team_name, played, won, drawn, lost, gf, ga, gd, points } =
        row;

    return (
        <tr>
            <td>{pos}</td>

            <td className="teamCell">{team_name}</td>

            <td>{played}</td>
            <td>{won}</td>
            <td>{drawn}</td>
            <td>{lost}</td>
            <td>{gf}</td>
            <td>{ga}</td>
            <td>{gd}</td>
            <td>{points}</td>
        </tr>
    );
}
