import { afterEach, describe, expect, it, vi } from 'vitest';
import { playSocialActionMotion } from './socialActionMotion';

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('playSocialActionMotion', () => {
    it('finishes a pending animation if the test window is torn down', async () => {
        const originalWindow = window;
        vi.useFakeTimers();

        try {
            const button = document.createElement('button');
            button.innerHTML = '<svg aria-hidden="true"></svg>';
            const finished = playSocialActionMotion(button, 'like');

            vi.stubGlobal('window', undefined);
            expect(() => vi.advanceTimersByTime(300)).not.toThrow();
            await expect(finished).resolves.toBeUndefined();
        } finally {
            vi.stubGlobal('window', originalWindow);
            vi.useRealTimers();
        }
    });

    it('plays a named action motion until its CSS animation ends', async () => {
        vi.stubGlobal('matchMedia', vi.fn(() => ({ matches: false } as MediaQueryList)));
        const button = document.createElement('button');
        button.innerHTML = '<svg aria-hidden="true"></svg>';
        const icon = button.querySelector('svg') as SVGElement;

        const finished = playSocialActionMotion(button, 'like');

        expect(icon.classList.contains('skuy-social-motion--like')).toBe(true);
        icon.dispatchEvent(new Event('animationend'));
        await finished;
        expect(icon.classList.contains('skuy-social-motion--like')).toBe(false);
    });

    it('does not start non-essential motion when reduced motion is enabled', async () => {
        vi.stubGlobal('matchMedia', vi.fn(() => ({ matches: true } as MediaQueryList)));
        const button = document.createElement('button');
        button.innerHTML = '<svg aria-hidden="true"></svg>';
        const icon = button.querySelector('svg') as SVGElement;

        await playSocialActionMotion(button, 'appreciation');

        expect(icon.className.baseVal).not.toContain('skuy-social-motion--appreciation');
    });

    it('safely skips controls without a direct icon', async () => {
        vi.stubGlobal('matchMedia', vi.fn(() => ({ matches: false } as MediaQueryList)));
        const button = document.createElement('button');
        button.textContent = 'Action';

        await expect(playSocialActionMotion(button, 'repost')).resolves.toBeUndefined();
        await expect(playSocialActionMotion(undefined, 'like')).resolves.toBeUndefined();
    });
});
