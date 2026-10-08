import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';
import type { PostDetailPageProps, ReactiveCommentCreationResponse } from '../../types/post';
import { Avatar } from '../../components/post/Avatar';
import { CommentList } from '../../components/post/CommentList';
import { ArrowLeftIcon, CheckIcon, ImageIcon, RepostContextIcon } from '../../components/post/icons';
import { MediaViewer } from '../../components/post/MediaViewer';
import { PostActions } from '../../components/post/PostActions';
import { PostOptions } from '../../components/post/PostOptions';
import {
    CommentMutationError,
    commentMutationMessage,
    isReactiveCommentCreationResponse,
    requestCommentMutation,
} from '../../components/post/commentMutations';

function renderContent(content: string, tagsBaseUrl: string): ReactNode[] {
    return content.split(/(#[a-zA-Z0-9_]+)/g).map((part, index) => {
        if (/^#[a-zA-Z0-9_]+$/.test(part)) {
            return (
                <a key={`${part}-${index}`} href={`${tagsBaseUrl}/${part.slice(1)}`} className="skuy-hashtag">
                    {part}
                </a>
            );
        }

        return <span key={`${part}-${index}`}>{part}</span>;
    });
}

export default function Show() {
    const { props } = usePage<PostDetailPageProps>();
    const {
        post,
        display_post: displayPost,
        author_context: authorContext,
        comments,
        expanded_reply_root_id: expandedReplyRootId,
        expanded_replies: expandedReplies,
        viewer,
        routes,
        translations,
    } = props;
    const [commentCount, setCommentCount] = useState(displayPost.interactions.comment_count);
    const [createdComments, setCreatedComments] = useState<ReactiveCommentCreationResponse[]>([]);

    useEffect(() => {
        setCommentCount(displayPost.interactions.comment_count);
        setCreatedComments([]);
    }, [displayPost.uid, displayPost.interactions.comment_count]);

    const handleCommentCreated = (response: ReactiveCommentCreationResponse) => {
        const scrollX = window.scrollX;
        const scrollY = window.scrollY;
        setCommentCount((current) => Math.max(current, response.comment_count));
        setCreatedComments((current) => [...current, response].slice(-100));
        requestAnimationFrame(() => window.scrollTo(scrollX, scrollY));
    };

    return (
        <div className="skuy-feed-page skuy-post-detail-page" data-skuy-prism-detail>
            <div className="skuy-post-detail__layout">
                <section className="skuy-feed-content skuy-post-detail__main skuy-post-detail__content mx-auto w-full max-w-2xl px-4 py-8 sm:px-6">
                <div className="skuy-post-detail__header mb-4 flex items-center gap-3 border-b border-[var(--skuy-border-subtle)] pb-3">
                    <a href={routes.feed} aria-label={translations.back_to_feed} className="skuy-action">
                        <ArrowLeftIcon />
                    </a>
                    <h1 className="skuy-post-detail__title text-xl font-bold text-[var(--skuy-text-primary)]">{translations.thread}</h1>
                </div>

                {post.is_repost && post.reposted_by && (
                    <div className="skuy-post__repost">
                        <RepostContextIcon className="text-base" />
                        <span>{translations.reposted_by.replace(':username', post.reposted_by.username)}</span>
                    </div>
                )}

                <article className="skuy-post skuy-post--detail-root skuy-social-post skuy-post-detail__root mb-4">
                    <div className="skuy-post__body">
                        <div className="skuy-post__body-main">
                            <div className="skuy-post__header skuy-post-detail__root-header">
                                <div className="skuy-post-detail__root-avatar">
                                    <a href={routes.author_profile} aria-label={translations.avatar_of.replace(':name', displayPost.author.name)}>
                                        <Avatar author={displayPost.author} labelTemplate={translations.avatar_of} />
                                    </a>
                                </div>
                                <div className="skuy-post__identity">
                                    <span className="skuy-post__display-name">{displayPost.author.name}</span>
                                    <span className="skuy-post__username">@{displayPost.author.username}</span>
                                    {displayPost.author.is_verified && (
                                        <span className="skuy-verified-badge" data-verified-badge role="img" title={translations.verified} aria-label={translations.verified}>
                                            <CheckIcon />
                                        </span>
                                    )}
                                    <span className="skuy-post__meta-separator" aria-hidden="true">·</span>
                                    <time dateTime={displayPost.datetime} className="skuy-post__time">
                                        {displayPost.display_time}
                                    </time>
                                    {displayPost.topic && (
                                        <>
                                            <span className="skuy-post__meta-separator" aria-hidden="true">·</span>
                                            <span className="skuy-post__topic">#{displayPost.topic}</span>
                                        </>
                                    )}
                                </div>
                                <PostOptions
                                    uid={post.uid}
                                    editUrl={routes.edit}
                                    deleteUrl={routes.delete}
                                    bookmarkStoreUrl={routes.bookmark_store}
                                    bookmarkDestroyUrl={routes.bookmark_destroy}
                                    bookmarked={displayPost.interactions.bookmarked}
                                    canBookmark={viewer.can_perform_social_writes}
                                    translations={translations}
                                />
                            </div>

                            <div className="skuy-post__content">
                                <p className="skuy-post__text">{renderContent(displayPost.content, routes.tags_base)}</p>
                                <MediaViewer media={displayPost.media} translations={translations} />
                            </div>
                        </div>

                        <PostActions
                            post={displayPost}
                            routes={routes}
                            translations={translations}
                            canPerformSocialWrites={viewer.can_perform_social_writes}
                            viewerId={viewer.id}
                            commentCount={commentCount}
                        />
                    </div>
                </article>

                <CommentComposer
                    author={viewer.author}
                    replyTo={displayPost.author.username}
                    actionUrl={routes.comment}
                    postUid={displayPost.uid}
                    commentFeedback={translations.comment_feedback}
                    onCommentCreated={handleCommentCreated}
                    translations={translations}
                    canPerformSocialWrites={viewer.can_perform_social_writes}
                />

                <section className="skuy-post-detail__conversation" aria-label={translations.replies}>
                    <CommentList
                        comments={comments}
                        expandedReplyRootId={expandedReplyRootId}
                        expandedReplies={expandedReplies}
                        postUid={displayPost.uid}
                        createdComments={createdComments}
                        onCommentCreated={handleCommentCreated}
                        routes={routes}
                        translations={translations}
                        canPerformSocialWrites={viewer.can_perform_social_writes}
                    />
                </section>
                </section>
                <aside className="skuy-post-detail__context" aria-label={translations.about_creator}>
                    <section className="skuy-post-detail__context-card">
                        <h2 className="skuy-post-detail__context-title">{translations.about_creator}</h2>
                        <a
                            href={routes.author_profile}
                            className="skuy-post-detail__creator-link"
                            aria-label={`${displayPost.author.name}, @${displayPost.author.username}`}
                        >
                            <Avatar author={displayPost.author} labelTemplate={translations.avatar_of} />
                            <span className="skuy-post-detail__creator-identity">
                                <strong>{displayPost.author.name}</strong>
                                <span>@{displayPost.author.username}</span>
                            </span>
                            {displayPost.author.is_verified && (
                                <span className="skuy-verified-badge" data-verified-badge role="img" title={translations.verified} aria-label={translations.verified}>
                                    <CheckIcon />
                                </span>
                            )}
                        </a>
                        <p className="skuy-post-detail__creator-bio">{authorContext.bio?.trim() || translations.no_bio}</p>
                        <dl className="skuy-post-detail__creator-stats">
                            <div className="skuy-post-detail__creator-stat">
                                <dt>{translations.threads}</dt>
                                <dd data-profile-stat="threads">{authorContext.posts_count.toLocaleString(props.locale)}</dd>
                            </div>
                            <div className="skuy-post-detail__creator-stat">
                                <dt>{translations.followers}</dt>
                                <dd data-profile-stat="followers">{authorContext.followers_count.toLocaleString(props.locale)}</dd>
                            </div>
                            <div className="skuy-post-detail__creator-stat">
                                <dt>{translations.following}</dt>
                                <dd data-profile-stat="following">{authorContext.followings_count.toLocaleString(props.locale)}</dd>
                            </div>
                        </dl>
                    </section>
                </aside>
            </div>
        </div>
    );
}

