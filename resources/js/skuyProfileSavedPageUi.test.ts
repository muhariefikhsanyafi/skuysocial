import { afterEach, describe, expect, it, vi } from 'vitest';
import { postMediaViewerController, savedPageController } from './skuyPrismPageUi';

type AlpineState = {
    $root: HTMLElement;
    $refs: Record<string, HTMLElement>;
    $nextTick(callback: () => void): void;
};

afterEach(() => {
    document.body.innerHTML = '';
    document.body.style.overflow = '';
});

function withAlpineContext<T extends object>(state: T, root: HTMLElement, refs: Record<string, HTMLElement>) {
    return Object.assign(state, {
        $root: root,
        $refs: refs,
        $nextTick: (callback: () => void) => callback(),
    }) as T & AlpineState;
}

describe('Saved collection page interactions', () => {
    it('initializes its visible count from the rendered collection', () => {
        const root = document.createElement('section');
        root.dataset.initialCount = '3';
        const state = withAlpineContext(savedPageController(), root, {});

        state.init();

        expect(state.visiblePostCount).toBe(3);
    });

    it('removes only a post whose bookmark was removed and updates the visible count', () => {
        const list = document.createElement('div');
        list.innerHTML = '<article data-feed-post-uid="saved-one"></article><article data-feed-post-uid="saved-two"></article>';
        const root = document.createElement('section');
        root.dataset.initialCount = '2';
        const state = withAlpineContext(savedPageController(), root, { savedPosts: list });
        state.init();

        state.removeUnsaved({ detail: { postUid: 'saved-one', bookmarked: true } } as unknown as Event);
        expect(list.querySelectorAll('article[data-feed-post-uid]')).toHaveLength(2);

        state.removeUnsaved({ detail: { postUid: 'missing', bookmarked: false } } as unknown as Event);
        expect(list.querySelectorAll('article[data-feed-post-uid]')).toHaveLength(2);

        state.removeUnsaved({ detail: { postUid: 'saved-one', bookmarked: false } } as unknown as Event);
        expect(list.querySelectorAll('article[data-feed-post-uid]')).toHaveLength(1);
        expect(state.visiblePostCount).toBe(1);
        expect(list.querySelector('[data-feed-post-uid="saved-two"]')).not.toBeNull();
    });

    it('moves focus to the next post or the empty-state Feed link after removal', () => {
        const list = document.createElement('div');
        list.innerHTML = '<article data-feed-post-uid="saved-one"><button>Remove</button></article><article data-feed-post-uid="saved-two"><a href="/saved/2">Next post</a></article>';
        const emptyState = document.createElement('div');
        emptyState.innerHTML = '<a data-saved-feed-link href="/feed">Browse Feed</a>';
        const root = document.createElement('section');
        root.dataset.initialCount = '2';
        document.body.append(list, emptyState);
        const state = withAlpineContext(savedPageController(), root, { savedPosts: list, savedEmptyState: emptyState });
        state.init();

        const removeButton = list.querySelector('button') as HTMLButtonElement;
        removeButton.focus();
        state.removeUnsaved({ detail: { postUid: 'saved-one', bookmarked: false } } as unknown as Event);
        expect(document.activeElement).toBe(list.querySelector('a[href="/saved/2"]'));

        const lastCard = list.querySelector('article[data-feed-post-uid]') as HTMLElement;
        const nextPostLink = lastCard.querySelector('a') as HTMLAnchorElement;
        nextPostLink.focus();
        state.removeUnsaved({ detail: { postUid: 'saved-two', bookmarked: false } } as unknown as Event);
        expect(state.visiblePostCount).toBe(0);
        expect(document.activeElement).toBe(emptyState.querySelector('[data-saved-feed-link]'));
    });
});

