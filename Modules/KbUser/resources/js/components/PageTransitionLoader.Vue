<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

const isNavigating = ref(false);
const isOverlayVisible = ref(false);
const loadingTitle = ref('Đang chuyển trang...');
const loadingSubTitle = ref('Hệ thống đang tải nội dung, vui lòng đợi trong giây lát');

let showOverlayTimer: ReturnType<typeof setTimeout> | undefined;
let hideOverlayTimer: ReturnType<typeof setTimeout> | undefined;
let safetyResetTimer: ReturnType<typeof setTimeout> | undefined;
let overlayShownAt = 0;

const resolveDestinationCopy = (pathname: string, method: string): { title: string; subtitle: string; delayMs: number } => {
    const normalizedPath = pathname.toLowerCase();

    if (normalizedPath.startsWith('/quotations')) {
        if (method === 'post' && normalizedPath.includes('/login')) {
            return {
                title: 'Đang xác thực & mở trang Tạo Báo Giá...',
                subtitle: 'Đang khởi tạo dữ liệu sản phẩm Ariston, K-Flex và công cụ lập báo giá',
                delayMs: 60,
            };
        }

        if (normalizedPath.includes('/login')) {
            return {
                title: 'Đang mở cổng Tạo Báo Giá...',
                subtitle: 'Đang chuẩn bị phiên làm việc bảo mật',
                delayMs: 90,
            };
        }

        return {
            title: 'Đang mở trang Tạo Báo Giá...',
            subtitle: 'Đang tải danh mục Ariston, bảng giá K-Flex & công cụ xuất Excel / PDF',
            delayMs: 70,
        };
    }

    if (normalizedPath.startsWith('/products')) {
        return {
            title: 'Đang tải danh mục sản phẩm...',
            subtitle: 'Thiết bị nhiệt Ariston & Bảo ôn cách nhiệt K-Flex chính hãng',
            delayMs: 150,
        };
    }

    if (normalizedPath.startsWith('/categories')) {
        return {
            title: 'Đang mở danh mục hệ thống...',
            subtitle: 'Giải pháp vật tư kỹ thuật HVAC & MEP cho công trình',
            delayMs: 150,
        };
    }

    if (normalizedPath.startsWith('/catalogues')) {
        return {
            title: 'Đang mở thư viện Catalogue...',
            subtitle: 'Tài liệu kỹ thuật, Datasheet & hồ sơ CO/CQ',
            delayMs: 150,
        };
    }

    if (normalizedPath.startsWith('/featured-projects')) {
        return {
            title: 'Đang tải hồ sơ dự án tiêu biểu...',
            subtitle: 'Các công trình dân dụng, thương mại & khu công nghiệp',
            delayMs: 150,
        };
    }

    if (normalizedPath.startsWith('/news')) {
        return {
            title: 'Đang tải chuyên mục tin tức...',
            subtitle: 'Cẩm nang kỹ thuật & tin tức dự án cơ điện MEP',
            delayMs: 150,
        };
    }

    if (normalizedPath.startsWith('/contacts')) {
        return {
            title: 'Đang mở trang Liên hệ & Báo giá...',
            subtitle: 'Kết nối bộ phận tư vấn kỹ thuật dự án HVAC Mart',
            delayMs: 150,
        };
    }

    if (normalizedPath.startsWith('/about-us')) {
        return {
            title: 'Đang tải hồ sơ năng lực...',
            subtitle: 'Thông tin doanh nghiệp & giải pháp cung ứng HVAC Mart',
            delayMs: 150,
        };
    }

    return {
        title: 'Đang chuyển trang...',
        subtitle: 'Hệ thống đang tải nội dung, vui lòng đợi trong giây lát',
        delayMs: 160,
    };
};

const clearTimers = (): void => {
    if (showOverlayTimer) {
        clearTimeout(showOverlayTimer);
        showOverlayTimer = undefined;
    }
    if (hideOverlayTimer) {
        clearTimeout(hideOverlayTimer);
        hideOverlayTimer = undefined;
    }
    if (safetyResetTimer) {
        clearTimeout(safetyResetTimer);
        safetyResetTimer = undefined;
    }
};

