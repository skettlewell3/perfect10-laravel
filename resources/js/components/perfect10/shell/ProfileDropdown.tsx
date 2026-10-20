import { useEffect, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';

export default function ProfileDropdown() {
    const [open, setOpen] = useState(false);

    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (ref.current && !ref.current.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, []);

    return (
        <div id="profileDropdown" ref={ref}>
            <button
                id="profileDropdownToggle"
                type="button"
                onClick={() => setOpen((previous) => !previous)}
                aria-label="Profile menu"
                aria-expanded={open}
            >
                <img
                    src="/assets/logos/FullLogo_Transparent_smallr.png"
                    alt="Perfect10"
                />
            </button>

            {open && (
                <div className="profileDropdownMenu left">
                    <Link
                        className="profileDropdownItem"
                        href="/profile"
                        onClick={() => setOpen(false)}
                    >
                        Profile
                    </Link>

                    <button
                        type="button"
                        className="profileDropdownItem disabled"
                        disabled
                    >
                        Hall of Fame <span className="tag">Soon!</span>
                    </button>

                    <button
                        type="button"
                        className="profileDropdownItem disabled"
                        disabled
                    >
                        Rules <span className="tag">Soon!</span>
                    </button>

                    <button
                        type="button"
                        className="profileDropdownItem logout disabled"
                        disabled
                    >
                        Sign Out
                    </button>
                </div>
            )}
        </div>
    );
}
