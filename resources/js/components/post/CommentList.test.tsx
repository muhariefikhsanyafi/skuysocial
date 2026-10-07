import { useState } from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type {
    CommentPagination,
    ExpandedReplies,
    PostDetailRoutes,
    PostDetailTranslations,
    ReactiveCommentCreationResponse,
} from '../../types/post';
import { CommentList } from './CommentList';

vi.mock('@inertiajs/react', () => ({
    Link: ({ children, ...props }: { children: React.ReactNode; [key: string]: unknown }) => <a {...props}>{children}</a>,
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
    feedback: {
        liked: 'Liked',
        unliked: 'Unliked',
        session_expired: 'Session expired',
        restricted: 'Restricted',
        invalid: 'Invalid like response',
        failed: 'Failed',
        network: 'Network failed',
    },
    comment_feedback: {
        session_expired: 'Comment session expired',
        restricted: 'Commenting is restricted',
        invalid: 'Invalid comment',
        failed: 'Comment action failed',
        network: 'Comment network failed',
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
            session_expired: 'Session expired',
            restricted: 'Restricted',
            invalid: 'Invalid appreciation type',
            failed: 'Appreciation failed',
            network: 'Network failed',
        },
    },
} as PostDetailTranslations;

const routes: PostDetailRoutes = {
    feed: '/feed',
    author_profile: '/@root-author',
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

function jsonResponse(payload: unknown, status = 201): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        redirected: false,
        json: async () => payload,
    } as Response;
}

function makeCreatedReply(content: string): ReactiveCommentCreationResponse {
    return {
        post_uid: 'post-1',
        comment_count: 3,
        root_comment_count: 1,
        parent_reply_count: 2,
        comment: {
            id: 12,
            post_uid: 'post-1',
            parent_id: root.id,
            content,
            datetime: '2026-09-23T00:01:00+00:00',
            display_time: 'now',
            author: replyAuthor,
            like_count: 0,
            liked: false,
            replies_count: 0,
            replies: [],
            actions: {
                like_url: '/comments/12/like',
                reply_url: routes.comment,
                share_url: '/post/post-1#reply-12',
            },
            reply_expansion_url: null,
        },
        feed_html: '<div class="skuy-comment"></div>',
    };
}

function CommentListHarness() {
    const [createdComments, setCreatedComments] = useState<ReactiveCommentCreationResponse[]>([]);

    return (
        <CommentList
            comments={comments}
            expandedReplyRootId={null}
            expandedReplies={expandedReplies}
            postUid="post-1"
            createdComments={createdComments}
            onCommentCreated={(response) => setCreatedComments((current) => [...current, response])}
            routes={routes}
            translations={translations}
            canPerformSocialWrites={true}
        />
    );
}

function renderListWithComments(items: CommentPagination['data'], expansion: ExpandedReplies | null = null) {
    render(
        <CommentList
            comments={{ ...comments, data: items }}
            expandedReplyRootId={expansion ? items[0].id : null}
            expandedReplies={expansion}
            postUid="post-1"
            createdComments={[]}
            onCommentCreated={vi.fn()}
            routes={routes}
            translations={translations}
            canPerformSocialWrites={true}
        />,
    );
}

function renderList() {
    return render(
        <CommentList
            comments={comments}
            expandedReplyRootId={null}
            expandedReplies={expandedReplies}
            postUid="post-1"
            createdComments={[]}
            onCommentCreated={vi.fn()}
            routes={routes}
            translations={translations}
            canPerformSocialWrites={true}
        />,
    );
}

