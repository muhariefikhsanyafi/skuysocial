import { useEffect, useRef, useState, type FormEvent } from 'react';
import type { AppreciationTypeValue, DisplayPost, PostDetailRoutes, PostDetailTranslations } from '../../types/post';
import { BookmarkIcon, CommentIcon, HeartIcon, RepostIcon, ShareIcon, StarIcon } from './icons';

interface PostActionsProps {
    post: DisplayPost;
    routes: PostDetailRoutes;
    translations: PostDetailTranslations;
    canPerformSocialWrites: boolean;
    viewerId: number;
    commentCount: number;
}

interface JsonActionPayload {
    code?: unknown;
    status?: unknown;
    post_uid?: unknown;
    liked?: unknown;
    like_count?: unknown;
    appreciation_count?: unknown;
    type?: unknown;
    message?: unknown;
    reposted?: unknown;
    repost_count?: unknown;
    bookmarked?: unknown;
}

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

const APPRECIATION_TYPES: AppreciationTypeValue[] = ['bermanfaat', 'inspiratif', 'solutif'];

function isAppreciationType(value: unknown): value is AppreciationTypeValue {
    return APPRECIATION_TYPES.includes(value as AppreciationTypeValue);
}

async function requestAppreciation(
    url: string,
    type: AppreciationTypeValue,
    translations: PostDetailTranslations,
): Promise<unknown> {
    let response: Response;

    try {
        response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ type }),
        });
    } catch {
        throw new Error(translations.appreciation.feedback.network);
    }

    const payload = await response.json().catch(() => null) as JsonActionPayload | null;

    if (response.redirected || response.status === 401 || response.status === 419) {
        throw new Error(translations.appreciation.feedback.session_expired);
    }

    if (!response.ok) {
        if (
            payload !== null
            && ['insufficient_points', 'self_appreciation_forbidden'].includes(String(payload.status))
            && typeof payload.message === 'string'
        ) {
            throw new Error(payload.message);
        }

        if (response.status === 403) {
            throw new Error(translations.appreciation.feedback.restricted);
        }

        if (response.status === 422) {
            throw new Error(translations.appreciation.feedback.invalid);
        }

        throw new Error(translations.appreciation.feedback.failed);
    }

    return payload;
}

async function requestAction(
    url: string,
    method: 'POST' | 'DELETE',
    body: Record<string, boolean> | null,
    translations: PostDetailTranslations,
): Promise<unknown> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (body !== null) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers,
        body: body === null ? undefined : JSON.stringify(body),
    });
    const payload = await response.json().catch(() => null) as JsonActionPayload | null;

    if (response.redirected || response.status === 401 || response.status === 419) {
        throw new Error(translations.feedback.session_expired);
    }

    if (response.status === 403) {
        throw new Error(translations.feedback.restricted);
    }

    if (!response.ok) {
        throw new Error(response.status === 422 ? translations.feedback.invalid : translations.feedback.failed);
    }

    return payload;
}

function feedbackForError(error: unknown, translations: PostDetailTranslations): string {
    const knownFeedback = Object.values(translations.feedback);

    return error instanceof Error && knownFeedback.includes(error.message)
        ? error.message
        : translations.feedback.network;
}

function isActionPayload(payload: unknown): payload is JsonActionPayload {
    return typeof payload === 'object' && payload !== null;
}

