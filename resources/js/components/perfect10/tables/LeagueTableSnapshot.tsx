import LeagueTable, { type LeagueTableRowData } from './LeagueTable';

type Props = {
    data: LeagueTableRowData[];
};

export default function LeagueTableSnapshot({ data }: Props) {
    return <LeagueTable data={data} />;
}
