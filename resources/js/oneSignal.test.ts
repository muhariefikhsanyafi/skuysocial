import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { initializeOneSignal } from './oneSignal';

interface FakeOneSignal {
    init: ReturnType<typeof vi.fn>;
    login: ReturnType<typeof vi.fn>;
    logout: ReturnType<typeof vi.fn>;
    Notifications: {
        permission: 'default' | 'granted' | 'denied';
        isPushSupported: ReturnType<typeof vi.fn>;
        requestPermission: ReturnType<typeof vi.fn>;
        addEventListener: ReturnType<typeof vi.fn>;
    };
    User: {
        PushSubscription: {
            id: string | null;
            token: string | null;
            optedIn: boolean;
            optIn: ReturnType<typeof vi.fn>;
            optOut: ReturnType<typeof vi.fn>;
            addEventListener: ReturnType<typeof vi.fn>;
        };
    };
}

function mountPushSettings(configured = true): {
    settings: HTMLElement;
    status: HTMLElement;
    deniedHelp: HTMLElement;
    enable: HTMLButtonElement;
    disable: HTMLButtonElement;
    logoutForm: HTMLFormElement;
} {
    document.head.innerHTML = '';
    document.body.innerHTML = `
        ${configured ? '<div data-onesignal-bootstrap data-app-id="app-public-id" data-external-id="skuysocial-user:24"></div>' : ''}
        <section
            data-push-settings
            data-state-checking="Checking this device."
            data-state-unavailable="Push notifications are unavailable."
            data-state-unsupported="This browser does not support push notifications."
            data-state-denied="Notifications are blocked in browser settings."
            data-state-not-requested="Notifications are off on this device."
            data-state-enabled="Notifications are enabled on this device."
            data-enable-label="Enable notifications on this device"
            data-disable-label="Disable notifications on this device"
        >
            <p data-push-status role="status" aria-live="polite"></p>
            <p data-push-denied-help hidden>Change notification permission in your browser or site settings.</p>
            <button type="button" data-push-enable hidden>Enable</button>
            <button type="button" data-push-disable hidden>Disable</button>
        </section>
        <form method="POST" action="/logout" data-onesignal-logout>
            <input type="hidden" name="_token" value="csrf">
            <button type="submit">Sign out</button>
        </form>
    `;

    const settings = document.querySelector<HTMLElement>('[data-push-settings]')!;

    return {
        settings,
        status: settings.querySelector<HTMLElement>('[data-push-status]')!,
        deniedHelp: settings.querySelector<HTMLElement>('[data-push-denied-help]')!,
        enable: settings.querySelector<HTMLButtonElement>('[data-push-enable]')!,
        disable: settings.querySelector<HTMLButtonElement>('[data-push-disable]')!,
        logoutForm: document.querySelector<HTMLFormElement>('[data-onesignal-logout]')!,
    };
}

function fakeOneSignal(overrides: Partial<FakeOneSignal> = {}): FakeOneSignal {
    const api: FakeOneSignal = {
        init: vi.fn().mockResolvedValue(undefined),
        login: vi.fn().mockResolvedValue(undefined),
        logout: vi.fn().mockResolvedValue(undefined),
        Notifications: {
            permission: 'default',
            isPushSupported: vi.fn().mockResolvedValue(true),
            requestPermission: vi.fn(),
            addEventListener: vi.fn(),
        },
        User: {
            PushSubscription: {
                id: null,
                token: null,
                optedIn: false,
                optIn: vi.fn().mockResolvedValue(undefined),
                optOut: vi.fn().mockResolvedValue(undefined),
                addEventListener: vi.fn(),
            },
        },
    };

    return Object.assign(api, overrides);
}

async function resolveSdk(api: FakeOneSignal): Promise<void> {
    const queue = (window as Window & { OneSignalDeferred?: Array<(sdk: FakeOneSignal) => Promise<void>> }).OneSignalDeferred;
    expect(queue).toHaveLength(1);
    await queue![0](api);
}

