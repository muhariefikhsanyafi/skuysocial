import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { PostDetailPageProps } from '../../types/post';
import Show from '../../pages/Post/Show';

const { usePageMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({
    usePage: usePageMock,
    router: {
        post: vi.fn(),
        delete: vi.fn(),
    },
}));

const author = {
    id: 1,
    name: 'Display Person',
    username: 'display_person',
    avatar_url: null,
    is_verified: true,
};

const props = {
    auth: { user: { id: 1, name: author.name, username: author.username } },
    locale: 'en',
    flash: { status: null },
    moderation: { state: null, allows_social_writes: true },
    post: {
        uid: 'detail-post',
        url: '/post/detail-post',
        is_repost: false,
        reposted_by: null,
    },
    display_post: {
        uid: 'detail-post',
        content: 'A root post with #design context.',
        datetime: '2026-09-25T00:00:00+00:00',
        display_time: 'now',
        author,
        topic: 'design-topic',
        media: [],
        interactions: {
            liked: false,
            like_count: 0,
            bookmarked: false,
            reposted: false,
            repost_count: 0,
            comment_count: 0,
        },
        capabilities: {
            can_edit: false,
            can_delete: false,
            can_perform_social_writes: true,
        },
    },
    comments: {
        data: [],
        current_page: 1,
        per_page: 20,
        has_more: false,
        next_url: null,
        previous_url: null,
    },
    expanded_reply_root_id: null,
    expanded_replies: null,
    viewer: { id: 1, author, can_perform_social_writes: true },
    routes: {
        feed: '/feed',
        like: '/posts/detail-post/like',
        repost: '/posts/detail-post/repost',
        bookmark_store: '/posts/detail-post/bookmark',
        bookmark_destroy: '/posts/detail-post/bookmark',
        comment: '/posts/detail-post/comments',
        share: '/post/detail-post',
        tags_base: '/tags',
        edit: null,
        delete: null,
    },
    translations: {
        back_to_feed: 'Back to feed',
        thread: 'Thread',
        replies: 'Replies',
        like: 'Like',
        unlike: 'Unlike',
        reply: 'Reply',
        repost: 'Repost',
        save: 'Save',
        remove_from_saved: 'Remove from Saved',
        share: 'Share',
        send: 'Send',
        view_more_replies: 'View more replies',
        image_coming_soon: 'Image support is coming soon',
        repost_coming_soon: 'Repost (Coming Soon)',
        open_options: 'Open Thread options',
        verified: 'Verified',
        avatar_of: 'Avatar for :name',
        reposted_by: ':username reposted',
        confirm_delete: 'Permanently delete this Thread?',
        edit: 'Edit',
        delete: 'Delete',
        comments_reply_to: 'Reply to :name...',
        thread_link_copied: 'Thread link copied!',
        comment_link_copied: 'Comment link copied!',
        reply_link_copied: 'Reply link copied!',
        photo: 'Photo',
        full_photo: 'Full-size photo',
        close_preview: 'Close preview',
        previous_image: 'Previous image',
        next_image: 'Next image',
        pagination_previous: 'Previous',
        pagination_next: 'Next',
        feedback: {
            liked: 'Thread liked.',
            unliked: 'Thread unliked.',
            session_expired: 'Your session expired.',
            restricted: 'Your account cannot perform this social action.',
            invalid: 'The like response was invalid.',
            failed: 'The like could not be updated.',
            network: 'The connection failed.',
        },
        comment_feedback: {
            session_expired: 'Comment session expired.',
            restricted: 'Commenting is restricted.',
            invalid: 'The comment action was rejected.',
            failed: 'Comment action failed.',
            network: 'Comment connection failed.',
        },
    },
} as PostDetailPageProps;

describe('Post detail composition', () => {
    let scrollXDescriptor: PropertyDescriptor | undefined;
    let scrollYDescriptor: PropertyDescriptor | undefined;

    beforeEach(() => {
        scrollXDescriptor = Object.getOwnPropertyDescriptor(window, 'scrollX');
        scrollYDescriptor = Object.getOwnPropertyDescriptor(window, 'scrollY');
        usePageMock.mockReturnValue({ props });
    });

    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
        if (scrollXDescriptor) Object.defineProperty(window, 'scrollX', scrollXDescriptor);
        if (scrollYDescriptor) Object.defineProperty(window, 'scrollY', scrollYDescriptor);
    });

    it('separates root post, reply composer, and conversation while leading with author identity', () => {
        render(<Show />);

        const page = document.querySelector('.skuy-post-detail-page');
        expect(page?.classList.contains('skuy-post-detail-page')).toBe(true);

        const root = screen.getByRole('article');
        expect(root.classList.contains('skuy-post-detail__root')).toBe(true);
        expect(within(root).getByText('Display Person').textContent).toBe('Display Person');
        expect(within(root).getByText('@display_person').textContent).toBe('@display_person');
        expect(within(root).getByText('#design-topic').textContent).toBe('#design-topic');
        expect(within(root).getByText('#design').getAttribute('href')).toBe('/tags/design');
        expect(within(root).getByRole('button', { name: 'Like' }).tagName).toBe('BUTTON');

        const composer = screen.getByRole('textbox', { name: 'Reply to display_person...' });
        expect(composer.closest('.skuy-post-detail__composer') !== null).toBe(true);

        const conversation = screen.getByRole('region', { name: 'Replies' });
        expect(conversation.classList.contains('skuy-post-detail__conversation')).toBe(true);
    });

    it('preserves the current scroll position after creating a comment reactively', async () => {
        const createdComment = {
            post_uid: 'detail-post',
            comment_count: 1,
            root_comment_count: 1,
            parent_reply_count: null,
            comment: {
                id: 91,
                post_uid: 'detail-post',
                parent_id: null,
                content: 'Created without page movement',
                datetime: '2026-09-25T00:01:00+00:00',
                display_time: 'now',
                author,
                like_count: 0,
                liked: false,
                replies_count: 0,
                replies: [],
                actions: {
                    like_url: '/comments/91/like',
                    reply_url: '/posts/detail-post/comments',
                    share_url: '/post/detail-post#comment-91',
                },
                reply_expansion_url: null,
            },
        };
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            status: 201,
            redirected: false,
            json: async () => createdComment,
        });
        const scrollToMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal('scrollTo', scrollToMock);
        vi.stubGlobal('requestAnimationFrame', ((callback: FrameRequestCallback) => {
            callback(0);
            return 1;
        }) as typeof window.requestAnimationFrame);
        Object.defineProperty(window, 'scrollX', { configurable: true, value: 12 });
        Object.defineProperty(window, 'scrollY', { configurable: true, value: 480 });

        render(<Show />);
        const input = screen.getByRole('textbox', { name: 'Reply to display_person...' });
        fireEvent.change(input, { target: { value: 'Created without page movement' } });
        fireEvent.submit(input.closest('form') as HTMLFormElement);

        await waitFor(() => expect(screen.getByText('Created without page movement')).toBeTruthy());
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(scrollToMock).toHaveBeenCalledWith(12, 480);
    });
});
