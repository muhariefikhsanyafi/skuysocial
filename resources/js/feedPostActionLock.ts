type FeedAction = 'like' | 'repost' | 'bookmark';

interface ButtonState {
    button: HTMLButtonElement;
    disabled: boolean;
    ariaBusy: string | null;
    pendingMarker: string | null;
}

export function acquireFeedPostActionLock(postUid: string, action: FeedAction): (() => void) | null {
    const matchingCards = Array.from(document.querySelectorAll<HTMLElement>('article[data-feed-post-uid]'))
        .filter((card) => card.getAttribute('data-feed-post-uid') === postUid);
    const buttons = matchingCards
        .map((card) => card.querySelector<HTMLButtonElement>(`button[data-feed-post-action="${action}"]`))
        .filter((button): button is HTMLButtonElement => button !== null);

    if (buttons.some((button) => button.getAttribute('data-feed-action-pending') === 'true')) {
        return null;
    }

    const previousStates: ButtonState[] = buttons.map((button) => ({
        button,
        disabled: button.disabled,
        ariaBusy: button.getAttribute('aria-busy'),
        pendingMarker: button.getAttribute('data-feed-action-pending'),
    }));

    for (const button of buttons) {
        button.setAttribute('data-feed-action-pending', 'true');
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
    }

    let released = false;

    return () => {
        if (released) return;
        released = true;

        for (const previous of previousStates) {
            if (previous.pendingMarker === null) {
                previous.button.removeAttribute('data-feed-action-pending');
            } else {
                previous.button.setAttribute('data-feed-action-pending', previous.pendingMarker);
            }

            previous.button.disabled = previous.disabled;
            if (previous.ariaBusy === null) {
                previous.button.removeAttribute('aria-busy');
            } else {
                previous.button.setAttribute('aria-busy', previous.ariaBusy);
            }
        }
    };
}
