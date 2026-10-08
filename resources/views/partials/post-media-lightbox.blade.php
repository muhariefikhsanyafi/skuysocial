<div
    x-show="openImage"
    x-ref="lightboxDialog"
    @click.self="closeImage()"
    @keydown.window="handleLightboxKeydown($event)"
    style="display: none;"
    role="dialog"
    aria-modal="true"
    aria-label="{{ __('threads.media.photo_preview') }}"
    tabindex="-1"
    class="skuy-lightbox fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-8"
    data-skuy-media-viewer
>
    <span
        x-show="lightboxImages.length > 1"
        x-text="'{{ __('threads.media.image_position', ['current' => '__CURRENT__', 'total' => '__TOTAL__']) }}'.replace('__CURRENT__', lightboxIndex + 1).replace('__TOTAL__', lightboxImages.length)"
        class="skuy-lightbox__counter"
        role="status"
        aria-live="polite"
    ></span>
    <button
        type="button"
        x-show="lightboxImages.length > 1"
        @click.stop="previousImage()"
        :disabled="lightboxIndex === 0"
        aria-label="{{ __('threads.media.previous_image') }}"
        class="skuy-lightbox__control absolute left-3 top-1/2 -translate-y-1/2 sm:left-8"
    >
        <x-fa-icon name="chevron-left" class="text-2xl" />
    </button>
    <button
        type="button"
        x-show="lightboxImages.length > 1"
        @click.stop="nextImage()"
        :disabled="lightboxIndex >= lightboxImages.length - 1"
        aria-label="{{ __('threads.media.next_image') }}"
        class="skuy-lightbox__control absolute right-3 top-1/2 -translate-y-1/2 sm:right-8"
    >
        <x-fa-icon name="chevron-right" class="text-2xl" />
    </button>
    <button
        type="button"
        x-ref="lightboxClose"
        @click="closeImage()"
        aria-label="{{ __('threads.media.close_preview') }}"
        class="skuy-lightbox__control absolute right-3 top-3 sm:right-8 sm:top-8"
    >
        <x-fa-icon name="xmark" weight="solid" class="text-2xl" />
    </button>
    <img
        :src="activeImage"
        alt="{{ __('threads.media.full_photo') }}"
        @pointerdown="startLightboxPointer($event)"
        @pointerup="endLightboxPointer($event)"
        @pointercancel="lightboxPointerStart = null"
        class="skuy-lightbox__image"
    >
</div>
