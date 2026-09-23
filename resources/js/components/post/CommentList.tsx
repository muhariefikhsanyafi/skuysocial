import { Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import type {
    CommentPagination,
    ExpandedReplies,
    PostDetailComment,
    PostDetailRoutes,
    PostDetailTranslations,
} from '../../types/post';
import { Avatar } from './Avatar';
import { CommentIcon, HeartIcon, RepostIcon, ShareIcon } from './icons';

interface CommentListProps {
    comments: CommentPagination;
    expandedReplyRootId: number | null;
    expandedReplies: ExpandedReplies | null;
    routes: PostDetailRoutes;
    translations: PostDetailTranslations;
    canPerformSocialWrites: boolean;
}

interface CommentItemProps {
    comment: PostDetailComment;
    isReply?: boolean;
    expandedReplyRootId: number | null;
    expandedReplies: ExpandedReplies | null;
    routes: PostDetailRoutes;
    translations: PostDetailTranslations;
    canPerformSocialWrites: boolean;
    isLastReplyOverride?: boolean;
    replyParentId?: number;
    replyTargetName?: string;
    onReplyRequest?: () => void;
    rootReplyOpen?: boolean;
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

function interpolate(template: string, name: string): string {
    return template.replace(':name', name);
}

async function copyText(url: string): Promise<boolean> {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(url);
        return true;
    }

    const textArea = document.createElement('textarea');
    textArea.value = url;
    textArea.style.position = 'fixed';
    textArea.style.left = '-9999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        return document.execCommand('copy');
    } finally {
        document.body.removeChild(textArea);
    }
}

export function CommentList({
    comments,
    expandedReplyRootId,
    expandedReplies,
    routes,
    translations,
    canPerformSocialWrites,
}: CommentListProps) {
    return (
        <>
            <div className="space-y-4">
                {comments.data.length > 0 && (
                    <h2 className="mb-4 border-b border-[var(--skuy-border-subtle)] pb-2 text-lg font-bold text-[var(--skuy-text-primary)]">
                        {translations.replies}
                    </h2>
                )}

                {comments.data.map((comment) => (
                    <CommentItem
                        key={comment.id}
                        comment={comment}
                        expandedReplyRootId={expandedReplyRootId}
                        expandedReplies={expandedReplies}
                        routes={routes}
                        translations={translations}
                        canPerformSocialWrites={canPerformSocialWrites}
                    />
                ))}
            </div>

            {(comments.previous_url || comments.next_url) && (
                <div className="mt-6 flex flex-wrap justify-center gap-3">
                    {comments.previous_url && (
                        <Link href={comments.previous_url} preserveScroll className="skuy-load-more min-w-0 sm:min-w-[220px]">
                            {translations.pagination_previous}
                        </Link>
                    )}
                    {comments.next_url && (
                        <Link href={comments.next_url} preserveScroll className="skuy-load-more min-w-0 sm:min-w-[220px]">
                            {translations.pagination_next}
                        </Link>
                    )}
                </div>
            )}
        </>
    );
}

