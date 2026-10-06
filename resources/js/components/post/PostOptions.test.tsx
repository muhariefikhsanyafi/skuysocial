import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { PostDetailTranslations } from '../../types/post';
import { PostOptions } from './PostOptions';

const { routerPost, routerDelete } = vi.hoisted(() => ({
    routerPost: vi.fn(),
    routerDelete: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({
    router: {
        post: routerPost,
        delete: routerDelete,
    },
}));

const translations = {
    open_options: 'Open Thread options',
    confirm_delete: 'Delete this thread?',
    edit: 'Edit',
    delete: 'Delete',
    save: 'Save',
    remove_from_saved: 'Remove from Saved',
} as PostDetailTranslations;

describe('PostOptions Bookmark motion', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        routerPost.mockReset();
        routerDelete.mockReset();
        document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
    });

    afterEach(() => cleanup());

    it('keeps the menu open during Save, animates only after success, then closes accessibly', async () => {
        render(
            <PostOptions
                uid="detail-post"
                editUrl={null}
                deleteUrl={null}
                bookmarkStoreUrl="/posts/detail-post/bookmark"
                bookmarkDestroyUrl="/posts/detail-post/bookmark"
                bookmarked={false}
                canBookmark={true}
                translations={translations}
            />,
        );

        const trigger = screen.getByRole('button', { name: 'Open Thread options' });
        fireEvent.click(trigger);
        const save = await screen.findByRole('menuitem', { name: 'Save' });
        fireEvent.click(save);

        expect(routerPost).toHaveBeenCalledWith(
            '/posts/detail-post/bookmark',
            {},
            expect.objectContaining({
                preserveScroll: true,
                headers: { 'X-CSRF-TOKEN': 'test-token' },
                onSuccess: expect.any(Function),
            }),
        );
        expect((save as HTMLButtonElement).disabled).toBe(true);
        expect(screen.getByRole('menuitem', { name: 'Save' })).toBeTruthy();

        const options = routerPost.mock.calls[0][2] as { onSuccess: () => void; onFinish: () => void };
        act(() => {
            options.onSuccess();
            options.onFinish();
        });
        const icon = save.querySelector('.skuy-fa-icon') as HTMLElement;
        expect(icon.classList.contains('skuy-social-motion--bookmark-save')).toBe(true);
        expect((save as HTMLButtonElement).disabled).toBe(true);
        expect(screen.getByRole('menuitem', { name: 'Save' })).toBeTruthy();

        icon.dispatchEvent(new Event('animationend'));
        await waitFor(() => expect(screen.queryByRole('menuitem')).toBeNull());
        expect(document.activeElement).toBe(trigger);
    });

    it('does not animate or optimistically save when Inertia returns an error', async () => {
        render(
            <PostOptions
                uid="detail-post"
                editUrl={null}
                deleteUrl={null}
                bookmarkStoreUrl="/posts/detail-post/bookmark"
                bookmarkDestroyUrl="/posts/detail-post/bookmark"
                bookmarked={false}
                canBookmark={true}
                translations={translations}
            />,
        );

        const trigger = screen.getByRole('button', { name: 'Open Thread options' });
        fireEvent.click(trigger);
        const save = await screen.findByRole('menuitem', { name: 'Save' });
        fireEvent.click(save);
        const options = routerPost.mock.calls[0][2] as { onError: (errors: object) => void };

        act(() => options.onError({ bookmark: 'Failed' }));

        expect(save.querySelector('.skuy-fa-icon')?.classList.contains('skuy-social-motion--bookmark-save')).toBe(false);
        await waitFor(() => expect(screen.queryByRole('menuitem')).toBeNull());
        expect(document.activeElement).toBe(trigger);
    });
});
