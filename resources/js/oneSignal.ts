type PermissionState = 'default' | 'granted' | 'denied';
type PushState = 'checking' | 'unavailable' | 'unsupported' | 'denied' | 'notRequested' | 'enabled';

interface PushSubscriptionApi {
    id?: string | null;
    token?: string | null;
    optedIn?: boolean;
    optIn?: () => Promise<unknown> | void;
    optOut?: () => Promise<unknown> | void;
    addEventListener?: (event: 'change', listener: () => void) => void;
}

interface OneSignalApi {
    init: (options: {
        appId: string;
        serviceWorkerPath: string;
        serviceWorkerParam: { scope: string };
    }) => Promise<void> | void;
    login: (externalId: string) => Promise<void> | void;
    logout: () => Promise<void> | void;
    Notifications: {
        permission: boolean;
        permissionNative: PermissionState;
        isPushSupported: () => boolean | Promise<boolean>;
        requestPermission: () => Promise<unknown> | void;
        addEventListener?: (event: 'permissionChange', listener: () => void) => void;
    };
    User: {
        PushSubscription: PushSubscriptionApi;
    };
}

type OneSignalDeferredWindow = Window & {
    OneSignalDeferred?: Array<(oneSignal: OneSignalApi) => Promise<void>>;
};

const SDK_URL = 'https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js';
const SDK_LOAD_TIMEOUT_MS = 8000;
const PUSH_ACTION_TIMEOUT_MS = 15000;
const LOGOUT_TIMEOUT_MS = 600;

export function initializeOneSignal(documentRef: Document = document): void {
    const bootstrap = documentRef.querySelector<HTMLElement>('[data-onesignal-bootstrap]');
    const settings = Array.from(documentRef.querySelectorAll<HTMLElement>('[data-push-settings]'));
    let oneSignal: OneSignalApi | null = null;
    let logoutRequested = false;
    let resolveReady: () => void = () => {};
    let readySettled = false;
    const ready = new Promise<void>((resolve) => {
        resolveReady = resolve;
    });

    const finishReady = (): void => {
        if (readySettled) {
            return;
        }

        readySettled = true;
        resolveReady();
    };

    bindLogoutForms(documentRef, () => oneSignal, ready, () => {
        logoutRequested = true;
    });

    const appId = bootstrap?.dataset.appId?.trim() ?? '';
    const externalId = bootstrap?.dataset.externalId?.trim() ?? '';

    if (!bootstrap || !appId || !externalId) {
        renderAll(settings, 'unavailable');
        finishReady();

        return;
    }

    if (bootstrap.dataset.onesignalInitialized === 'true') {
        return;
    }

    bootstrap.dataset.onesignalInitialized = 'true';
    renderAll(settings, 'checking');

    const browserWindow = documentRef.defaultView as OneSignalDeferredWindow | null;

    if (!browserWindow || !documentRef.head) {
        renderAll(settings, 'unavailable');
        finishReady();

        return;
    }

    let loadTimer: number | undefined;
    const markUnavailable = (): void => {
        if (loadTimer !== undefined) {
            browserWindow.clearTimeout(loadTimer);
            loadTimer = undefined;
        }

        renderAll(settings, 'unavailable');
        finishReady();
    };

    loadTimer = browserWindow.setTimeout(markUnavailable, SDK_LOAD_TIMEOUT_MS);

    const deferred = browserWindow.OneSignalDeferred ?? [];
    deferred.push(async (sdk: OneSignalApi): Promise<void> => {
        oneSignal = sdk;

        try {
            await sdk.init({
                appId,
                serviceWorkerPath: 'onesignal/OneSignalSDKWorker.js',
                serviceWorkerParam: { scope: '/onesignal/' },
            });

            if (!logoutRequested) {
                await sdk.login(externalId);
            }

            if (loadTimer !== undefined) {
                browserWindow.clearTimeout(loadTimer);
                loadTimer = undefined;
            }

            finishReady();

            if (logoutRequested) {
                return;
            }

            bindPushEvents(sdk, settings);
            await refreshAll(settings, sdk);
        } catch {
            markUnavailable();
        }
    });
    browserWindow.OneSignalDeferred = deferred;

    const script = documentRef.createElement('script');
    script.async = true;
    script.src = SDK_URL;
    script.dataset.onesignalSdk = 'true';
    script.addEventListener('error', markUnavailable, { once: true });
    documentRef.head.appendChild(script);
}

