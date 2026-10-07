import '../css/skuy-prism.css';
import '../css/skuy-prism-navigation.css';
import Alpine from 'alpinejs';
import { acquireFeedPostActionLock } from './feedPostActionLock';
import { initializeMessageComposers } from './messageComposer';
import { initializeOneSignal } from './oneSignal';
import { playSocialActionMotion } from './socialActionMotion';

window.Alpine = Alpine;
initializeMessageComposers();
initializeOneSignal();

Alpine.data('feedCommentControl', (config = {}) => ({
    showComments: false,
    commentCount: Number.isInteger(config.commentCount) ? Math.max(0, config.commentCount) : 0,
    rootCommentCount: Number.isInteger(config.rootCommentCount) ? Math.max(0, config.rootCommentCount) : 0,
    postUid: typeof config.postUid === 'string' ? config.postUid : '',
    replyLabel: typeof config.replyLabel === 'string' ? config.replyLabel : '',
    moreCommentsLabel: typeof config.moreCommentsLabel === 'string' ? config.moreCommentsLabel : '',

    formatActionCount(count) {
        const safeCount = Number.isInteger(count) ? Math.max(0, count) : 0;
        if (safeCount < 1000) return String(safeCount);

        for (const [suffix, unit] of [['B', 1_000_000_000], ['M', 1_000_000], ['K', 1_000]]) {
            if (safeCount >= unit) return `${Math.floor(safeCount / unit)}${suffix}`;
        }

        return String(safeCount);
    },

    commentDisplayCount() {
        return this.commentCount > 0 ? this.formatActionCount(this.commentCount) : '';
    },

    commentButtonLabel() {
        return this.commentCount > 0 ? `${this.replyLabel} (${this.commentCount})` : this.replyLabel;
    },

    syncCommentAction(article = this.$el) {
        const button = article.querySelector('[data-feed-comment-action]');
        const countNode = article.querySelector('[data-feed-comment-count]');
        if (!button || !countNode) return;

        const displayCount = this.commentDisplayCount();
        countNode.textContent = displayCount;
        countNode.style.display = displayCount === '' ? 'none' : '';
        article.setAttribute('data-feed-comment-count-value', String(this.commentCount));
        article.setAttribute('data-feed-root-comment-count-value', String(this.rootCommentCount));
        button.setAttribute('aria-label', this.commentButtonLabel());
        button.setAttribute('title', this.commentButtonLabel());
        button.classList.toggle('skuy-action--dense-count', displayCount.length > 3);

        const moreComments = article.querySelector('[data-feed-more-comments]');
        if (moreComments) {
            const showMore = this.rootCommentCount > 1;
            moreComments.textContent = this.moreCommentsLabel.replace('__COUNT__', String(Math.max(0, this.rootCommentCount - 1)));
            moreComments.style.display = showMore ? '' : 'none';
        }
    },

    syncDuplicateCommentActions() {
        const matchingArticles = [...document.querySelectorAll('article[data-feed-post-uid]')]
            .filter((article) => article.getAttribute('data-feed-post-uid') === this.postUid);

        for (const article of matchingArticles) {
            const commentCount = Number(article.getAttribute('data-feed-comment-count-value'));
            const rootCommentCount = Number(article.getAttribute('data-feed-root-comment-count-value'));
            if (Number.isInteger(commentCount) && commentCount >= 0) this.commentCount = Math.max(this.commentCount, commentCount);
            if (Number.isInteger(rootCommentCount) && rootCommentCount >= 0) this.rootCommentCount = Math.max(this.rootCommentCount, rootCommentCount);
        }

        matchingArticles.forEach((article) => this.syncCommentAction(article));
    },

    commentCreated(event) {
        const detail = event?.detail;
        if (
            !detail
            || detail.post_uid !== this.postUid
            || !Number.isInteger(detail.comment_count)
            || detail.comment_count < 0
            || !Number.isInteger(detail.root_comment_count)
            || detail.root_comment_count < 0
            || !detail.comment
            || !Number.isInteger(detail.comment.id)
            || detail.comment.id < 1
            || detail.comment.post_uid !== this.postUid
            || typeof detail.feed_html !== 'string'
        ) {
            return;
        }

        const scrollX = window.scrollX;
        const scrollY = window.scrollY;
        const createdId = Number(detail.comment.id);
        const alreadyInserted = this.$el.querySelector(`[data-feed-comment-id="${createdId}"]`);

        if (!alreadyInserted && detail.comment.parent_id === null) {
            const createdRoots = this.$el.querySelector('[data-feed-created-roots]');
            if (createdRoots) createdRoots.insertAdjacentHTML('afterbegin', detail.feed_html);
        } else if (!alreadyInserted && Number.isInteger(detail.comment.parent_id)) {
            const parent = this.$el.querySelector(`[data-feed-comment-id="${Number(detail.comment.parent_id)}"]`);
            const parentBody = parent?.querySelector('.skuy-comment__body');
            if (parent && parentBody) {
                let replyList = parent.querySelector('[data-feed-reply-list]');
                if (!replyList) {
                    replyList = document.createElement('div');
                    replyList.className = 'skuy-comment__replies';
                    replyList.setAttribute('data-feed-reply-list', '');
                    parentBody.appendChild(replyList);
                }
                replyList.insertAdjacentHTML('afterbegin', detail.feed_html);
            }
        }

        this.commentCount = detail.comment_count;
        this.rootCommentCount = detail.root_comment_count;
        this.showComments = true;
        this.$nextTick(() => this.syncDuplicateCommentActions());
        requestAnimationFrame(() => window.scrollTo(scrollX, scrollY));
    },
}));

