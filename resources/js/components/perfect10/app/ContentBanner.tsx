import { router, usePage } from '@inertiajs/react';

import type { Perfect10PageProps } from '@/types/perfect10';

type Props = {
    title: string;
    backUrl?: string;
    backLabel?: string;
};

export default function ContentBanner({ title, backUrl, backLabel }: Props) {
    const { flavour } = usePage<Perfect10PageProps>().props;

    const dashboardUrl = `/${flavour.flavour_code.trim().toLowerCase()}`;

    const destination = backUrl ?? dashboardUrl;

    const handleBack = () => {
        router.visit(destination, {
            replace: true,
        });
    };

    return (
        <div className="contentBanner">
            <button
                type="button"
                className="backButton"
                onClick={handleBack}
                aria-label={backLabel ?? 'Go to Dashboard'}
            >
                <img src="/assets/svg/backArrow.svg" alt="" />
            </button>

            <div className="pageTitle">{title.toUpperCase()}</div>
        </div>
    );
}
