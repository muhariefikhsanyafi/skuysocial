import { afterEach, describe, expect, it, vi } from 'vitest';
import { avatarStudio } from './avatarStudio';

const config = {
    saveUrl: '/pengaturan/avatar', previews: { wavatar: '/preview/wavatar', retro: '/preview/retro' },
    initialUrl: '/storage/previous.jpg', currentStyle: null, failedMessage: 'Failed; previous photo kept.', userId: 7,
};

afterEach(() => vi.restoreAllMocks());

describe('Avatar Studio', () => {
    it('requires a loaded choice and persists successful selection with the same local URL', async () => {
        const request = vi.fn().mockResolvedValue(new Response(JSON.stringify({ avatar_url: '/storage/retro.png', style: 'retro', message: 'Saved' })));
        const studio = avatarStudio(config, request);
        studio.mode = 'gravatar'; studio.chooseStyle('retro');
        expect(studio.canSave).toBe(false);
        studio.status.retro = 'ready';
        const listener = vi.fn();
        window.addEventListener('avatar-saved', listener, { once: true });
        await studio.save();
        expect((request.mock.calls[0][1].body as FormData).get('style')).toBe('retro');
        expect(studio.currentUrl).toBe('/storage/retro.png');
        expect(studio.currentStyle).toBe('retro');
        expect(studio.canSave).toBe(false);
        expect(listener).toHaveBeenCalledOnce();
    });

    it('keeps the old avatar after failed saves and prevents duplicate submissions', async () => {
        let resolve!: (value: Response) => void;
        const request = vi.fn().mockImplementation(() => new Promise<Response>(done => { resolve = done; }));
        const studio = avatarStudio(config, request);
        studio.mode = 'gravatar'; studio.status.wavatar = 'ready';
        const first = studio.save();
        await studio.save();
        expect(request).toHaveBeenCalledOnce();
        resolve(new Response('{}', { status: 503 })); await first;
        expect(studio.currentUrl).toBe(config.initialUrl);
        expect(studio.error).toBe(config.failedMessage);
        expect(studio.saving).toBe(false);
    });

    it('rejects unsupported upload files before making a request', () => {
        const studio = avatarStudio(config);
        studio.selectFile({ target: { files: [new File(['x'], 'file.svg', { type: 'image/svg+xml' })] } } as unknown as Event);
        expect(studio.file).toBeNull();
        expect(studio.error).toBe(config.failedMessage);
    });

    it('cancel clears a pending upload and restores the existing style', () => {
        const revoke = vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {});
        const studio = avatarStudio({ ...config, currentStyle: 'retro' });
        studio.openStudio(); studio.selected = 'wavatar'; studio.uploadUrl = 'blob:synthetic';
        studio.closeStudio();
        expect(studio.selected).toBe('retro');
        expect(studio.visible).toBe(false);
        expect(revoke).toHaveBeenCalledWith('blob:synthetic');
        expect(document.body.style.overflow).toBe('');
    });
});