Alpine.data('feedCommentComposer', (config = {}) => ({
    url: typeof config.url === 'string' ? config.url : '',
    postUid: typeof config.postUid === 'string' ? config.postUid : '',
    parentId: Number.isInteger(config.parentId) ? config.parentId : null,
    labels: config.labels || {},
    content: '',
    pending: false,
    error: '',

    commentError(message) {
        const error = new Error(message);
        error.name = 'FeedCommentError';
        return error;
    },

    async submit(event) {
        event?.preventDefault();
        if (this.pending || this.content.trim() === '') return;

        this.pending = true;
        this.error = '';

        try {
            const url = new URL(this.url, window.location.href);
            if (url.origin !== window.location.origin) throw this.commentError(this.labels.failed);

            const csrf = this.$el.querySelector('input[name="_token"]')?.value || '';
            const body = { content: this.content };
            if (this.parentId !== null) body.parent_id = this.parentId;

            let response;
            try {
                response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Skuy-Comment-Surface': 'feed',
                    },
                    body: JSON.stringify(body),
                });
            } catch {
                throw this.commentError(this.labels.network);
            }
            const payload = await response.json().catch(() => null);

            if (response.redirected || response.status === 401 || response.status === 419) {
                throw this.commentError(this.labels.sessionExpired);
            }
            if (response.status === 403) {
                throw this.commentError(typeof payload?.message === 'string' ? payload.message : this.labels.restricted);
            }
            if (response.status === 422) {
                const validationError = payload?.errors?.content?.[0] ?? payload?.errors?.parent_id?.[0];
                throw this.commentError(typeof validationError === 'string' ? validationError : this.labels.invalid);
            }
            if (!response.ok) throw this.commentError(this.labels.failed);
            if (
                !payload
                || payload.post_uid !== this.postUid
                || payload.comment?.post_uid !== this.postUid
                || payload.comment.parent_id !== this.parentId
                || !Number.isInteger(payload.comment_count)
                || payload.comment_count < 0
                || !Number.isInteger(payload.root_comment_count)
                || payload.root_comment_count < 0
                || (this.parentId === null && payload.parent_reply_count !== null)
                || (this.parentId !== null && (!Number.isInteger(payload.parent_reply_count) || payload.parent_reply_count < 1))
                || !Number.isInteger(payload.comment.id)
                || typeof payload.feed_html !== 'string'
            ) {
                throw this.commentError(this.labels.invalid);
            }

            this.content = '';
            this.$dispatch('feed-comment-created', payload);
        } catch (error) {
            this.error = error instanceof Error && error.name === 'FeedCommentError'
                ? error.message
                : this.labels.network;
        } finally {
            this.pending = false;
        }
    },
}));