describe('CommentList reactive mutations', () => {
    beforeEach(() => {
        document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        document.body.innerHTML = '';
    });

    it('exposes the root and nested reply structure for the conversation connector', () => {
        renderList();

        const directReply = screen.getByText('Root content').closest('.skuy-post-detail__reply--root');
        const nestedReply = screen.getByText('Reply content').closest('.skuy-post-detail__reply--nested');

        expect(directReply).not.toBeNull();
        expect(nestedReply).not.toBeNull();
        expect(nestedReply?.closest('.skuy-comment__replies')).not.toBeNull();
        expect(screen.getByText('Root author')).toBeTruthy();
        expect(screen.getByText('@root-author')).toBeTruthy();
        expect(screen.getByText('Reply author')).toBeTruthy();
        expect(screen.getByText('@reply-author')).toBeTruthy();
        expect(document.querySelectorAll('.skuy-comment__avatar-column')).toHaveLength(2);
        const repliesGroup = document.querySelector('.skuy-comment__replies--has-items');
        expect(repliesGroup).not.toBeNull();
        expect(repliesGroup?.querySelectorAll('.skuy-post-detail__reply--nested')).toHaveLength(1);
        expect(repliesGroup?.querySelectorAll('.skuy-post-detail__thread-elbow')).toHaveLength(1);
        expect(repliesGroup?.querySelectorAll('.is-thread-last')).toHaveLength(1);
        expect(repliesGroup?.querySelectorAll('.is-thread-middle')).toHaveLength(0);
        const spine = repliesGroup?.querySelector(':scope > .skuy-post-detail__thread-spine');
        expect(repliesGroup?.querySelectorAll('.skuy-post-detail__thread-spine')).toHaveLength(1);
        expect(spine?.getAttribute('aria-hidden')).toBe('true');
        expect((repliesGroup as HTMLElement).style.getPropertyValue('--thread-reply-count')).toBe('1');
        expect(spine?.nextElementSibling).toBe(nestedReply);
        expect(repliesGroup?.querySelectorAll(':scope > .skuy-post-detail__reply--nested')).toHaveLength(1);
        expect(repliesGroup?.querySelector('.is-thread-last')).toBe(repliesGroup?.querySelector(':scope > .skuy-post-detail__reply--nested'));
        expect(directReply?.querySelector('.skuy-comment__avatar-column')?.getAttribute('class')).toContain('items-center');
        expect(repliesGroup?.querySelector('.is-thread-last .skuy-comment__avatar-column')?.getAttribute('class')).toContain('items-center');
        expect(screen.getAllByRole('button', { name: 'Reply' })[0].querySelector('[data-comment-reply-count="1"]')).not.toBeNull();
    });

    it('renders one continuous spine and ends it at the last of multiple replies', () => {
        const multiReplyRoot = {
            ...root,
            replies_count: 2,
            replies: [root.replies[0], { ...root.replies[0], id: 12, content: 'Second reply' }],
        };
        renderListWithComments([multiReplyRoot]);

        const repliesGroup = document.querySelector('.skuy-comment__replies--has-items');
        expect(document.querySelectorAll('.skuy-post-detail__reply-group')).toHaveLength(1);
        expect(repliesGroup?.querySelectorAll(':scope > .skuy-post-detail__thread-spine')).toHaveLength(1);
        expect(repliesGroup?.querySelectorAll('.skuy-post-detail__thread-spine')).toHaveLength(1);
        expect((repliesGroup as HTMLElement).style.getPropertyValue('--thread-reply-count')).toBe('2');
        expect(repliesGroup?.querySelectorAll('.skuy-post-detail__thread-elbow')).toHaveLength(2);
        expect(repliesGroup?.querySelectorAll('.is-thread-middle')).toHaveLength(1);
        expect(repliesGroup?.querySelectorAll('.is-thread-last')).toHaveLength(1);
        expect(repliesGroup?.querySelector('.is-thread-last')).toBe(repliesGroup?.querySelectorAll(':scope > .skuy-post-detail__reply--nested').item(1));
        expect(document.querySelector('.skuy-post-detail__thread-stem')).toBeNull();
        expect(screen.getAllByRole('button', { name: 'Reply' })[0].querySelector('[data-comment-reply-count="2"]')).not.toBeNull();
    });

    it('renders no thread connector for a root without replies', () => {
        renderListWithComments([{ ...root, replies_count: 0, replies: [] }]);

        expect(document.querySelector('.skuy-comment__replies--has-items')).toBeNull();
        expect(document.querySelector('.skuy-post-detail__reply-group')).toBeNull();
        expect(document.querySelector('.skuy-post-detail__thread-spine')).toBeNull();
        expect(document.querySelector('.skuy-post-detail__thread-elbow')).toBeNull();
    });

    it('sizes the expanded spine from visible children rather than the preview or total count', () => {
        renderListWithComments([{ ...root, replies_count: 12, replies: [] }], {
            data: [root.replies[0], { ...root.replies[0], id: 12, content: 'Expanded final reply' }],
            per_page: 10,
            has_more: true,
            next_url: '/post/post-1?replies_for=10&replies_cursor=next',
            previous_url: null,
        });

        const group = document.querySelector('.skuy-post-detail__reply-group') as HTMLElement;
        expect(group.style.getPropertyValue('--thread-reply-count')).toBe('2');
        expect(group.querySelectorAll('.skuy-post-detail__thread-spine')).toHaveLength(1);
        expect(group.querySelectorAll('.skuy-post-detail__thread-elbow')).toHaveLength(2);
        expect(group.querySelector('.is-thread-last')).toBe(screen.getByText('Expanded final reply').closest('.skuy-post-detail__reply--nested'));
        expect(screen.getByRole('link', { name: 'View more replies' }).closest('.skuy-post-detail__reply-group')).toBeNull();
        expect(document.querySelector('[data-comment-reply-count="12"]')).not.toBeNull();
    });

    it('creates a reply with the root parent and inserts it under that thread', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse(makeCreatedReply('Reply from the nested control')));
        vi.stubGlobal('fetch', fetchMock);
        render(<CommentListHarness />);

        const replyButtons = screen.getAllByRole('button', { name: 'Reply' });
        fireEvent.click(replyButtons[1]);
        const input = screen.getByPlaceholderText('Reply to Root author...');
        fireEvent.change(input, { target: { value: 'Reply from the nested control' } });
        fireEvent.click(screen.getByRole('button', { name: 'Send' }));

        await waitFor(() => expect(screen.getByText('Reply from the nested control')).toBeTruthy());
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][0].toString()).toBe('http://localhost:3000/posts/post-1/comments');
        expect(JSON.parse(fetchMock.mock.calls[0][1].body as string)).toEqual({
            content: 'Reply from the nested control',
            parent_id: root.id,
        });
        const createdReply = screen.getByText('Reply from the nested control').closest('.skuy-post-detail__reply--nested');
        expect(createdReply?.closest('.skuy-comment__replies')).not.toBeNull();
        await waitFor(() => expect(screen.getAllByRole('button', { name: 'Reply' })[0].querySelector('[data-comment-reply-count="2"]')).not.toBeNull());
        const group = createdReply?.closest('.skuy-post-detail__reply-group') as HTMLElement;
        expect(group.style.getPropertyValue('--thread-reply-count')).toBe('2');
        expect(group.querySelectorAll('.skuy-post-detail__thread-spine')).toHaveLength(1);
        expect(createdReply?.classList.contains('is-thread-middle')).toBe(true);
        expect(group.querySelectorAll('.is-thread-last')).toHaveLength(1);
        expect(group.querySelector('.is-thread-last')).toBe(screen.getByText('Reply content').closest('.skuy-post-detail__reply--nested'));
    });

    it('updates comment Like state and count only from the server response', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ comment_id: root.id, liked: true, like_count: 1 }, 200));
        vi.stubGlobal('fetch', fetchMock);
        renderList();

        const likeButton = screen.getAllByRole('button', { name: 'Like' })[0];
        fireEvent.click(likeButton);

        await waitFor(() => expect(likeButton.getAttribute('aria-pressed')).toBe('true'));
        expect(likeButton.querySelector('span')?.textContent).toBe('1');
        expect(JSON.parse(fetchMock.mock.calls[0][1].body as string)).toEqual({ liked: true });
    });

    it('blocks duplicate Like submits while preserving state during the request', async () => {
        let resolveResponse: (response: Response) => void = () => undefined;
        const responsePromise = new Promise<Response>((resolve) => {
            resolveResponse = resolve;
        });
        const fetchMock = vi.fn().mockReturnValue(responsePromise);
        vi.stubGlobal('fetch', fetchMock);
        renderList();

        const likeButton = screen.getAllByRole('button', { name: 'Like' })[0];
        const form = likeButton.closest('form');
        if (!form) throw new Error('Expected comment Like form');
        fireEvent.submit(form);
        fireEvent.submit(form);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(likeButton.getAttribute('aria-pressed')).toBe('false');
        resolveResponse(jsonResponse({ comment_id: root.id, liked: true, like_count: 1 }, 200));
        await waitFor(() => expect(likeButton.getAttribute('aria-pressed')).toBe('true'));
    });

    it('keeps reply text and open composer when server validation fails', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ errors: { content: ['The comment field is required.'] } }, 422));
        vi.stubGlobal('fetch', fetchMock);
        renderList();

        fireEvent.click(screen.getAllByRole('button', { name: 'Reply' })[0]);
        const input = screen.getByPlaceholderText('Reply to Root author...') as HTMLInputElement;
        fireEvent.change(input, { target: { value: 'Keep this text after failure' } });
        fireEvent.click(screen.getByRole('button', { name: 'Send' }));

        await waitFor(() => expect(screen.getByRole('alert').textContent).toBe('The comment field is required.'));
        expect(input.value).toBe('Keep this text after failure');
    });
});
