import { useMemo } from 'react';

import ComparisonSummaryHeader from './ComparisonSummaryHeader';
import ComparisonSummaryRow from './ComparisonSummaryRow';

import type {
    TeamComparisonData,
    TeamComparisonMode,
} from '@/types/perfect10/team-comparison';

import {
    summariseDirectComparison,
    summariseGenericComparison,
} from '@/utils/perfect10/team-comparison';

type Props = {
    data: TeamComparisonData;
    comparisonMode: TeamComparisonMode;
};

export default function TeamComparisonSummary({ data, comparisonMode }: Props) {
    const summary = useMemo(
        () =>
            comparisonMode === 'generic'
                ? summariseGenericComparison(data)
                : summariseDirectComparison(data),
        [data, comparisonMode],
    );

    return (
        <div className="comparisonSummary">
            <ComparisonSummaryHeader />

            <ComparisonSummaryRow label="MP" stat="played" summary={summary} />

            <ComparisonSummaryRow label="W" stat="w" summary={summary} />

            <ComparisonSummaryRow label="D" stat="d" summary={summary} />

            <ComparisonSummaryRow label="L" stat="l" summary={summary} />

            <ComparisonSummaryRow label="GF" stat="gf" summary={summary} />

            <ComparisonSummaryRow label="GA" stat="ga" summary={summary} />

            <ComparisonSummaryRow
                label={comparisonMode === 'direct' ? '+/-' : 'Pts'}
                stat={comparisonMode === 'direct' ? 'swing' : 'pts'}
                summary={summary}
            />
        </div>
    );
}
