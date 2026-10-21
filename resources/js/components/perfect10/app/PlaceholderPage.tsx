import { Head } from '@inertiajs/react';

import ContentBanner from './ContentBanner';

type Props = {
    title: string;
};

export default function PlaceholderPage({ title }: Props) {

    return (
        <>
            <Head title={title} />

            <div className="pageShell">
                <ContentBanner
                    title={title}
                />
            </div>
        </>
    );
}
