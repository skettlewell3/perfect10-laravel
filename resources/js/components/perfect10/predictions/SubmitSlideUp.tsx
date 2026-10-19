
import type { ReactNode } from 'react';

type SubmitSlideUpProps = {
    visible: boolean;
    canSubmit: boolean;
    isUpdating: boolean;
    changedCount: number;
    totalCount: number;
    processing: boolean;
    error?: string | null;
    onSubmit: () => void;
};

export default function SubmitSlideUp({
    visible,
    canSubmit,
    isUpdating,
    changedCount,
    totalCount,
    processing,
    error = null,
    onSubmit,
}: SubmitSlideUpProps) {
    if (!visible) return null;

    const submissionLabel = processing
        ? 'Submitting...'
        : isUpdating
          ? 'Update Predictions'
          : 'Submit Predictions';

    const description: ReactNode = isUpdating
        ? `${changedCount} prediction${changedCount === 1 ? '' : 's'} changed`
        : `${changedCount} / ${totalCount} predictions ready`;

    return (
        <div
            className="submitSlideUp"
            role="region"
            aria-label="Prediction submission"
        >
            <div className="submitSlideUpContent">
                <div className="submitSlideUpInfo">
                    <span className="submitSlideUpDescription">
                        {description}
                    </span>

                    {error && (
                        <p className="submitSlideUpError" role="alert">
                            {error}
                        </p>
                    )}
                </div>

                <button
                    type="button"
                    className="submitSlideUpButton"
                    disabled={!canSubmit || processing}
                    onClick={onSubmit}
                >
                    {submissionLabel}
                </button>
            </div>
        </div>
    );
}
