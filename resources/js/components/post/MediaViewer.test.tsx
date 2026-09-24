import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import type { PostDetailTranslations, PostMedia } from '../../types/post';
import { MediaViewer } from './MediaViewer';

const translations = {
    photo: 'Photo',
    full_photo: 'Full image',
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

    it('traps focus, supports keyboard image navigation, and restores the trigger on close', async () => {
        render(<MediaViewer media={media} translations={translations} />);

        const triggers = screen.getAllByRole('button', { name: 'Photo' });
        fireEvent.click(triggers[0]);

        const closeButton = await screen.findByRole('button', { name: 'Close preview' });
        await waitFor(() => expect(document.activeElement).toBe(closeButton));
        expect((screen.getByRole('img', { name: 'Full photo one' }) as HTMLImageElement).src).toContain('/storage/one.jpg');

        fireEvent.keyDown(document, { key: 'ArrowRight' });
        expect((screen.getByRole('img', { name: 'Full photo two' }) as HTMLImageElement).src).toContain('/storage/two.jpg');

        fireEvent.keyDown(document, { key: 'ArrowLeft' });
        expect((screen.getByRole('img', { name: 'Full photo one' }) as HTMLImageElement).src).toContain('/storage/one.jpg');

        const nextButton = screen.getByRole('button', { name: 'Next image' });
        nextButton.focus();
        fireEvent.keyDown(document, { key: 'Tab' });
        expect(document.activeElement).toBe(closeButton);

        fireEvent.keyDown(document, { key: 'Escape' });
        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
        await waitFor(() => expect(document.activeElement).toBe(triggers[0]));
    });
});
