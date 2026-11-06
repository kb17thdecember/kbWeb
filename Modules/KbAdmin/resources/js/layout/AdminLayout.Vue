<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

type AdminNotificationItem = {
    id: string;
    type: 'consultation' | 'chat' | 'project' | string;
    title: string;
    subtitle: string;
    badge: string;
    href: string;
    created_at: string | null;
};

type AdminNotificationsPayload = {
    openRequestsCount: number;
    pendingRequestsCount: number;
    chatUnreadCount: number;
    supportProjectsCount: number;
    totalCount: number;
    items: AdminNotificationItem[];
};

type AdminGlobalSearchItem = {
    id: string;
    type: string;
    groupLabel: string;
    title: string;
    subtitle: string;
    badge: string;
    image?: string | null;
    shortCode?: string;
    href: string;
    keyword: string;
};

type AdminGlobalSearchResponse = {
    query: string;
    total: number;
    items: AdminGlobalSearchItem[];
};

const page = usePage<{
    auth: { user: { name: string } | null };
    adminSessionTimeoutMinutes?: number;
    adminNotifications?: AdminNotificationsPayload;
    siteSettings?: Record<string, string>;
    toast?: string | null;
}>();
const currentUrl = computed(() => page.url.split('?')[0]);

type AdminStat = {
    label: string;
    count: number;
    description: string;
};

const props = defineProps<{
    stats: AdminStat[];
    openRequestsCount: number;
    chatUnreadCount?: number;
    supportProjectsCount?: number;
}>();

const menuItems = [
    { label: 'Dashboard', icon: 'fa-solid fa-house', href: '/admin/dashboard' },
    { label: 'Lượt Truy Cập', icon: 'fa-solid fa-eye', href: '/admin/visits' },
    { label: 'Sản Phẩm', icon: 'fa-solid fa-box', href: '/admin/products' },
    { label: 'Bảng Giá K-Flex', icon: 'fa-solid fa-tags', href: '/admin/kflex-prices' },
    { label: 'Danh Mục', icon: 'fa-solid fa-layer-group', href: '/admin/categories' },
    { label: 'Dự Án Tiêu Biểu', icon: 'fa-solid fa-building', href: '/admin/case-studies' },
    { label: 'Bài Viết', icon: 'fa-solid fa-newspaper', href: '/admin/articles' },
    { label: 'Catalogue', icon: 'fa-solid fa-file-pdf', href: '/admin/catalogues' },
    { label: 'Khách Hàng', icon: 'fa-solid fa-users', href: '/admin/customers' },
    { label: 'Yêu Cầu Tư Vấn', icon: 'fa-solid fa-headset', href: '/admin/consultations' },
    { label: 'Chat Cùng Khách', icon: ['fas', 'comments'], href: '/admin/chats' },
    { label: 'Báo Cáo Dự Án', icon: 'fa-solid fa-chart-line', href: '/admin/project-reports' },
    { label: 'Tạo Báo Giá PDF', icon: 'fa-solid fa-file-invoice-dollar', href: '/admin/quotations' },
    { label: 'Tối Ưu SEO (Google)', icon: 'fa-solid fa-magnifying-glass-chart', href: '/admin/seo' },
    { label: 'Cài Đặt Hệ Thống', icon: 'fa-solid fa-gear', href: '/admin/settings' },
];

const visibleStats = computed(() => props.stats);
const brandName = computed(() => page.props.siteSettings?.company_name || 'HVAC Mart');
const siteLogoUrl = computed(() => {
    const raw = (page.props.siteSettings?.site_logo || '').trim();
    if (!raw) {
        return '';
    }
    if (raw.startsWith('data:') || raw.startsWith('http://') || raw.startsWith('https://') || raw.startsWith('/')) {
        return raw;
    }

    return `/storage/${raw}`;
});

const isToastVisible = ref(false);
const toastMessage = ref('');
let toastTimeout: ReturnType<typeof setTimeout> | undefined;

