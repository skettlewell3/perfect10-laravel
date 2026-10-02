import { Head, usePage } from '@inertiajs/react';

import ContentBanner from '@/components/perfect10/app/ContentBanner';
import type { Perfect10PageProps } from '@/types/perfect10';

export default function FixturesPage() {
    const { flavour } = usePage<Perfect10PageProps>().props;

    return (
        <>
            <Head title="Fixtures" />

            <div className="pageShell">
                <ContentBanner
                    title="Fixtures"
                    fallbackUrl={`/?flavour=${encodeURIComponent(flavour.flavour_code)}`}
                />
            </div>
        </>
    );
}
