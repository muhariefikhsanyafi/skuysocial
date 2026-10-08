import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { PostDetailPageProps } from '../../types/post';
import Show from '../../pages/Post/Show';

const { usePageMock, routerPostMock, routerDeleteMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
    routerDeleteMock: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({
    usePage: usePageMock,
    router: {
        post: routerPostMock,
        delete: routerDeleteMock,
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
    },
    author_context: {
        bio: 'A real city-garden designer.',
        posts_count: 12,
        followers_count: 34,
        followings_count: 5,
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
        author_profile: '/@display_person',
        like: '/posts/detail-post/like',
        appreciation: '/posts/detail-post/appreciation',
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
        about_creator: 'About the creator',
        no_bio: 'This user has not written a bio.',
        threads: 'Threads',
        followers: 'Followers',
        following: 'Following',
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
        photo_gallery: 'Photo gallery',
        image_position: 'Photo :current of :total',
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
            dismiss: 'Cancel',
            choose_type: 'Choose an Appreciation type',
            cost_label: '5 Points',
            cost_copy: 'Costs 5 Points.',
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
    },
} as PostDetailPageProps;

describe('Post detail composition', () => {
    let scrollXDescriptor: PropertyDescriptor | undefined;
    let scrollYDescriptor: PropertyDescriptor | undefined;

    beforeEach(() => {
        scrollXDescriptor = Object.getOwnPropertyDescriptor(window, 'scrollX');
        scrollYDescriptor = Object.getOwnPropertyDescriptor(window, 'scrollY');
        usePageMock.mockReturnValue({ props });
        routerPostMock.mockReset();
        routerDeleteMock.mockReset();
    });

    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
        if (scrollXDescriptor) Object.defineProperty(window, 'scrollX', scrollXDescriptor);
        if (scrollYDescriptor) Object.defineProperty(window, 'scrollY', scrollYDescriptor);
    });

    it('uses the Skuy Prism detail grid and a data-backed author context card', () => {
        usePageMock.mockReturnValue({
            props: {
                ...props,
                routes: { ...props.routes, author_profile: '/@display_person' },
            },
        });
        render(<Show />);

        const page = document.querySelector('[data-skuy-prism-detail]');
        expect(page).not.toBeNull();
        expect(page?.querySelector('.skuy-post-detail__layout')).not.toBeNull();
        expect(page?.querySelector('main')).toBeNull();
        expect(page?.querySelector('.skuy-post-detail__main')).not.toBeNull();

        const context = screen.getByRole('complementary', { name: 'About the creator' });
        const creatorLink = within(context).getByRole('link', { name: /Display Person.*@display_person/ });
        expect(creatorLink.getAttribute('href')).toBe('/@display_person');
        expect(within(context).getByText('A real city-garden designer.')).not.toBeNull();
        expect(context.querySelector('[data-profile-stat="threads"]')?.textContent).toBe('12');
        expect(context.querySelector('[data-profile-stat="followers"]')?.textContent).toBe('34');
        expect(context.querySelector('[data-profile-stat="following"]')?.textContent).toBe('5');
        expect(page?.querySelector('.skuy-post-detail__root-header .skuy-avatar')).not.toBeNull();
    });

    it('separates root post, reply composer, and conversation while leading with author identity', () => {
        render(<Show />);

        const page = document.querySelector('.skuy-post-detail-page');
        expect(page?.classList.contains('skuy-post-detail-page')).toBe(true);

        const root = screen.getByRole('article');
        expect(root.classList.contains('skuy-post-detail__root')).toBe(true);
        expect(root.classList.contains('skuy-post--detail-root')).toBe(true);
        expect(root.classList.contains('skuy-social-post')).toBe(true);
        expect(page?.querySelector('.skuy-post-detail__header')).not.toBeNull();
        expect(page?.querySelector('.skuy-post-detail__title')?.textContent).toBe('Thread');
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

    it('keeps Save and Remove from Saved in the mobile post-options menu', () => {
        render(<Show />);

        fireEvent.click(screen.getByRole('button', { name: 'Open Thread options' }));
        let menu = screen.getByRole('menu', { name: 'Open Thread options' });
        let saveAction = within(menu).getByRole('menuitem', { name: 'Save' });
        expect(saveAction.className).toContain('skuy-post-options__bookmark');
        fireEvent.click(saveAction);

        expect(routerPostMock).toHaveBeenCalledWith(
            '/posts/detail-post/bookmark',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );

        cleanup();
        const savedProps = {
            ...props,
            display_post: {
                ...props.display_post,
                interactions: { ...props.display_post.interactions, bookmarked: true },
            },
        } as PostDetailPageProps;
        usePageMock.mockReturnValue({ props: savedProps });
        render(<Show />);

        fireEvent.click(screen.getByRole('button', { name: 'Open Thread options' }));
        menu = screen.getByRole('menu', { name: 'Open Thread options' });
        const removeAction = within(menu).getByRole('menuitem', { name: 'Remove from Saved' });
        expect(removeAction.className).toContain('skuy-post-options__bookmark');
        fireEvent.click(removeAction);

        expect(routerDeleteMock).toHaveBeenCalledWith(
            '/posts/detail-post/bookmark',
            expect.objectContaining({ preserveScroll: true }),
        );
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
