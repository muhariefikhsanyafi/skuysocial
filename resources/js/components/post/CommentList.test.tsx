import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { CommentPagination, ExpandedReplies, PostDetailRoutes, PostDetailTranslations } from '../../types/post';
import { CommentList } from './CommentList';

const { routerPost, routerDelete } = vi.hoisted(() => ({
    routerPost: vi.fn(),
    routerDelete: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({
    Link: ({ children, ...props }: { children: React.ReactNode; [key: string]: unknown }) => <a {...props}>{children}</a>,
    router: {
        post: routerPost,
        delete: routerDelete,
    },
}));

const translations = {
    like: 'Like',
    unlike: 'Unlike',
    reply: 'Reply',
    repost_coming_soon: 'Repost (Coming Soon)',
    share: 'Share',
    send: 'Send',
    view_more_replies: 'View more replies',
    comments_reply_to: 'Reply to :name...',
    image_coming_soon: 'Image support is coming soon',
    avatar_of: 'Avatar for :name',
    comment_link_copied: 'Comment link copied!',
    reply_link_copied: 'Reply link copied!',
    feedback: { failed: 'Failed' },
} as PostDetailTranslations;

const routes: PostDetailRoutes = {
    feed: '/feed',
    like: '/posts/post-1/like',
    repost: '/post/post-1/repost',
    bookmark_store: '/posts/post-1/bookmark',
    bookmark_destroy: '/posts/post-1/bookmark',
    comment: '/posts/post-1/comments',
    share: '/post/post-1',
    tags_base: '/tags',
    edit: null,
    delete: null,
};

const author = {
    id: 1,
    name: 'Root author',
    username: 'root-author',
    avatar_url: null,
    is_verified: false,
};

const replyAuthor = {
    id: 2,
    name: 'Reply author',
    username: 'reply-author',
    avatar_url: null,
    is_verified: false,
};

const root = {
    id: 10,
    content: 'Root content',
    datetime: '2026-09-23T00:00:00+00:00',
    display_time: 'now',
    author,
    like_count: 0,
    liked: false,
    replies_count: 1,
    replies: [{
        id: 11,
        content: 'Reply content',
        datetime: '2026-09-23T00:00:00+00:00',
        display_time: 'now',
        author: replyAuthor,
        like_count: 0,
        liked: false,
        replies_count: 0,
        replies: [],
        actions: {
            like_url: '/comments/11/like',
            reply_url: routes.comment,
            share_url: '/post/post-1#reply-11',
        },
    }],
    actions: {
        like_url: '/comments/10/like',
        reply_url: routes.comment,
        share_url: '/post/post-1#comment-10',
    },
};

const comments: CommentPagination = {
    data: [root],
    current_page: 1,
    per_page: 20,
    has_more: false,
    next_url: null,
    previous_url: null,
};

const expandedReplies: ExpandedReplies | null = null;

describe('CommentList reply targeting', () => {
    beforeEach(() => {
        routerPost.mockReset();
        routerDelete.mockReset();
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('keeps replies attached to their root comment because nested parents are invalid', () => {
        render(
            <CommentList
                comments={comments}
                expandedReplyRootId={null}
                expandedReplies={expandedReplies}
                routes={routes}
                translations={translations}
                canPerformSocialWrites={true}
            />,
        );

        const replyButtons = screen.getAllByRole('button', { name: 'Reply' });
        fireEvent.click(replyButtons[1]);

        fireEvent.change(screen.getByPlaceholderText('Reply to Root author...'), {
            target: { value: 'Reply from the nested control' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Send' }));

        expect(routerPost).toHaveBeenCalledWith(
            routes.comment,
            { content: 'Reply from the nested control', parent_id: root.id },
            expect.any(Object),
        );
    });
});