function bindLogoutForms(
    documentRef: Document,
    getOneSignal: () => OneSignalApi | null,
    sdkReady: Promise<void>,
    onLogoutRequested: () => void,
): void {
    for (const form of documentRef.querySelectorAll<HTMLFormElement>('form[data-onesignal-logout]')) {
        if (form.dataset.onesignalLogoutBound === 'true') {
            continue;
        }

        form.dataset.onesignalLogoutBound = 'true';
        form.addEventListener('submit', (event) => {
            event.preventDefault();

            if (form.dataset.onesignalLogoutPending === 'true') {
                return;
            }

            form.dataset.onesignalLogoutPending = 'true';
            onLogoutRequested();

            void submitAfterLogoutBestEffort(form, getOneSignal, sdkReady);
        });
    }
}

async function submitAfterLogoutBestEffort(
    form: HTMLFormElement,
    getOneSignal: () => OneSignalApi | null,
    sdkReady: Promise<void>,
): Promise<void> {
    let timeout: number | undefined;
    const timeoutPromise = new Promise<void>((resolve) => {
        timeout = window.setTimeout(resolve, LOGOUT_TIMEOUT_MS);
    });

    try {
        await Promise.race([
            (async () => {
                await sdkReady;
                await getOneSignal()?.logout();
            })(),
            timeoutPromise,
        ]);
    } catch {
        // OneSignal cleanup is best-effort; the Laravel logout form must still submit.
    } finally {
        if (timeout !== undefined) {
            window.clearTimeout(timeout);
        }

        form.submit();
    }
}

function bindPushEvents(oneSignal: OneSignalApi, settings: HTMLElement[]): void {
    const refresh = (): void => {
        void refreshAll(settings, oneSignal);
    };

    try {
        oneSignal.Notifications.addEventListener?.('permissionChange', refresh);
    } catch {
        // Permission events are optional; the visible controls still refresh after each action.
    }

    try {
        oneSignal.User.PushSubscription.addEventListener?.('change', refresh);
    } catch {
        // Subscription events are optional; the visible controls still refresh after each action.
    }
}

async function refreshAll(settings: HTMLElement[], oneSignal: OneSignalApi): Promise<void> {
    await Promise.all(settings.map((section) => refreshSettings(section, oneSignal)));
}

async function readPushDeviceState(oneSignal: OneSignalApi): Promise<PushState> {
    const pushSupported = await Promise.resolve(oneSignal.Notifications.isPushSupported());

    if (!pushSupported || typeof Notification === 'undefined') {
        return 'unsupported';
    }

    const browserPermission = Notification.permission;
    const sdkNativePermission = oneSignal.Notifications.permissionNative;

    if (browserPermission === 'denied' || sdkNativePermission === 'denied') {
        return 'denied';
    }

    const permissionsGranted = browserPermission === 'granted'
        && oneSignal.Notifications.permission === true
        && sdkNativePermission === 'granted';

    return permissionsGranted && oneSignal.User.PushSubscription.optedIn === true
        ? 'enabled'
        : 'notRequested';
}

async function refreshSettings(section: HTMLElement, oneSignal: OneSignalApi): Promise<PushState> {
    const refreshVersion = (Number(section.dataset.pushRefreshVersion) || 0) + 1;
    const refreshKey = String(refreshVersion);
    section.dataset.pushRefreshVersion = refreshKey;
    section.setAttribute('aria-busy', 'true');
    renderActionError(section, false);

    const isLatestRefresh = (): boolean => section.dataset.pushRefreshVersion === refreshKey;

    try {
        const state = await readPushDeviceState(oneSignal);

        if (!isLatestRefresh()) {
            return (section.dataset.pushState as PushState | undefined) ?? 'checking';
        }

        renderState(section, state);
        updateControls(section, oneSignal.User.PushSubscription, state, oneSignal);

        return state;
    } catch {
        if (!isLatestRefresh()) {
            return (section.dataset.pushState as PushState | undefined) ?? 'checking';
        }

        renderState(section, 'unavailable');
        updateControls(section, null, 'unavailable', null);

        return 'unavailable';
    } finally {
        if (isLatestRefresh()) {
            section.setAttribute('aria-busy', 'false');
        }
    }
}

