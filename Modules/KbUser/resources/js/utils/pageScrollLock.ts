let activeLocks = 0;
let savedState: { scrollY: number; htmlOverflowY: string; bodyStyle: string } | null = null;

/*
 * Freezes the page with a fixed body while keeping the root scrollbar visible,
 * so the layout width does not change when a modal or drawer opens.
 */
export const lockPageScroll = (): (() => void) => {
    if (typeof document === 'undefined') {
        return () => {};
    }

    if (activeLocks === 0) {
        const root = document.documentElement;
        const body = document.body;

        savedState = {
            scrollY: window.scrollY,
            htmlOverflowY: root.style.overflowY,
            bodyStyle: body.getAttribute('style') ?? '',
        };
        root.style.overflowY = 'scroll';
        body.style.position = 'fixed';
        body.style.top = `-${savedState.scrollY}px`;
        body.style.left = '0';
        body.style.right = '0';
        body.style.width = '100%';
    }

    activeLocks += 1;
    let isReleased = false;

    return () => {
        if (isReleased) {
            return;
        }

        isReleased = true;
        activeLocks -= 1;

        if (activeLocks === 0 && savedState) {
            const { scrollY, htmlOverflowY, bodyStyle } = savedState;
            const root = document.documentElement;
            const previousBehavior = root.style.scrollBehavior;

            root.style.overflowY = htmlOverflowY;
            if (bodyStyle) {
                document.body.setAttribute('style', bodyStyle);
            } else {
                document.body.removeAttribute('style');
            }
            root.style.scrollBehavior = 'auto';
            window.scrollTo(0, scrollY);
            root.style.scrollBehavior = previousBehavior;
            savedState = null;
        }
    };
};