function CommentItem({
    comment,
    isReply = false,
    expandedReplyRootId,
    expandedReplies,
    routes,
    translations,
    canPerformSocialWrites,
    isLastReplyOverride,
    replyParentId: replyParentIdOverride,
    replyTargetName: replyTargetNameOverride,
    onReplyRequest,
    rootReplyOpen = false,
}: CommentItemProps) {
    const [openReply, setOpenReply] = useState(false);
    const [replyContent, setReplyContent] = useState('');
    const [pending, setPending] = useState(false);
    const [statusMessage, setStatusMessage] = useState('');
    const isExpanded = !isReply && expandedReplyRootId === comment.id && expandedReplies !== null;
    const visibleReplies = isExpanded ? expandedReplies.data : comment.replies;
    const replyParentId = replyParentIdOverride ?? comment.id;
    const replyTargetName = replyTargetNameOverride ?? comment.author.name;
    const hasMoreReplies = isExpanded
        ? expandedReplies.has_more
        : comment.replies_count > visibleReplies.length;
    const moreRepliesUrl = isExpanded
        ? expandedReplies.next_url ? `${expandedReplies.next_url}#comment-${comment.id}` : null
        : comment.reply_expansion_url ?? null;

    const submitReply = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!canPerformSocialWrites || pending || replyContent.trim() === '') return;

        setPending(true);
        setStatusMessage('');
        router.post(
            routes.comment,
            {
                content: replyContent,
                parent_id: replyParentId,
            },
            {
                ...actionOptions(),
                onSuccess: () => {
                    setReplyContent('');
                    setOpenReply(false);
                },
                onError: () => setStatusMessage(translations.feedback.failed),
                onFinish: () => setPending(false),
            },
        );
    };

    const toggleCommentLike = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!canPerformSocialWrites || pending) return;

        setPending(true);
        router.post(comment.actions.like_url, {}, {
            ...actionOptions(),
            onError: () => setStatusMessage(translations.feedback.failed),
            onFinish: () => setPending(false),
        });
    };

    const shareComment = async () => {
        try {
            if (await copyText(comment.actions.share_url)) {
                setStatusMessage(isReply ? translations.reply_link_copied : translations.comment_link_copied);
            }
        } catch {
            setStatusMessage(translations.feedback.network);
        }
    };

    return (
        <div id={`${isReply ? 'reply' : 'comment'}-${comment.id}`} className="skuy-comment relative scroll-mt-20">
            <div className={`flex flex-shrink-0 flex-col items-center ${isReply ? 'mt-0.5' : ''}`}>
                <Avatar author={comment.author} size={isReply ? 'tiny' : 'small'} labelTemplate={translations.avatar_of} />
                {!isReply && (openReply || comment.replies_count > 0) && (
                    <div className="mt-2 mb-1 min-h-[20px] w-0.5 flex-1 bg-[var(--skuy-border-subtle)]" />
                )}
                {isReply && !isLastReplyOverride && (
                    <div className="mt-1 min-h-[10px] w-0.5 flex-1 bg-[var(--skuy-border-subtle)]" />
                )}
            </div>

            <div className={`skuy-comment__body ${isReply ? 'pb-1' : 'pb-4'}`}>
                <div className="skuy-comment__identity">
                    <span className="skuy-comment__user">{comment.author.username}</span>
                    <time dateTime={comment.datetime} className="skuy-comment__time">{comment.display_time}</time>
                </div>
                <p className="skuy-comment__text mb-1">{comment.content}</p>

                <div className={`skuy-actions mt-1.5${isReply ? '' : ' mb-2'}`}>
                    <form onSubmit={toggleCommentLike}>
                        <button
                            type="submit"
                            aria-label={comment.liked ? translations.unlike : translations.like}
                            aria-pressed={comment.liked}
                            title={comment.liked ? translations.unlike : translations.like}
                            disabled={!canPerformSocialWrites || pending}
                            className={`skuy-action skuy-action--reaction disabled:cursor-not-allowed disabled:opacity-50${comment.liked ? ' !border-[var(--skuy-reaction)] !bg-[var(--skuy-reaction)] !text-[var(--skuy-on-reaction)]' : ''}`}
                        >
                            <HeartIcon className={isReply ? 'h-3.5 w-3.5' : 'h-4 w-4'} filled={comment.liked} />
                            {comment.like_count > 0 && <span className="text-[10px] font-semibold">{comment.like_count}</span>}
                        </button>
                    </form>

                    <button
                        type="button"
                        onClick={() => {
                            if (isReply && onReplyRequest) {
                                onReplyRequest();
                                return;
                            }

                            setOpenReply((current) => !current);
                        }}
                        disabled={!canPerformSocialWrites}
                        className="skuy-action disabled:cursor-not-allowed disabled:opacity-50"
                        title={translations.reply}
                        aria-label={translations.reply}
                        aria-expanded={isReply ? rootReplyOpen : openReply}
                    >
                        <CommentIcon className={isReply ? 'h-3.5 w-3.5' : 'h-4 w-4'} />
                    </button>

                    <button
                        type="button"
                        className="skuy-action"
                        title={translations.repost_coming_soon}
                        aria-label={translations.repost_coming_soon}
                    >
                        <RepostIcon className={isReply ? 'h-3.5 w-3.5' : 'h-4 w-4'} />
                    </button>

                    <button
                        type="button"
                        onClick={() => void shareComment()}
                        className="skuy-action"
                        title={translations.share}
                        aria-label={translations.share}
                    >
                        <ShareIcon className={isReply ? 'h-3.5 w-3.5' : 'h-4 w-4'} />
                    </button>
                </div>

                {!isReply && openReply && (
                    <form onSubmit={submitReply} className="skuy-inline-form">
                        <input
                            type="text"
                            name="content"
                            value={replyContent}
                            onChange={(event) => setReplyContent(event.target.value)}
                            className="skuy-inline-input"
                            placeholder={interpolate(translations.comments_reply_to, replyTargetName)}
                            required
                            autoComplete="off"
                            disabled={!canPerformSocialWrites || pending}
                        />
                        <button type="submit" disabled={!canPerformSocialWrites || pending || replyContent.trim() === ''} className="skuy-button skuy-button--primary min-h-[40px] shrink-0 px-3 py-1 text-xs">
                            {translations.send}
                        </button>
                    </form>
                )}

                {statusMessage && (
                    <p className="mt-2 text-xs font-semibold text-[var(--skuy-danger)]" role="status" aria-live="polite">
                        {statusMessage}
                    </p>
                )}

                {visibleReplies.length > 0 && (
                    <div className="skuy-comment__replies">
                        {visibleReplies.map((reply, index) => (
                            <CommentItem
                                key={reply.id}
                                comment={reply}
                                isReply
                                expandedReplyRootId={null}
                                expandedReplies={null}
                                routes={routes}
                                translations={translations}
                                canPerformSocialWrites={canPerformSocialWrites}
                                isLastReplyOverride={index === visibleReplies.length - 1}
                                replyParentId={comment.id}
                                replyTargetName={comment.author.name}
                                onReplyRequest={() => setOpenReply((current) => !current)}
                                rootReplyOpen={openReply}
                            />
                        ))}
                    </div>
                )}

                {hasMoreReplies && moreRepliesUrl && (
                    <div className="mt-3">
                        <Link href={moreRepliesUrl} preserveScroll className="skuy-load-more max-w-full">
                            {translations.view_more_replies}
                        </Link>
                    </div>
                )}
            </div>
        </div>
    );
}