export function PostActions({ post, routes, translations, canPerformSocialWrites, viewerId, commentCount }: PostActionsProps) {
    const [liked, setLiked] = useState(post.interactions.liked);
    const [likeCount, setLikeCount] = useState(post.interactions.like_count);
    const [appreciationCount, setAppreciationCount] = useState(post.interactions.appreciation_count);
    const [hasAppreciated, setHasAppreciated] = useState(post.interactions.appreciated);
    const [appreciationType, setAppreciationType] = useState(post.interactions.appreciation_type);
    const [appreciationChoicesOpen, setAppreciationChoicesOpen] = useState(false);
    const [appreciationPopoverPlacement, setAppreciationPopoverPlacement] = useState<'above' | 'below'>('below');
    const [reposted, setReposted] = useState(post.interactions.reposted);
    const [repostCount, setRepostCount] = useState(post.interactions.repost_count);
    const [bookmarked, setBookmarked] = useState(post.interactions.bookmarked);
    const [likePending, setLikePending] = useState(false);
    const [repostPending, setRepostPending] = useState(false);
    const [bookmarkPending, setBookmarkPending] = useState(false);
    const [appreciationPending, setAppreciationPending] = useState(false);
    const likeInFlightRef = useRef(false);
    const repostInFlightRef = useRef(false);
    const bookmarkInFlightRef = useRef(false);
    const appreciationInFlightRef = useRef(false);
    const appreciationTriggerRef = useRef<HTMLButtonElement>(null);
    const appreciationPopoverRef = useRef<HTMLDivElement>(null);
    const [statusMessage, setStatusMessage] = useState('');
    const [statusIsError, setStatusIsError] = useState(false);

    useEffect(() => {
        setLiked(post.interactions.liked);
        setLikeCount(post.interactions.like_count);
        setAppreciationCount(post.interactions.appreciation_count);
        setHasAppreciated(post.interactions.appreciated);
        setAppreciationType(post.interactions.appreciation_type);
        setAppreciationChoicesOpen(false);
        setReposted(post.interactions.reposted);
        setRepostCount(post.interactions.repost_count);
        setBookmarked(post.interactions.bookmarked);
    }, [
        post.interactions.bookmarked,
        post.interactions.like_count,
        post.interactions.liked,
        post.interactions.appreciation_count,
        post.interactions.appreciated,
        post.interactions.appreciation_type,
        post.interactions.repost_count,
        post.interactions.reposted,
    ]);

    useEffect(() => {
        if (!appreciationChoicesOpen) return;

        const trigger = appreciationTriggerRef.current;
        const popover = appreciationPopoverRef.current;

        if (trigger === null || popover === null) return;

        const triggerRect = trigger.getBoundingClientRect();
        const popoverHeight = popover.getBoundingClientRect().height;
        const mobileDock = document.querySelector<HTMLElement>('.skuy-mobile-nav');
        const bottomBoundary = mobileDock !== null && mobileDock.getClientRects().length > 0
            ? mobileDock.getBoundingClientRect().top
            : window.innerHeight;
        const spaceBelow = bottomBoundary - triggerRect.bottom - 8;
        const spaceAbove = triggerRect.top - 8;

        setAppreciationPopoverPlacement(
            spaceBelow >= popoverHeight || spaceAbove < popoverHeight ? 'below' : 'above',
        );
    }, [appreciationChoicesOpen]);

    const setErrorStatus = (message: string) => {
        setStatusMessage(message);
        setStatusIsError(true);
    };

    const toggleLike = async () => {
        if (!canPerformSocialWrites || likePending || likeInFlightRef.current) return;

        likeInFlightRef.current = true;
        const previousLiked = liked;
        const previousCount = likeCount;
        const desiredLiked = !liked;
        setLikePending(true);
        setStatusMessage('');
        setStatusIsError(false);
        setLiked(desiredLiked);
        setLikeCount(Math.max(0, previousCount + (desiredLiked ? 1 : -1)));

        try {
            const payload = await requestAction(routes.like, 'POST', { liked: desiredLiked }, translations);

            if (
                !isActionPayload(payload)
                || payload.post_uid !== post.uid
                || typeof payload.liked !== 'boolean'
                || !Number.isInteger(payload.like_count)
                || Number(payload.like_count) < 0
            ) {
                throw new Error(translations.feedback.invalid);
            }

            setLiked(payload.liked);
            setLikeCount(Number(payload.like_count));
            setStatusMessage(payload.liked ? translations.feedback.liked : translations.feedback.unliked);
        } catch (error) {
            setLiked(previousLiked);
            setLikeCount(previousCount);
            setErrorStatus(feedbackForError(error, translations));
        } finally {
            likeInFlightRef.current = false;
            setLikePending(false);
        }
    };

    const giveAppreciation = async (type: AppreciationTypeValue) => {
        if (
            !canPerformSocialWrites
            || viewerId === post.author.id
            || hasAppreciated
            || appreciationPending
            || appreciationInFlightRef.current
        ) {
            return;
        }

        appreciationInFlightRef.current = true;
        setAppreciationPending(true);
        setStatusMessage('');
        setStatusIsError(false);

        try {
            const payload = await requestAppreciation(routes.appreciation, type, translations);

            if (
                !isActionPayload(payload)
                || !['appreciated', 'already_appreciated'].includes(String(payload.status))
                || !isAppreciationType(payload.type)
                || !Number.isInteger(payload.appreciation_count)
                || Number(payload.appreciation_count) < 0
                || typeof payload.message !== 'string'
            ) {
                throw new Error(translations.appreciation.feedback.invalid);
            }

            setAppreciationCount(Number(payload.appreciation_count));
            setHasAppreciated(true);
            setAppreciationType(payload.type);
            setAppreciationChoicesOpen(false);
            setStatusMessage(payload.message);
        } catch (error) {
            const message = error instanceof Error && error.message !== ''
                ? error.message
                : translations.appreciation.feedback.network;
            setErrorStatus(message);
        } finally {
            appreciationInFlightRef.current = false;
            setAppreciationPending(false);
        }
    };

    const toggleRepost = async () => {
        if (!canPerformSocialWrites || repostPending || repostInFlightRef.current) return;

        repostInFlightRef.current = true;
        setRepostPending(true);
        setStatusMessage('');
        setStatusIsError(false);

        try {
            const payload = await requestAction(routes.repost, 'POST', { reposted: !reposted }, translations);

            if (
                !isActionPayload(payload)
                || payload.post_uid !== post.uid
                || typeof payload.reposted !== 'boolean'
                || !Number.isInteger(payload.repost_count)
                || Number(payload.repost_count) < 0
            ) {
                throw new Error(translations.feedback.invalid);
            }

            setReposted(payload.reposted);
            setRepostCount(Number(payload.repost_count));
        } catch (error) {
            setErrorStatus(feedbackForError(error, translations));
        } finally {
            repostInFlightRef.current = false;
            setRepostPending(false);
        }
    };

    const toggleBookmark = async () => {
        if (!canPerformSocialWrites || bookmarkPending || bookmarkInFlightRef.current) return;

        bookmarkInFlightRef.current = true;
        const desiredBookmarked = !bookmarked;
        setBookmarkPending(true);
        setStatusMessage('');
        setStatusIsError(false);

        try {
            const payload = await requestAction(
                desiredBookmarked ? routes.bookmark_store : routes.bookmark_destroy,
                desiredBookmarked ? 'POST' : 'DELETE',
                null,
                translations,
            );

            if (
                !isActionPayload(payload)
                || payload.post_uid !== post.uid
                || typeof payload.bookmarked !== 'boolean'
            ) {
                throw new Error(translations.feedback.invalid);
            }

            setBookmarked(payload.bookmarked);
        } catch (error) {
            setErrorStatus(feedbackForError(error, translations));
        } finally {
            bookmarkInFlightRef.current = false;
            setBookmarkPending(false);
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
            setStatusIsError(false);
        } catch {
            setErrorStatus(translations.feedback.network);
        }
    };

    const bookmarkLabel = bookmarked ? translations.remove_from_saved : translations.save;
    const canOfferAppreciation = canPerformSocialWrites && viewerId !== post.author.id && !hasAppreciated;
    const appreciationCountLabel = (appreciationCount === 1
        ? translations.appreciation.count_one
        : translations.appreciation.count_other
    ).replace(':count', String(appreciationCount));
    const selectedAppreciationLabel = appreciationType === null
        ? ''
        : translations.appreciation.selected.replace(':type', translations.appreciation.types[appreciationType]);

    return (
        <>
            <div className="skuy-actions skuy-actions--primary mt-4 border-t border-[var(--skuy-border-subtle)] pt-3">
                <form onSubmit={(event: FormEvent) => { event.preventDefault(); void toggleLike(); }}>
                    <button
                        type="submit"
                        aria-label={`${liked ? translations.unlike : translations.like}${likeCount > 0 ? ` (${likeCount})` : ''}`}
                        aria-pressed={liked}
                        aria-busy={likePending}
                        title={liked ? translations.unlike : translations.like}
                        disabled={!canPerformSocialWrites || likePending}
                        className="skuy-action skuy-action--reaction disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <HeartIcon filled={liked} />
                        <span className="text-xs font-semibold">{likeCount > 0 ? likeCount : ''}</span>
                    </button>
                </form>

                {(canOfferAppreciation || hasAppreciated || appreciationCount > 0) && (
                    <div className="skuy-appreciation skuy-appreciation--inline">
                        {canOfferAppreciation ? (
                            <>
                                <button
                                    type="button"
                                    ref={appreciationTriggerRef}
                                    data-post-appreciation-trigger
                                    className="skuy-action skuy-action--appreciation"
                                    aria-label={`${translations.appreciation.action}, ${translations.appreciation.cost_label}`}
                                    title={`${translations.appreciation.action}, ${translations.appreciation.cost_label}`}
                                    aria-expanded={appreciationChoicesOpen}
                                    aria-controls={`post-appreciation-${post.uid}-choices`}
                                    onClick={() => setAppreciationChoicesOpen((open) => !open)}
                                >
                                    <StarIcon />
                                    {appreciationCount > 0 && (
                                        <span className="skuy-appreciation__count" aria-label={appreciationCountLabel}>{appreciationCount}</span>
                                    )}
                                </button>
                                {appreciationChoicesOpen && (
                                    <div
                                        ref={appreciationPopoverRef}
                                        id={`post-appreciation-${post.uid}-choices`}
                                        className={`skuy-appreciation__popover${appreciationPopoverPlacement === 'above' ? ' is-above' : ''}`}
                                    >
                                        <p id={`post-appreciation-${post.uid}-choose-type`} className="skuy-appreciation__choose-type">
                                            {translations.appreciation.choose_type}
                                        </p>
                                        <p className="skuy-appreciation__cost">{translations.appreciation.cost_copy}</p>
                                        <div
                                            className="skuy-appreciation__choices"
                                            role="group"
                                            aria-labelledby={`post-appreciation-${post.uid}-choose-type`}
                                        >
                                            {APPRECIATION_TYPES.map((type) => (
                                                <button
                                                    key={type}
                                                    type="button"
                                                    onClick={() => void giveAppreciation(type)}
                                                    disabled={appreciationPending}
                                                    aria-busy={appreciationPending}
                                                    className="skuy-appreciation__choice disabled:cursor-not-allowed disabled:opacity-50"
                                                >
                                                    {translations.appreciation.types[type]}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </>
                        ) : hasAppreciated ? (
                            <button
                                type="button"
                                disabled
                                aria-label={selectedAppreciationLabel}
                                title={selectedAppreciationLabel}
                                aria-pressed="true"
                                className="skuy-action skuy-action--appreciation skuy-action--appreciated"
                            >
                                <StarIcon filled />
                                {appreciationCount > 0 && <span className="skuy-appreciation__count">{appreciationCount}</span>}
                            </button>
                        ) : (
                            <span className="skuy-action skuy-action--appreciation" aria-label={appreciationCountLabel} title={appreciationCountLabel}>
                                <StarIcon />
                                <span className="skuy-appreciation__count">{appreciationCount}</span>
                            </span>
                        )}
                    </div>
                )}

                <div className="skuy-action cursor-default" aria-label={translations.reply} title={translations.reply}>
                    <CommentIcon />
                    <span className="text-xs font-semibold">{commentCount > 0 ? commentCount : ''}</span>
                </div>

                <form onSubmit={(event: FormEvent) => { event.preventDefault(); void toggleRepost(); }}>
                    <button
                        type="submit"
                        aria-label={translations.repost}
                        aria-pressed={reposted}
                        aria-busy={repostPending}
                        title={translations.repost}
                        disabled={!canPerformSocialWrites || repostPending}
                        className="skuy-action skuy-action--selected disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <RepostIcon active={reposted} />
                        {repostCount > 0 && <span className="text-xs font-semibold">{repostCount}</span>}
                    </button>
                </form>

                <form onSubmit={(event: FormEvent) => { event.preventDefault(); void toggleBookmark(); }}>
                    <button
                        type="submit"
                        aria-label={bookmarkLabel}
                        title={bookmarkLabel}
                        aria-pressed={bookmarked}
                        aria-busy={bookmarkPending}
                        disabled={!canPerformSocialWrites || bookmarkPending}
                        className="skuy-action skuy-action--bookmark disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <BookmarkIcon filled={bookmarked} />
                    </button>
                </form>

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
            {hasAppreciated && selectedAppreciationLabel && (
                <p className="skuy-appreciation__selected" role="status">
                    {selectedAppreciationLabel}
                </p>
            )}
            {statusMessage && (
                <p
                    className={`text-xs font-semibold ${statusIsError ? 'text-[var(--skuy-danger)]' : 'text-[var(--skuy-text-muted)]'}`}
                    role={statusIsError ? 'alert' : 'status'}
                    aria-live={statusIsError ? 'assertive' : 'polite'}
                >
                    {statusMessage}
                </p>
            )}
        </>
    );
}
