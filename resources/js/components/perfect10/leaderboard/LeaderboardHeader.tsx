export default function LeaderboardHeader() {
    return (
        <div className="boardHeaderContainer">
            <div className="leaderboardHeader leaderboardLayout">
                <div className="focus" title="Rank">
                    #
                </div>

                <div className="normal" title="Display Name">
                    Name
                </div>

                <div className="normal" title="Predicted Fixtures">
                    P
                </div>

                <div className="focus double">
                    <div title="Points">Pts</div>

                    <div title="# of Perfect10s">10s</div>
                </div>

                <div className="normal" title="# of Correct Results">
                    R
                </div>

                <div className="normal" title="# of Correct Home">
                    H
                </div>

                <div className="normal" title="# of Correct Away">
                    A
                </div>

                <div className="normal" title="# of Correct Goal Differences">
                    GD
                </div>

                <div className="normal" title="# of Correct Goals">
                    G
                </div>
            </div>
        </div>
    );
}
