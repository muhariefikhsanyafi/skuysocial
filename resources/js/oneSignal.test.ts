import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { initializeOneSignal } from './oneSignal';

interface FakeOneSignal {
    init: ReturnType<typeof vi.fn>;
    login: ReturnType<typeof vi.fn>;
    logout: ReturnType<typeof vi.fn>;
    Notifications: {
        permission: boolean;
        permissionNative: 'default' | 'granted' | 'denied';
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
    error: HTMLElement;
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
            data-state-action-failed="Could not update notifications on this device. Please try again."
            data-enable-label="Enable notifications on this device"
            data-disable-label="Disable notifications on this device"
        >
            <p data-push-status role="status" aria-live="polite"></p>
            <p data-push-action-error hidden role="alert"></p>
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
        error: settings.querySelector<HTMLElement>('[data-push-action-error]')!,
        logoutForm: document.querySelector<HTMLFormElement>('[data-onesignal-logout]')!,
    };
}

function fakeOneSignal(overrides: Partial<FakeOneSignal> = {}): FakeOneSignal {
    const api: FakeOneSignal = {
        init: vi.fn().mockResolvedValue(undefined),
        login: vi.fn().mockResolvedValue(undefined),
        logout: vi.fn().mockResolvedValue(undefined),
        Notifications: {
            permission: false,
            permissionNative: 'default',
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

function setBrowserPermission(permission: NotificationPermission): void {
    vi.stubGlobal('Notification', { permission });
}

function setGrantedPermissions(api: FakeOneSignal): void {
    setBrowserPermission('granted');
    api.Notifications.permission = true;
    api.Notifications.permissionNative = 'granted';
}

describe('OneSignal device opt-in foundation', () => {
    let formSubmit: ReturnType<typeof vi.spyOn>;

    beforeEach(() => {
        vi.useFakeTimers();
        setBrowserPermission('default');
    });

    afterEach(() => {
        formSubmit?.mockRestore();
        vi.clearAllTimers();
        vi.useRealTimers();
        vi.unstubAllGlobals();
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
        expect(api.User.PushSubscription.addEventListener).toHaveBeenCalledTimes(1);
        expect(api.User.PushSubscription.optIn).not.toHaveBeenCalled();
        expect(api.Notifications.requestPermission).not.toHaveBeenCalled();
        expect(document.querySelector<HTMLElement>('[data-push-status]')?.textContent).toBe('Notifications are off on this device.');
    });

    it('renders enabled after a hard refresh when the browser and OneSignal report a subscribed device', async () => {
        const { settings, status, enable, disable } = mountPushSettings();
        initializeOneSignal();
        const api = fakeOneSignal();
        setGrantedPermissions(api);
        api.User.PushSubscription.optedIn = true;
        api.User.PushSubscription.id = 'subscription-id';
        api.User.PushSubscription.token = 'push-token';

        await resolveSdk(api);

        expect(api.login).toHaveBeenCalledWith('skuysocial-user:24');
        expect(settings.dataset.pushState).toBe('enabled');
        expect(status.textContent).toBe('Notifications are enabled on this device.');
        expect(status.textContent).not.toBe('Notifications are off on this device.');
        expect(enable.hidden).toBe(true);
        expect(disable.hidden).toBe(false);
    });

    it('renders off when permissions are granted but the subscription is opted out', async () => {
        const { status, enable } = mountPushSettings();
        initializeOneSignal();
        const api = fakeOneSignal();
        setGrantedPermissions(api);
        api.User.PushSubscription.id = 'subscription-id';
        api.User.PushSubscription.token = 'push-token';
        api.User.PushSubscription.optedIn = false;

        await resolveSdk(api);

        expect(status.textContent).toBe('Notifications are off on this device.');
        expect(enable.hidden).toBe(false);
    });

    it('does not load the SDK and reports unavailable when config is absent', () => {
        const { status } = mountPushSettings(false);
        initializeOneSignal();

        expect(document.querySelector('script[data-onesignal-sdk]')).toBeNull();
        expect(status.textContent).toBe('Push notifications are unavailable.');
    });

    it('requests permission and opts in only after an explicit click', async () => {
        const { enable, disable, status } = mountPushSettings();
        initializeOneSignal();
        const api = fakeOneSignal();
        api.Notifications.requestPermission.mockImplementation(async () => setGrantedPermissions(api));
        api.User.PushSubscription.optIn.mockImplementation(async () => {
            api.User.PushSubscription.optedIn = true;
            api.User.PushSubscription.id = 'subscription-id';
            api.User.PushSubscription.token = 'push-token';
        });
        await resolveSdk(api);

        expect(api.User.PushSubscription.optIn).not.toHaveBeenCalled();
        expect(api.Notifications.requestPermission).not.toHaveBeenCalled();
        expect(api.User.PushSubscription.addEventListener).toHaveBeenCalledTimes(1);
        expect(status.textContent).toBe('Notifications are off on this device.');
        expect(enable.hidden).toBe(false);

        enable.click();
        await vi.waitFor(() => expect(status.textContent).toBe('Notifications are enabled on this device.'));

        expect(api.Notifications.requestPermission).toHaveBeenCalledTimes(1);
        expect(api.User.PushSubscription.optIn).toHaveBeenCalledTimes(1);
        expect(disable.hidden).toBe(false);
    });

    it('does not request permission again when the browser already grants it', async () => {
        const { enable, status } = mountPushSettings();
        initializeOneSignal();
        const api = fakeOneSignal();
        setGrantedPermissions(api);
        api.User.PushSubscription.optIn.mockImplementation(async () => {
            api.User.PushSubscription.optedIn = true;
        });
        await resolveSdk(api);

        enable.click();
        await vi.waitFor(() => expect(status.textContent).toBe('Notifications are enabled on this device.'));

        expect(api.Notifications.requestPermission).not.toHaveBeenCalled();
        expect(api.User.PushSubscription.optIn).toHaveBeenCalledTimes(1);
    });

    it('shows localized feedback when an explicit enable attempt fails', async () => {
        const { enable, error } = mountPushSettings();
        initializeOneSignal();
        const api = fakeOneSignal();
        api.Notifications.requestPermission.mockImplementation(async () => setGrantedPermissions(api));
        api.User.PushSubscription.optIn.mockRejectedValue(new Error('synthetic SDK error'));
        await resolveSdk(api);

        enable.click();
        await vi.waitFor(() => expect(error.hidden).toBe(false));

        expect(error.textContent).toBe('Could not update notifications on this device. Please try again.');
        expect(api.User.PushSubscription.optIn).toHaveBeenCalledTimes(1);
    });

    it('returns from checking and shows localized feedback when an SDK action never settles', async () => {
        const { enable, error, settings, status } = mountPushSettings();
        initializeOneSignal();
        const api = fakeOneSignal();
        setGrantedPermissions(api);
        api.User.PushSubscription.optIn.mockImplementation(() => new Promise<void>(() => {}));
        await resolveSdk(api);

        enable.click();
        expect(settings.getAttribute('aria-busy')).toBe('true');
        await vi.advanceTimersByTimeAsync(15000);
        await vi.waitFor(() => expect(error.hidden).toBe(false));

        expect(status.textContent).toBe('Notifications are off on this device.');
        expect(settings.getAttribute('aria-busy')).toBe('false');
        expect(error.textContent).toBe('Could not update notifications on this device. Please try again.');
    });

    it('uses granted permission and opted-in state while subscription identifiers hydrate', async () => {
        const { disable, status } = mountPushSettings();
        initializeOneSignal();
        const api = fakeOneSignal();
        setGrantedPermissions(api);
        api.User.PushSubscription.optedIn = true;
        api.User.PushSubscription.id = null;
        api.User.PushSubscription.token = null;
        await resolveSdk(api);

        expect(status.textContent).toBe('Notifications are enabled on this device.');
        expect(disable.hidden).toBe(false);
    });

    it('explains denied permission and hides opt-in until browser/site settings change', async () => {
        const { deniedHelp, enable, status } = mountPushSettings();
        initializeOneSignal();
        const api = fakeOneSignal();
        setBrowserPermission('denied');
        api.Notifications.permissionNative = 'denied';
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
        setGrantedPermissions(api);
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
        expect(Notification.permission).toBe('granted');
        expect(api.Notifications.permission).toBe(true);
        expect(api.Notifications.permissionNative).toBe('granted');
    });

    it('refreshes device status when the push subscription changes', async () => {
        const { status } = mountPushSettings();
        initializeOneSignal();
        const api = fakeOneSignal();
        await resolveSdk(api);

        expect(status.textContent).toBe('Notifications are off on this device.');
        expect(api.User.PushSubscription.addEventListener).toHaveBeenCalledWith('change', expect.any(Function));
        const subscriptionListener = api.User.PushSubscription.addEventListener.mock.calls[0][1] as () => void;
        setGrantedPermissions(api);
        api.User.PushSubscription.optedIn = true;
        api.User.PushSubscription.id = 'subscription-id';
        api.User.PushSubscription.token = 'push-token';

        subscriptionListener();
        await vi.waitFor(() => expect(status.textContent).toBe('Notifications are enabled on this device.'));
    });

    it('refreshes after authenticated login finishes and the SDK exposes device state', async () => {
        const { status } = mountPushSettings();
        initializeOneSignal();
        const api = fakeOneSignal();
        let completeLogin: (() => void) | undefined;
        api.login.mockImplementation(() => new Promise<void>((resolve) => {
            completeLogin = resolve;
        }));

        const bootstrap = resolveSdk(api);
        await vi.waitFor(() => expect(api.login).toHaveBeenCalledWith('skuysocial-user:24'));
        expect(status.textContent).toBe('Checking this device.');

        setGrantedPermissions(api);
        api.User.PushSubscription.optedIn = true;
        api.User.PushSubscription.id = 'subscription-id';
        api.User.PushSubscription.token = 'push-token';
        completeLogin?.();
        await bootstrap;

        expect(status.textContent).toBe('Notifications are enabled on this device.');
    });

    it('degrades gracefully when SDK initialization fails', async () => {
        const { status } = mountPushSettings();
        initializeOneSignal();
        const api = fakeOneSignal();
        api.init.mockRejectedValue(new Error('synthetic SDK failure'));

        await expect(resolveSdk(api)).resolves.toBeUndefined();
        expect(status.textContent).toBe('Push notifications are unavailable.');
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