describe('Profile and Saved post media viewer', () => {
    it('opens at the requested image, locks scrolling, and restores focus and scroll state on close', () => {
        document.body.style.overflow = 'auto';
        const trigger = document.createElement('button');
        const closeButton = document.createElement('button');
        document.body.append(trigger, closeButton);
        const viewer = withAlpineContext(postMediaViewerController(), document.body, { lightboxClose: closeButton });

        viewer.openLightbox(['one.jpg', 'two.jpg'], 1, { currentTarget: trigger } as unknown as Event);

        expect(viewer.openImage).toBe(true);
        expect(viewer.activeImage).toBe('two.jpg');
        expect(viewer.lightboxIndex).toBe(1);
        expect(document.body.style.overflow).toBe('hidden');
        expect(document.activeElement).toBe(closeButton);

        viewer.closeImage();
        expect(viewer.openImage).toBe(false);
        expect(document.body.style.overflow).toBe('auto');
        expect(document.activeElement).toBe(trigger);
    });

    it('clamps keyboard and pointer navigation and ignores vertical swipes', () => {
        const viewer = withAlpineContext(postMediaViewerController(), document.body, {});
        viewer.openLightbox(['one.jpg', 'two.jpg', 'three.jpg'], 0);
        const right = { key: 'ArrowRight', preventDefault: vi.fn() } as unknown as KeyboardEvent;
        const left = { key: 'ArrowLeft', preventDefault: vi.fn() } as unknown as KeyboardEvent;

        viewer.handleLightboxKeydown(right);
        expect(viewer.activeImage).toBe('two.jpg');
        viewer.handleLightboxKeydown(left);
        viewer.handleLightboxKeydown(left);
        expect(viewer.activeImage).toBe('one.jpg');
        expect(left.preventDefault).toHaveBeenCalledTimes(2);

        viewer.startLightboxPointer({ pointerId: 1, clientX: 240, clientY: 100 } as PointerEvent);
        viewer.endLightboxPointer({ pointerId: 1, clientX: 120, clientY: 105 } as PointerEvent);
        expect(viewer.activeImage).toBe('two.jpg');

        viewer.startLightboxPointer({ pointerId: 1, clientX: 120, clientY: 100 } as PointerEvent);
        viewer.endLightboxPointer({ pointerId: 1, clientX: 110, clientY: 180 } as PointerEvent);
        expect(viewer.activeImage).toBe('two.jpg');

        viewer.nextImage();
        viewer.nextImage();
        expect(viewer.activeImage).toBe('three.jpg');
    });

    it('traps keyboard focus within the open viewer', () => {
        const dialog = document.createElement('div');
        const closeButton = document.createElement('button');
        const nextButton = document.createElement('button');
        dialog.append(closeButton, nextButton);
        document.body.append(dialog);
        const viewer = withAlpineContext(postMediaViewerController(), document.body, { lightboxDialog: dialog });
        viewer.openImage = true;

        closeButton.focus();
        const backwards = { key: 'Tab', shiftKey: true, preventDefault: vi.fn() } as unknown as KeyboardEvent;
        viewer.handleLightboxKeydown(backwards);
        expect(backwards.preventDefault).toHaveBeenCalledOnce();
        expect(document.activeElement).toBe(nextButton);

        nextButton.focus();
        const forwards = { key: 'Tab', shiftKey: false, preventDefault: vi.fn() } as unknown as KeyboardEvent;
        viewer.handleLightboxKeydown(forwards);
        expect(forwards.preventDefault).toHaveBeenCalledOnce();
        expect(document.activeElement).toBe(closeButton);
    });

    it('keeps single-image viewers bounded and closes on Escape', () => {
        const viewer = withAlpineContext(postMediaViewerController(), document.body, {});
        viewer.openLightbox(['single.jpg'], 9);

        viewer.previousImage();
        viewer.nextImage();
        expect(viewer.activeImage).toBe('single.jpg');

        const escape = { key: 'Escape', preventDefault: vi.fn() } as unknown as KeyboardEvent;
        viewer.handleLightboxKeydown(escape);
        expect(escape.preventDefault).toHaveBeenCalledOnce();
        expect(viewer.openImage).toBe(false);
    });
});
