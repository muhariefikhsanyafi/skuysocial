import { router } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';
import type { DisplayPost, PostDetailRoutes, PostDetailTranslations } from '../../types/post';
import { BookmarkIcon, CommentIcon, HeartIcon, RepostIcon, ShareIcon } from './icons';

interface PostActionsProps {
    post: DisplayPost;
    routes: PostDetailRoutes;
    translations: PostDetailTranslations;
    canPerformSocialWrites: boolean;
}

interface LikePayload {
    post_uid: string;
    liked: boolean;
    like_count: number;
}

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function actionOptions() {
    return {
        preserveScroll: true,
        headers: {
            'X-CSRF-TOKEN': csrfToken(),
        },
    };
}

export function PostActions({ post, routes, translations, canPerformSocialWrites }: PostActionsProps) {
    const [liked, setLiked] = useState(post.interactions.liked);
    const [likeCount, setLikeCount] = useState(post.interactions.like_count);
    const [likePending, setLikePending] = useState(false);
    const [pendingAction, setPendingAction] = useState<string | null>(null);
    const [statusMessage, setStatusMessage] = useState('');

    useEffect(() => {
        setLiked(post.interactions.liked);
        setLikeCount(post.interactions.like_count);
    }, [post.interactions.like_count, post.interactions.liked]);

    const setErrorStatus = (message: string) => {
        setStatusMessage(message);
    };

    const toggleLike = async () => {
        if (!canPerformSocialWrites || likePending) return;

        const previousLiked = liked;
        const previousCount = likeCount;
        const desiredLiked = !liked;
        setLikePending(true);
        setStatusMessage('');
        setLiked(desiredLiked);
        setLikeCount(Math.max(0, previousCount + (desiredLiked ? 1 : -1)));

        try {
            const response = await fetch(routes.like, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ liked: desiredLiked }),
            });
            const payload = await response.json().catch(() => null) as LikePayload | { code?: string } | null;

            if (response.redirected || response.status === 401 || response.status === 419) {
                throw new Error(translations.feedback.session_expired);
            }

            if (response.status === 403) {
                throw new Error(
                    payload && 'code' in payload && payload.code === 'moderation_state_forbids_social_write'
                        ? translations.feedback.restricted
                        : translations.feedback.failed,
                );
            }

            if (
                !response.ok
                || !payload
                || !('post_uid' in payload)
                || payload.post_uid !== post.uid
                || typeof payload.liked !== 'boolean'
                || !Number.isInteger(payload.like_count)
                || payload.like_count < 0
            ) {
                throw new Error(response.status === 422 ? translations.feedback.invalid : translations.feedback.failed);
            }

            setLiked(payload.liked);
            setLikeCount(payload.like_count);
            setStatusMessage(payload.liked ? translations.feedback.liked : translations.feedback.unliked);
        } catch (error) {
            setLiked(previousLiked);
            setLikeCount(previousCount);
            const knownFeedback = Object.values(translations.feedback);
            const message = error instanceof Error && knownFeedback.includes(error.message)
                ? error.message
                : translations.feedback.network;
            setErrorStatus(message);
        } finally {
            setLikePending(false);
        }
    };

    const submitPostAction = (key: string, method: 'post' | 'delete', url: string) => {
        if (!canPerformSocialWrites || pendingAction !== null) return;

        setPendingAction(key);
        setStatusMessage('');
        const options = {
            ...actionOptions(),
            onError: () => setErrorStatus(translations.feedback.failed),
            onFinish: () => setPendingAction(null),
        };

        if (method === 'delete') {
            router.delete(url, options);
        } else {
            router.post(url, {}, options);
        }
    };

    const copyShareUrl = async () => {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(routes.share);
            } else {
                const textArea = document.createElement('textarea');
                textArea.value = routes.share;
                textArea.style.position = 'fixed';
                textArea.style.left = '-9999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                } finally {
                    document.body.removeChild(textArea);
                }
            }
            setStatusMessage(translations.thread_link_copied);
        } catch {
            setStatusMessage(translations.feedback.network);
        }
    };

    const bookmarkLabel = post.interactions.bookmarked
        ? translations.remove_from_saved
        : translations.save;

    return (
        <>
            <div className="skuy-actions mt-4 border-t border-[var(--skuy-border-subtle)] pt-3">
                <form onSubmit={(event: FormEvent) => { event.preventDefault(); void toggleLike(); }}>
                    <button
                        type="submit"
                        aria-label={`${liked ? translations.unlike : translations.like}${likeCount > 0 ? ` (${likeCount})` : ''}`}
                        aria-pressed={liked}
                        title={liked ? translations.unlike : translations.like}
                        disabled={!canPerformSocialWrites || likePending}
                        className={`skuy-action skuy-action--reaction disabled:cursor-not-allowed disabled:opacity-50${liked ? ' !border-[var(--skuy-reaction)] !bg-[var(--skuy-reaction)] !text-[var(--skuy-on-reaction)]' : ''}`}
                    >
                        <HeartIcon filled={liked} />
                        <span className="text-xs font-semibold">{likeCount > 0 ? likeCount : ''}</span>
                    </button>
                </form>

                <div className="skuy-action cursor-default" aria-label={translations.reply} title={translations.reply}>
                    <CommentIcon />
                    <span className="text-xs font-semibold">{post.interactions.comment_count > 0 ? post.interactions.comment_count : ''}</span>
                </div>

                <form onSubmit={(event: FormEvent) => { event.preventDefault(); submitPostAction('repost', 'post', routes.repost); }}>
                    <button
                        type="submit"
                        aria-label={translations.repost}
                        aria-pressed={post.interactions.reposted}
                        title={translations.repost}
                        disabled={!canPerformSocialWrites || pendingAction !== null}
                        className={`skuy-action skuy-action--selected disabled:cursor-not-allowed disabled:opacity-50${post.interactions.reposted ? ' !border-[var(--skuy-secondary)] !bg-[var(--skuy-secondary)] !text-[var(--skuy-on-secondary)]' : ''}`}
                    >
                        <RepostIcon />
                        {post.interactions.repost_count > 0 && <span className="text-xs font-semibold">{post.interactions.repost_count}</span>}
                    </button>
                </form>

                {post.interactions.bookmarked ? (
                    <form onSubmit={(event: FormEvent) => { event.preventDefault(); submitPostAction('bookmark', 'delete', routes.bookmark_destroy); }}>
                        <button
                            type="submit"
                            aria-label={bookmarkLabel}
                            title={bookmarkLabel}
                            aria-pressed="true"
                            disabled={!canPerformSocialWrites || pendingAction !== null}
                            className="skuy-action skuy-action--bookmark disabled:cursor-not-allowed disabled:opacity-50 !border-[var(--skuy-secondary)] !bg-[var(--skuy-selected)] !text-[var(--skuy-on-selected)]"
                        >
                            <BookmarkIcon filled />
                        </button>
                    </form>
                ) : (
                    <form onSubmit={(event: FormEvent) => { event.preventDefault(); submitPostAction('bookmark', 'post', routes.bookmark_store); }}>
                        <button
                            type="submit"
                            aria-label={bookmarkLabel}
                            title={bookmarkLabel}
                            aria-pressed="false"
                            disabled={!canPerformSocialWrites || pendingAction !== null}
                            className="skuy-action skuy-action--bookmark disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <BookmarkIcon />
                        </button>
                    </form>
                )}

                <button
                    type="button"
                    onClick={() => void copyShareUrl()}
                    className="skuy-action"
                    title={translations.share}
                    aria-label={translations.share}
                >
                    <ShareIcon />
                </button>
            </div>
            {statusMessage && (
                <p className="text-xs font-semibold text-[var(--skuy-text-muted)]" role="status" aria-live="polite">
                    {statusMessage}
                </p>
            )}
        </>
    );
}
