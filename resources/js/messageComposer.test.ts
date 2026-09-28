import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { initializeMessageComposers } from './messageComposer';

const originalScrollIntoView = Element.prototype.scrollIntoView;

function response(payload: unknown, status = 201, redirected = false): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        redirected,
        json: async () => payload,
    } as Response;
}

function mountComposer(): {
    form: HTMLFormElement;
    input: HTMLTextAreaElement;
    button: HTMLButtonElement;
    feedback: HTMLElement;
    history: HTMLOListElement;
} {
    document.head.innerHTML = '<meta name="csrf-token" content="chat-csrf">';
    document.body.innerHTML = `
        <main class="skuy-direct-chat-content">
            <ol data-message-history aria-live="polite">
                <li class="skuy-direct-chat-empty" role="status">No messages yet</li>
            </ol>
            <form
                action="/pesan/17"
                method="POST"
                data-message-composer
                data-conversation-id="17"
                data-sender-id="7"
                data-send-label="Send"
                data-sending-label="Sending…"
                data-sent-label="Message sent."
                data-session-expired="Your session expired."
                data-restricted="Messaging is restricted."
                data-validation="Check the message."
                data-failed="Message failed."
                data-network="Connection failed."
            >
                <input type="hidden" name="_token" value="chat-csrf">
                <textarea name="body" required aria-invalid="false"></textarea>
                <p data-message-feedback role="status" hidden></p>
                <button type="submit" data-message-submit>Send</button>
            </form>
        </main>
    `;

    const form = document.querySelector<HTMLFormElement>('[data-message-composer]')!;

    return {
        form,
        input: form.querySelector<HTMLTextAreaElement>('textarea[name="body"]')!,
        button: form.querySelector<HTMLButtonElement>('[data-message-submit]')!,
        feedback: form.querySelector<HTMLElement>('[data-message-feedback]')!,
        history: document.querySelector<HTMLOListElement>('[data-message-history]')!,
    };
}

function submit(form: HTMLFormElement): void {
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
}

function createdMessage(body: string) {
    return {
        message: {
            id: 91,
            conversation_id: 17,
            sender_id: 7,
            body,
            created_at: '2026-09-29T10:15:00+07:00',
            display_time: 'just now',
        },
    };
}

