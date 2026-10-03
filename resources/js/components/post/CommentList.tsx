import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState, type CSSProperties, type FormEvent } from 'react';
import type {
    CommentPagination,
    ExpandedReplies,
    PostDetailComment,
    PostDetailRoutes,
    PostDetailTranslations,
    ReactiveCommentCreationResponse,
} from '../../types/post';
import {
    CommentMutationError,
    commentMutationMessage,
    isCommentLikeResponse,
    isReactiveCommentCreationResponse,
    requestCommentMutation,
} from './commentMutations';
import { Avatar } from './Avatar';
import { CommentIcon, HeartIcon, RepostIcon, ShareIcon } from './icons';

interface CommentListProps {
    comments: CommentPagination;
    expandedReplyRootId: number | null;
    expandedReplies: ExpandedReplies | null;
    postUid: string;
    createdComments: ReactiveCommentCreationResponse[];
    onCommentCreated: (response: ReactiveCommentCreationResponse) => void;
    routes: PostDetailRoutes;
    translations: PostDetailTranslations;
    canPerformSocialWrites: boolean;
}

interface CommentItemProps {
    comment: PostDetailComment;
    isReply?: boolean;
    isLastReply?: boolean;
    expandedReplyRootId: number | null;
    expandedReplies: ExpandedReplies | null;
    postUid: string;
    onCommentCreated: (response: ReactiveCommentCreationResponse) => void;
    routes: PostDetailRoutes;
    translations: PostDetailTranslations;
    canPerformSocialWrites: boolean;
    replyParentId?: number;
    replyTargetName?: string;
    onReplyRequest?: () => void;
    rootReplyOpen?: boolean;
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
    postUid,
    createdComments,
    onCommentCreated,
    routes,
    translations,
    canPerformSocialWrites,
}: CommentListProps) {
    const [visibleComments, setVisibleComments] = useState(comments.data);
    const [visibleExpandedReplies, setVisibleExpandedReplies] = useState(expandedReplies);
    const processedCommentIds = useRef(new Set<number>());

    useEffect(() => {
        setVisibleComments(comments.data);
    }, [comments.data]);

    useEffect(() => {
        setVisibleExpandedReplies(expandedReplies);
    }, [expandedReplies]);

    useEffect(() => {
        const pageSize = Math.max(1, comments.per_page);

        for (const creation of createdComments) {
            const comment = creation.comment;
            if (creation.post_uid !== postUid || processedCommentIds.current.has(comment.id)) continue;

            processedCommentIds.current.add(comment.id);

            if (comment.parent_id === null) {
                setVisibleComments((current) => [
                    comment,
                    ...current.filter((item) => item.id !== comment.id),
                ].slice(0, pageSize));
                continue;
            }

            const parentId = comment.parent_id;
            const parentReplyCount = creation.parent_reply_count;
            setVisibleComments((current) => current.map((root) => root.id === parentId
                ? {
                    ...root,
                    replies_count: parentReplyCount ?? root.replies_count + 1,
                    replies: [comment, ...root.replies.filter((reply) => reply.id !== comment.id)],
                }
                : root));

            if (expandedReplyRootId === parentId && parentReplyCount !== null) {
                setVisibleExpandedReplies((current) => {
                    if (current === null) return current;

                    return {
                        ...current,
                        data: [comment, ...current.data.filter((reply) => reply.id !== comment.id)]
                            .slice(0, current.per_page),
                    };
                });
            }
        }
    }, [createdComments, comments.per_page, expandedReplyRootId, postUid]);

    return (
        <>
            <div className="skuy-post-detail__reply-list space-y-4">
                {visibleComments.length > 0 && (
                    <h2 className="skuy-post-detail__reply-heading mb-4 border-b border-[var(--skuy-border-subtle)] pb-2 text-lg font-bold text-[var(--skuy-text-primary)]">
                        {translations.replies}
                    </h2>
                )}

                {visibleComments.map((comment) => (
                    <CommentItem
                        key={comment.id}
                        comment={comment}
                        expandedReplyRootId={expandedReplyRootId}
                        expandedReplies={visibleExpandedReplies}
                        postUid={postUid}
                        onCommentCreated={onCommentCreated}
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
    isLastReply = false,
    expandedReplyRootId,
    expandedReplies,
    postUid,
    onCommentCreated,
    routes,
    translations,
    canPerformSocialWrites,
    replyParentId: replyParentIdOverride,
    replyTargetName: replyTargetNameOverride,
    onReplyRequest,
    rootReplyOpen = false,
}: CommentItemProps) {
    const [openReply, setOpenReply] = useState(false);
    const [replyContent, setReplyContent] = useState('');
    const [replyPending, setReplyPending] = useState(false);
    const [likePending, setLikePending] = useState(false);
    const [liked, setLiked] = useState(comment.liked);
    const [likeCount, setLikeCount] = useState(comment.like_count);
    const [replyError, setReplyError] = useState('');
    const [likeError, setLikeError] = useState('');
    const [statusMessage, setStatusMessage] = useState('');
    const [statusIsError, setStatusIsError] = useState(false);
    const replyInFlightRef = useRef(false);
    const likeInFlightRef = useRef(false);
    const isExpanded = !isReply && expandedReplyRootId === comment.id && expandedReplies !== null;
    const visibleReplies = isExpanded ? expandedReplies.data : comment.replies;
    const replyParentId = replyParentIdOverride ?? comment.id;
    const replyTargetName = replyTargetNameOverride ?? comment.author.name;
    const repliesCount = comment.replies_count;
    const hasMoreReplies = isExpanded
        ? expandedReplies.has_more
        : repliesCount > visibleReplies.length;
    const moreRepliesUrl = isExpanded
        ? expandedReplies.next_url ? `${expandedReplies.next_url}#comment-${comment.id}` : null
        : comment.reply_expansion_url ?? null;

    useEffect(() => {
        setLiked(comment.liked);
        setLikeCount(comment.like_count);
    }, [comment.id, comment.liked, comment.like_count]);

    const submitReply = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!canPerformSocialWrites || replyInFlightRef.current || replyContent.trim() === '') return;

        replyInFlightRef.current = true;
        setReplyPending(true);
        setReplyError('');

        try {
            const payload = await requestCommentMutation(routes.comment, {
                content: replyContent,
                parent_id: replyParentId,
            });
            if (!isReactiveCommentCreationResponse(payload, postUid, replyParentId)) {
                throw new CommentMutationError('invalid');
            }

            onCommentCreated(payload);
            setReplyContent('');
            setOpenReply(false);
        } catch (error) {
            setReplyError(commentMutationMessage(error, translations.comment_feedback));
        } finally {
            replyInFlightRef.current = false;
            setReplyPending(false);
        }
    };

    const toggleCommentLike = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!canPerformSocialWrites || likeInFlightRef.current) return;

        likeInFlightRef.current = true;
        setLikePending(true);
        setLikeError('');
        setStatusMessage('');
        setStatusIsError(false);

        try {
            const payload = await requestCommentMutation(comment.actions.like_url, { liked: !liked });
            if (!isCommentLikeResponse(payload, comment.id)) {
                throw new CommentMutationError('invalid');
            }

            setLiked(payload.liked);
            setLikeCount(payload.like_count);
        } catch (error) {
            setLikeError(commentMutationMessage(error, translations.comment_feedback));
        } finally {
            likeInFlightRef.current = false;
            setLikePending(false);
        }
    };

    const shareComment = async () => {
        try {
            if (await copyText(comment.actions.share_url)) {
                setStatusMessage(isReply ? translations.reply_link_copied : translations.comment_link_copied);
                setStatusIsError(false);
            }
        } catch {
            setStatusMessage(translations.feedback.network);
            setStatusIsError(true);
        }
    };

    return (
        <div
            id={`${isReply ? 'reply' : 'comment'}-${comment.id}`}
            className={`skuy-comment skuy-post-detail__reply ${isReply ? `skuy-post-detail__reply--nested ${isLastReply ? 'is-thread-last' : 'is-thread-middle'}` : 'skuy-post-detail__reply--root'} relative scroll-mt-20`}
        >
            {isReply && <span aria-hidden="true" className="skuy-post-detail__thread-elbow" />}
            <div className={`skuy-comment__avatar-column flex flex-shrink-0 flex-col items-center ${isReply ? 'mt-0.5' : ''}`}>
                <Avatar author={comment.author} size={isReply ? 'tiny' : 'small'} labelTemplate={translations.avatar_of} />
            </div>

            <div className={`skuy-comment__body ${isReply ? 'pb-1' : 'pb-4'}`}>
                <div className="skuy-comment__identity">
                    <span className="skuy-comment__name">{comment.author.name}</span>
                    <span className="skuy-comment__user">@{comment.author.username}</span>
                    <time dateTime={comment.datetime} className="skuy-comment__time">{comment.display_time}</time>
                </div>
                <p className="skuy-comment__text mb-1">{comment.content}</p>

                <div className={`skuy-actions mt-1.5${isReply ? '' : ' mb-2'}`}>
                    <form onSubmit={toggleCommentLike}>
                        <button
                            type="submit"
                            aria-label={liked ? translations.unlike : translations.like}
                            aria-pressed={liked}
                            aria-busy={likePending}
                            title={liked ? translations.unlike : translations.like}
                            disabled={!canPerformSocialWrites || likePending}
                            className="skuy-action skuy-action--reaction disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <HeartIcon className={isReply ? 'text-sm' : 'text-base'} filled={liked} />
                            {likeCount > 0 && <span className="text-[10px] font-semibold">{likeCount}</span>}
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
                        <CommentIcon className={isReply ? 'text-sm' : 'text-base'} />
                        {!isReply && repliesCount > 0 && <span data-comment-reply-count={repliesCount} className="text-[10px] font-semibold">{repliesCount}</span>}
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
                        <ShareIcon className={isReply ? 'text-sm' : 'text-base'} />
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
                            maxLength={500}
                            autoComplete="off"
                            disabled={!canPerformSocialWrites || replyPending}
                        />
                        <button
                            type="submit"
                            disabled={!canPerformSocialWrites || replyPending || replyContent.trim() === ''}
                            aria-busy={replyPending}
                            className="skuy-button skuy-button--primary min-h-[40px] shrink-0 px-3 py-1 text-xs"
                        >
                            {replyPending ? '...' : translations.send}
                        </button>
                    </form>
                )}

                {likeError && (
                    <p className="mt-2 text-xs font-semibold text-[var(--skuy-danger)]" role="alert" aria-live="assertive">
                        {likeError}
                    </p>
                )}
                {replyError && (
                    <p className="mt-2 text-xs font-semibold text-[var(--skuy-danger)]" role="alert" aria-live="assertive">
                        {replyError}
                    </p>
                )}
                {statusMessage && (
                    <p
                        className={`mt-2 text-xs font-semibold ${statusIsError ? 'text-[var(--skuy-danger)]' : 'text-[var(--skuy-text-muted)]'}`}
                        role={statusIsError ? 'alert' : 'status'}
                        aria-live={statusIsError ? 'assertive' : 'polite'}
                    >
                        {statusMessage}
                    </p>
                )}

                {visibleReplies.length > 0 && (
                    <div
                        className="skuy-comment__replies skuy-comment__replies--has-items skuy-post-detail__reply-group"
                        data-reply-thread-root={comment.id}
                        style={{ '--thread-reply-count': visibleReplies.length } as CSSProperties}
                    >
                        <span aria-hidden="true" className="skuy-post-detail__thread-spine" />
                        {visibleReplies.map((reply, index) => (
                            <CommentItem
                                key={reply.id}
                                comment={reply}
                                isReply
                                expandedReplyRootId={null}
                                expandedReplies={null}
                                postUid={postUid}
                                onCommentCreated={onCommentCreated}
                                routes={routes}
                                translations={translations}
                                canPerformSocialWrites={canPerformSocialWrites}
                                isLastReply={index === visibleReplies.length - 1}
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
