import { useEffect, useRef, useState, type PointerEvent as ReactPointerEvent, type UIEvent } from 'react';
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
    const trackRef = useRef<HTMLDivElement>(null);
    const swipeStartRef = useRef<{ x: number; y: number } | null>(null);

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
                setActiveIndex((current) => Math.max(0, current - 1));
                return;
            }

            if (media.length > 1 && event.key === 'ArrowRight') {
                event.preventDefault();
                setActiveIndex((current) => Math.min(media.length - 1, current + 1));
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
        const track = event.currentTarget;
        const slides = Array.from(track.querySelectorAll<HTMLElement>('[data-media-slide="true"]'));
        if (slides.length < 2) return;

        const trackCenter = track.getBoundingClientRect().left + (track.clientWidth / 2);
        let nearestIndex = 0;
        let nearestDistance = Number.POSITIVE_INFINITY;

        slides.forEach((slide, index) => {
            const rect = slide.getBoundingClientRect();
            const distance = Math.abs((rect.left + (rect.width / 2)) - trackCenter);
            if (distance < nearestDistance) {
                nearestDistance = distance;
                nearestIndex = index;
            }
        });

        setActiveIndex(nearestIndex);
    };

    const imagePositionLabel = (index: number) => translations.image_position
        .replace(':current', String(index + 1))
        .replace(':total', String(media.length));

    const scrollToSlide = (requestedIndex: number) => {
        const track = trackRef.current;
        if (!track || media.length < 2) return;

        const targetIndex = Math.max(0, Math.min(media.length - 1, requestedIndex));
        const slide = track.querySelectorAll<HTMLElement>('[data-media-slide="true"]')[targetIndex];
        if (!slide) return;

        const targetLeft = track.scrollLeft
            + slide.getBoundingClientRect().left
            - track.getBoundingClientRect().left
            - ((track.clientWidth - slide.clientWidth) / 2);
        const maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);

        const prefersReducedMotion = typeof window.matchMedia === 'function'
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const scrollOptions = {
            left: Math.max(0, Math.min(maxScroll, targetLeft)),
            behavior: prefersReducedMotion ? 'auto' as ScrollBehavior : 'smooth' as ScrollBehavior,
        };

        if (typeof track.scrollTo === 'function') track.scrollTo(scrollOptions);
        else track.scrollLeft = scrollOptions.left;
        setActiveIndex(targetIndex);
    };

    const startSwipe = (event: ReactPointerEvent<HTMLImageElement>) => {
        swipeStartRef.current = { x: event.clientX, y: event.clientY };
    };

    const endSwipe = (event: ReactPointerEvent<HTMLImageElement>) => {
        const start = swipeStartRef.current;
        swipeStartRef.current = null;
        if (!start || media.length < 2) return;

        const deltaX = event.clientX - start.x;
        const deltaY = event.clientY - start.y;
        if (Math.abs(deltaX) < 48 || Math.abs(deltaX) <= Math.abs(deltaY) * 1.25) return;

        event.stopPropagation();
        setActiveIndex((current) => Math.max(0, Math.min(
            media.length - 1,
            current + (deltaX < 0 ? 1 : -1),
        )));
    };

    const cancelSwipe = () => {
        swipeStartRef.current = null;
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
                <div className="skuy-media-gallery">
                    <div
                        ref={trackRef}
                        data-media-slider="true"
                        role="region"
                        aria-label={translations.photo_gallery}
                        aria-roledescription="carousel"
                        tabIndex={0}
                        onScroll={handleScroll}
                        onKeyDown={(event) => {
                            if (event.key === 'ArrowLeft') {
                                event.preventDefault();
                                scrollToSlide(activeIndex - 1);
                            } else if (event.key === 'ArrowRight') {
                                event.preventDefault();
                                scrollToSlide(activeIndex + 1);
                            }
                        }}
                        className="skuy-media-track"
                    >
                        {media.map((item, index) => (
                            <button
                                key={item.url}
                                type="button"
                                data-media-slide="true"
                                onClick={(event) => openImage(index, event.currentTarget)}
                                className="skuy-media-item w-[85%] border-0 p-0 text-start sm:w-[48%]"
                                aria-label={imagePositionLabel(index)}
                                aria-current={activeIndex === index ? 'true' : undefined}
                            >
                                <img src={item.url} alt={item.alt} className="h-full w-full object-cover" />
                            </button>
                        ))}
                    </div>
                    <div className="skuy-media-gallery__controls" role="group" aria-label={translations.photo_gallery}>
                        <button
                            type="button"
                            data-media-previous="true"
                            onClick={() => scrollToSlide(activeIndex - 1)}
                            disabled={activeIndex === 0}
                            aria-label={translations.previous_image}
                            className="skuy-media-gallery__control"
                        >
                            <ChevronLeftIcon />
                        </button>
                        <button
                            type="button"
                            data-media-next="true"
                            onClick={() => scrollToSlide(activeIndex + 1)}
                            disabled={activeIndex === media.length - 1}
                            aria-label={translations.next_image}
                            className="skuy-media-gallery__control"
                        >
                            <ChevronRightIcon />
                        </button>
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
                    <span className="sr-only" role="status" aria-live="polite">{imagePositionLabel(activeIndex)}</span>
                </div>
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
                        <>
                            <span className="skuy-lightbox__counter" role="status" aria-live="polite">
                                {imagePositionLabel(activeIndex)}
                            </span>
                            <button
                                type="button"
                                onClick={(event) => {
                                    event.stopPropagation();
                                    setActiveIndex((current) => Math.max(0, current - 1));
                                }}
                                disabled={activeIndex === 0}
                                aria-label={translations.previous_image}
                                className="skuy-lightbox__control absolute left-4 top-1/2 z-[110] -translate-y-1/2"
                            >
                                <ChevronLeftIcon />
                            </button>
                        </>
                    )}
                    <img
                        src={media[activeIndex].full_url}
                        alt={media[activeIndex].full_alt}
                        onPointerDown={startSwipe}
                        onPointerUp={endSwipe}
                        onPointerCancel={cancelSwipe}
                        className="skuy-lightbox__image max-h-[90vh] max-w-full border border-white object-contain"
                    />
                    {media.length > 1 && (
                        <button
                            type="button"
                            onClick={(event) => {
                                event.stopPropagation();
                                setActiveIndex((current) => Math.min(media.length - 1, current + 1));
                            }}
                            disabled={activeIndex === media.length - 1}
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
