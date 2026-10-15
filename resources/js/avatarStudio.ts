interface StudioConfig {
    saveUrl: string;
    previews: Record<string, string>;
    initialUrl: string;
    currentStyle: string | null;
    failedMessage: string;
    userId: number;
}

export function avatarStudio(config: StudioConfig, request: typeof fetch = fetch) {
    return {
        visible: false,
        mode: config.currentStyle ? 'gravatar' : 'upload',
        selected: config.currentStyle ?? 'wavatar',
        currentStyle: config.currentStyle,
        currentUrl: config.initialUrl,
        uploadUrl: '',
        file: null as File | null,
        saving: false,
        error: '',
        success: '',
        retryVersion: 0,
        status: {} as Record<string, 'loading' | 'ready' | 'failed'>,
        returnFocus: null as HTMLElement | null,
        previousOverflow: '',
        get preview(): string {
            return this.mode === 'gravatar' ? this.styleUrl(this.selected) : (this.uploadUrl || this.currentUrl);
        },
        get canSave(): boolean {
            return !this.saving && (this.mode === 'upload' ? this.file !== null : this.status[this.selected] === 'ready' && this.selected !== this.currentStyle);
        },
        styleUrl(style: string) {
            return config.previews[style] + (this.retryVersion ? `${config.previews[style].includes('?') ? '&' : '?'}retry=${this.retryVersion}` : '');
        },
        openStudio() {
            if (this.visible) return;
            this.returnFocus = document.activeElement as HTMLElement;
            this.previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            this.visible = true;
            this.error = '';
            this.success = '';
        },
        closeStudio() {
            if (this.saving || !this.visible) return;
            this.visible = false;
            document.body.style.overflow = this.previousOverflow;
            this.clearUpload();
            this.mode = this.currentStyle ? 'gravatar' : 'upload';
            this.selected = this.currentStyle ?? 'wavatar';
            this.error = '';
            this.returnFocus?.focus();
        },
        clearUpload(resetInput = true) {
            if (this.uploadUrl) URL.revokeObjectURL(this.uploadUrl);
            this.uploadUrl = '';
            this.file = null;
            const input = document.querySelector<HTMLInputElement>('[data-avatar-studio-upload]');
            if (input && resetInput) input.value = '';
        },
        selectFile(event: Event) {
            const file = (event.target as HTMLInputElement).files?.[0];
            this.clearUpload(false);
            this.error = '';
            this.success = '';
            if (!file) return;
            if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type) || file.size > 2 * 1024 * 1024) {
                this.error = config.failedMessage;
                return;
            }
            this.file = file;
            this.uploadUrl = URL.createObjectURL(file);
        },
        chooseStyle(style: string) {
            this.selected = style;
            this.error = '';
            this.success = '';
        },
        imageLoaded(style: string, image: HTMLImageElement) {
            this.status[style] = image.naturalWidth > 0 ? 'ready' : 'failed';
        },
        retry() {
            this.status = {};
            this.retryVersion += 1;
            this.error = '';
        },
        trapFocus(event: KeyboardEvent, dialog: HTMLElement) {
            if (event.key !== 'Tab') return;
            const elements = [...dialog.querySelectorAll<HTMLElement>('button:not(:disabled), input, [href]')]
                .filter(element => element.getClientRects().length > 0 && element.tabIndex >= 0);
            const first = elements[0];
            const last = elements[elements.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault(); last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault(); first?.focus();
            }
        },
        async save() {
            if (!this.canSave) return;
            this.saving = true;
            this.error = '';
            this.success = '';
            const body = new FormData();
            body.append('source', this.mode);
            if (this.mode === 'upload' && this.file) body.append('avatar', this.file);
            else body.append('style', this.selected);
            try {
                const response = await request(config.saveUrl, {
                    method: 'POST', body, credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' },
                });
                const data = await response.json();
                if (!response.ok || typeof data.avatar_url !== 'string' || typeof data.message !== 'string') {
                    throw new Error('Avatar save failed');
                }
                this.currentUrl = data.avatar_url;
                this.currentStyle = data.style ?? null;
                this.success = data.message;
                this.clearUpload();
                window.dispatchEvent(new CustomEvent('avatar-saved', { detail: { url: data.avatar_url, userId: config.userId } }));
            } catch {
                this.error = config.failedMessage;
            } finally {
                this.saving = false;
            }
        },
        destroy() {
            if (this.uploadUrl) URL.revokeObjectURL(this.uploadUrl);
            if (this.visible) document.body.style.overflow = this.previousOverflow;
        },
    };
}