const finishNavigation = (): void => {
    if (showOverlayTimer) {
        clearTimeout(showOverlayTimer);
        showOverlayTimer = undefined;
    }
    if (safetyResetTimer) {
        clearTimeout(safetyResetTimer);
        safetyResetTimer = undefined;
    }

    isNavigating.value = false;

    if (!isOverlayVisible.value) {
        return;
    }

    const elapsed = Date.now() - overlayShownAt;
    const remaining = Math.max(0, 150 - elapsed);

    if (hideOverlayTimer) {
        clearTimeout(hideOverlayTimer);
    }

    hideOverlayTimer = setTimeout(() => {
        isOverlayVisible.value = false;
        hideOverlayTimer = undefined;
    }, remaining);
};

let removeStartListener: (() => void) | undefined;
let removeFinishListener: (() => void) | undefined;
let removeErrorListener: (() => void) | undefined;
let removeExceptionListener: (() => void) | undefined;

onMounted(() => {
    removeStartListener = router.on('start', (event) => {
        const visit = event.detail.visit as {
            url?: URL | string;
            method?: string;
            prefetch?: boolean;
            preserveScroll?: boolean | string;
        };

        if (visit?.prefetch) {
            return;
        }

        const method = String(visit?.method || 'get').toLowerCase();
        if (method !== 'get' && visit?.preserveScroll) {
            return;
        }

        let pathname = '/';
        try {
            if (typeof visit?.url === 'string') {
                pathname = new URL(visit.url, window.location.origin).pathname;
            } else if (visit?.url && typeof visit.url.pathname === 'string') {
                pathname = visit.url.pathname;
            }
        } catch {
            pathname = window.location.pathname || '/';
        }

        const copy = resolveDestinationCopy(pathname, method);
        loadingTitle.value = copy.title;
        loadingSubTitle.value = copy.subtitle;

        clearTimers();
        isNavigating.value = true;

        showOverlayTimer = setTimeout(() => {
            overlayShownAt = Date.now();
            isOverlayVisible.value = true;
            showOverlayTimer = undefined;
        }, copy.delayMs);

        safetyResetTimer = setTimeout(() => {
            isNavigating.value = false;
            isOverlayVisible.value = false;
        }, 15000);
    });

    removeFinishListener = router.on('finish', (event) => {
        const visit = event.detail.visit as { prefetch?: boolean } | undefined;
        if (visit?.prefetch) {
            return;
        }

        finishNavigation();
    });

    removeErrorListener = router.on('error', () => {
        finishNavigation();
    });

    removeExceptionListener = router.on('networkError', () => {
        finishNavigation();
    });
});

onUnmounted(() => {
    clearTimers();
    removeStartListener?.();
    removeFinishListener?.();
    removeErrorListener?.();
    removeExceptionListener?.();
});
</script>

<template>
    <Teleport to="body">
        <div class="kb-page-transition-root" aria-live="polite" :aria-busy="isNavigating">
            <Transition name="kb-top-bar-fade">
                <div v-if="isNavigating" class="kb-page-top-loader" role="progressbar" aria-label="Đang tải trang">
                    <div class="kb-page-top-loader-bar"></div>
                </div>
            </Transition>

            <Transition name="kb-page-overlay-fade">
                <div v-if="isOverlayVisible" class="kb-page-loading-overlay" role="status">
                    <div class="kb-page-loading-card">
                        <div class="kb-page-loading-spinner-wrap" aria-hidden="true">
                            <span class="kb-page-loading-ring kb-page-loading-ring-outer"></span>
                            <span class="kb-page-loading-ring kb-page-loading-ring-inner"></span>
                            <span class="kb-page-loading-brand">HM</span>
                        </div>
                        <div class="kb-page-loading-copy">
                            <strong class="kb-page-loading-title">{{ loadingTitle }}</strong>
                            <span class="kb-page-loading-subtitle">{{ loadingSubTitle }}</span>
                        </div>
                        <div class="kb-page-loading-shimmer" aria-hidden="true">
                            <span class="kb-page-loading-shimmer-bar"></span>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </Teleport>
</template>

<style>
.kb-page-top-loader {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    z-index: 100000;
    overflow: hidden;
    background: rgba(183, 154, 99, 0.18);
    pointer-events: none;
}

