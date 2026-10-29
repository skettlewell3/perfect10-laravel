type Props = {
    showLeaderboard: boolean;
    onToggle: () => void;
};

export default function FixtureLeaderboardToggle({
    showLeaderboard,
    onToggle,
}: Props) {
    return (
        <button
            type="button"
            className="leaderboardToggle"
            aria-expanded={showLeaderboard}
            onClick={onToggle}
        >
            {showLeaderboard ? 'Hide Leaderboard' : 'Show Leaderboard'}
        </button>
    );
}
