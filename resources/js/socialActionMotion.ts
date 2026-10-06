export type SocialActionMotion =
    | 'like'
    | 'like-release'
    | 'appreciation'
    | 'bookmark-save'
    | 'bookmark-remove'
    | 'repost'
    | 'share';

const motions: Record<SocialActionMotion, { className: string; durationMs: number }> = {
    like: { className: 'skuy-social-motion--like', durationMs: 220 },
    'like-release': { className: 'skuy-social-motion--like-release', durationMs: 180 },
    appreciation: { className: 'skuy-social-motion--appreciation', durationMs: 230 },
    'bookmark-save': { className: 'skuy-social-motion--bookmark-save', durationMs: 210 },
    'bookmark-remove': { className: 'skuy-social-motion--bookmark-remove', durationMs: 180 },
    repost: { className: 'skuy-social-motion--repost', durationMs: 210 },
    share: { className: 'skuy-social-motion--share', durationMs: 170 },
};

const activeMotions = new WeakMap<Element, { cancel: () => void }>();

export function playSocialActionMotion(
    action: HTMLElement | null | undefined,
    motion: SocialActionMotion,
): Promise<void> {
    if (!action || typeof window === 'undefined') return Promise.resolve();

    const icon = Array.from(action.children).find((child) => child.matches('svg, .skuy-fa-icon'));
    if (!icon || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
        return Promise.resolve();
    }

    const { className, durationMs } = motions[motion];
    activeMotions.get(icon)?.cancel();
    icon.classList.remove(className);
    void icon.getBoundingClientRect().width;

    return new Promise((resolve) => {
        let finished = false;
        let fallbackTimer = 0;

        const finish = (event?: Event) => {
            if (finished || (event && event.target !== icon)) return;
            finished = true;
            icon.classList.remove(className);
            icon.removeEventListener('animationend', onAnimationEnd);
            icon.removeEventListener('animationcancel', onAnimationEnd);
            window.clearTimeout(fallbackTimer);
            activeMotions.delete(icon);
            resolve();
        };
        const onAnimationEnd = (event: Event) => finish(event);

        activeMotions.set(icon, { cancel: () => finish() });
        icon.addEventListener('animationend', onAnimationEnd);
        icon.addEventListener('animationcancel', onAnimationEnd);
        icon.classList.add(className);
        fallbackTimer = window.setTimeout(finish, durationMs + 60);
    });
}