.kb-page-top-loader-bar {
    width: 45%;
    height: 100%;
    background: linear-gradient(90deg, #1e3328 0%, #b79a63 55%, #d6bc84 100%);
    box-shadow: 0 0 12px rgba(183, 154, 99, 0.75);
    animation: kbTopLoaderSlide 1s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

.kb-page-loading-overlay {
    position: fixed;
    inset: 0;
    z-index: 99995;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 25, 20, 0.34);
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
}

.kb-page-loading-card {
    width: min(390px, calc(100vw - 32px));
    padding: 24px 26px 20px;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.96);
    border: 1px solid rgba(183, 154, 99, 0.38);
    box-shadow:
        0 22px 48px rgba(12, 22, 17, 0.22),
        0 4px 14px rgba(183, 154, 99, 0.14);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 14px;
}

[data-theme='dark'] .kb-page-loading-card {
    background: rgba(22, 34, 28, 0.96);
    border-color: rgba(183, 154, 99, 0.42);
    box-shadow: 0 22px 48px rgba(0, 0, 0, 0.5);
}

.kb-page-loading-spinner-wrap {
    position: relative;
    width: 60px;
    height: 60px;
    display: grid;
    place-items: center;
}

.kb-page-loading-ring {
    position: absolute;
    inset: 0;
    border-radius: 50%;
}

.kb-page-loading-ring-outer {
    border: 2.5px solid rgba(30, 51, 40, 0.12);
    border-top-color: #1e3328;
    border-right-color: #b79a63;
    animation: kbSpin RingOuter 0.9s linear infinite;
    animation-name: kbSpinRingOuter;
}

[data-theme='dark'] .kb-page-loading-ring-outer {
    border-color: rgba(255, 255, 255, 0.12);
    border-top-color: #d6bc84;
    border-right-color: #b79a63;
}

.kb-page-loading-ring-inner {
    inset: 7px;
    border: 2px dashed rgba(183, 154, 99, 0.55);
    animation: kbSpinRingInner 1.6s linear infinite reverse;
}

.kb-page-loading-brand {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e3328 0%, #2a4738 100%);
    color: #e6cf9c;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.06em;
    display: grid;
    place-items: center;
    box-shadow: 0 4px 10px rgba(30, 51, 40, 0.25);
}

.kb-page-loading-copy {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.kb-page-loading-title {
    font-size: 15px;
    font-weight: 700;
    color: #17271e;
    line-height: 1.35;
}

[data-theme='dark'] .kb-page-loading-title {
    color: #f3f5f0;
}

.kb-page-loading-subtitle {
    font-size: 12.5px;
    color: #5c6b5e;
    line-height: 1.45;
}

[data-theme='dark'] .kb-page-loading-subtitle {
    color: #a9b8ac;
}

.kb-page-loading-shimmer {
    width: 100%;
    height: 4px;
    border-radius: 999px;
    background: rgba(30, 51, 40, 0.08);
    overflow: hidden;
    margin-top: 2px;
}

[data-theme='dark'] .kb-page-loading-shimmer {
    background: rgba(255, 255, 255, 0.1);
}

.kb-page-loading-shimmer-bar {
    display: block;
    width: 45%;
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #1e3328, #b79a63, #1e3328);
    animation: kbTopLoaderSlide 1.15s ease-in-out infinite;
}

.kb-top-bar-fade-enter-active,
.kb-top-bar-fade-leave-active {
    transition: opacity 0.18s ease;
}

.kb-top-bar-fade-enter-from,
.kb-top-bar-fade-leave-to {
    opacity: 0;
}

.kb-page-overlay-fade-enter-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.kb-page-overlay-fade-leave-active {
    transition: opacity 0.16s ease, transform 0.16s ease;
}

.kb-page-overlay-fade-enter-from,
.kb-page-overlay-fade-leave-to {
    opacity: 0;
    transform: scale(0.98);
}

@keyframes kbTopLoaderSlide {
    0% {
        transform: translateX(-110%);
    }
    100% {
        transform: translateX(240%);
    }
}

@keyframes kbSpinRingOuter {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

@keyframes kbSpinRingInner {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}
</style>
