import Alpine from 'alpinejs';

window.Alpine = Alpine;

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

        const desiredLiked = !this.liked;
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
            this.publishPeerState();
            this.setFeedback(this.liked ? this.labels.liked : this.labels.unliked, false);
        } catch (error) {
            this.setFeedback(this.feedbackForError(error), true);
        } finally {
            this.isLikeRequestInFlight = false;
        }
    },

    async toggleRepost(event) {
        event?.preventDefault();
        if (this.isRepostRequestInFlight) return;

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
            this.publishPeerState();
        } catch (error) {
            this.setFeedback(this.feedbackForError(error), true);
        } finally {
            this.isRepostRequestInFlight = false;
        }
    },

    async toggleBookmark(event) {
        event?.preventDefault();
        if (this.isBookmarkRequestInFlight) return;

        const desiredBookmarked = !this.bookmarked;
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
            this.publishPeerState();
        } catch (error) {
            this.setFeedback(this.feedbackForError(error), true);
        } finally {
            this.isBookmarkRequestInFlight = false;
        }
    },
}));

Alpine.start();