interface CommentComposerProps {
    author: PostDetailPageProps['viewer']['author'];
    replyTo: string;
    actionUrl: string;
    postUid: string;
    commentFeedback: PostDetailPageProps['translations']['comment_feedback'];
    onCommentCreated: (response: ReactiveCommentCreationResponse) => void;
    translations: PostDetailPageProps['translations'];
    canPerformSocialWrites: boolean;
}

export function CommentComposer({
    author,
    replyTo,
    actionUrl,
    postUid,
    commentFeedback,
    onCommentCreated,
    translations,
    canPerformSocialWrites,
}: CommentComposerProps) {
    const [content, setContent] = useState('');
    const [pending, setPending] = useState(false);
    const [statusMessage, setStatusMessage] = useState('');
    const inFlightRef = useRef(false);

    const submitComment = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!canPerformSocialWrites || inFlightRef.current || content.trim() === '') return;

        inFlightRef.current = true;
        setPending(true);
        setStatusMessage('');

        try {
            const payload = await requestCommentMutation(actionUrl, { content });
            if (!isReactiveCommentCreationResponse(payload, postUid, null)) {
                throw new CommentMutationError('invalid');
            }

            onCommentCreated(payload);
            setContent('');
        } catch (error) {
            setStatusMessage(commentMutationMessage(error, commentFeedback));
        } finally {
            inFlightRef.current = false;
            setPending(false);
        }
    };

    return (
        <div className="skuy-composer skuy-post-detail__composer mb-6">
            <form onSubmit={submitComment} className="skuy-post-detail__composer-form">
                <div className="skuy-post-detail__composer-avatar">
                    <Avatar author={author} size="small" labelTemplate={translations.avatar_of} />
                </div>
                <input
                    type="text"
                    name="content"
                    value={content}
                    onChange={(event) => setContent(event.target.value)}
                    className="skuy-inline-input skuy-post-detail__composer-input"
                    placeholder={translations.comments_reply_to.replace(':name', replyTo)}
                    aria-label={translations.comments_reply_to.replace(':name', replyTo)}
                    required
                    maxLength={500}
                    autoComplete="off"
                    disabled={!canPerformSocialWrites || pending}
                />
                <button type="button" disabled={!canPerformSocialWrites} className="skuy-action shrink-0 disabled:cursor-not-allowed disabled:opacity-50" title={translations.image_coming_soon} aria-label={translations.image_coming_soon}>
                    <ImageIcon />
                </button>
                {content.trim().length > 0 && (
                    <button type="submit" disabled={!canPerformSocialWrites || pending} aria-busy={pending} className="skuy-post-detail__submit">
                        {pending ? '...' : translations.send}
                    </button>
                )}
            </form>
            {statusMessage && (
                <p className="mt-2 text-xs font-semibold text-[var(--skuy-danger)]" role="alert" aria-live="assertive">
                    {statusMessage}
                </p>
            )}
        </div>
    );
}