Alpine.data('likeControl', (config = {}) => ({
    liked: Boolean(config.liked),
    likeCount: Number.isInteger(config.likeCount) ? Math.max(0, config.likeCount) : 0,
    reposted: Boolean(config.reposted),
    repostCount: Number.isInteger(config.repostCount) ? Math.max(0, config.repostCount) : 0,
    bookmarked: Boolean(config.bookmarked),
    postUid: typeof config.postUid === 'string' ? config.postUid : '',
    urls: config.urls || {},
    labels: config.labels || {},
    isLikeRequestInFlight: false,
    isRepostRequestInFlight: false,
    isBookmarkRequestInFlight: false,
    feedbackMessage: '',
    feedbackIsError: false,

    init() {
        this.syncLikePresentation();
        this.syncRepostPresentation();
        this.syncBookmarkPresentation();
    },

    formatActionCount(count) {
        const safeCount = Number.isInteger(count) ? Math.max(0, count) : 0;
        if (safeCount < 1000) return String(safeCount);

        for (const [suffix, unit] of [
            ['B', 1_000_000_000],
            ['M', 1_000_000],
            ['K', 1_000],
        ]) {
            if (safeCount >= unit) return `${Math.floor(safeCount / unit)}${suffix}`;
        }

        return String(safeCount);
    },

    likeDisplayCount() {
        return this.likeCount > 0 ? this.formatActionCount(this.likeCount) : '';
    },

    repostDisplayCount() {
        return this.repostCount > 0 ? this.formatActionCount(this.repostCount) : '';
    },

    commentDisplayCount() {
        const count = Number.isInteger(this.commentCount) ? Math.max(0, this.commentCount) : 0;
        return count > 0 ? this.formatActionCount(count) : '';
    },

    commentButtonLabel() {
        const count = Number.isInteger(this.commentCount) ? Math.max(0, this.commentCount) : 0;
        const label = this.labels.reply || '';
        return count > 0 ? `${label} (${count})` : label;
    },

    likeButtonLabel() {
        const label = this.liked ? this.labels.unlike : this.labels.like;

        return this.likeCount > 0 ? `${label} (${this.likeCount})` : label;
    },

    repostButtonLabel() {
        return this.repostCount > 0 ? `${this.labels.repost} (${this.repostCount})` : this.labels.repost;
    },

    bookmarkButtonLabel() {
        return this.bookmarked ? this.labels.removeFromSaved : this.labels.save;
    },

    bookmarkFormAction() {
        return this.bookmarked ? this.urls.bookmarkDestroy : this.urls.bookmarkStore;
    },

    syncLikePresentation() {
        const icon = this.$refs.likeIcon;
        if (icon) {
            icon.classList.toggle('fa-solid', this.liked);
            icon.classList.toggle('fa-regular', !this.liked);
        }

        const button = this.$refs.likeButton;
        if (button) {
            button.classList.toggle('skuy-action--dense-count', this.likeDisplayCount().length > 3);
        }
    },

    syncRepostPresentation() {
        const icon = this.$refs.repostIcon;
        if (icon) {
            icon.classList.toggle('fa-solid', this.reposted);
            icon.classList.toggle('fa-regular', !this.reposted);
        }

        const button = this.$refs.repostButton;
        if (button) {
            button.classList.toggle('skuy-action--dense-count', this.repostDisplayCount().length > 3);
        }
    },

    syncBookmarkPresentation() {
        const icon = this.$refs.bookmarkIcon;
        if (icon) {
            icon.classList.toggle('fa-solid', this.bookmarked);
            icon.classList.toggle('fa-regular', !this.bookmarked);
        }
    },

    syncPeerState(event) {
        const state = event?.detail;
        if (!state || state.postUid !== this.postUid) return;

        if (typeof state.liked === 'boolean') this.liked = state.liked;
        if (Number.isInteger(state.likeCount) && state.likeCount >= 0) this.likeCount = state.likeCount;
        if (typeof state.reposted === 'boolean') this.reposted = state.reposted;
        if (Number.isInteger(state.repostCount) && state.repostCount >= 0) this.repostCount = state.repostCount;
        if (typeof state.bookmarked === 'boolean') this.bookmarked = state.bookmarked;

        this.syncLikePresentation();
        this.syncRepostPresentation();
        this.syncBookmarkPresentation();
    },

    publishPeerState() {
        window.dispatchEvent(new CustomEvent('skuy-post-action-state', {
            detail: {
                postUid: this.postUid,
                liked: this.liked,
                likeCount: this.likeCount,
                reposted: this.reposted,
                repostCount: this.repostCount,
                bookmarked: this.bookmarked,
            },
        }));
    },

    acquireActionLock(action) {
        return acquireFeedPostActionLock(this.postUid, action);
    },

    setFeedback(message, isError) {
        this.feedbackMessage = message;
        this.feedbackIsError = isError;
    },

    actionError(message) {
        const error = new Error(message);
        error.name = 'PostActionError';
        return error;
    },

    feedbackForError(error) {
        const knownFeedback = [
            this.labels.sessionExpired,
            this.labels.restricted,
            this.labels.invalid,
            this.labels.failed,
            this.labels.network,
        ];

        return error instanceof Error && (error.name === 'PostActionError' || knownFeedback.includes(error.message))
            ? error.message
            : this.labels.network;
    },

    csrfToken() {
        return this.$el.querySelector('input[name="_token"]')?.value || '';
    },

    async requestAction(url, method, body = null) {
        let requestUrl;

        try {
            requestUrl = new URL(url, window.location.href);
        } catch {
            throw this.actionError(this.labels.failed);
        }

        if (requestUrl.origin !== window.location.origin) {
            throw this.actionError(this.labels.failed);
        }

        const headers = {
            Accept: 'application/json',
            'X-CSRF-TOKEN': this.csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        };
        if (body !== null) headers['Content-Type'] = 'application/json';

        let response;
        try {
            response = await fetch(requestUrl, {
                method,
                credentials: 'same-origin',
                headers,
                body: body === null ? undefined : JSON.stringify(body),
            });
        } catch {
            throw this.actionError(this.labels.network);
        }

        let payload = null;
        try {
            payload = await response.json();
        } catch {
            payload = null;
        }

        if (response.redirected || response.status === 401 || response.status === 419) {
            throw this.actionError(this.labels.sessionExpired);
        }

        if (response.status === 403) {
            const message = payload?.code === 'moderation_state_forbids_social_write'
                && typeof payload.message === 'string'
                ? payload.message
                : this.labels.restricted;
            throw this.actionError(message);
        }

        if (!response.ok) {
            throw this.actionError(response.status === 422 ? this.labels.invalid : this.labels.failed);
        }

        return payload;
    },

    async toggleLike(event) {
        event?.preventDefault();
        if (this.isLikeRequestInFlight) return;
        const releaseActionLock = this.acquireActionLock('like');
        if (!releaseActionLock) return;

        const previousLiked = this.liked;
        const desiredLiked = !previousLiked;
        this.feedbackMessage = '';
        this.feedbackIsError = false;
        this.isLikeRequestInFlight = true;

        try {
            const payload = await this.requestAction(this.urls.like, 'POST', { liked: desiredLiked });

            if (
                !payload
                || typeof payload.post_uid !== 'string'
                || payload.post_uid !== this.postUid
                || typeof payload.liked !== 'boolean'
                || !Number.isInteger(payload.like_count)
                || payload.like_count < 0
            ) {
                throw this.actionError(this.labels.invalid);
            }

            this.liked = payload.liked;
            this.likeCount = payload.like_count;
            this.syncLikePresentation();
            if (payload.liked !== previousLiked) {
                void playSocialActionMotion(this.$refs.likeButton, payload.liked ? 'like' : 'like-release');
            }
            this.publishPeerState();
            this.setFeedback(this.liked ? this.labels.liked : this.labels.unliked, false);
        } catch (error) {
            this.setFeedback(this.feedbackForError(error), true);
        } finally {
            this.isLikeRequestInFlight = false;
            releaseActionLock();
        }
    },

    async toggleRepost(event) {
        event?.preventDefault();
        if (this.isRepostRequestInFlight) return;
        const releaseActionLock = this.acquireActionLock('repost');
        if (!releaseActionLock) return;

        const previousReposted = this.reposted;
        this.feedbackMessage = '';
        this.feedbackIsError = false;
        this.isRepostRequestInFlight = true;

        try {
            const payload = await this.requestAction(this.urls.repost, 'POST', { reposted: !this.reposted });

            if (
                !payload
                || typeof payload.post_uid !== 'string'
                || payload.post_uid !== this.postUid
                || typeof payload.reposted !== 'boolean'
                || !Number.isInteger(payload.repost_count)
                || payload.repost_count < 0
            ) {
                throw this.actionError(this.labels.invalid);
            }

            this.reposted = payload.reposted;
            this.repostCount = payload.repost_count;
            this.syncRepostPresentation();
            if (payload.reposted !== previousReposted) {
                void playSocialActionMotion(this.$refs.repostButton, 'repost');
            }
            this.publishPeerState();
        } catch (error) {
            this.setFeedback(this.feedbackForError(error), true);
        } finally {
            this.isRepostRequestInFlight = false;
            releaseActionLock();
        }
    },

    async toggleBookmark(event) {
        event?.preventDefault();
        if (this.isBookmarkRequestInFlight) return;
        const releaseActionLock = this.acquireActionLock('bookmark');
        if (!releaseActionLock) return;

        const previousBookmarked = this.bookmarked;
        const desiredBookmarked = !previousBookmarked;
        this.feedbackMessage = '';
        this.feedbackIsError = false;
        this.isBookmarkRequestInFlight = true;

        try {
            const payload = await this.requestAction(
                desiredBookmarked ? this.urls.bookmarkStore : this.urls.bookmarkDestroy,
                desiredBookmarked ? 'POST' : 'DELETE',
            );

            if (
                !payload
                || typeof payload.post_uid !== 'string'
                || payload.post_uid !== this.postUid
                || typeof payload.bookmarked !== 'boolean'
            ) {
                throw this.actionError(this.labels.invalid);
            }

            this.bookmarked = payload.bookmarked;
            this.syncBookmarkPresentation();
            if (payload.bookmarked !== previousBookmarked) {
                await playSocialActionMotion(
                    this.$refs.bookmarkButton,
                    payload.bookmarked ? 'bookmark-save' : 'bookmark-remove',
                );
            }
            this.publishPeerState();
        } catch (error) {
            this.setFeedback(this.feedbackForError(error), true);
        } finally {
            this.isBookmarkRequestInFlight = false;
            releaseActionLock();
        }
    },
}));

Alpine.start();
