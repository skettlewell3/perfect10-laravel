import { router } from '@inertiajs/react';

interface Stage {
    stage_id: number;
    stage_name: string;
}

interface Filters {
    scopeType: string;
    scopeId: number | null;
    population: string;
}

interface Props {
    leaderboardStages: Stage[];
    filters: Filters;
    flavourCode?: string | null;
}

export default function LeaderboardFilters({
    leaderboardStages,
    filters,
    flavourCode,
}: Props) {
    const visit = (params: {
        scope?: string;
        stage?: number | null;
        population?: string;
    }) => {
        const scope = params.scope ?? filters.scopeType;

        const stage =
            scope === 'stage'
                ? params.stage === undefined
                    ? filters.scopeType === 'stage'
                        ? filters.scopeId
                        : null
                    : params.stage
                : null;

        const url = flavourCode
            ? `/${flavourCode.toLowerCase()}/leaderboards`
            : window.location.pathname;

        router.get(
            url,
            {
                scope,
                ...(stage !== null ? { stage } : {}),
                population: params.population ?? filters.population,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: false,
            },
        );
    };

    const handleCampaign = () => {
        visit({
            scope: 'campaign',
            stage: null,
        });
    };

    const handleStage = () => {
        visit({
            scope: 'stage',
            stage: null,
        });
    };

    const handleStageChange = (event: React.ChangeEvent<HTMLSelectElement>) => {
        visit({
            scope: 'stage',
            stage: Number(event.target.value),
        });
    };

    const populationOpts = [
        { label: 'Global', value: 'global' },
        { label: 'Users', value: 'users' },
        { label: 'Clubs', value: 'clubs' },
        { label: 'Bots', value: 'bots' },
    ];

    return (
        <div className="filters leaderboardFilters">
            <div className="filterFixedRow">
                <div className="fixStatusFilter">
                    {populationOpts.map((opt) => (
                        <button
                            key={opt.value}
                            type="button"
                            className={
                                filters.population === opt.value ? 'active' : ''
                            }
                            onClick={() =>
                                visit({
                                    population: opt.value,
                                })
                            }
                        >
                            {opt.label}
                        </button>
                    ))}
                </div>
            </div>

            <div className="filterRow">
                <div className="scopeTypeFilter">
                    <button
                        type="button"
                        className={
                            filters.scopeType === 'campaign' ? 'active' : ''
                        }
                        onClick={handleCampaign}
                    >
                        CAMPAIGN
                    </button>

                    <button
                        type="button"
                        className={
                            filters.scopeType === 'stage' ? 'active' : ''
                        }
                        onClick={handleStage}
                    >
                        ROUND
                    </button>
                </div>

                <div
                    className={`scopeIdFilter ${
                        filters.scopeType === 'stage' ? 'active' : ''
                    }`}
                >
                    {filters.scopeType === 'stage' && (
                        <select
                            name="scopeIdFilter"
                            id="scopeIdFilter"
                            value={filters.scopeId ?? ''}
                            onChange={handleStageChange}
                        >
                            {leaderboardStages.map((stage) => (
                                <option
                                    key={stage.stage_id}
                                    value={stage.stage_id}
                                >
                                    {stage.stage_name}
                                </option>
                            ))}
                        </select>
                    )}
                </div>
            </div>
        </div>
    );
}
