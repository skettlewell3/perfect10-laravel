
import { router } from '@inertiajs/react';

type Props = {
    title: string;
    fallbackUrl?: string;
};

export default function ContentBanner({ title, fallbackUrl }: Props) {
    const handleBack = () => {
        const flavour = window.location.pathname.split('/')[1];

        const dashboardUrl = fallbackUrl ?? (
            flavour ? `/${flavour}` : '/'
        );

        router.visit(dashboardUrl, {
            replace: true,
        });
    };

    return (
        <div className="contentBanner">
            <button
                type="button"
                className="backButton"
                onClick={handleBack}
                aria-label="Go to Dashboard"
            >
                <img src="/assets/svg/backArrow.svg" alt="" />
            </button>

            <div className="pageTitle">{title.toUpperCase()}</div>
        </div>
    );
}
