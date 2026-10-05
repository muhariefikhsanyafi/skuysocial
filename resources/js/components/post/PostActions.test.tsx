import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { DisplayPost, PostDetailRoutes, PostDetailTranslations } from '../../types/post';
import { PostActions } from './PostActions';

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

const translations: PostDetailTranslations = {
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
    appreciation: {
        label: 'Post Appreciation',
        action: 'Appreciate',
        choose_type: 'Choose an Appreciation type',
        cost_label: '5 Points',
        cost_copy: 'Costs exactly 5 Points. Spent by you, not transferred.',
        count_one: ':count Appreciation',
        count_other: ':count Appreciations',
        selected: 'You appreciated this post with :type.',
        types: { bermanfaat: 'Useful', inspiratif: 'Inspiring', solutif: 'Solution-oriented' },
        feedback: {
            insufficient_points: 'You need 5 Points.',
            self_appreciation_forbidden: 'You cannot appreciate your own post.',
            session_expired: 'Your session expired.',
            restricted: 'Your account cannot perform this social action.',
            invalid: 'Choose a valid Appreciation type.',
            failed: 'Appreciation failed.',
            network: 'The connection failed.',
        },
    },
};

const post: DisplayPost = {
    uid: 'post-1',
    content: 'Post content',
    datetime: '2026-09-23T00:00:00+00:00',
    display_time: 'now',
    author: {
        id: 1,
        name: 'Owner',
        username: 'owner',
        avatar_url: null,
        is_verified: false,
    },
    topic: null,
    media: [],
    interactions: {
        liked: false,
        like_count: 1,
        appreciation_count: 0,
        appreciated: false,
        appreciation_type: null,
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
};

const routes: PostDetailRoutes = {
    feed: '/feed',
    like: '/posts/post-1/like',
    appreciation: '/posts/post-1/appreciation',
    repost: '/post/post-1/repost',
    bookmark_store: '/posts/post-1/bookmark',
    bookmark_destroy: '/posts/post-1/bookmark',
    comment: '/posts/post-1/comments',
    share: '/post/post-1',
    tags_base: '/tags',
    edit: null,
    delete: null,
};

const appreciationPost = {
    ...post,
    interactions: {
        ...post.interactions,
        appreciation_count: 0,
        appreciated: false,
        appreciation_type: null,
    },
} as DisplayPost;

const appreciationRoutes = {
    ...routes,
    appreciation: '/posts/post-1/appreciation',
} as PostDetailRoutes;

const appreciationTranslations = {
    ...translations,
    appreciation: {
        label: 'Post Appreciation',
        action: 'Appreciate',
        choose_type: 'Choose an Appreciation type',
        cost_label: '5 Points',
        cost_copy: 'Costs exactly 5 Points. Spent by you, not transferred.',
        count_one: ':count Appreciation',
        count_other: ':count Appreciations',
        selected: 'You appreciated this post with :type.',
        types: {
            bermanfaat: 'Useful',
            inspiratif: 'Inspiring',
            solutif: 'Solution-oriented',
        },
        feedback: {
            insufficient_points: 'You need 5 Points.',
            self_appreciation_forbidden: 'You cannot appreciate your own post.',
            session_expired: 'Your session expired.',
            restricted: 'Your account cannot perform this social action.',
            invalid: 'Choose a valid Appreciation type.',
            failed: 'Appreciation failed.',
            network: 'The connection failed.',
        },
    },
} as PostDetailTranslations;

describe('PostActions like state', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        routerPost.mockReset();
        routerDelete.mockReset();
        document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
    });

    afterEach(() => {
        cleanup();
    });

    it('commits the server response and prevents a duplicate request while pending', async () => {
        const fetchMock = vi.fn().mockResolvedValue(
            new Response(JSON.stringify({ post_uid: 'post-1', liked: true, like_count: 2 }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', fetchMock);

        render(<PostActions post={post} routes={routes} translations={translations} canPerformSocialWrites={true} viewerId={2} commentCount={post.interactions.comment_count} />);
        const button = screen.getByRole('button', { name: /Like/ });

        fireEvent.click(button);
        fireEvent.click(button);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(button.getAttribute('aria-pressed')).toBe('true');

        await waitFor(() => expect(screen.getByRole('status').textContent).toBe('Thread liked.'));
        await waitFor(() => expect((button as HTMLButtonElement).disabled).toBe(false));
        expect(button.getAttribute('aria-pressed')).toBe('true');
        expect(button.getAttribute('aria-label')).toBe('Unlike (2)');
    });

    it('disables social writes when the server marks the viewer as restricted', () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        const restrictedPost = {
            ...post,
            capabilities: {
                ...post.capabilities,
                can_perform_social_writes: false,
            },
        };

        render(<PostActions post={restrictedPost} routes={routes} translations={translations} canPerformSocialWrites={false} viewerId={2} commentCount={restrictedPost.interactions.comment_count} />);

        expect((screen.getByRole('button', { name: /Like/ }) as HTMLButtonElement).disabled).toBe(true);
        expect((screen.getByRole('button', { name: 'Repost' }) as HTMLButtonElement).disabled).toBe(true);
        expect((screen.getByRole('button', { name: 'Save' }) as HTMLButtonElement).disabled).toBe(true);
        expect((screen.getByRole('button', { name: 'Share' }) as HTMLButtonElement).disabled).toBe(false);
        expect(fetchMock).not.toHaveBeenCalled();
    });
    it('rolls optimistic state back when the like request fails', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));

        render(<PostActions post={post} routes={routes} translations={translations} canPerformSocialWrites={true} viewerId={2} commentCount={post.interactions.comment_count} />);
        const button = screen.getByRole('button', { name: /Like/ });

        fireEvent.click(button);
        expect(button.getAttribute('aria-pressed')).toBe('true');

        await waitFor(() => {
            expect(button.getAttribute('aria-pressed')).toBe('false');
            expect(screen.getByRole('alert').textContent).toContain('connection failed');
        });
    });

    it('reports forbidden actions as restricted and rolls back the like state', async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ message: 'Forbidden' }), {
            status: 403,
            headers: { 'Content-Type': 'application/json' },
        }));
        vi.stubGlobal('fetch', fetchMock);

        render(<PostActions post={post} routes={routes} translations={translations} canPerformSocialWrites={true} viewerId={2} commentCount={post.interactions.comment_count} />);
        const button = screen.getByRole('button', { name: /Like/ });
        fireEvent.click(button);

        await waitFor(() => {
            expect(button.getAttribute('aria-pressed')).toBe('false');
            expect(screen.getByRole('alert').textContent).toBe(translations.feedback.restricted);
        });
    });

    it('offers three Appreciation types at five Points and locks the selected type after success', async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({
            status: 'appreciated',
            type: 'bermanfaat',
            appreciation_count: 1,
            message: 'Your Useful Appreciation was recorded. 5 Points spent.',
        }), {
            status: 201,
            headers: { 'Content-Type': 'application/json' },
        }));
        vi.stubGlobal('fetch', fetchMock);

        render(
            <PostActions
                post={appreciationPost}
                routes={appreciationRoutes}
                translations={appreciationTranslations}
                canPerformSocialWrites={true}
                viewerId={2}
                commentCount={post.interactions.comment_count}
            />,
        );

        expect(screen.getByText('Costs exactly 5 Points. Spent by you, not transferred.')).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: /Appreciate/ }));
        const choices = screen.getByRole('group', { name: 'Choose an Appreciation type' });
        expect(within(choices).getAllByRole('button')).toHaveLength(3);
        fireEvent.click(within(choices).getByRole('button', { name: 'Useful' }));

        await waitFor(() => expect(screen.getByText('Your Useful Appreciation was recorded. 5 Points spent.')).toBeTruthy());
        expect(fetchMock).toHaveBeenCalledWith('/posts/post-1/appreciation', expect.objectContaining({
            method: 'POST',
            body: JSON.stringify({ type: 'bermanfaat' }),
        }));
        expect(screen.getByText('You appreciated this post with Useful.')).toBeTruthy();
        expect(screen.getByText('1 Appreciation')).toBeTruthy();
        expect(screen.queryByRole('group', { name: 'Choose an Appreciation type' })).toBeNull();
        expect(screen.getByRole('button', { name: /Like/ }).className).toContain('skuy-action--reaction');
    });

    it('shows localized insufficient-Points feedback without marking the post appreciated', async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({
            status: 'insufficient_points',
            type: null,
            appreciation_count: 0,
            message: 'You need at least 5 Points to appreciate a post.',
        }), {
            status: 422,
            headers: { 'Content-Type': 'application/json' },
        }));
        vi.stubGlobal('fetch', fetchMock);

        render(
            <PostActions
                post={appreciationPost}
                routes={appreciationRoutes}
                translations={appreciationTranslations}
                canPerformSocialWrites={true}
                viewerId={2}
                commentCount={post.interactions.comment_count}
            />,
        );
        fireEvent.click(screen.getByRole('button', { name: /Appreciate/ }));
        fireEvent.click(screen.getByRole('button', { name: 'Inspiring' }));

        await waitFor(() => expect(screen.getByRole('alert').textContent).toBe('You need at least 5 Points to appreciate a post.'));
        expect(screen.queryByText('You appreciated this post with Inspiring.')).toBeNull();
        expect(screen.queryByText('You appreciated this post with Useful.')).toBeNull();
    });

    it('hides Appreciation controls on self-owned and already-appreciated posts', () => {
        render(
            <PostActions
                post={appreciationPost}
                routes={appreciationRoutes}
                translations={appreciationTranslations}
                canPerformSocialWrites={true}
                viewerId={appreciationPost.author.id}
                commentCount={post.interactions.comment_count}
            />,
        );

        expect(screen.queryByRole('button', { name: /Appreciate/ })).toBeNull();
        expect(screen.queryByRole('group', { name: 'Choose an Appreciation type' })).toBeNull();

        cleanup();
        const alreadyAppreciatedPost = {
            ...appreciationPost,
            interactions: {
                ...appreciationPost.interactions,
                appreciation_count: 1,
                appreciated: true,
                appreciation_type: 'inspiratif',
            },
        } as DisplayPost;
        render(
            <PostActions
                post={alreadyAppreciatedPost}
                routes={appreciationRoutes}
                translations={appreciationTranslations}
                canPerformSocialWrites={true}
                viewerId={2}
                commentCount={post.interactions.comment_count}
            />,
        );

        expect(screen.getByText('You appreciated this post with Inspiring.')).toBeTruthy();
        expect(screen.queryByRole('button', { name: /Appreciate/ })).toBeNull();
    });
});
