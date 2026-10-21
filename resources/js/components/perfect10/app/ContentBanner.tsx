import { router, usePage } from '@inertiajs/react';

import type { Perfect10PageProps } from '@/types/perfect10';

type Props = {
    title: string;
};

export default function ContentBanner({ title }: Props) {
    const { flavour } = usePage<Perfect10PageProps>().props;

    const handleBack = () => {
        router.visit(`/${flavour.flavour_code.toLowerCase()}`, {
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