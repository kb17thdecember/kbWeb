
<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const currentUrl = computed(() => page.url.split('?')[0]);

type AdminStat = {
    label: string;
    count: number;
    description: string;
};

const props = defineProps<{
    stats: AdminStat[];
    openRequestsCount: number;
}>();

const menuItems = [
    { label: 'Dashboard', icon: '▦', href: '/admin/dashboard' },
    { label: 'Sản Phẩm', icon: '▧', href: '/admin/products' },
    { label: 'Danh Mục', icon: '▤', href: '/admin/categories' },
    { label: 'Dự Án Tiêu Biểu', icon: '▣', href: '/admin/case-studies' },
    { label: 'Yêu Cầu Tư Vấn', icon: '☷', href: '/admin/consultations' },
    { label: 'Báo Cáo Dự Án', icon: '☷', href: '/admin/project-reports' },
];

const visibleStats = computed(() => props.stats);

function exportProjectReports(): void {
    window.location.assign('/admin/project-reports/export');
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
        onSuccess: () => window.alert('Nhập dữ liệu Excel thành công.'),
        onError: (errors) => window.alert(Object.values(errors).join('\n')),
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
          <span class="mark">N</span>
          <div>
            <b>KB TECH</b><small>ADMINISTRATION</small>
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
                    <span class="nav-icon">{{ item.icon }}</span>
                    <span>{{ item.label }}</span>
                    <span v-if="item.href === '/admin/consultations'" class="count">{{ props.openRequestsCount }}</span>
          </Link>
          </nav>
        </div>
        <div class="side-note">
          <b>Khu vực quản trị</b>Chỉnh sửa dữ liệu và xuất báo cáo dự án.<button id="exportBtn" type="button" @click="exportProjectReports">Xuất báo cáo Excel ↗</button><button id="importBtn" type="button" :disabled="isImporting" @click="importFileInput?.click()">{{ isImporting ? 'Đang nhập...' : 'Nhập dữ liệu Excel ↑' }}</button><input ref="importFileInput" class="hidden" type="file" id="importFile" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" @change="importProjectReports">
        </div>
      </aside>
      <main class="main">
        <header class="topbar">
          <div class="top-left">
            <span class="top-title" id="topTitle">
              {{ menuItems.find(item => currentUrl === item.href)?.label || '' }}
            </span>
            <label class="global-search">⌕ <input id="globalSearch" type="search" placeholder="Tìm sản phẩm, dự án, khách hàng..."></label>
          </div>
          <div class="profile">
            <span class="avatar">A</span>
            <div>
              <b>Administrator</b><small>Quản trị viên</small>
            </div>
            <button class="btn" type="button" @click="logout">Đăng xuất</button>
          </div>
        </header>
        <div class="content">
          <div class="welcome">
            <div>
              <div class="eyebrow">KB TECH / ADMIN CONTROL CENTER</div>
              <h1>Quản trị vận hành.</h1>
              <p>Quản lý sản phẩm, hình ảnh, danh mục, dự án và yêu cầu tư vấn.</p>
            </div>
            <div class="subnote">
              <b>Ghi chú dữ liệu</b><br>File này dùng cùng các khóa localStorage với website NOVA TECH khi được mở trên cùng domain. Nếu mở bằng file://, hãy dùng chức năng xuất/nhập JSON để chuyển dữ liệu.</div>
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
    </div>
</template>