function hasNativeNotificationPermission(): boolean {
    return typeof Notification !== 'undefined' && Notification.permission === 'granted';
}

async function runSettingsAction(
    section: HTMLElement,
    button: HTMLButtonElement,
    oneSignal: OneSignalApi,
    expectedState: 'enabled' | 'notRequested',
    action: () => Promise<unknown> | void,
): Promise<void> {
    section.dataset.pushActionPending = 'true';
    button.disabled = true;
    section.setAttribute('aria-busy', 'true');
    renderState(section, 'checking');
    renderActionError(section, false);

    let actionSucceeded = true;
    let timeout: number | undefined;
    const timeoutPromise = new Promise<never>((_resolve, reject) => {
        timeout = window.setTimeout(() => reject(new Error('Push settings action timed out.')), PUSH_ACTION_TIMEOUT_MS);
    });

    try {
        await Promise.race([Promise.resolve().then(action), timeoutPromise]);
    } catch {
        actionSucceeded = false;
    } finally {
        if (timeout !== undefined) {
            window.clearTimeout(timeout);
        }

        delete section.dataset.pushActionPending;
    }

    const state = await refreshSettings(section, oneSignal);
    const actionNeedsFeedback = !actionSucceeded || state !== expectedState;
    const stateHasSpecificFeedback = state === 'denied' || state === 'unsupported' || state === 'unavailable';

    if (actionNeedsFeedback && !stateHasSpecificFeedback) {
        renderActionError(section, true);
    }
}

function updateControls(
    section: HTMLElement,
    subscription: PushSubscriptionApi | null,
    state: PushState,
    oneSignal: OneSignalApi | null,
): void {
    const enable = section.querySelector<HTMLButtonElement>('[data-push-enable]');
    const disable = section.querySelector<HTMLButtonElement>('[data-push-disable]');
    const deniedHelp = section.querySelector<HTMLElement>('[data-push-denied-help]');
    const actionPending = section.dataset.pushActionPending === 'true';

    if (deniedHelp) {
        deniedHelp.hidden = state !== 'denied';
    }

    if (enable) {
        const canEnable = state === 'notRequested'
            && typeof subscription?.optIn === 'function'
            && oneSignal !== null;
        enable.hidden = !canEnable;
        enable.disabled = actionPending;
        enable.onclick = canEnable ? () => {
            void runSettingsAction(section, enable, oneSignal, 'enabled', async () => {
                if (!hasNativeNotificationPermission()) {
                    await oneSignal.Notifications.requestPermission();
                }

                await subscription.optIn?.();
            });
        } : null;
    }

    if (disable) {
        const canDisable = state === 'enabled'
            && typeof subscription?.optOut === 'function'
            && oneSignal !== null;
        disable.hidden = !canDisable;
        disable.disabled = actionPending;
        disable.onclick = canDisable ? () => {
            void runSettingsAction(section, disable, oneSignal, 'notRequested', () => subscription.optOut?.());
        } : null;
    }
}

function renderAll(settings: HTMLElement[], state: PushState): void {
    for (const section of settings) {
        renderState(section, state);
        renderActionError(section, false);
        updateControls(section, null, state, null);
        section.setAttribute('aria-busy', 'false');
    }
}

function renderActionError(section: HTMLElement, visible: boolean): void {
    const error = section.querySelector<HTMLElement>('[data-push-action-error]');

    if (!error) {
        return;
    }

    error.textContent = visible ? section.dataset.stateActionFailed ?? '' : '';
    error.hidden = !visible;
}

function renderState(section: HTMLElement, state: PushState): void {
    const status = section.querySelector<HTMLElement>('[data-push-status]');
    const deniedHelp = section.querySelector<HTMLElement>('[data-push-denied-help]');
    const label = section.dataset[`state${state[0].toUpperCase()}${state.slice(1)}`];

    if (status) {
        status.textContent = label ?? '';
    }

    if (deniedHelp) {
        deniedHelp.hidden = state !== 'denied';
    }

    section.dataset.pushState = state;
}
