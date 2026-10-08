import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { PostDetailTranslations, PostMedia } from '../../types/post';
import { MediaViewer } from './MediaViewer';

const translations = {
    about_creator: 'About the creator',
    no_bio: 'This user has not written a bio.',
    threads: 'Threads',
    followers: 'Followers',
    following: 'Following',
    photo: 'Photo',
    photo_gallery: 'Photo gallery',
    image_position: 'Image :current of :total',
    full_photo: 'Full-size photo',
    close_preview: 'Close preview',
    previous_image: 'Previous image',
    next_image: 'Next image',
} as PostDetailTranslations;

const media: PostMedia[] = [
    {
        type: 'image',
        url: '/storage/one.jpg',
        full_url: '/storage/one.jpg',
        alt: 'Photo one',
        full_alt: 'Full photo one',
    },
    {
        type: 'image',
        url: '/storage/two.jpg',
        full_url: '/storage/two.jpg',
        alt: 'Photo two',
        full_alt: 'Full photo two',
    },
    {
        type: 'image',
        url: '/storage/three.jpg',
        full_url: '/storage/three.jpg',
        alt: 'Photo three',
        full_alt: 'Full photo three',
    },
];

describe('MediaViewer lightbox accessibility', () => {
    afterEach(() => cleanup());

    it('keeps a single detail image separate from carousel controls', () => {
        render(<MediaViewer media={[media[0]]} translations={translations} />);

        expect(document.querySelector('.skuy-post-detail__media')).not.toBeNull();
        expect(document.querySelector('[data-media-slider="true"]')).toBeNull();
        expect(document.querySelector('[data-media-indicator="true"]')).toBeNull();
    });

    it('uses an internal scroll track and pager for multi-image detail media', () => {
        render(<MediaViewer media={media} translations={translations} />);

        expect(document.querySelector('[data-media-slider="true"]')?.classList.contains('skuy-media-track')).toBe(true);
        expect(document.querySelectorAll('[data-media-slide="true"]').length).toBe(media.length);
        expect(document.querySelectorAll('[data-media-indicator-dot="true"]').length).toBe(media.length);
    });

    it('provides bounded previous and next controls for the inline carousel', () => {
        render(<MediaViewer media={media} translations={translations} />);

        const track = document.querySelector('[data-media-slider="true"]') as HTMLElement;
        const scrollTo = vi.fn();
        Object.defineProperty(track, 'clientWidth', { configurable: true, value: 360 });
        Object.defineProperty(track, 'scrollWidth', { configurable: true, value: 1080 });
        Object.defineProperty(track, 'scrollTo', { configurable: true, value: scrollTo });

        const previous = document.querySelector('[data-media-previous]') as HTMLButtonElement;
        const next = document.querySelector('[data-media-next]') as HTMLButtonElement;
        expect(previous.disabled).toBe(true);

        fireEvent.click(next);
        expect(screen.getByRole('button', { name: 'Image 2 of 3' }).getAttribute('aria-current')).toBe('true');
        expect(scrollTo).toHaveBeenCalledOnce();
        fireEvent.click(next);
        expect(screen.getByRole('button', { name: 'Image 3 of 3' }).getAttribute('aria-current')).toBe('true');
        expect(next.disabled).toBe(true);
        expect(document.querySelector('[role="dialog"]')).toBeNull();

        fireEvent.click(previous);
        expect(screen.getByRole('button', { name: 'Image 2 of 3' }).getAttribute('aria-current')).toBe('true');
        fireEvent.click(previous);
        expect(screen.getByRole('button', { name: 'Image 1 of 3' }).getAttribute('aria-current')).toBe('true');
        expect(previous.disabled).toBe(true);
    });

    it('supports horizontal touch swipes in the viewer without treating vertical gestures as slides', async () => {
        render(<MediaViewer media={media} translations={translations} />);
        fireEvent.click(screen.getByRole('button', { name: 'Image 1 of 3' }));

        const image = await screen.findByRole('img', { name: 'Full photo one' });
        fireEvent.pointerDown(image, { pointerId: 1, pointerType: 'touch', clientX: 250, clientY: 120 });
        fireEvent.pointerUp(image, { pointerId: 1, pointerType: 'touch', clientX: 140, clientY: 124 });
        expect(await screen.findByRole('img', { name: 'Full photo two' })).toBeTruthy();

        const secondImage = screen.getByRole('img', { name: 'Full photo two' });
        fireEvent.pointerDown(secondImage, { pointerId: 2, pointerType: 'touch', clientX: 160, clientY: 120 });
        fireEvent.pointerUp(secondImage, { pointerId: 2, pointerType: 'touch', clientX: 152, clientY: 220 });
        expect(screen.getByRole('img', { name: 'Full photo two' })).toBeTruthy();
    });

    it('traps focus, supports keyboard image navigation, and restores the trigger on close', async () => {
        render(<MediaViewer media={media} translations={translations} />);

        const triggers = screen.getAllByRole('button', { name: 'Image 1 of 3' });
        fireEvent.click(triggers[0]);

        const closeButton = await screen.findByRole('button', { name: 'Close preview' });
        await waitFor(() => expect(document.activeElement).toBe(closeButton));
        expect((screen.getByRole('img', { name: 'Full photo one' }) as HTMLImageElement).src).toContain('/storage/one.jpg');

        fireEvent.keyDown(document, { key: 'ArrowRight' });
        expect((screen.getByRole('img', { name: 'Full photo two' }) as HTMLImageElement).src).toContain('/storage/two.jpg');

        fireEvent.keyDown(document, { key: 'ArrowLeft' });
        expect((screen.getByRole('img', { name: 'Full photo one' }) as HTMLImageElement).src).toContain('/storage/one.jpg');

        const nextButton = document.querySelector('.skuy-lightbox [aria-label="Next image"]') as HTMLButtonElement;
        nextButton.focus();
        fireEvent.keyDown(document, { key: 'Tab' });
        expect(document.activeElement).toBe(closeButton);

        fireEvent.keyDown(document, { key: 'Escape' });
        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
        await waitFor(() => expect(document.activeElement).toBe(triggers[0]));
    });
});
