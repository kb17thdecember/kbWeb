<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
    { label: 'Cài Đặt Hệ Thống', icon: 'fa-solid fa-gear', href: '/admin/settings' },
];

const visibleStats = computed(() => props.stats);
const brandName = computed(() => page.props.siteSettings?.company_name || 'HVAC Mart');

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
const notificationFilter = ref<'all' | 'consultation' | 'chat' | 'project'>('all');
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
        totalCount: openRequests + chatUnread + supportProjects,
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
        void refreshNotifications();
    }
}

function handleDocumentPointerDown(event: MouseEvent): void {
    if (!isNotificationOpen.value) {
        return;
    }

    const target = event.target as Node | null;
    if (target && notificationWrapRef.value && !notificationWrapRef.value.contains(target)) {
        isNotificationOpen.value = false;
    }
}

function handleEscapeKey(event: KeyboardEvent): void {
    if (event.key === 'Escape' && isNotificationOpen.value) {
        isNotificationOpen.value = false;
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

async function exportProjectReports(): Promise<void> {
    try {
        const response = await fetch('/admin/project-reports/export', {
            credentials: 'same-origin',
            headers: { Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
        });

        if (!response.ok) {
            throw new Error('export failed');
        }

        const fileName = /filename="?([^";]+)"?/.exec(response.headers.get('Content-Disposition') ?? '')?.[1]
            ?? 'bao-cao-du-an.xlsx';
        const url = URL.createObjectURL(await response.blob());
        const link = document.createElement('a');
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
        showToast('Đã xuất báo cáo Excel thành công.');
    } catch {
        showToast('Không thể xuất báo cáo Excel. Vui lòng thử lại.');
    }
}

function logout(): void {
    router.post('/admin/logout');
}

const importFileInput = ref<HTMLInputElement | null>(null);
const isImporting = ref(false);

function importProjectReports(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    isImporting.value = true;
    router.post('/admin/project-reports/import', { file }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => showToast('Nhập dữ liệu Excel thành công.'),
        onError: (errors) => showToast(Object.values(errors).join(' ')),
        onFinish: () => {
            isImporting.value = false;
            input.value = '';
        },
    });
}
</script>

<template>
    <div class="app">
      <aside class="sidebar">
        <div class="brand">
          <span class="mark">HM</span>
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
        <div class="side-note">
          <b>Khu vực quản trị</b>Chỉnh sửa dữ liệu và xuất báo cáo dự án.<button id="exportBtn" type="button" @click="exportProjectReports"><FontAwesomeIcon icon="fa-solid fa-file-export" /> Xuất báo cáo Excel</button><button id="importBtn" type="button" :disabled="isImporting" @click="importFileInput?.click()"><FontAwesomeIcon :icon="isImporting ? 'fa-solid fa-spinner' : 'fa-solid fa-file-import'" /> {{ isImporting ? 'Đang nhập...' : 'Nhập dữ liệu Excel' }}</button><input ref="importFileInput" class="hidden" type="file" id="importFile" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" @change="importProjectReports">
        </div>
      </aside>
      <main class="main">
        <header class="topbar">
          <div class="top-left">
            <span class="top-title" id="topTitle">
              {{ menuItems.find(item => currentUrl === item.href)?.label || '' }}
            </span>
            <label class="global-search"><FontAwesomeIcon icon="fa-solid fa-magnifying-glass" /> <input id="globalSearch" type="search" placeholder="Tìm sản phẩm, dự án, khách hàng..."></label>
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
                      <button
                          type="button"
                          class="admin-notif-tab"
                          :class="{ active: notificationFilter === 'project' }"
                          @click="notificationFilter = 'project'"
                      >
                          Dự án ({{ supportProjectsBadge }})
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
                      <span>Mọi yêu cầu tư vấn, tin nhắn và dự án đều đã được xử lý.</span>
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
