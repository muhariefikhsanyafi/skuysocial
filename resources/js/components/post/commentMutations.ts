import type {
    CommentFeedbackTranslations,
    CommentLikeResponse,
    ReactiveCommentCreationResponse,
} from '../../types/post';

type CommentMutationFailure = 'session_expired' | 'restricted' | 'invalid' | 'failed' | 'network';

export class CommentMutationError extends Error {
    constructor(
        readonly reason: CommentMutationFailure,
        message = '',
    ) {
        super(message);
        this.name = 'CommentMutationError';
    }
}

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function firstValidationMessage(payload: unknown): string | null {
    if (!isRecord(payload) || !isRecord(payload.errors)) return null;

    for (const value of Object.values(payload.errors)) {
        if (Array.isArray(value) && typeof value[0] === 'string') return value[0];
    }

    return null;
}

export async function requestCommentMutation(
    url: string,
    body: Record<string, string | number | boolean>,
): Promise<unknown> {
    let requestUrl: URL;
    try {
        requestUrl = new URL(url, window.location.href);
    } catch {
        throw new CommentMutationError('failed');
    }

    if (requestUrl.origin !== window.location.origin) {
        throw new CommentMutationError('failed');
    }

    let response: Response;
    try {
        response = await fetch(requestUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });
    } catch {
        throw new CommentMutationError('network');
    }

    const payload: unknown = await response.json().catch(() => null);

    if (response.redirected || response.status === 401 || response.status === 419) {
        throw new CommentMutationError('session_expired');
    }

    if (response.status === 403) {
        const message = isRecord(payload) && typeof payload.message === 'string' ? payload.message : '';
        throw new CommentMutationError('restricted', message);
    }

    if (response.status === 422) {
        throw new CommentMutationError('invalid', firstValidationMessage(payload) ?? '');
    }

    if (!response.ok) {
        throw new CommentMutationError('failed');
    }

    return payload;
}

export function commentMutationMessage(
    error: unknown,
    translations: CommentFeedbackTranslations,
): string {
    if (!(error instanceof CommentMutationError)) return translations.network;

    switch (error.reason) {
        case 'session_expired':
            return translations.session_expired;
        case 'restricted':
            return error.message || translations.restricted;
        case 'invalid':
            return error.message || translations.invalid;
        case 'failed':
            return translations.failed;
        case 'network':
            return translations.network;
    }
}

export function isReactiveCommentCreationResponse(
    payload: unknown,
    postUid: string,
    parentId: number | null,
): payload is ReactiveCommentCreationResponse {
    if (!isRecord(payload) || !isRecord(payload.comment)) return false;

    const comment = payload.comment;
    const author = comment.author;
    const actions = comment.actions;

    return payload.post_uid === postUid
        && Number.isInteger(payload.comment_count)
        && Number(payload.comment_count) >= 0
        && Number.isInteger(payload.root_comment_count)
        && Number(payload.root_comment_count) >= 0
        && (parentId === null
            ? payload.parent_reply_count === null
            : Number.isInteger(payload.parent_reply_count) && Number(payload.parent_reply_count) >= 1)
        && Number.isInteger(comment.id)
        && Number(comment.id) > 0
        && comment.post_uid === postUid
        && comment.parent_id === parentId
        && typeof comment.content === 'string'
        && typeof comment.datetime === 'string'
        && typeof comment.display_time === 'string'
        && isRecord(author)
        && Number.isInteger(author.id)
        && typeof author.name === 'string'
        && typeof author.username === 'string'
        && (typeof author.avatar_url === 'string' || author.avatar_url === null)
        && typeof author.is_verified === 'boolean'
        && Number.isInteger(comment.like_count)
        && Number(comment.like_count) >= 0
        && typeof comment.liked === 'boolean'
        && Number.isInteger(comment.replies_count)
        && Number(comment.replies_count) >= 0
        && Array.isArray(comment.replies)
        && isRecord(actions)
        && typeof actions.like_url === 'string'
        && typeof actions.reply_url === 'string'
        && typeof actions.share_url === 'string';
}

export function isCommentLikeResponse(payload: unknown, commentId: number): payload is CommentLikeResponse {
    return isRecord(payload)
        && payload.comment_id === commentId
        && typeof payload.liked === 'boolean'
        && Number.isInteger(payload.like_count)
        && Number(payload.like_count) >= 0;
}
