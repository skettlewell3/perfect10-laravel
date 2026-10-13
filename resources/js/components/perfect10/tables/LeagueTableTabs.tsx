export type LeagueTableView =
    | 'predicted'
    | 'actual'
    | 'comparison';

type Props = {
    view: LeagueTableView;
    setView: (view: LeagueTableView) => void;
};

export default function LeagueTableTabs({
    view,
    setView,
}: Props) {
    const tabs: {
        key: LeagueTableView;
        label: string;
    }[] = [
        {
            key: 'predicted',
            label: 'Predicted',
        },
        {
            key: 'actual',
            label: 'Actual',
        },
        {
            key: 'comparison',
            label: 'Compare',
        },
    ];

    return (
        <div className="leagueTableTabs">
            {tabs.map((tab) => (
                <button
                    key={tab.key}
                    type="button"
                    className={
                        view === tab.key
                            ? 'active'
                            : ''
                    }
                    onClick={() => setView(tab.key)}
                >
                    {tab.label}
                </button>
            ))}
        </div>
    );
}