describe('direct-message composer progressive enhancement', () => {
    beforeEach(() => {
        vi.stubGlobal('fetch', vi.fn());
    });

    afterEach(() => {
        Object.defineProperty(Element.prototype, 'scrollIntoView', {
            configurable: true,
            writable: true,
            value: originalScrollIntoView,
        });
        vi.unstubAllGlobals();
        document.head.innerHTML = '';
        document.body.innerHTML = '';
    });

    it('positions a loaded conversation at its newest visible message', () => {
        const { form, history } = mountComposer();
        form.remove();
        const latest = document.createElement('li');
        latest.className = 'skuy-direct-chat-message-row skuy-direct-chat-message-row--other';
        const scrollIntoView = vi.fn();
        Object.defineProperty(latest, 'scrollIntoView', { value: scrollIntoView });
        history.append(latest);

        initializeMessageComposers(document);

        expect(scrollIntoView).toHaveBeenCalledWith({ behavior: 'auto', block: 'end' });
    });

    it('appends a confirmed message with safe text, preserves newlines, then clears and scrolls', async () => {
        const fetchMock = vi.mocked(fetch);
        const body = '<script>not markup</script>\nsecond line';
        fetchMock.mockResolvedValue(response(createdMessage(body)));
        const scrollIntoView = vi.fn();
        Element.prototype.scrollIntoView = scrollIntoView;
        const { form, input, button, feedback, history } = mountComposer();
        input.value = body;
        initializeMessageComposers(document);

        submit(form);

        await vi.waitFor(() => expect(history.querySelector('[data-message-id="91"]')).not.toBeNull());
        const appendedBody = history.querySelector('.skuy-direct-chat-message__body')!;
        const appendedTime = history.querySelector('time')!;

        expect(appendedBody.textContent).toBe(body);
        expect(appendedBody.querySelector('script')).toBeNull();
        expect(appendedTime.dateTime).toBe('2026-09-29T10:15:00+07:00');
        expect(appendedTime.textContent).toContain('just now');
        expect(input.value).toBe('');
        expect(button.disabled).toBe(false);
        expect(button.textContent).toBe('Send');
        expect(feedback.textContent).toBe('Message sent.');
        expect(feedback.hidden).toBe(false);
        expect(scrollIntoView).toHaveBeenCalledTimes(1);
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][0]).toBe('http://localhost:3000/pesan/17');
        expect(fetchMock.mock.calls[0][1]?.headers).toMatchObject({
            Accept: 'application/json',
            'X-CSRF-TOKEN': 'chat-csrf',
            'X-Requested-With': 'XMLHttpRequest',
        });
        expect(fetchMock.mock.calls[0][1]?.body).toBeInstanceOf(FormData);
        expect((fetchMock.mock.calls[0][1]?.body as FormData).get('body')).toBe(body);
    });

    it('keeps the draft and avoids scrolling when validation fails', async () => {
        const fetchMock = vi.mocked(fetch);
        fetchMock.mockResolvedValue(response({ errors: { body: ['Write a message before sending.'] } }, 422));
        const scrollIntoView = vi.fn();
        Element.prototype.scrollIntoView = scrollIntoView;
        const { form, input, button, feedback } = mountComposer();
        input.value = 'Keep this draft';
        initializeMessageComposers(document);

        submit(form);

        await vi.waitFor(() => expect(feedback.textContent).toBe('Write a message before sending.'));
        expect(input.value).toBe('Keep this draft');
        expect(input.getAttribute('aria-invalid')).toBe('true');
        expect(button.disabled).toBe(false);
        expect(button.textContent).toBe('Send');
        expect(scrollIntoView).not.toHaveBeenCalled();
    });

    it.each([
        [401, false, 'Your session expired.'],
        [419, false, 'Your session expired.'],
        [403, false, 'Messaging is restricted.'],
        [200, true, 'Your session expired.'],
        [500, false, 'Message failed.'],
    ])('shows the expected feedback for status %i and redirect=%s', async (status, redirected, message) => {
        const fetchMock = vi.mocked(fetch);
        fetchMock.mockResolvedValue(response({}, status, redirected));
        const { form, input, button, feedback, history } = mountComposer();
        input.value = 'Retain on server error';
        initializeMessageComposers(document);

        submit(form);

        await vi.waitFor(() => expect(feedback.textContent).toBe(message));
        expect(input.value).toBe('Retain on server error');
        expect(button.disabled).toBe(false);
        expect(history.querySelector('[data-message-id]')).toBeNull();
    });

    it('retains the draft and releases the pending state after a network error', async () => {
        const fetchMock = vi.mocked(fetch);
        fetchMock.mockRejectedValue(new TypeError('offline'));
        const { form, input, button, feedback } = mountComposer();
        input.value = 'Retain while offline';
        initializeMessageComposers(document);

        submit(form);

        await vi.waitFor(() => expect(feedback.textContent).toBe('Connection failed.'));
        expect(input.value).toBe('Retain while offline');
        expect(button.disabled).toBe(false);
        expect(button.textContent).toBe('Send');
    });

    it('prevents duplicate sends while the request is in flight', async () => {
        const fetchMock = vi.mocked(fetch);
        let resolveResponse!: (result: Response) => void;
        fetchMock.mockReturnValue(new Promise((resolve) => { resolveResponse = resolve; }));
        const { form, input, button, history } = mountComposer();
        input.value = 'Only once';
        initializeMessageComposers(document);

        submit(form);
        submit(form);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(button.disabled).toBe(true);
        expect(button.textContent).toBe('Sending…');
        expect(input.disabled).toBe(true);

        resolveResponse(response(createdMessage('Only once')));
        await vi.waitFor(() => expect(history.querySelector('[data-message-id="91"]')).not.toBeNull());
        expect(button.disabled).toBe(false);
        expect(input.disabled).toBe(false);
    });
});
