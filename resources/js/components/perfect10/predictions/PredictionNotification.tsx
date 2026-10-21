import type { ReactNode } from 'react';

export type PredictionNotificationData = {
    type: 'success' | 'error';
    isUpdating: boolean;
    message?: string;
};

type PredictionNotificationProps = {
    notification: PredictionNotificationData | null;
    onClose: () => void;
};

export default function PredictionNotification({
    notification,
    onClose,
}: PredictionNotificationProps) {
    if (!notification) return null;

    const isSuccess = notification.type === 'success';

    const title = isSuccess
        ? notification.isUpdating
            ? 'Predictions Updated!'
            : 'Predictions Submitted!'
        : 'Submission Failed';

    const description = isSuccess
        ? notification.isUpdating
            ? 'Your predictions have been updated successfully.'
            : 'Thanks for playing Perfect10. Good luck!'
        : notification.message ??
          "Couldn't submit your predictions. Please try again.";

    const icon: ReactNode = isSuccess ? '✓' : '!';

    return (
        <div
            className={`predictionNotification ${
                isSuccess
                    ? 'predictionNotificationSuccess'
                    : 'predictionNotificationError'
            }`}
            role={isSuccess ? 'status' : 'alert'}
        >
            <div className="predictionNotificationIcon" aria-hidden="true">
                {icon}
            </div>

            <div className="predictionNotificationContent">
                <h3 className="predictionNotificationTitle">
                    {title}
                </h3>

                <p className="predictionNotificationDescription">
                    {description}
                </p>
            </div>

            <button
                type="button"
                className="predictionNotificationClose"
                onClick={onClose}
                aria-label="Dismiss notification"
            >
                ×
            </button>
        </div>
    );
}