
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const page = usePage<{ auth: { user: { name: string } | null } }>();
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
}>();

console.log(props.stats);

const menuItems = [
    { label: 'Dashboard', icon: 'fa-solid fa-house', href: '/admin/dashboard' },
    { label: 'Sản Phẩm', icon: 'fa-solid fa-box', href: '/admin/products' },
    { label: 'Danh Mục', icon: 'fa-solid fa-layer-group', href: '/admin/categories' },
    { label: 'Dự Án Tiêu Biểu', icon: 'fa-solid fa-building', href: '/admin/case-studies' },
    { label: 'Yêu Cầu Tư Vấn', icon: 'fa-solid fa-headset', href: '/admin/consultations' },
    { label: 'Chat Cùng Khách', icon: ['fas', 'comments'], href: '/admin/chats' },
    { label: 'Báo Cáo Dự Án', icon: 'fa-solid fa-chart-line', href: '/admin/project-reports' },
];

const visibleStats = computed(() => props.stats);

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

onBeforeUnmount(() => {
    if (toastTimeout) {
        clearTimeout(toastTimeout);
    }

    window.clearInterval(chatUnreadTimer);
});

const liveChatUnread = ref<number | null>(null);
const chatUnreadCount = computed(() => liveChatUnread.value ?? props.chatUnreadCount ?? 0);

watch(
    () => props.chatUnreadCount,
    () => {
        liveChatUnread.value = null;
    },
);

async function refreshChatUnread(): Promise<void> {
    try {
        const response = await fetch('/admin/chats/list', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!response.ok) {
            return;
        }

        const data = (await response.json()) as { conversations: { unread_count: number }[] };
        liveChatUnread.value = data.conversations.reduce((sum, item) => sum + item.unread_count, 0);
    } catch {
        // Bỏ qua lỗi mạng tạm thời, lần polling sau sẽ thử lại.
    }
}

const chatUnreadTimer = window.setInterval(refreshChatUnread, 10000);
void refreshChatUnread();


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
            <b>HVAC Mart</b><small>ADMINISTRATION</small>
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
                    <span v-if="item.href === '/admin/consultations'" class="count count-alert">{{ props.openRequestsCount }}</span>
                    <span v-if="item.href === '/admin/chats' && chatUnreadCount > 0" class="count count-alert">{{ chatUnreadCount }}</span>
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
              <div class="eyebrow">HVAC MART / ADMIN CONTROL CENTER</div>
              <h1>Quản trị vận hành.</h1>
              <p>Quản lý sản phẩm, hình ảnh, danh mục, dự án và yêu cầu tư vấn.</p>
            </div>
            <div class="subnote">
              <b>Ghi chú dữ liệu</b><br>File này dùng cùng các khóa localStorage với website HVAC Mart khi được mở trên cùng domain. Nếu mở bằng file://, hãy dùng chức năng xuất/nhập JSON để chuyển dữ liệu.</div>
          </div>
          <div class="warning" id="storageWarning">Dữ liệu được lưu trong trình duyệt. Bản HTML tĩnh chưa có máy chủ, đăng nhập bảo mật hoặc đồng bộ giữa các thiết bị.</div>
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
