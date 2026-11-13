<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

const isOverlayVisible = ref(false);

let hideOverlayTimer: ReturnType<typeof setTimeout> | undefined;
let safetyResetTimer: ReturnType<typeof setTimeout> | undefined;
let overlayShownAt = 0;

const MIN_VISIBLE_MS = 380;

const clearTimers = (): void => {
    if (hideOverlayTimer) {
        clearTimeout(hideOverlayTimer);
        hideOverlayTimer = undefined;
    }
    if (safetyResetTimer) {
        clearTimeout(safetyResetTimer);
        safetyResetTimer = undefined;
    }
};

const startNavigationLoader = (): void => {
    clearTimers();
    overlayShownAt = Date.now();
    isOverlayVisible.value = true;

    safetyResetTimer = setTimeout(() => {
        isOverlayVisible.value = false;
        safetyResetTimer = undefined;
    }, 15000);
};

const finishNavigationLoader = (): void => {
    if (safetyResetTimer) {
        clearTimeout(safetyResetTimer);
        safetyResetTimer = undefined;
    }

    if (!isOverlayVisible.value) {
        return;
    }

    const elapsed = Date.now() - overlayShownAt;
    const remaining = Math.max(0, MIN_VISIBLE_MS - elapsed);

    if (hideOverlayTimer) {
        clearTimeout(hideOverlayTimer);
    }

    hideOverlayTimer = setTimeout(() => {
        isOverlayVisible.value = false;
        hideOverlayTimer = undefined;
    }, remaining);
};

const onNativeAnchorClick = (event: MouseEvent): void => {
    if (
        event.defaultPrevented ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey
    ) {
        return;
    }

    const target = event.target as HTMLElement | null;
    const anchor = target?.closest?.('a[href]') as HTMLAnchorElement | null;
    if (!anchor) {
        return;
    }

    if (
        anchor.hasAttribute('download') ||
        (anchor.target && anchor.target !== '_self') ||
        anchor.getAttribute('rel')?.includes('external')
    ) {
        return;
    }

    const rawHref = anchor.getAttribute('href')?.trim() || '';
    if (
        !rawHref ||
        rawHref.startsWith('#') ||
        rawHref.startsWith('javascript:') ||
        rawHref.startsWith('mailto:') ||
        rawHref.startsWith('tel:')
    ) {
        return;
    }

    try {
        const destUrl = new URL(anchor.href, window.location.origin);
        if (destUrl.origin !== window.location.origin) {
            return;
        }
        // Ignore pure same-page anchor jumps
        if (
            destUrl.pathname === window.location.pathname &&
            destUrl.search === window.location.search &&
            destUrl.hash
        ) {
            return;
        }

        startNavigationLoader();
    } catch {
        // Ignore invalid URLs
    }
};

let removeStartListener: (() => void) | undefined;
let removeFinishListener: (() => void) | undefined;
let removeCancelListener: (() => void) | undefined;
let removeErrorListener: (() => void) | undefined;
let removeExceptionListener: (() => void) | undefined;
let removeInvalidListener: (() => void) | undefined;

onMounted(() => {
    document.addEventListener('click', onNativeAnchorClick, false);

    removeStartListener = router.on('start', (event) => {
        const visit = event.detail.visit as {
            prefetch?: boolean;
            method?: string;
            url?: URL | string;
        };

        if (visit?.prefetch) {
            return;
        }

        const method = String(visit?.method || 'get').toLowerCase();
        let pathname = '';
        try {
            if (typeof visit?.url === 'string') {
                pathname = new URL(visit.url, window.location.origin).pathname;
            } else if (visit?.url && typeof visit.url.pathname === 'string') {
                pathname = visit.url.pathname;
            }
        } catch {
            pathname = '';
        }

        if (method !== 'get' && pathname.includes('/site-visits/track')) {
            return;
        }

        startNavigationLoader();
    });

    removeFinishListener = router.on('finish', (event) => {
        const visit = event.detail.visit as { prefetch?: boolean } | undefined;
        if (visit?.prefetch) {
            return;
        }

        finishNavigationLoader();
    });

    removeCancelListener = router.on('cancel', () => {
        finishNavigationLoader();
    });

    removeErrorListener = router.on('error', () => {
        finishNavigationLoader();
    });

    removeExceptionListener = router.on('networkError', () => {
        finishNavigationLoader();
    });

    removeInvalidListener = router.on('httpException', () => {
        finishNavigationLoader();
    });
});

