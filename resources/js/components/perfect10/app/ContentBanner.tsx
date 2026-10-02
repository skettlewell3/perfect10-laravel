import { router } from '@inertiajs/react';

type Props = {
    title: string;
    fallbackUrl?: string;
};

export default function ContentBanner({ title, fallbackUrl = '/' }: Props) {
    const handleBack = () => {
        if (window.history.length > 1) {
            window.history.back();
            return;
        }

        router.visit(fallbackUrl);
    };

    return (
        <div className="contentBanner">
            <button
                type="button"
                className="backButton"
                onClick={handleBack}
                aria-label="Go Back"
            >
                <img src="/assets/svg/backArrow.svg" alt="" />
            </button>

            <div className="pageTitle">{title.toUpperCase()}</div>
        </div>
    );
}
