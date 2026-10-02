import { Head, usePage } from '@inertiajs/react';

import type { Flavour } from '@/types/perfect10';

import ContentBanner from './ContentBanner';

type Props = {
    title: string;
};

export default function PlaceholderPage({ title }: Props) {
    const { flavour } = usePage<{
        flavour: Flavour;
    }>().props;

    return (
        <>
            <Head title={title} />

            <div className="pageShell">
                <ContentBanner
                    title={title}
                    fallbackUrl={`/?flavour=${encodeURIComponent(flavour.flavour_code)}`}
                />
            </div>
        </>
    );
}