describe('OneSignal device opt-in foundation', () => {
    let formSubmit: ReturnType<typeof vi.spyOn>;

    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        formSubmit?.mockRestore();
        vi.clearAllTimers();
        vi.useRealTimers();
        document.head.innerHTML = '';
        document.body.innerHTML = '';
        delete (window as Window & { OneSignalDeferred?: unknown }).OneSignalDeferred;
    });

    it('initializes once, logs in with the stable ID, and never prompts on page load', async () => {
        mountPushSettings();
        initializeOneSignal();
        initializeOneSignal();

        const scripts = document.querySelectorAll('script[data-onesignal-sdk]');
        expect(scripts).toHaveLength(1);
        const api = fakeOneSignal();
        await resolveSdk(api);

        expect(api.init).toHaveBeenCalledWith({
            appId: 'app-public-id',
            serviceWorkerPath: 'onesignal/OneSignalSDKWorker.js',
            serviceWorkerParam: { scope: '/onesignal/' },
        });
        expect(api.login).toHaveBeenCalledWith('skuysocial-user:24');
        expect(api.User.PushSubscription.optIn).not.toHaveBeenCalled();
        expect(api.Notifications.requestPermission).not.toHaveBeenCalled();
        expect(document.querySelector<HTMLElement>('[data-push-status]')?.textContent).toBe('Notifications are off on this device.');
    });

    it('does not load the SDK and reports unavailable when config is absent', () => {
        const { status } = mountPushSettings(false);
        initializeOneSignal();

        expect(document.querySelector('script[data-onesignal-sdk]')).toBeNull();
        expect(status.textContent).toBe('Push notifications are unavailable.');
    });

    it('calls opt-in only after an explicit click and enables only with a usable subscription', async () => {
        const { enable, disable, status } = mountPushSettings();
        initializeOneSignal();
        const api = fakeOneSignal();
        api.User.PushSubscription.optIn.mockImplementation(async () => {
            api.Notifications.permission = 'granted';
            api.User.PushSubscription.optedIn = true;
            api.User.PushSubscription.id = 'subscription-id';
            api.User.PushSubscription.token = 'push-token';
        });
        await resolveSdk(api);

        expect(api.User.PushSubscription.optIn).not.toHaveBeenCalled();
        expect(status.textContent).toBe('Notifications are off on this device.');
        expect(enable.hidden).toBe(false);

        enable.click();
        await vi.waitFor(() => expect(status.textContent).toBe('Notifications are enabled on this device.'));

        expect(api.User.PushSubscription.optIn).toHaveBeenCalledTimes(1);
        expect(disable.hidden).toBe(false);
    });

    it('does not claim enabled until both subscription ID and push token are available', async () => {
        const { enable, status } = mountPushSettings();
        initializeOneSignal();
        const api = fakeOneSignal();
        api.Notifications.permission = 'granted';
        api.User.PushSubscription.optedIn = true;
        api.User.PushSubscription.id = 'subscription-id';
        api.User.PushSubscription.token = null;
        await resolveSdk(api);

        expect(status.textContent).toBe('Notifications are off on this device.');
        expect(enable.hidden).toBe(false);
    });

    it('explains denied permission and hides opt-in until browser/site settings change', async () => {
        const { deniedHelp, enable, status } = mountPushSettings();
        initializeOneSignal();
        const api = fakeOneSignal();
        api.Notifications.permission = 'denied';
        await resolveSdk(api);

        expect(status.textContent).toBe('Notifications are blocked in browser settings.');
        expect(deniedHelp.hidden).toBe(false);
        expect(enable.hidden).toBe(true);
        expect(api.User.PushSubscription.optIn).not.toHaveBeenCalled();
    });

    it('degrades gracefully when push is unsupported', async () => {
        const { enable, disable, status } = mountPushSettings();
        initializeOneSignal();
        const api = fakeOneSignal();
        api.Notifications.isPushSupported.mockResolvedValue(false);
        await resolveSdk(api);

        expect(status.textContent).toBe('This browser does not support push notifications.');
        expect(enable.hidden).toBe(true);
        expect(disable.hidden).toBe(true);
    });

    it('degrades gracefully when the SDK CDN fails', () => {
        const { status } = mountPushSettings();
        initializeOneSignal();

        document.querySelector('script[data-onesignal-sdk]')?.dispatchEvent(new Event('error'));

        expect(status.textContent).toBe('Push notifications are unavailable.');
    });

    it('opts out this device and refreshes the state', async () => {
        const { disable, status } = mountPushSettings();
        initializeOneSignal();
        const api = fakeOneSignal();
        api.Notifications.permission = 'granted';
        api.User.PushSubscription.optedIn = true;
        api.User.PushSubscription.id = 'subscription-id';
        api.User.PushSubscription.token = 'push-token';
        api.User.PushSubscription.optOut.mockImplementation(async () => {
            api.User.PushSubscription.optedIn = false;
        });
        await resolveSdk(api);

        expect(status.textContent).toBe('Notifications are enabled on this device.');
        disable.click();
        await vi.waitFor(() => expect(status.textContent).toBe('Notifications are off on this device.'));
        expect(api.User.PushSubscription.optOut).toHaveBeenCalledTimes(1);
    });

    it('submits logout even when OneSignal logout rejects', async () => {
        const { logoutForm } = mountPushSettings();
        initializeOneSignal();
        const api = fakeOneSignal();
        api.logout.mockRejectedValue(new Error('synthetic SDK error'));
        await resolveSdk(api);
        formSubmit = vi.spyOn(HTMLFormElement.prototype, 'submit').mockImplementation(() => {});

        const event = new Event('submit', { bubbles: true, cancelable: true });
        logoutForm.dispatchEvent(event);
        await vi.waitFor(() => expect(formSubmit).toHaveBeenCalledTimes(1));

        expect(event.defaultPrevented).toBe(true);
        expect(api.logout).toHaveBeenCalledTimes(1);
    });

    it('keeps logout bounded when the SDK never becomes ready', async () => {
        const { logoutForm } = mountPushSettings();
        initializeOneSignal();
        formSubmit = vi.spyOn(HTMLFormElement.prototype, 'submit').mockImplementation(() => {});

        logoutForm.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await vi.advanceTimersByTimeAsync(600);

        expect(formSubmit).toHaveBeenCalledTimes(1);
    });

    it('keeps logout working when the SDK is unavailable', async () => {
        const { logoutForm } = mountPushSettings(false);
        initializeOneSignal();
        formSubmit = vi.spyOn(HTMLFormElement.prototype, 'submit').mockImplementation(() => {});

        logoutForm.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await vi.waitFor(() => expect(formSubmit).toHaveBeenCalledTimes(1));
    });
});
