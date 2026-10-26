import type {
    ComparisonSummary,
    ComparisonSummaryStats,
} from '@/utils/perfect10/team-comparison';

type Props = {
    label: string;
    stat: keyof ComparisonSummaryStats;
    summary: ComparisonSummary;
};

export default function ComparisonSummaryRow({ label, stat, summary }: Props) {
    const { t1, t2 } = summary;

    return (
        <div className="comparisonSummaryRow">
            <div>{t1.total[stat]}</div>
            <div>{t1.home[stat]}</div>
            <div>{t1.away[stat]}</div>

            <div className="summaryLabel">{label}</div>

            <div>{t2.home[stat]}</div>
            <div>{t2.away[stat]}</div>
            <div>{t2.total[stat]}</div>
        </div>
    );
}
