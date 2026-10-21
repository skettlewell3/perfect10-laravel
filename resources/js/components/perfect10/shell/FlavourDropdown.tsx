import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import type { Flavour, Perfect10PageProps } from '@/types/perfect10';

export default function FlavourDropdown() {
    const [open, setOpen] = useState(false);

    const { flavours, flavour } = usePage<Perfect10PageProps>().props;

    function handleSelect(selectedFlavour: Flavour) {
        setOpen(false);
        
        const selectedCode = selectedFlavour.flavour_code.toLowerCase();
        
        const currentPath = window.location.pathname;
        const currentParams = new URLSearchParams(window.location.search);
        
        const segments = currentPath.split('/').filter(Boolean);
        
        const isProfilePage = segments[0] === 'profile';
        
        if (isProfilePage) {
            router.get(`/${selectedCode}`);
            return;
        }
    
        if (selectedFlavour.flavour_id === flavour.flavour_id) {
            return;
        }
    
        segments[0] = selectedCode;
    
        const nextPath = `/${segments.join('/')}`;
    
        const nextParams: Record<string, string> = {};
    
        /*
         * Preserve filters that are meaningful across flavours.
         *
         * Stage IDs are flavour-specific, so deliberately do not
         * carry the current stage across.
         */
        if (segments[1] === 'leaderboards') {
            const scope = currentParams.get('scope');
            const population = currentParams.get('population');
        
            if (scope) {
                nextParams.scope = scope;
            }
        
            if (population) {
                nextParams.population = population;
            }
        }
    
        router.get(nextPath, nextParams, {
            preserveScroll: true,
        });
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
