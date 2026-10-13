import type { LeagueTableRowData } from './LeagueTable';

type Props = {
    row: LeagueTableRowData;
};

export default function LeagueTableComparisonRow({
    row,
}: Props) {
    return (
        <div className="leagueComparisonRow">
            <span>
                {row.pos}
            </span>

            <span>
                {row.short_code ?? row.team_name}
            </span>

            <span>
                {row.played}
            </span>

            <span>
                {row.points}
            </span>
        </div>
    );
}