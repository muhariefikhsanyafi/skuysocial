interface DirectMessage {
    id: number;
    conversation_id: number;
    sender_id: number;
    body: string;
    created_at: string;
    display_time: string;
}

interface DirectMessageResponse {
    message?: unknown;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function isPositiveInteger(value: unknown): value is number {
    return Number.isSafeInteger(value) && typeof value === 'number' && value > 0;
}

function isDirectMessage(value: unknown, conversationId: number, senderId: number): value is DirectMessage {
    if (!isRecord(value)) return false;

    return isPositiveInteger(value.id)
        && value.conversation_id === conversationId
        && value.sender_id === senderId
        && typeof value.body === 'string'
        && typeof value.created_at === 'string'
        && Number.isFinite(Date.parse(value.created_at))
        && typeof value.display_time === 'string';
}

function validationMessage(payload: unknown): string | null {
    if (!isRecord(payload) || !isRecord(payload.errors)) return null;

    const bodyErrors = payload.errors.body;

    return Array.isArray(bodyErrors) && typeof bodyErrors[0] === 'string'
        ? bodyErrors[0]
        : null;
}

function appendMessage(history: HTMLOListElement, message: DirectMessage): HTMLElement {
    history.querySelector('.skuy-direct-chat-empty')?.remove();

    const row = document.createElement('li');
    row.classList.add('skuy-direct-chat-message-row', 'skuy-direct-chat-message-row--own');

    const article = document.createElement('article');
    article.className = 'skuy-direct-chat-message';
    article.dataset.messageId = String(message.id);

    const body = document.createElement('p');
    body.className = 'skuy-direct-chat-message__body';
    body.textContent = message.body;

    const time = document.createElement('time');
    time.className = 'skuy-direct-chat-message__time';
    time.dateTime = message.created_at;
    time.textContent = message.display_time;

    article.append(body, time);
    row.append(article);
    history.append(row);

    return row;
}

function bindComposer(form: HTMLFormElement): void {
    if (form.dataset.messageComposerBound === 'true') return;

    const input = form.querySelector<HTMLTextAreaElement>('textarea[name="body"]');
    const submitButton = form.querySelector<HTMLButtonElement>('[data-message-submit]');
    const feedback = form.querySelector<HTMLElement>('[data-message-feedback]');
    const content = form.closest('.skuy-direct-chat-content');
    const history = content?.querySelector<HTMLOListElement>('[data-message-history]');

    if (!input || !submitButton || !feedback || !history) return;

    form.dataset.messageComposerBound = 'true';
    let inFlight = false;

    const setFeedback = (message: string, invalid = false): void => {
        feedback.textContent = message;
        feedback.hidden = message.length === 0;
        input.setAttribute('aria-invalid', invalid ? 'true' : 'false');
    };

    input.addEventListener('input', () => {
        if (!feedback.hidden) setFeedback('');
        else input.setAttribute('aria-invalid', 'false');
    });

    form.addEventListener('submit', async (event: SubmitEvent) => {
        event.preventDefault();

        if (inFlight || !form.reportValidity()) return;

        const formData = new FormData(form);
        const sendLabel = form.dataset.sendLabel ?? submitButton.textContent ?? '';
        inFlight = true;
        setFeedback('');
        form.setAttribute('aria-busy', 'true');
        input.disabled = true;
        submitButton.disabled = true;
        submitButton.textContent = form.dataset.sendingLabel ?? sendLabel;

        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
            let response: Response;

            try {
                response = await fetch(form.action, {
                    method: form.method.toUpperCase(),
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                });
            } catch {
                setFeedback(form.dataset.network ?? '');
                return;
            }

            if (response.redirected || response.status === 401 || response.status === 419) {
                setFeedback(form.dataset.sessionExpired ?? '');
                return;
            }

            if (response.status === 403) {
                setFeedback(form.dataset.restricted ?? '');
                return;
            }

            const payload = await response.json().catch(() => null) as DirectMessageResponse | null;

            if (response.status === 422) {
                setFeedback(
                    validationMessage(payload) ?? form.dataset.validation ?? '',
                    true,
                );
                return;
            }

            if (!response.ok) {
                setFeedback(form.dataset.failed ?? '');
                return;
            }

            const conversationId = Number(form.dataset.conversationId);
            const senderId = Number(form.dataset.senderId);

            if (
                !isPositiveInteger(conversationId)
                || !isPositiveInteger(senderId)
                || !isRecord(payload)
                || !isDirectMessage(payload.message, conversationId, senderId)
            ) {
                setFeedback(form.dataset.failed ?? '');
                return;
            }

            const newMessage = appendMessage(history, payload.message);
            input.value = '';
            newMessage.scrollIntoView?.({ behavior: 'smooth', block: 'end' });
            setFeedback(form.dataset.sentLabel ?? '');
        } catch {
            setFeedback(form.dataset.failed ?? '');
        } finally {
            inFlight = false;
            form.removeAttribute('aria-busy');
            input.disabled = false;
            submitButton.disabled = false;
            submitButton.textContent = sendLabel;
        }
    });
}

export function initializeMessageComposers(root: ParentNode = document): void {
    root.querySelectorAll<HTMLOListElement>('[data-message-history]').forEach((history) => {
        const latestMessage = history.querySelector<HTMLElement>('.skuy-direct-chat-message-row:last-child');
        latestMessage?.scrollIntoView?.({ behavior: 'auto', block: 'end' });
    });

    root.querySelectorAll<HTMLFormElement>('[data-message-composer]').forEach(bindComposer);
}