function showToast(message: string): void {
    if (toastTimeout) {
        clearTimeout(toastTimeout);
    }

    toastMessage.value = message;
    isToastVisible.value = true;
    toastTimeout = setTimeout(() => {
        isToastVisible.value = false;
        toastTimeout = undefined;
    }, 3500);
}

watch(
    () => page.props.toast,
    (message) => {
        if (message) {
            showToast(message);
        }
    },
    { immediate: true },
);

const ACTIVITY_EVENTS = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'] as const;
let inactivityTimer: ReturnType<typeof setTimeout> | undefined;

function resetInactivityTimer(): void {
    if (inactivityTimer) {
        clearTimeout(inactivityTimer);
    }

    const timeoutMs = (page.props.adminSessionTimeoutMinutes ?? 30) * 60 * 1000;
    inactivityTimer = setTimeout(() => {
        router.post('/admin/logout');
    }, timeoutMs);
}

const isNotificationOpen = ref(false);
const notificationFilter = ref<'all' | 'consultation' | 'chat'>('all');
const notificationWrapRef = ref<HTMLElement | null>(null);
const liveNotifications = ref<AdminNotificationsPayload | null>(null);

const notificationsData = computed<AdminNotificationsPayload>(() => {
    if (liveNotifications.value) {
        return liveNotifications.value;
    }

    if (page.props.adminNotifications) {
        return page.props.adminNotifications;
    }

    const openRequests = props.openRequestsCount ?? 0;
    const chatUnread = props.chatUnreadCount ?? 0;
    const supportProjects = props.supportProjectsCount ?? 0;

    return {
        openRequestsCount: openRequests,
        pendingRequestsCount: openRequests,
        chatUnreadCount: chatUnread,
        supportProjectsCount: supportProjects,
        totalCount: openRequests + chatUnread,
        items: [],
    };
});

const openRequestsBadge = computed(() => notificationsData.value.openRequestsCount);
const chatUnreadCount = computed(() => notificationsData.value.chatUnreadCount);
const supportProjectsBadge = computed(() => notificationsData.value.supportProjectsCount);
const totalNotificationCount = computed(() => notificationsData.value.totalCount);

const filteredNotifications = computed(() => {
    const allItems = notificationsData.value.items;
    if (notificationFilter.value === 'all') {
        return allItems;
    }

    return allItems.filter((item) => item.type === notificationFilter.value);
});

watch(
    () => [props.openRequestsCount, props.chatUnreadCount, page.props.adminNotifications],
    () => {
        liveNotifications.value = null;
    },
);

async function refreshNotifications(): Promise<void> {
    try {
        const response = await fetch('/admin/notifications', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Background-Poll': '1',
            },
        });

        if (response.status === 401) {
            window.location.href = '/admin/login';

            return;
        }

        if (!response.ok) {
            return;
        }

        liveNotifications.value = (await response.json()) as AdminNotificationsPayload;
    } catch {
        // Bỏ qua lỗi mạng tạm thời, lần polling sau sẽ thử lại.
    }
}

function csrfToken(): string {
    return decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '');
}

async function markAllChatsRead(): Promise<void> {
    try {
        const response = await fetch('/admin/notifications/read-chats', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': csrfToken(),
            },
        });

        if (response.ok) {
            liveNotifications.value = (await response.json()) as AdminNotificationsPayload;
            showToast('Đã đánh dấu đã đọc toàn bộ tin nhắn chat.');
        }
    } catch {
        showToast('Không thể cập nhật trạng thái tin nhắn.');
    }
}

function toggleNotificationDropdown(): void {
    isNotificationOpen.value = !isNotificationOpen.value;
    if (isNotificationOpen.value) {
        isGlobalSearchOpen.value = false;
        void refreshNotifications();
    }
}

