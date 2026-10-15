// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { chooseProfilePostMenuPlacement } from './skuy-flow-profile.js';

afterEach(() => {
    vi.restoreAllMocks();
    document.documentElement.removeAttribute('data-flow-page');
    document.body.replaceChildren();
});

describe('profile post-options placement', () => {
    it('opens above the trigger when a fixed bottom dock blocks downward placement', () => {
        expect(chooseProfilePostMenuPlacement({
            triggerTop: 632,
            triggerBottom: 676,
            menuHeight: 137,
            safeBottom: 710,
        })).toEqual({
            placement: 'above',
            availableHeight: 616,
            needsScroll: false,
        });
    });

    it('opens below the trigger when the menu is near the top edge', () => {
        expect(chooseProfilePostMenuPlacement({
            triggerTop: 60,
            triggerBottom: 104,
            menuHeight: 137,
            safeBottom: 710,
        })).toEqual({
            placement: 'below',
            availableHeight: 598,
            needsScroll: false,
        });
    });

    it('limits and scrolls a menu when neither side has enough room', () => {
        expect(chooseProfilePostMenuPlacement({
            triggerTop: 60,
            triggerBottom: 104,
            menuHeight: 700,
            safeBottom: 180,
        })).toEqual({
            placement: 'below',
            availableHeight: 68,
            needsScroll: true,
        });
    });

    it('places an open menu on the first trigger click even when bubbling is stopped', async () => {
        document.documentElement.dataset.flowPage = 'profile';
        document.body.innerHTML = '<div class="skuy-post__options"><button aria-haspopup="menu"></button><div role="menu" style="display: none"></div></div><nav class="skuy-mobile-nav"></nav>';

        const options = document.querySelector<HTMLDivElement>('.skuy-post__options');
        const trigger = options?.querySelector<HTMLButtonElement>('button');
        const menu = options?.querySelector<HTMLDivElement>('[role="menu"]');
        const dock = document.querySelector<HTMLElement>('.skuy-mobile-nav');
        const frameCallbacks: FrameRequestCallback[] = [];
        expect(options).not.toBeNull();
        expect(trigger).not.toBeNull();
        expect(menu).not.toBeNull();
        expect(dock).not.toBeNull();

        vi.spyOn(window, 'requestAnimationFrame').mockImplementation((callback) => {
            frameCallbacks.push(callback);
            return frameCallbacks.length;
        });
        vi.spyOn(trigger!, 'getBoundingClientRect').mockReturnValue(new DOMRect(0, 632, 44, 44));
        vi.spyOn(menu!, 'getBoundingClientRect').mockReturnValue(new DOMRect(119, 0, 176, 137));
        vi.spyOn(dock!, 'getBoundingClientRect').mockReturnValue(new DOMRect(0, 710, 320, 70));
        Object.defineProperty(menu!, 'scrollHeight', { configurable: true, value: 137 });
        trigger!.addEventListener('click', () => {
            window.requestAnimationFrame(() => { menu!.style.display = 'block'; });
        });
        options!.addEventListener('click', (event) => event.stopPropagation());

        vi.resetModules();
        await import('./skuy-flow-profile.js');
        trigger!.click();
        frameCallbacks.splice(0).forEach((callback) => callback(0));
        await Promise.resolve();
        frameCallbacks.splice(0).forEach((callback) => callback(0));

        expect(menu!.dataset.flowProfilePlacement).toBe('above');
        expect(menu!.style.getPropertyValue('--flow-profile-menu-max-height')).toBe('616px');
    });
});
