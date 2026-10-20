import { router, usePage } from '@inertiajs/react';

import type { Perfect10PageProps } from '@/types/perfect10';

type Props = {
    title: string;
    fallbackUrl?: string;
};

export default function ContentBanner({ title, fallbackUrl }: Props) {
    const { flavour } = usePage<Perfect10PageProps>().props;

    const handleBack = () => {
        const dashboardUrl =
            fallbackUrl ?? `/${flavour.flavour_code.toLowerCase()}`;

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