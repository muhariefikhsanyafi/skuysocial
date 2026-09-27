import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { PostDetailPageProps, ReactiveCommentCreationResponse } from '../../types/post';
import { CommentComposer } from '../../pages/Post/Show';

const author = {
    id: 7,
    name: 'Viewer',
    username: 'viewer',
    avatar_url: null,
    is_verified: false,
};

const translations = {
    send: 'Send',
    image_coming_soon: 'Image support is coming soon',
    comments_reply_to: 'Reply to :name...',
    avatar_of: 'Avatar for :name',
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
} as PostDetailPageProps['translations'];

function jsonResponse(payload: unknown, status = 201): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        redirected: false,
        json: async () => payload,
    } as Response;
}

function makeCreatedComment(content: string): ReactiveCommentCreationResponse {
    return {
        post_uid: 'post-1',
        comment_count: 1,
        root_comment_count: 1,
        parent_reply_count: null,
        comment: {
            id: 101,
            post_uid: 'post-1',
            parent_id: null,
            content,
            datetime: '2026-09-23T00:01:00+00:00',
            display_time: 'now',
            author,
            like_count: 0,
            liked: false,
            replies_count: 0,
            replies: [],
            actions: {
                like_url: '/comments/101/like',
                reply_url: '/posts/post-1/comments',
                share_url: '/posts/post-1#comment-101',
            },
            reply_expansion_url: '/posts/post-1?comments_page=1&replies_for=101#comment-101',
        },
        feed_html: '<div class="skuy-comment"></div>',
    };
}

function renderComposer(onCommentCreated = vi.fn()) {
    return render(
        <CommentComposer
            author={author}
            replyTo="root-author"
            actionUrl="/posts/post-1/comments"
            postUid="post-1"
            commentFeedback={translations.comment_feedback}
            onCommentCreated={onCommentCreated}
            translations={translations}
            canPerformSocialWrites={true}
        />,
    );
}

describe('Post Detail top-level comment composer', () => {
    beforeEach(() => {
        document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        document.body.innerHTML = '';
    });

    it('creates a top-level comment without Inertia navigation and clears only after success', async () => {
        const response = makeCreatedComment('A new top-level comment');
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse(response));
        const onCommentCreated = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        renderComposer(onCommentCreated);

        const input = screen.getByRole('textbox');
        fireEvent.change(input, { target: { value: 'A new top-level comment' } });
        fireEvent.click(screen.getByRole('button', { name: 'Send' }));

        await waitFor(() => expect(onCommentCreated).toHaveBeenCalledWith(response));
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][0].toString()).toBe('http://localhost:3000/posts/post-1/comments');
        expect(JSON.parse(fetchMock.mock.calls[0][1].body as string)).toEqual({ content: 'A new top-level comment' });
        expect((input as HTMLInputElement).value).toBe('');
    });

    it('retains entered text and reports validation errors', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ errors: { content: ['The comment field is required.'] } }, 422));
        vi.stubGlobal('fetch', fetchMock);
        renderComposer();

        const input = screen.getByRole('textbox') as HTMLInputElement;
        fireEvent.change(input, { target: { value: 'Keep this after failure' } });
        fireEvent.click(screen.getByRole('button', { name: 'Send' }));

        await waitFor(() => expect(screen.getByRole('alert').textContent).toBe('The comment field is required.'));
        expect(input.value).toBe('Keep this after failure');
    });
});
