import { router } from '@inertiajs/react';
import { useState } from 'react';

import type { Flavour } from '@/types/perfect10';

type Props = {
    clubCode: string;
    flavour: Flavour;
};

export default function SendClubInviteForm({ clubCode, flavour }: Props) {
    const [username, setUsername] = useState('');
    const [message, setMessage] = useState('');

    const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const cleanUsername = username.trim();

        if (!cleanUsername) {
            return;
        }

        router.post(
            `/${flavour.flavour_code.toLowerCase()}/clubs/${clubCode}/invites`,
            {
                username: cleanUsername,
            },
            {
                preserveScroll: true,

                onSuccess: () => {
                    setUsername('');
                    setMessage('Invite sent');
                },

                onError: (errors) => {
                    setMessage(errors.username ?? 'Unable to send invite');
                },
            },
        );
    };

    return (
        <>
            <form className="clubInviteForm" onSubmit={handleSubmit}>
                <label>Invite Users</label>

                <input
                    type="text"
                    value={username}
                    onChange={(event) => setUsername(event.target.value)}
                    placeholder="Insert Username e.g user_########"
                />

                <button type="submit">Send Invite</button>
            </form>

            {message && <p>{message}</p>}
        </>
    );
}
