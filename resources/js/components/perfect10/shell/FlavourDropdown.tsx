import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { Flavour, Perfect10PageProps } from '@/types/perfect10';

export default function FlavourDropdown() {
    const [open, setOpen] = useState(false);

    const { flavours, flavour } = usePage<Perfect10PageProps>().props;

    function handleSelect(selectedFlavour: Flavour) {
        if (selectedFlavour.flavour_id === flavour.flavour_id) {
            setOpen(false);
            return;
        }

        setOpen(false);

        router.get(
            '/',
            {
                flavour: selectedFlavour.flavour_code,
            },
            {
                preserveScroll: true,
            },
        );
    }

    return (
        <div id="flavourDropdown" className={open ? 'open' : ''}>
            <button
                id="flavourDropdownToggle"
                type="button"
                onClick={() => setOpen((previous) => !previous)}
            >
                <div className="flavourName">
                    {flavour.competition_code}
                    <span>▼</span>
                </div>
            </button>

            {open && (
                <ul id="flavourDropdownMenu">
                    {flavours.map((availableFlavour) => (
                        <li
                            key={availableFlavour.flavour_id}
                            onClick={() => handleSelect(availableFlavour)}
                            className={
                                availableFlavour.flavour_id ===
                                flavour.flavour_id
                                    ? 'active'
                                    : ''
                            }
                        >
                            <span>{availableFlavour.flavour_code}</span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
