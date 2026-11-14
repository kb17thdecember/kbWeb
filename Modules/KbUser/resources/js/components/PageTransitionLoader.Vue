<script setup lang="ts">
import { faSpinner } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

const byPrefixAndName = {
    fas: {
        spinner: faSpinner,
    },
};

const isOverlayVisible = ref(false);

let showOverlayTimer: ReturnType<typeof setTimeout> | undefined;

const SHOW_DELAY_MS = 80;

const clearShowTimer = (): void => {
    if (showOverlayTimer) {
        clearTimeout(showOverlayTimer);
        showOverlayTimer = undefined;
    }
};

const startNavigationLoader = (): void => {
    clearShowTimer();
    showOverlayTimer = setTimeout(() => {
        isOverlayVisible.value = true;
        showOverlayTimer = undefined;
    }, SHOW_DELAY_MS);
};

const finishNavigationLoader = (): void => {
    clearShowTimer();
    isOverlayVisible.value = false;
};

let removeStartListener: (() => void) | undefined;
let removeFinishListener: (() => void) | undefined;
let removeCancelListener: (() => void) | undefined;
let removeErrorListener: (() => void) | undefined;
let removeExceptionListener: (() => void) | undefined;
let removeInvalidListener: (() => void) | undefined;

onMounted(() => {
    removeStartListener = router.on('start', (event) => {
        const visit = event.detail.visit as {
            prefetch?: boolean;
            async?: boolean;
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
    clearShowTimer();
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
        <Transition name="cart-backdrop">
            <div
                v-if="isOverlayVisible"
                class="overlay open kb-page-loading-overlay"
                role="status"
                aria-live="polite"
                aria-label="Đang tải trang"
            >
                <FontAwesomeIcon
                    :icon="byPrefixAndName.fas['spinner']"
                    class="kb-page-spinner-fa-icon"
                />
            </div>
        </Transition>
    </Teleport>
</template>

<style>
.kb-page-loading-overlay {
    z-index: 99995;
    display: flex;
    align-items: center;
    justify-content: center;
}

.kb-page-loading-overlay.cart-backdrop-enter-active {
    transition: opacity 0.18s ease !important;
}

.kb-page-loading-overlay.cart-backdrop-leave-active {
    transition: opacity 0.12s ease-out !important;
}

.kb-page-spinner-fa-icon {
    font-size: 32px;
    color: var(--champ, #dfcda9);
    animation: kbPageSpinnerRotate 0.9s linear infinite !important;
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
    .kb-page-spinner-fa-icon {
        animation: kbPageSpinnerRotate 0.9s linear infinite !important;
    }
}
</style>
