import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('likeControl', (config = {}) => ({
    liked: Boolean(config.liked),
    likeCount: Number.isInteger(config.likeCount) ? config.likeCount : 0,
    postUid: typeof config.postUid === 'string' ? config.postUid : '',
    url: typeof config.url === 'string' ? config.url : '',
    labels: config.labels || {},
    isLikeRequestInFlight: false,
    feedbackMessage: '',
    feedbackIsError: false,

    init() {
        this.syncLikePresentation();
    },

    formatLikeCount() {
        if (this.likeCount < 1000) return String(this.likeCount);

        for (const [suffix, unit] of [
            ['B', 1_000_000_000],
            ['M', 1_000_000],
            ['K', 1_000],
        ]) {
            if (this.likeCount >= unit) return `${Math.floor(this.likeCount / unit)}${suffix}`;
        }

        return String(this.likeCount);
    },

    likeDisplayCount() {
        return this.likeCount > 0 ? this.formatLikeCount() : '';
    },

    likeButtonLabel() {
        const label = this.liked ? this.labels.unlike : this.labels.like;

        return this.likeCount > 0 ? `${label} (${this.likeCount})` : label;
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

    setFeedback(message, isError) {
        this.feedbackMessage = message;
        this.feedbackIsError = isError;
    },

    csrfToken() {
        return this.$el.querySelector('input[name="_token"]')?.value || '';
    },

    async toggleLike(event) {
        event?.preventDefault();

        if (this.isLikeRequestInFlight) return;

        const desiredLiked = !this.liked;
        this.feedbackMessage = '';
        this.feedbackIsError = false;
        this.isLikeRequestInFlight = true;

        try {
            const requestUrl = new URL(this.url, window.location.href);
            if (requestUrl.origin !== window.location.origin) throw new Error('Invalid like URL.');

            const response = await fetch(requestUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ liked: desiredLiked }),
            });

            let payload = null;
            try {
                payload = await response.json();
            } catch {
                payload = null;
            }

            if (response.redirected || response.status === 401 || response.status === 419) {
                this.setFeedback(this.labels.sessionExpired, true);
                return;
            }

            if (response.status === 403) {
                const message = payload?.code === 'moderation_state_forbids_social_write'
                    && typeof payload.message === 'string'
                    ? payload.message
                    : this.labels.restricted;
                this.setFeedback(message, true);
                return;
            }

            if (!response.ok) {
                this.setFeedback(response.status === 422 ? this.labels.invalid : this.labels.failed, true);
                return;
            }

            if (
                !payload
                || typeof payload.post_uid !== 'string'
                || payload.post_uid !== this.postUid
                || typeof payload.liked !== 'boolean'
                || !Number.isInteger(payload.like_count)
                || payload.like_count < 0
            ) {
                this.setFeedback(this.labels.invalid, true);
                return;
            }

            this.liked = payload.liked;
            this.likeCount = payload.like_count;
            this.syncLikePresentation();
            this.setFeedback(this.liked ? this.labels.liked : this.labels.unliked, false);
        } catch {
            this.setFeedback(this.labels.network, true);
        } finally {
            this.isLikeRequestInFlight = false;
        }
    },
}));

Alpine.start();
