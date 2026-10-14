import { useState } from 'react';
import { router } from '@inertiajs/react';

export default function CreateClubForm() {
    const [clubName, setClubName] = useState('');
    const [message, setMessage] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const cleanName = clubName.trim();

        if (cleanName.length < 3 || cleanName.length > 16) {
            setMessage('Club name must be between 3 and 16 characters');

            return;
        }

        setMessage('');
        setIsSubmitting(true);

        router.post(
            `${window.location.pathname}/club`,
            {
                club_name: cleanName,
            },
            {
                onError: (errors) => {
                    setMessage(errors.club_name ?? 'Unable to create club.');
                },

                onFinish: () => {
                    setIsSubmitting(false);
                },
            },
        );
    };

    return (
        <>
            <p>Your club is currently inactive.</p>

            <form onSubmit={handleSubmit}>
                <label htmlFor="clubName">Club Name</label>

                <input
                    id="clubName"
                    type="text"
                    value={clubName}
                    minLength={3}
                    maxLength={16}
                    onChange={(event) => setClubName(event.target.value)}
                />

                {clubName.length > 0 && (
                    <p>Choose a club name between 3 and 16 characters.</p>
                )}

                <button type="submit" disabled={isSubmitting}>
                    {isSubmitting ? 'Creating...' : 'Create Club'}
                </button>
            </form>

            {message && <p className="profileMessage">{message}</p>}
        </>
    );
}
