type FixtureExpandButtonProps = {
    isExpanded?: boolean;
    onClick: () => void;
    disabled?: boolean;
};

export default function FixtureExpandButton({
    isExpanded = false,
    onClick,
    disabled = false,
}: FixtureExpandButtonProps) {
    return (
        <button
            type="button"
            className="fixtureExpandButton"
            onClick={onClick}
            disabled={disabled}
            aria-expanded={isExpanded}
            aria-label={
                isExpanded
                    ? 'Collapse fixture details'
                    : 'Expand fixture details'
            }
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                {isExpanded ? (
                    <path d="M18 15l-6-6-6 6" />
                ) : (
                    <path d="M6 9l6 6 6-6" />
                )}
            </svg>
        </button>
    );
}
