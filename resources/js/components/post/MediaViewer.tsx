import { useEffect, useRef, useState, type UIEvent } from 'react';
import type { PostDetailTranslations, PostMedia } from '../../types/post';
import { ChevronLeftIcon, ChevronRightIcon, CloseIcon } from './icons';

interface MediaViewerProps {
    media: PostMedia[];
    translations: PostDetailTranslations;
}

export function MediaViewer({ media, translations }: MediaViewerProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);
    const closeButtonRef = useRef<HTMLButtonElement>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const lightboxRef = useRef<HTMLDivElement>(null);

    const closeImage = () => {
        setIsOpen(false);
        window.requestAnimationFrame(() => triggerRef.current?.focus());
    };

    useEffect(() => {
        if (!isOpen) return undefined;

        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeImage();
                return;
            }

            if (media.length > 1 && event.key === 'ArrowLeft') {
                event.preventDefault();
                setActiveIndex((current) => current === 0 ? media.length - 1 : current - 1);
                return;
            }

            if (media.length > 1 && event.key === 'ArrowRight') {
                event.preventDefault();
                setActiveIndex((current) => current === media.length - 1 ? 0 : current + 1);
                return;
            }

            if (event.key !== 'Tab') return;

            const focusable = Array.from(
                lightboxRef.current?.querySelectorAll<HTMLElement>(
                    'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
                ) ?? [],
            );

            if (focusable.length === 0) return;

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };

        document.addEventListener('keydown', handleKeyDown);
        closeButtonRef.current?.focus();

        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, media.length]);

    if (media.length === 0) return null;

    const openImage = (index: number, trigger: HTMLButtonElement) => {
        setActiveIndex(index);
        triggerRef.current = trigger;
        setIsOpen(true);
    };

    const handleScroll = (event: UIEvent<HTMLDivElement>) => {
        if (media.length < 2) return;

        const maxScroll = event.currentTarget.scrollWidth - event.currentTarget.clientWidth;
        if (maxScroll <= 0) return;

        setActiveIndex(Math.round((event.currentTarget.scrollLeft / maxScroll) * (media.length - 1)));
    };

    return (
        <div className="skuy-post-detail__media mb-4">
            {media.length === 1 ? (
                <button
                    ref={triggerRef}
                    type="button"
                    onClick={(event) => openImage(0, event.currentTarget)}
                    className="block w-full cursor-pointer overflow-hidden rounded-[var(--radius-medium)] border border-[var(--skuy-border-strong)] bg-[var(--skuy-surface-subtle)] text-start"
                    aria-label={translations.photo}
                >
                    <img src={media[0].url} alt={media[0].alt} className="h-auto w-full object-cover transition-opacity hover:opacity-90" />
                </button>
            ) : (
                <>
                    <div
                        data-media-slider="true"
                        onScroll={handleScroll}
                        className="skuy-media-track"
                    >
                        {media.map((item, index) => (
                            <button
                                key={item.url}
                                type="button"
                                data-media-slide="true"
                                onClick={(event) => openImage(index, event.currentTarget)}
                                className="skuy-media-item w-[85%] border-0 p-0 text-start sm:w-[48%]"
                                aria-label={translations.photo}
                            >
                                <img src={item.url} alt={item.alt} className="h-full w-full object-cover" />
                            </button>
                        ))}
                    </div>
                    <div data-media-indicator="true" className="skuy-media-dots mb-2" aria-hidden="true">
                        {media.map((item, index) => (
                            <div
                                key={item.url}
                                data-media-indicator-dot="true"
                                className={`skuy-media-dot${activeIndex === index ? ' is-active' : ''}`}
                            />
                        ))}
                    </div>
                </>
            )}

            {isOpen && (
                <div
                    ref={lightboxRef}
                    className="skuy-lightbox fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-2 backdrop-blur-sm"
                    role="dialog"
                    aria-modal="true"
                    aria-label={translations.full_photo}
                    onClick={(event) => {
                        if (event.target === event.currentTarget) closeImage();
                    }}
                >
                    <button
                        ref={closeButtonRef}
                        type="button"
                        onClick={closeImage}
                        aria-label={translations.close_preview}
                        className="skuy-lightbox__control absolute right-4 top-4 z-[110]"
                    >
                        <CloseIcon />
                    </button>
                    {media.length > 1 && (
                        <button
                            type="button"
                            onClick={(event) => {
                                event.stopPropagation();
                                setActiveIndex((current) => current === 0 ? media.length - 1 : current - 1);
                            }}
                            aria-label={translations.previous_image}
                            className="skuy-lightbox__control absolute left-4 top-1/2 z-[110] -translate-y-1/2"
                        >
                            <ChevronLeftIcon />
                        </button>
                    )}
                    <img
                        src={media[activeIndex].full_url}
                        alt={media[activeIndex].full_alt}
                        className="skuy-lightbox__image max-h-[90vh] max-w-full border border-white object-contain"
                    />
                    {media.length > 1 && (
                        <button
                            type="button"
                            onClick={(event) => {
                                event.stopPropagation();
                                setActiveIndex((current) => current === media.length - 1 ? 0 : current + 1);
                            }}
                            aria-label={translations.next_image}
                            className="skuy-lightbox__control absolute right-4 top-1/2 z-[110] -translate-y-1/2"
                        >
                            <ChevronRightIcon />
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}
