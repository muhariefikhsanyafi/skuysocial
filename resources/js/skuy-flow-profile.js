const PROFILE_MENU_GAP = 8;

function finiteNumber(value, fallback = 0) {
    return Number.isFinite(value) ? value : fallback;
}

export function chooseProfilePostMenuPlacement({
    triggerTop,
    triggerBottom,
    menuHeight,
    safeBottom,
}) {
    const top = finiteNumber(triggerTop);
    const bottom = Math.max(top, finiteNumber(triggerBottom, top));
    const height = Math.max(0, finiteNumber(menuHeight));
    const lowerBoundary = Math.max(0, finiteNumber(safeBottom));
    const aboveHeight = Math.max(0, Math.floor(top - (PROFILE_MENU_GAP * 2)));
    const belowHeight = Math.max(0, Math.floor(lowerBoundary - bottom - PROFILE_MENU_GAP));
    const aboveFits = height <= aboveHeight;
    const belowFits = height <= belowHeight;

    let placement;
    if (aboveFits && belowFits) {
        placement = belowHeight >= aboveHeight ? 'below' : 'above';
    } else if (aboveFits) {
        placement = 'above';
    } else if (belowFits) {
        placement = 'below';
    } else {
        placement = belowHeight > aboveHeight ? 'below' : 'above';
    }

    const availableHeight = placement === 'above' ? aboveHeight : belowHeight;

    return {
        placement,
        availableHeight,
        needsScroll: height > availableHeight,
    };
}

function initializeProfilePostMenuPlacement() {
    if (typeof document === 'undefined' || typeof window === 'undefined') return;
    if (document.documentElement?.dataset.flowPage !== 'profile') return;

    const menuSelector = '.skuy-post__options > [role="menu"]';
    let animationFrame = 0;

    const syncOpenMenus = () => {
        animationFrame = 0;

        document.querySelectorAll(menuSelector).forEach((menu) => {
            if (window.getComputedStyle(menu).display === 'none') return;

            const options = menu.closest('.skuy-post__options');
            const trigger = options?.querySelector('button[aria-haspopup="menu"]');
            if (!trigger) return;

            const triggerRect = trigger.getBoundingClientRect();
            const menuRect = menu.getBoundingClientRect();
            const dock = document.querySelector('.skuy-mobile-nav');
            const dockStyle = dock ? window.getComputedStyle(dock) : null;
            const dockRect = dock?.getBoundingClientRect();
            const dockIsVisible = Boolean(
                dockRect
                && dockStyle?.display !== 'none'
                && dockStyle?.visibility !== 'hidden'
                && dockRect.height > 0
                && dockRect.top < window.innerHeight,
            );
            const safeBottom = dockIsVisible
                ? dockRect.top
                : window.innerHeight - PROFILE_MENU_GAP;
            const placement = chooseProfilePostMenuPlacement({
                triggerTop: triggerRect.top,
                triggerBottom: triggerRect.bottom,
                menuHeight: Math.max(menuRect.height, menu.scrollHeight),
                safeBottom,
            });

            menu.dataset.flowProfilePlacement = placement.placement;
            menu.style.setProperty('--flow-profile-menu-max-height', `${placement.availableHeight}px`);
            menu.dataset.flowProfileScrollable = placement.needsScroll ? 'true' : 'false';
        });
    };

    const scheduleSync = () => {
        if (animationFrame) return;
        animationFrame = window.requestAnimationFrame(syncOpenMenus);
    };

    const menuVisibility = new WeakMap();
    const menuObserver = new MutationObserver((mutations) => {
        let menuOpened = false;

        mutations.forEach(({ target }) => {
            if (!(target instanceof Element)) return;

            const isVisible = window.getComputedStyle(target).display !== 'none';
            const wasVisible = menuVisibility.get(target) ?? false;
            menuVisibility.set(target, isVisible);
            if (isVisible && !wasVisible) menuOpened = true;
        });

        if (menuOpened) scheduleSync();
    });

    document.querySelectorAll(menuSelector).forEach((menu) => {
        menuVisibility.set(menu, window.getComputedStyle(menu).display !== 'none');
        menuObserver.observe(menu, { attributes: true, attributeFilter: ['style'] });
    });

    document.addEventListener('click', (event) => {
        if (event.target instanceof Element && event.target.closest('.skuy-post__options button[aria-haspopup="menu"]')) {
            scheduleSync();
        }
    }, true);
    window.addEventListener('resize', scheduleSync, { passive: true });
    window.addEventListener('scroll', scheduleSync, { capture: true, passive: true });
    window.addEventListener('orientationchange', scheduleSync, { passive: true });
}

initializeProfilePostMenuPlacement();
