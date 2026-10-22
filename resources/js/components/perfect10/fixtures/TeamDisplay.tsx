import type { CSSProperties } from 'react';

type TeamDisplayProps = {
    name: string | null;
    variant: 'flag' | 'colours';

    flagCode?: string | null;

    primaryColor?: string | null;
    secondaryColor?: string | null;
    tertiaryColor?: string | null;

    isWinner?: boolean;
    isLoser?: boolean;
    showProgressionMarker?: boolean;
};

function getTeamStyle(
    primary?: string | null,
    secondary?: string | null,
    tertiary?: string | null,
): CSSProperties {
    const style: CSSProperties = {};

    if (primary) {
        style.backgroundColor = primary;
    }

    if (secondary) {
        style.color = secondary;
    }

    if (tertiary) {
        style.textShadow = `
            -0.5px -0.5px 0 ${tertiary},
             0.5px -0.5px 0 ${tertiary},
            -0.5px  0.5px 0 ${tertiary},
             0.5px  0.5px 0 ${tertiary}
        `;
    }

    return style;
}

export default function TeamDisplay({
    name,
    variant,
    flagCode,
    primaryColor,
    secondaryColor,
    tertiaryColor,
    isWinner = false,
    isLoser = false,
    showProgressionMarker = false,
}: TeamDisplayProps) {
    const displayName = name || 'TBC';

    const isLongName = displayName.length > (variant === 'flag' ? 15 : 10);

    const teamStyle =
        variant === 'colours'
            ? getTeamStyle(primaryColor, secondaryColor, tertiaryColor)
            : undefined;

    const showFlag = variant === 'flag' && Boolean(name) && Boolean(flagCode);

    return (
        <div className="teamBlock" style={teamStyle}>
            {showFlag && (
                <div className="flagWrap">
                    <img
                        src={`https://flagcdn.com/w40/${flagCode!.toLowerCase()}.png`}
                        alt={`${displayName} flag`}
                        className="wcFlag"
                    />
                </div>
            )}

            <div
                className={[
                    'teamName',
                    isLongName ? 'longName' : '',
                    isWinner ? 'winner' : '',
                    isLoser ? 'loser' : '',
                ]
                    .filter(Boolean)
                    .join(' ')}
            >
                {displayName}

                {showProgressionMarker && (
                    <sup className="qualifierMark">*</sup>
                )}
            </div>
        </div>
    );
}
