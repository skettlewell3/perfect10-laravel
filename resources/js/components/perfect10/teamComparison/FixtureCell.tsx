type Props = {
    hGoals: number | null;
    aGoals: number | null;
    metaData: number | null;
    className?: string;
};

export default function FixtureCell({
    hGoals,
    aGoals,
    metaData,
    className = '',
}: Props) {
    const hasResult = hGoals !== null && aGoals !== null;
    const isPaired = metaData !== null;

    return (
        <div
            className={`fixtureCell ${className} ${
                !isPaired ? 'opaqueFixture' : ''
            } ${!hasResult ? 'emptyFixture' : ''}`}
        >
            <div className="resultCell">
                {hasResult ? `${hGoals}-${aGoals}` : '–'}
            </div>

            <div className="metaCell">{metaData ?? ''}</div>
        </div>
    );
}