onUnmounted(() => {
    clearTimers();
    document.removeEventListener('click', onNativeAnchorClick, false);
    removeStartListener?.();
    removeFinishListener?.();
    removeCancelListener?.();
    removeErrorListener?.();
    removeExceptionListener?.();
    removeInvalidListener?.();
});
</script>

<template>
    <Teleport to="body">
        <Transition name="kb-cart-like-backdrop">
            <div
                v-if="isOverlayVisible"
                class="kb-page-loading-backdrop"
                role="status"
                aria-live="polite"
                aria-label="Đang tải trang"
            >
                <div class="kb-page-spinner-center">
                    <div class="kb-page-spinner-box">
                        <svg
                            class="kb-page-spinner-svg"
                            viewBox="0 0 64 64"
                            fill="none"
                            aria-hidden="true"
                        >
                            <circle
                                class="kb-page-spinner-track"
                                cx="32"
                                cy="32"
                                r="25"
                                stroke-width="4.5"
                            />
                            <circle
                                class="kb-page-spinner-arc"
                                cx="32"
                                cy="32"
                                r="25"
                                stroke-width="4.5"
                                stroke-linecap="round"
                            />
                        </svg>
                        <FontAwesomeIcon
                            :icon="['fas', 'spinner']"
                            class="kb-page-spinner-fa-icon"
                        />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style>
.kb-page-loading-backdrop {
    position: fixed;
    inset: 0;
    z-index: 99995;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #06140f80;
    backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
    pointer-events: auto;
    transition: opacity 0.28s ease-out !important;
}

.kb-page-loading-backdrop.kb-cart-like-backdrop-enter-from,
.kb-page-loading-backdrop.kb-cart-like-backdrop-leave-to {
    opacity: 0;
}

.kb-page-loading-backdrop.kb-cart-like-backdrop-enter-active,
.kb-page-loading-backdrop.kb-cart-like-backdrop-leave-active {
    transition: opacity 0.28s ease !important;
}

.kb-page-spinner-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    transform: translateZ(0);
}

.kb-page-spinner-box {
    position: relative;
    width: 74px;
    height: 74px;
    border-radius: 50%;
    background: rgba(23, 39, 30, 0.82);
    border: 1px solid rgba(183, 154, 99, 0.45);
    box-shadow:
        0 16px 38px rgba(0, 0, 0, 0.32),
        0 0 0 6px rgba(255, 255, 255, 0.08);
    display: flex;
    align-items: center;
    justify-content: center;
}

.kb-page-spinner-svg {
    position: absolute;
    inset: 4px;
    width: calc(100% - 8px);
    height: calc(100% - 8px);
    animation: kbPageSpinnerRotate 0.85s linear infinite !important;
}

.kb-page-spinner-track {
    stroke: rgba(223, 205, 169, 0.18);
}

.kb-page-spinner-arc {
    stroke: #b79a63;
    stroke-dasharray: 95 160;
    stroke-dashoffset: 0;
}

.kb-page-spinner-fa-icon {
    font-size: 22px;
    color: #f2efe5;
    animation: kbPageSpinnerRotate 1.1s linear infinite !important;
}

@keyframes kbPageSpinnerRotate {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .kb-page-loading-backdrop,
    .kb-page-loading-backdrop.kb-cart-like-backdrop-enter-active,
    .kb-page-loading-backdrop.kb-cart-like-backdrop-leave-active {
        transition: opacity 0.28s ease !important;
    }

    .kb-page-spinner-svg {
        animation: kbPageSpinnerRotate 0.85s linear infinite !important;
    }

    .kb-page-spinner-fa-icon {
        animation: kbPageSpinnerRotate 1.1s linear infinite !important;
    }
}
</style>