const globalSearchWrapRef = ref<HTMLElement | null>(null);
const globalSearchInputRef = ref<HTMLInputElement | null>(null);
const globalSearchQuery = ref('');
const globalSearchResults = ref<AdminGlobalSearchItem[]>([]);
const globalSearchError = ref('');
const isGlobalSearchOpen = ref(false);
const isGlobalSearchLoading = ref(false);
let globalSearchController: AbortController | undefined;

const searchResultsUrl = computed(() => {
    const trimmed = globalSearchQuery.value.trim();
    if (globalSearchResults.value.length > 0) {
        const firstBase = globalSearchResults.value[0].href.split('?')[0];

        return `${firstBase}?search=${encodeURIComponent(trimmed)}#workspace`;
    }

    const searchablePaths = [
        '/admin/products',
        '/admin/categories',
        '/admin/project-reports',
        '/admin/case-studies',
        '/admin/consultations',
        '/admin/customers',
        '/admin/articles',
        '/admin/catalogues',
    ];
    const targetPath = searchablePaths.includes(currentUrl.value) ? currentUrl.value : '/admin/products';

    return `${targetPath}?search=${encodeURIComponent(trimmed)}#workspace`;
});

async function searchAdminItems(query: string): Promise<void> {
    globalSearchController?.abort();
    globalSearchResults.value = [];
    globalSearchError.value = '';
    isGlobalSearchLoading.value = false;

    if (query.trim().length < 2) {
        return;
    }

    const controller = new AbortController();
    globalSearchController = controller;
    isGlobalSearchLoading.value = true;

    try {
        const response = await fetch(`/admin/search?q=${encodeURIComponent(query.trim())}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(`Tìm kiếm thất bại (${response.status}).`);
        }

        const payload = (await response.json()) as AdminGlobalSearchResponse;

        if (!controller.signal.aborted) {
            globalSearchResults.value = payload.items ?? [];
        }
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }
        globalSearchError.value = 'Không thể tìm kiếm lúc này. Vui lòng thử lại.';
    } finally {
        if (!controller.signal.aborted) {
            isGlobalSearchLoading.value = false;
        }
    }
}

function onGlobalSearchInput(event: Event): void {
    const input = event.target;
    if (input instanceof HTMLInputElement) {
        globalSearchQuery.value = input.value;
        isGlobalSearchOpen.value = true;
        void searchAdminItems(input.value);
    }
}

function handleGlobalSearchFocus(): void {
    isGlobalSearchOpen.value = true;
    if (globalSearchQuery.value.trim().length >= 2 && globalSearchResults.value.length === 0 && !isGlobalSearchLoading.value) {
        void searchAdminItems(globalSearchQuery.value);
    }
}

function syncWorkspaceSearchInput(keyword: string): void {
    window.dispatchEvent(new CustomEvent('kbadmin-search', { detail: { search: keyword } }));
    void nextTick(() => {
        window.setTimeout(() => {
            const workspace = document.getElementById('workspace');
            if (!workspace) {
                return;
            }
            const input = workspace.querySelector<HTMLInputElement>(
                'input[type="search"], input[placeholder*="Tìm"], input[placeholder*="tìm"]',
            );
            if (input && input.value !== keyword) {
                input.value = keyword;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
            workspace.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 50);
    });
}

function onClickSearchResult(item: AdminGlobalSearchItem): void {
    isGlobalSearchOpen.value = false;
    syncWorkspaceSearchInput(item.keyword || globalSearchQuery.value.trim());
}

function submitGlobalSearch(): void {
    const query = globalSearchQuery.value.trim();
    if (query.length > 0) {
        isGlobalSearchOpen.value = false;
        router.visit(searchResultsUrl.value, {
            onFinish: () => {
                syncWorkspaceSearchInput(query);
            },
        });
    }
}

function handleDocumentPointerDown(event: MouseEvent): void {
    const target = event.target as Node | null;
    if (isNotificationOpen.value && target && notificationWrapRef.value && !notificationWrapRef.value.contains(target)) {
        isNotificationOpen.value = false;
    }
    if (isGlobalSearchOpen.value && target && globalSearchWrapRef.value && !globalSearchWrapRef.value.contains(target)) {
        isGlobalSearchOpen.value = false;
    }
}

function handleEscapeKey(event: KeyboardEvent): void {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        isGlobalSearchOpen.value = true;
        globalSearchInputRef.value?.focus();

        return;
    }

    if (event.key === 'Escape') {
        if (isNotificationOpen.value) {
            isNotificationOpen.value = false;
        }
        if (isGlobalSearchOpen.value) {
            isGlobalSearchOpen.value = false;
        }
    }
}

function formatRelativeTime(isoDate: string | null): string {
    if (!isoDate) {
        return 'Vừa xong';
    }

    const time = new Date(isoDate).getTime();
    if (Number.isNaN(time)) {
        return '';
    }

    const diffMinutes = Math.max(0, Math.floor((Date.now() - time) / 60000));
    if (diffMinutes < 1) {
        return 'Vừa xong';
    }
    if (diffMinutes < 60) {
        return `${diffMinutes} phút trước`;
    }

    const diffHours = Math.floor(diffMinutes / 60);
    if (diffHours < 24) {
        return `${diffHours} giờ trước`;
    }

    const diffDays = Math.floor(diffHours / 24);
    if (diffDays < 7) {
        return `${diffDays} ngày trước`;
    }

    return new Date(isoDate).toLocaleDateString('vi-VN');
}

function notificationIcon(type: string): string {
    if (type === 'chat') {
        return 'fa-solid fa-comments';
    }
    if (type === 'project') {
        return 'fa-solid fa-chart-line';
    }

    return 'fa-solid fa-headset';
}

const notificationPollTimer = window.setInterval(refreshNotifications, 10000);
void refreshNotifications();

let removeSuccessListener: (() => void) | undefined;

function handleCustomAdminToast(event: Event): void {
    const message = (event as CustomEvent<{ message?: string }>).detail?.message;
    if (message) {
        showToast(message);
    }
}

onMounted(() => {
    if (page.props.toast) {
        showToast(page.props.toast);
    }

    const params = new URLSearchParams(window.location.search);
    const initialSearch = params.get('search') ?? params.get('q');
    if (initialSearch) {
        syncWorkspaceSearchInput(initialSearch);
    }

    window.addEventListener('kbadmin-toast', handleCustomAdminToast);

    removeSuccessListener = router.on('success', (event) => {
        const flashedToast = (event.detail.page.props as { toast?: string | null }).toast;

        if (flashedToast) {
            showToast(flashedToast);
        }
    });

    resetInactivityTimer();
    ACTIVITY_EVENTS.forEach((eventName) => {
        window.addEventListener(eventName, resetInactivityTimer, { passive: true });
    });
    document.addEventListener('mousedown', handleDocumentPointerDown);
    document.addEventListener('keydown', handleEscapeKey);
});

onBeforeUnmount(() => {
    removeSuccessListener?.();
    window.removeEventListener('kbadmin-toast', handleCustomAdminToast);

    if (toastTimeout) {
        clearTimeout(toastTimeout);
    }

    globalSearchController?.abort();

    if (inactivityTimer) {
        clearTimeout(inactivityTimer);
    }

    ACTIVITY_EVENTS.forEach((eventName) => {
        window.removeEventListener(eventName, resetInactivityTimer);
    });
    document.removeEventListener('mousedown', handleDocumentPointerDown);
    document.removeEventListener('keydown', handleEscapeKey);

    window.clearInterval(notificationPollTimer);
});

function logout(): void {
    router.post('/admin/logout');
}
</script>

<template>
    <div class="app">
      <aside class="sidebar">
        <div class="brand">
          <img
              v-if="siteLogoUrl"
              :src="siteLogoUrl"
              :alt="brandName"
              class="mark mark-logo"
          >
          <span v-else class="mark">HM</span>
          <div>
            <b>{{ brandName }}</b><small>ADMINISTRATION</small>
          </div>
        </div>
        <div>
          <p class="navlabel">MENU QUẢN TRỊ</p>
          <nav class="nav" id="nav">
            <Link
                v-for="item in menuItems"
                :key="item.label"
                :href="item.href"
                :class="{ active: currentUrl === item.href }"
                :title="item.label"
            >
                <span class="nav-icon"><FontAwesomeIcon :icon="item.icon" /></span>
                <span>{{ item.label }}</span>
                <span
                    v-if="item.href === '/admin/consultations'"
                    class="count"
                    :class="{ 'count-alert': openRequestsBadge > 0 }"
                    :title="`${openRequestsBadge} yêu cầu tư vấn chưa hoàn tất`"
                >{{ openRequestsBadge }}</span>
                <span
                    v-if="item.href === '/admin/chats' && chatUnreadCount > 0"
                    class="count count-alert"
                    :title="`${chatUnreadCount} tin nhắn khách hàng chưa đọc`"
                >{{ chatUnreadCount }}</span>
                <span
                    v-if="item.href === '/admin/project-reports' && supportProjectsBadge > 0"
                    class="count count-warn"
                    :title="`${supportProjectsBadge} dự án đang cần hỗ trợ`"
                >{{ supportProjectsBadge }}</span>
            </Link>
          </nav>
        </div>
      </aside>
      <main class="main">
        <header class="topbar">
          <div class="top-left">
            <span class="top-title" id="topTitle">
              {{ menuItems.find(item => currentUrl === item.href)?.label || '' }}
            </span>
            <form
              ref="globalSearchWrapRef"
              class="global-search-wrap"
              role="search"
              @submit.prevent="submitGlobalSearch"
            >
              <label class="global-search" for="globalSearch">
                <FontAwesomeIcon icon="fa-solid fa-magnifying-glass" />
                <input
                  id="globalSearch"
                  ref="globalSearchInputRef"
                  :value="globalSearchQuery"
                  type="search"
                  autocomplete="off"
                  placeholder="Tìm sản phẩm, dự án, khách hàng..."
                  @input="onGlobalSearchInput"
                  @focus="handleGlobalSearchFocus"
                >
                <button type="submit" class="global-search-submit" aria-label="Xem tất cả kết quả">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="10.8" cy="10.8" r="6.8"></circle>
                    <path d="m16 16 5 5"></path>
                  </svg>
                </button>
              </label>

              <div
                v-if="isGlobalSearchOpen && globalSearchQuery.trim().length > 0"
                class="navbar-search-results"
                role="listbox"
                aria-label="Kết quả tìm kiếm"
              >
                <p v-if="globalSearchQuery.trim().length < 2" class="navbar-search-message">
                  Nhập ít nhất 2 ký tự để tìm kiếm.
                </p>
                <p v-else-if="isGlobalSearchLoading" class="navbar-search-message">
                  Đang tìm kiếm...
                </p>
                <p v-else-if="globalSearchError" class="navbar-search-message" role="alert">
                  {{ globalSearchError }}
                </p>
                <p v-else-if="globalSearchResults.length === 0" class="navbar-search-message">
                  Không tìm thấy kết quả phù hợp.
                </p>
                <template v-else>
                  <Link
                    v-for="item in globalSearchResults"
                    :key="item.id"
                    class="navbar-search-result"
                    :href="item.href"
                    role="option"
                    @click="onClickSearchResult(item)"
                  >
                    <span class="navbar-search-result-image">
                      <img v-if="item.image" :src="item.image" :alt="item.title">
                      <span v-else>{{ item.shortCode || item.groupLabel }}</span>
                    </span>
                    <span class="navbar-search-result-copy">
                      <strong>{{ item.title }}</strong>
                      <small>{{ item.groupLabel }} · {{ item.subtitle }} · {{ item.badge }}</small>
                    </span>
                  </Link>
                  <button class="navbar-search-all" type="submit">
                    Xem tất cả kết quả cho “{{ globalSearchQuery.trim() }}”
                  </button>
                </template>
              </div>
            </form>
          </div>
          <div class="profile">
            <div ref="notificationWrapRef" class="admin-notif">
              <button
                  id="adminNotificationBell"
                  class="admin-notif-bell"
                  :class="{ 'is-open': isNotificationOpen, 'has-unread': totalNotificationCount > 0 }"
                  type="button"
                  aria-label="Thông báo vận hành"
                  :aria-expanded="isNotificationOpen"
                  @click="toggleNotificationDropdown"
              >
                  <FontAwesomeIcon icon="fa-solid fa-bell" />
                  <span v-if="totalNotificationCount > 0" class="admin-notif-badge">
                      {{ totalNotificationCount > 99 ? '99+' : totalNotificationCount }}
                  </span>
              </button>

              <div v-if="isNotificationOpen" class="admin-notif-dropdown" role="dialog" aria-label="Danh sách thông báo chưa xử lý">
                  <div class="admin-notif-head">
                      <div>
                          <strong>Thông báo vận hành</strong>
                          <small>{{ totalNotificationCount }} mục đang chờ xử lý</small>
                      </div>
                      <button
                          v-if="chatUnreadCount > 0"
                          type="button"
                          class="admin-notif-mark-btn"
                          @click="markAllChatsRead"
                      >
                          Đọc hết chat
                      </button>
                  </div>

                  <div class="admin-notif-tabs" role="tablist">
                      <button
                          type="button"
                          class="admin-notif-tab"
                          :class="{ active: notificationFilter === 'all' }"
                          @click="notificationFilter = 'all'"
                      >
                          Tất cả ({{ totalNotificationCount }})
                      </button>
                      <button
                          type="button"
                          class="admin-notif-tab"
                          :class="{ active: notificationFilter === 'consultation' }"
                          @click="notificationFilter = 'consultation'"
                      >
                          Tư vấn ({{ openRequestsBadge }})
                      </button>
                      <button
                          type="button"
                          class="admin-notif-tab"
                          :class="{ active: notificationFilter === 'chat' }"
                          @click="notificationFilter = 'chat'"
                      >
                          Chat ({{ chatUnreadCount }})
                      </button>
                  </div>

                  <div v-if="filteredNotifications.length > 0" class="admin-notif-list">
                      <Link
                          v-for="item in filteredNotifications"
                          :key="item.id"
                          :href="item.href"
                          class="admin-notif-item"
                          :class="`is-${item.type}`"
                          @click="isNotificationOpen = false"
                      >
                          <span class="admin-notif-icon">
                              <FontAwesomeIcon :icon="notificationIcon(item.type)" />
                          </span>
                          <div class="admin-notif-body">
                              <div class="admin-notif-title-row">
                                  <b>{{ item.title }}</b>
                                  <span class="admin-notif-tag">{{ item.badge }}</span>
                              </div>
                              <p>{{ item.subtitle }}</p>
                              <small>{{ formatRelativeTime(item.created_at) }}</small>
                          </div>
                      </Link>
                  </div>
                  <div v-else class="admin-notif-empty">
                      <FontAwesomeIcon icon="fa-solid fa-circle-check" />
                      <b>Không có mục tồn đọng</b>
                      <span>Mọi yêu cầu tư vấn và tin nhắn đều đã được xử lý.</span>
                  </div>

                  <div class="admin-notif-foot">
                      <Link href="/admin/consultations" @click="isNotificationOpen = false">Xem yêu cầu tư vấn</Link>
                      <Link href="/admin/chats" @click="isNotificationOpen = false">Mở hộp thư chat</Link>
                  </div>
              </div>
            </div>

            <span class="avatar">A</span>
            <div>
              <b>{{ page.props.auth.user?.name ?? 'Administrator' }}</b><small>Quản trị viên</small>
            </div>
            <button class="btn" type="button" @click="logout"><FontAwesomeIcon icon="fa-solid fa-right-from-bracket" /> Đăng xuất</button>
          </div>
        </header>
        <div class="content">
          <div class="welcome">
            <div>
              <div class="eyebrow">{{ brandName.toUpperCase() }} / ADMIN CONTROL CENTER</div>
              <h1>Quản trị vận hành.</h1>
              <p>Quản lý sản phẩm, hình ảnh, danh mục, dự án và yêu cầu tư vấn.</p>
            </div>
            <div class="subnote">
              <b>Đồng bộ hệ thống trực tuyến</b><br>Mọi cập nhật về sản phẩm, danh mục, dự án, tin tức và báo cáo đều được lưu trên máy chủ cơ sở dữ liệu và hiển thị tức thời trên website {{ brandName }}.</div>
          </div>
          <div class="stats" id="stats">
            <div class="stat" v-for="stat in visibleStats" :key="stat.label">
              <span>{{ stat.label }}</span>
              <strong>{{ stat.count }}</strong>
              <small>{{ stat.description }}</small>
            </div>
          </div>
          <section class="workspace" id="workspace">
            <slot />
          </section>
        </div>
      </main>
      <div class="toast" :class="{ show: isToastVisible }" role="status" aria-live="polite">{{ toastMessage }}</div>
    </div>
</template>

<style scoped>
.global-search-wrap {
    position: relative;
}

.global-search-submit {
    display: grid;
    width: 28px;
    height: 28px;
    padding: 6px;
    color: #2f2b21;
    background: #d8c398;
    border: 0;
    border-radius: 6px;
    place-items: center;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.15s ease;
}

.global-search-submit:hover {
    background: #c7ab73;
}

.global-search-submit svg {
    width: 14px;
    height: 14px;
}

.navbar-search-results {
    position: absolute;
    z-index: 120;
    top: calc(100% + 8px);
    left: 0;
    width: min(410px, calc(100vw - 30px));
    max-height: min(410px, 65vh);
    overflow-y: auto;
    background: var(--surface, #fffdf8);
    border: 1px solid var(--line, #e6dfd1);
    box-shadow: 0 18px 40px rgba(28, 25, 19, 0.14);
}

.navbar-search-message {
    margin: 0;
    padding: 14px 12px;
    color: var(--muted, #776f5f);
    font-size: 12px;
}

.navbar-search-result {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px;
    border-bottom: 1px solid var(--line, #eee8dc);
    text-decoration: none;
    color: inherit;
    transition: background 0.15s ease;
}

.navbar-search-result:hover {
    background: #f6f1e7;
}

.navbar-search-result-image {
    display: grid;
    flex: none;
    width: 48px;
    height: 48px;
    overflow: hidden;
    color: #9e824c;
    background: #f1e8d6;
    font-size: 10px;
    font-weight: 800;
    text-align: center;
    padding: 2px;
    place-items: center;
}

.navbar-search-result-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.navbar-search-result-copy {
    display: grid;
    min-width: 0;
    gap: 4px;
}

.navbar-search-result-copy strong {
    overflow: hidden;
    font-size: 12.5px;
    color: #2f2b21;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.navbar-search-result-copy small {
    color: #776f5f;
    font-size: 11px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.navbar-search-all {
    width: 100%;
    padding: 12px 10px;
    color: #987a42;
    background: transparent;
    border: 0;
    font-size: 11.5px;
    font-weight: 700;
    text-align: left;
    cursor: pointer;
}

.navbar-search-all:hover {
    background: #f6f1e7;
}
</style>
