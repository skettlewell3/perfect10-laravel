import { useState } from 'react';
import { router } from '@inertiajs/react';

import CopyCode from '@/components/perfect10/app/CopyCode';

export type ProfileDetailsData = {
    profile_id: string;
    display_name: string;
    username: string;
    created_at: string;
};

type Props = {
    profile: ProfileDetailsData;
};

export default function ProfileDetailsCard({ profile }: Props) {
    const [displayName, setDisplayName] = useState(profile.display_name);

    const [message, setMessage] = useState('');

    const [isSaving, setIsSaving] = useState(false);

    const createdDate = new Date(profile.created_at).toLocaleDateString(
        'en-GB',
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        },
    );

    const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        setMessage('');
        setIsSaving(true);

        router.patch(
            window.location.pathname,
            {
                display_name: displayName,
            },
            {
                preserveScroll: true,

                onSuccess: () => {
                    setMessage('Display name updated');
                },

                onError: (errors) => {
                    setMessage(
                        errors.display_name ?? 'Unable to update display name.',
                    );
                },

                onFinish: () => {
                    setIsSaving(false);
                },
            },
        );
    };

    return (
        <section className="accountCard">
            <h2>
                Profile Details
                <div className="accountUsername">
                    <span>#{profile.username}</span>

                    <CopyCode value={profile.username} />
                </div>
            </h2>

            <p className="accountCreated">Member since {createdDate}</p>

            <form onSubmit={handleSubmit}>
                <label htmlFor="displayName">Display Name</label>

                <input
                    id="displayName"
                    type="text"
                    value={displayName}
                    minLength={3}
                    maxLength={16}
                    onChange={(event) => setDisplayName(event.target.value)}
                />

                <button type="submit" disabled={isSaving}>
                    {isSaving ? 'Saving...' : 'Save Changes'}
                </button>
            </form>

            {message && <p className="accountMessage">{message}</p>}
        </section>
    );
}
