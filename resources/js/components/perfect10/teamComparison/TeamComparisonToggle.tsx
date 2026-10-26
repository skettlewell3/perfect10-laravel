import type { TeamComparisonMode } from '@/types/perfect10/team-comparison';

type Props = {
    comparisonMode: TeamComparisonMode;
    onModeChange: (mode: TeamComparisonMode) => void;
};

export default function TeamComparisonToggle({
    comparisonMode,
    onModeChange,
}: Props) {
    return (
        <div className="comparisonToggle">
            <span
                role="button"
                tabIndex={0}
                aria-pressed={comparisonMode === 'generic'}
                className={comparisonMode === 'generic' ? 'active' : ''}
                onClick={() => onModeChange('generic')}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        onModeChange('generic');
                    }
                }}
            >
                Overall
            </span>

            <span
                role="button"
                tabIndex={0}
                aria-pressed={comparisonMode === 'direct'}
                className={comparisonMode === 'direct' ? 'active' : ''}
                onClick={() => onModeChange('direct')}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        onModeChange('direct');
                    }
                }}
            >
                Direct
            </span>
        </div>
    );
}
