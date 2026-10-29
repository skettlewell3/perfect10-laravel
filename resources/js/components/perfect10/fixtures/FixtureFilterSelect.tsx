import { useEffect, useId, useRef, useState } from 'react';

export type FixtureFilterSelectOption = {
    value: number | string;
    label: string;
};

type Props = {
    value: number | string | null;
    options: FixtureFilterSelectOption[];
    placeholder: string;
    onChange: (value: number | string | null) => void;
    disabled?: boolean;
    active?: boolean;
};

export default function FixtureFilterSelect({
    value,
    options,
    placeholder,
    onChange,
    disabled = false,
    active = false,
}: Props) {
    const id = useId();
    const containerRef = useRef<HTMLDivElement>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const listRef = useRef<HTMLDivElement>(null);
    const optionRefs = useRef<(HTMLDivElement | null)[]>([]);
    const searchRef = useRef('');
    const searchTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const [open, setOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);

    const allOptions: FixtureFilterSelectOption[] = [
        { value: '', label: placeholder },
        ...options,
    ];

    const selectedIndex = allOptions.findIndex(
        (option) => option.value === (value ?? ''),
    );

    const selectedOption = allOptions[selectedIndex];

    const listboxId = `${id}-listbox`;

    const selectOption = (index: number) => {
        const option = allOptions[index];
        if (!option) return;

        onChange(option.value === '' ? null : option.value);
        setOpen(false);
        triggerRef.current?.focus();
    };

    const openList = (initialIndex?: number) => {
        if (disabled) return;

        setActiveIndex(
            initialIndex ?? (selectedIndex >= 0 ? selectedIndex : 0),
        );
        setOpen(true);
    };

    const closeList = (restoreFocus = true) => {
        setOpen(false);

        if (restoreFocus) {
            triggerRef.current?.focus();
        }
    };

    useEffect(() => {
        if (!open) return;

        listRef.current?.focus();

        optionRefs.current[activeIndex]?.scrollIntoView({
            block: 'nearest',
        });
    }, [open, activeIndex]);

    useEffect(() => {
        if (!open) return;

        const handlePointerDown = (event: PointerEvent) => {
            if (
                event.target instanceof Node &&
                !containerRef.current?.contains(event.target)
            ) {
                setOpen(false);
            }
        };

        document.addEventListener('pointerdown', handlePointerDown);

        return () => {
            document.removeEventListener('pointerdown', handlePointerDown);
        };
    }, [open]);

    useEffect(() => {
        return () => {
            if (searchTimerRef.current !== null) {
                clearTimeout(searchTimerRef.current);
            }
        };
    }, []);

    const handleTriggerKeyDown = (
        event: React.KeyboardEvent<HTMLButtonElement>,
    ) => {
        if (disabled) return;

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            openList();
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            openList();
        }

        if (event.key === 'Home') {
            event.preventDefault();
            openList(0);
        }

        if (event.key === 'End') {
            event.preventDefault();
            openList(allOptions.length - 1);
        }
    };

    const handleListKeyDown = (event: React.KeyboardEvent<HTMLDivElement>) => {
        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                setActiveIndex((previous) =>
                    Math.min(previous + 1, allOptions.length - 1),
                );
                return;

            case 'ArrowUp':
                event.preventDefault();
                setActiveIndex((previous) => Math.max(previous - 1, 0));
                return;

            case 'Home':
                event.preventDefault();
                setActiveIndex(0);
                return;

            case 'End':
                event.preventDefault();
                setActiveIndex(allOptions.length - 1);
                return;

            case 'PageDown':
                event.preventDefault();
                setActiveIndex((previous) =>
                    Math.min(previous + 7, allOptions.length - 1),
                );
                return;

            case 'PageUp':
                event.preventDefault();
                setActiveIndex((previous) => Math.max(previous - 7, 0));
                return;

            case 'Enter':
            case ' ':
                event.preventDefault();
                selectOption(activeIndex);
                return;

            case 'Escape':
                event.preventDefault();
                closeList();
                return;

            case 'Tab':
                closeList(false);
                return;

            default:
                break;
        }

        if (
            event.key.length !== 1 ||
            event.ctrlKey ||
            event.altKey ||
            event.metaKey
        ) {
            return;
        }

        const character = event.key.toLowerCase();

        searchRef.current += character;

        if (searchTimerRef.current !== null) {
            clearTimeout(searchTimerRef.current);
        }

        searchTimerRef.current = setTimeout(() => {
            searchRef.current = '';
            searchTimerRef.current = null;
        }, 600);

        const search = searchRef.current;

        const findMatch = (query: string) => {
            for (let offset = 1; offset <= allOptions.length; offset++) {
                const index = (activeIndex + offset) % allOptions.length;

                if (allOptions[index].label.toLowerCase().startsWith(query)) {
                    return index;
                }
            }

            return -1;
        };

        let matchIndex = findMatch(search);

        if (matchIndex === -1 && search.length > 1) {
            searchRef.current = character;
            matchIndex = findMatch(character);
        }

        if (matchIndex !== -1) {
            setActiveIndex(matchIndex);
        }
    };

    return (
        <div
            ref={containerRef}
            className={`filterSelectContainer customFilterSelect ${
                open ? 'isOpen' : ''
            }`}
            onBlur={(event) => {
                if (
                    !event.currentTarget.contains(
                        event.relatedTarget as Node | null,
                    )
                ) {
                    setOpen(false);
                }
            }}
        >
            <button
                ref={triggerRef}
                type="button"
                className={`fixtureSelectTrigger ${active ? 'active' : ''}`}
                disabled={disabled}
                aria-label={placeholder}
                aria-haspopup="listbox"
                aria-expanded={open}
                aria-controls={listboxId}
                onClick={() => {
                    if (open) {
                        closeList();
                    } else {
                        openList();
                    }
                }}
                onKeyDown={handleTriggerKeyDown}
            >
                <span className="fixtureSelectValue">
                    {selectedOption?.label ?? placeholder}
                </span>

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    focusable="false"
                    className="fixtureSelectChevron"
                >
                    <path
                        d="m6 9 6 6 6-6"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                </svg>
            </button>

            {open && (
                <div
                    id={listboxId}
                    ref={listRef}
                    className="fixtureSelectOptions"
                    role="listbox"
                    tabIndex={-1}
                    aria-label={placeholder}
                    aria-activedescendant={`${id}-option-${activeIndex}`}
                    onKeyDown={handleListKeyDown}
                >
                    {allOptions.map((option, index) => (
                        <div
                            key={`${option.value}-${index}`}
                            id={`${id}-option-${index}`}
                            ref={(element) => {
                                optionRefs.current[index] = element;
                            }}
                            role="option"
                            aria-selected={option.value === (value ?? '')}
                            className={[
                                'fixtureSelectOption',
                                index === activeIndex ? 'focused' : '',
                                option.value === (value ?? '')
                                    ? 'selected'
                                    : '',
                            ]
                                .filter(Boolean)
                                .join(' ')}
                            onMouseEnter={() => setActiveIndex(index)}
                            onClick={() => selectOption(index)}
                        >
                            {option.label}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
