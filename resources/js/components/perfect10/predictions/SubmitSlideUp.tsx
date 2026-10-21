type SubmitSlideUpProps = {
    visible: boolean;
    canSubmit: boolean;
    isUpdating: boolean;
    processing: boolean;
    onSubmit: () => void;
};

export default function SubmitSlideUp({
    visible,
    canSubmit,
    isUpdating,
    processing,
    onSubmit,
}: SubmitSlideUpProps) {
    if (!visible) return null;

    const submissionLabel = isUpdating
        ? 'Update Predictions'
        : 'Submit Predictions';

    return (
        <div className="bulkSubmitBar">
            <button
                type="button"
                disabled={!canSubmit || processing}
                onClick={onSubmit}
                aria-busy={processing}
                aria-label={
                    processing
                        ? 'Submitting predictions'
                        : submissionLabel
                }
            >
                {processing ? (
                    <span
                        className="submitButtonSpinner"
                        aria-hidden="true"
                    />
                ) : (
                    submissionLabel
                )}
            </button>
        </div>
    );
}