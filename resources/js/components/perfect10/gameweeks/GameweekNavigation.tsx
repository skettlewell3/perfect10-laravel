import { useEffect, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { router } from '@inertiajs/react';

type GameweekNavigationItem = {
    gameweek_id: number;
    gameweek_number: number;
    status: string;
};

type GameweekNavigationProps = {
    flavourCode: string;
    gameweeks: GameweekNavigationItem[];
    selectedGameweekId: number | null;
    activeGameweekId: number | null;
};

export default function GameweekNavigation({
    flavourCode,
    gameweeks,
    selectedGameweekId,
    activeGameweekId,
}: GameweekNavigationProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [focusedIndex, setFocusedIndex] = useState(0);

    const containerRef = useRef<HTMLDivElement>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const optionRefs = useRef<(HTMLButtonElement | null)[]>([]);

    const selectedIndex = gameweeks.findIndex(
        (gameweek) => gameweek.gameweek_id === selectedGameweekId,
    );

    const selectedGameweek =
        selectedIndex >= 0 ? gameweeks[selectedIndex] : null;

    const previousGameweek =
        selectedIndex > 0 ? gameweeks[selectedIndex - 1] : null;

    const nextGameweek =
        selectedIndex >= 0 && selectedIndex < gameweeks.length - 1
            ? gameweeks[selectedIndex + 1]
            : null;

    const navigate = (gameweekNumber: number) => {
        setIsOpen(false);

        router.get(
            `/${encodeURIComponent(flavourCode.trim().toLowerCase())}/gameweek/${gameweekNumber}`,
            {},
            {
                preserveState: false,
                preserveScroll: false,
            },
        );
    };

    const openOptions = () => {
        setFocusedIndex(Math.max(0, selectedIndex));
        setIsOpen(true);
    };

    useEffect(() => {
        if (!isOpen) return;

        const handleOutsideClick = (event: MouseEvent) => {
            if (
                containerRef.current &&
                !containerRef.current.contains(event.target as Node)
            ) {
                setIsOpen(false);
            }
        };

        const handleEscape = (event: globalThis.KeyboardEvent) => {
            if (event.key === 'Escape') {
                setIsOpen(false);
                triggerRef.current?.focus();
            }
        };

        document.addEventListener('mousedown', handleOutsideClick);
        document.addEventListener('keydown', handleEscape);

        return () => {
            document.removeEventListener('mousedown', handleOutsideClick);
            document.removeEventListener('keydown', handleEscape);
        };
    }, [isOpen]);

    useEffect(() => {
        if (isOpen) {
            optionRefs.current[focusedIndex]?.focus();
        }
    }, [isOpen, focusedIndex]);

    const handleOptionKeyDown = (
        event: KeyboardEvent<HTMLButtonElement>,
        index: number,
    ) => {
        let nextIndex = index;

        switch (event.key) {
            case 'ArrowDown':
                nextIndex = Math.min(index + 1, gameweeks.length - 1);
                break;

            case 'ArrowUp':
                nextIndex = Math.max(index - 1, 0);
                break;

            case 'Home':
                nextIndex = 0;
                break;

            case 'End':
                nextIndex = gameweeks.length - 1;
                break;

            default:
                return;
        }

        event.preventDefault();
        setFocusedIndex(nextIndex);
    };

    if (gameweeks.length === 0) {
        return null;
    }

    return (
        <nav className="gameweekNavigation" aria-label="Gameweek navigation">
            <button
                type="button"
                className="gameweekNavigationArrow previous"
                disabled={previousGameweek === null}
                onClick={() => {
                    if (previousGameweek) {
                        navigate(previousGameweek.gameweek_number);
                    }
                }}
                aria-label="Previous gameweek"
            >
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    className="gameweekNavigationChevron"
                >
                    <path d="m15 18-6-6 6-6" />
                </svg>
            </button>

            <div
                ref={containerRef}
                className={`customFilterSelect gameweekNavigationSelect ${
                    isOpen ? 'isOpen' : ''
                }`}
            >
                <button
                    ref={triggerRef}
                    type="button"
                    className="fixtureSelectTrigger gameweekNavigationTrigger"
                    aria-label="Select gameweek"
                    aria-haspopup="listbox"
                    aria-expanded={isOpen}
                    onClick={() => {
                        if (isOpen) {
                            setIsOpen(false);
                        } else {
                            openOptions();
                        }
                    }}
                    onKeyDown={(event) => {
                        if (
                            !isOpen &&
                            (event.key === 'ArrowDown' ||
                                event.key === 'ArrowUp')
                        ) {
                            event.preventDefault();
                            openOptions();
                        }
                    }}
                >
                    <span className="fixtureSelectValue">
                        {selectedGameweek
                            ? `Gameweek ${selectedGameweek.gameweek_number}${
                                  selectedGameweek.gameweek_id ===
                                  activeGameweekId
                                      ? ' (Active)'
                                      : ''
                              }`
                            : 'Select Gameweek'}
                    </span>

                    <svg
                        className="fixtureSelectChevron"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        aria-hidden="true"
                    >
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                </button>

                {isOpen && (
                    <div
                        className="fixtureSelectOptions gameweekNavigationOptions"
                        role="listbox"
                        aria-label="Gameweeks"
                    >
                        {gameweeks.map((gameweek, index) => {
                            const isSelected =
                                gameweek.gameweek_id === selectedGameweekId;

                            return (
                                <button
                                    key={gameweek.gameweek_id}
                                    ref={(element) => {
                                        optionRefs.current[index] = element;
                                    }}
                                    type="button"
                                    role="option"
                                    aria-selected={isSelected}
                                    tabIndex={index === focusedIndex ? 0 : -1}
                                    className={`fixtureSelectOption gameweekNavigationOption ${
                                        isSelected ? 'selected' : ''
                                    }`}
                                    onKeyDown={(event) =>
                                        handleOptionKeyDown(event, index)
                                    }
                                    onClick={() =>
                                        navigate(gameweek.gameweek_number)
                                    }
                                >
                                    Gameweek {gameweek.gameweek_number}
                                    {gameweek.gameweek_id === activeGameweekId
                                        ? ' (Active)'
                                        : ''}
                                </button>
                            );
                        })}
                    </div>
                )}
            </div>

            <button
                type="button"
                className="gameweekNavigationArrow next"
                disabled={nextGameweek === null}
                onClick={() => {
                    if (nextGameweek) {
                        navigate(nextGameweek.gameweek_number);
                    }
                }}
                aria-label="Next gameweek"
            >
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    className="gameweekNavigationChevron"
                >
                    <path d="m9 18 6-6-6-6" />
                </svg>
            </button>
        </nav>
    );
}

export type { GameweekNavigationItem };
