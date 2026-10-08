type SavedPageRefs = {
    savedPosts?: HTMLElement | null;
    savedEmptyState?: HTMLElement | null;
    savedPagination?: HTMLElement | null;
};

type SavedPageScope = {
    $root: HTMLElement;
    $refs: SavedPageRefs;
    $nextTick(callback: () => void): void;
    visiblePostCount: number;
};

type BookmarkActionDetail = {
    postUid?: unknown;
    bookmarked?: unknown;
};

type MediaViewerScope = {
    $refs: {
        lightboxClose?: HTMLElement | null;
        lightboxDialog?: HTMLElement | null;
    };
    $nextTick(callback: () => void): void;
    openImage: boolean;
    activeImage: string;
    lightboxImages: string[];
    lightboxIndex: number;
    lightboxTrigger: HTMLElement | null;
    lightboxPointerStart: { pointerId: number; x: number; y: number } | null;
    previousBodyOverflow: string;
    setLightboxImage(index: number): void;
    previousImage(): void;
    nextImage(): void;
    closeImage(): void;
};

export function savedPageController() {
    return {
        visiblePostCount: 0,

        init(this: SavedPageScope) {
            const initialCount = Number(this.$root.dataset.initialCount);
            this.visiblePostCount = Number.isFinite(initialCount) ? Math.max(0, Math.trunc(initialCount)) : 0;
        },

        removeUnsaved(this: SavedPageScope, event: Event) {
            const detail = (event as CustomEvent<BookmarkActionDetail> | null)?.detail;
            if (detail?.bookmarked !== false || typeof detail.postUid !== 'string') return;

            const savedPosts = this.$refs.savedPosts;
            if (!savedPosts) return;

            const cards = Array.from(savedPosts.querySelectorAll<HTMLElement>('article[data-feed-post-uid]'));
            const card = cards.find((candidate) => candidate.dataset.feedPostUid === detail.postUid);
            if (!card) return;

            const focusWasInside = card.contains(document.activeElement);
            const adjacentCard = card.nextElementSibling ?? card.previousElementSibling;
            const focusCandidate = adjacentCard?.querySelector<HTMLElement>('button:not([disabled]), a[href]') ?? null;
            card.remove();
            this.visiblePostCount = savedPosts.querySelectorAll('article[data-feed-post-uid]').length;

            if (!focusWasInside) return;

            this.$nextTick(() => {
                if (focusCandidate?.isConnected) {
                    focusCandidate.focus();
                    return;
                }

                const emptyAction = this.$refs.savedEmptyState?.querySelector<HTMLElement>('[data-saved-feed-link]');
                const paginationAction = this.$refs.savedPagination?.querySelector<HTMLElement>('a[href]');
                (emptyAction ?? paginationAction)?.focus();
            });
        },
    };
}

export function postMediaViewerController() {
    return {
        openImage: false,
        activeImage: '',
        lightboxImages: [] as string[],
        lightboxIndex: 0,
        lightboxTrigger: null as HTMLElement | null,
        lightboxPointerStart: null as { pointerId: number; x: number; y: number } | null,
        previousBodyOverflow: '',

        openLightbox(this: MediaViewerScope, images: unknown, index = 0, event: Event | null = null) {
            const normalizedImages = Array.isArray(images)
                ? images.filter((image): image is string => typeof image === 'string' && image.trim() !== '')
                : [];
            if (normalizedImages.length === 0) return;

            if (!this.openImage) this.previousBodyOverflow = document.body.style.overflow;
            const requestedIndex = Number.isFinite(Number(index)) ? Math.trunc(Number(index)) : 0;
            this.lightboxImages = normalizedImages;
            this.lightboxIndex = Math.max(0, Math.min(requestedIndex, normalizedImages.length - 1));
            this.activeImage = normalizedImages[this.lightboxIndex];
            const currentTarget = event?.currentTarget;
            this.lightboxTrigger = typeof HTMLElement !== 'undefined' && currentTarget instanceof HTMLElement ? currentTarget : null;
            this.openImage = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => this.$refs.lightboxClose?.focus());
        },

        closeImage(this: MediaViewerScope) {
            if (!this.openImage) return;

            const trigger = this.lightboxTrigger;
            this.openImage = false;
            this.activeImage = '';
            this.lightboxImages = [];
            this.lightboxIndex = 0;
            this.lightboxPointerStart = null;
            document.body.style.overflow = this.previousBodyOverflow;
            this.$nextTick(() => {
                if (trigger?.isConnected) trigger.focus();
            });
        },

        setLightboxImage(this: MediaViewerScope, index: number) {
            if (this.lightboxImages.length === 0) return;
            const safeIndex = Number.isFinite(index) ? Math.trunc(index) : this.lightboxIndex;
            this.lightboxIndex = Math.max(0, Math.min(safeIndex, this.lightboxImages.length - 1));
            this.activeImage = this.lightboxImages[this.lightboxIndex];
        },

        previousImage(this: MediaViewerScope) {
            this.setLightboxImage(this.lightboxIndex - 1);
        },

        nextImage(this: MediaViewerScope) {
            this.setLightboxImage(this.lightboxIndex + 1);
        },

        startLightboxPointer(this: MediaViewerScope, event: PointerEvent) {
            this.lightboxPointerStart = { pointerId: event.pointerId, x: event.clientX, y: event.clientY };
        },

        endLightboxPointer(this: MediaViewerScope, event: PointerEvent) {
            const start = this.lightboxPointerStart;
            this.lightboxPointerStart = null;
            if (!start || start.pointerId !== event.pointerId || this.lightboxImages.length < 2) return;

            const deltaX = event.clientX - start.x;
            const deltaY = event.clientY - start.y;
            if (Math.abs(deltaX) < 48 || Math.abs(deltaX) < Math.abs(deltaY) * 1.2) return;
            if (deltaX < 0) this.nextImage();
            else this.previousImage();
        },

        handleLightboxKeydown(this: MediaViewerScope, event: KeyboardEvent) {
            if (!this.openImage) return;

            if (event.key === 'Escape') {
                event.preventDefault();
                this.closeImage();
                return;
            }
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                this.previousImage();
                return;
            }
            if (event.key === 'ArrowRight') {
                event.preventDefault();
                this.nextImage();
                return;
            }
            if (event.key !== 'Tab') return;

            const dialog = this.$refs.lightboxDialog;
            const focusable = Array.from(dialog?.querySelectorAll<HTMLElement>('button:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])') ?? []);
            if (focusable.length === 0) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
    };
}
