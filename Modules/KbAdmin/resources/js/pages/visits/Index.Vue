<template>
    <AdminLayout :stats="props.stats" :open-requests-count="props.openRequestsCount">
        <div class="page-head">
            <div>
                <h2>Kiểm tra lượt truy cập trang (User)</h2>
                <p>
                    Hiển thị tối đa {{ props.visitSummary.maxLimit }} lượt truy cập gần nhất trên giao diện khách hàng.
                    Các lượt cũ hơn sẽ tự động xóa để tối ưu dữ liệu.
                </p>
            </div>
            <div class="actions">
                <button class="btn primary" type="button" :disabled="isRefreshing" @click="refreshVisits">
                    <FontAwesomeIcon :icon="['fas', 'rotate-right']" />
                    {{ isRefreshing ? 'Đang tải...' : 'Làm mới' }}
                </button>
                <button
                    v-if="props.visits.length > 0"
                    class="btn danger"
                    type="button"
                    :disabled="isClearing"
                    @click="clearAllVisits"
                >
                    {{ isClearing ? 'Đang xóa...' : 'Xóa lịch sử' }}
                </button>
                <input
                    v-model="search"
                    class="search"
                    type="search"
                    placeholder="Tìm thiết bị, IP, trang, khách..."
                    aria-label="Tìm kiếm lượt truy cập"
                >
            </div>
        </div>

        <div class="cardgrid" style="margin-bottom: 16px">
            <div class="card">
                <h3>Thống kê theo thiết bị (20 lượt gần nhất)</h3>
                <p>Phân loại thiết bị của khách truy cập gần đây trên website.</p>

                <div v-for="item in deviceBreakdown" :key="item.label" class="statusline">
                    <span class="dot" :style="{ background: item.color }"></span>
                    <div>
                        <b>{{ item.label }}</b>
                        <small>{{ item.description }}</small>
                    </div>
                    <strong>{{ item.count }}</strong>
                </div>
            </div>

            <div class="card">
                <h3>Cơ chế ghi nhận &amp; Tự động dọn dẹp</h3>
                <p>Chỉ tính lượt truy cập trên trang User, không tính thao tác của quản trị viên trên trang Admin.</p>

                <div class="statusline">
                    <span class="dot" style="background: #18352e"></span>
                    <div>
                        <b>Đang lưu trữ</b>
                        <small>Tối đa {{ props.visitSummary.maxLimit }} lượt gần nhất (tự động xóa lượt cũ)</small>
                    </div>
                    <strong>{{ props.visitSummary.total }} / {{ props.visitSummary.maxLimit }}</strong>
                </div>

                <div class="statusline">
                    <span class="dot" style="background: #b59a65"></span>
                    <div>
                        <b>Trong hôm nay</b>
                        <small>Số lượt trong danh sách diễn ra hôm nay</small>
                    </div>
                    <strong>{{ props.visitSummary.today }}</strong>
                </div>

                <div class="statusline">
                    <span class="dot" style="background: #66805a"></span>
                    <div>
                        <b>Khách đã đăng nhập</b>
                        <small>Lượt truy cập có tài khoản thành viên</small>
                    </div>
                    <strong>{{ props.visitSummary.authenticated }}</strong>
                </div>
            </div>
        </div>

        <div class="filters">
            <label>
                LOẠI THIẾT BỊ
                <select v-model="deviceFilter" aria-label="Lọc theo loại thiết bị">
                    <option value="all">Tất cả thiết bị ({{ props.visits.length }})</option>
                    <option value="desktop">Máy tính ({{ props.visitSummary.desktop }})</option>
                    <option value="mobile">Điện thoại ({{ props.visitSummary.mobile }})</option>
                    <option value="tablet">Máy tính bảng ({{ props.visitSummary.tablet }})</option>
                    <option value="bot">Bot / Tự động ({{ props.visitSummary.bot }})</option>
                </select>
            </label>
            <button
                v-if="deviceFilter !== 'all' || search"
                class="btn"
                type="button"
                @click="clearFilters"
            >Xóa bộ lọc</button>
            <em>Hiển thị {{ filteredVisits.length }} / {{ props.visits.length }} lượt gần nhất (tối đa {{ props.visitSummary.maxLimit }} lượt)</em>
        </div>

        <div v-if="filteredVisits.length" class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Thiết bị truy cập</th>
                        <th>Trình duyệt &amp; IP</th>
                        <th>Trang truy cập</th>
                        <th>Khách truy cập</th>
                        <th>Thời gian</th>
                        <th>Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(visit, index) in filteredVisits" :key="visit.id">
                        <td>
                            <b>#{{ index + 1 }}</b>
                        </td>
                        <td>
                            <div class="item">
                                <div class="request-detail-avatar" :title="visit.device_label">
                                    <FontAwesomeIcon :icon="deviceIcon(visit.device_type)" />
                                </div>
                                <div>
                                    <b>{{ visit.device_model }}</b>
                                    <small>
                                        <span class="badge" style="margin-right: 6px">{{ visit.device_label }}</span>
                                        {{ visit.os }}
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <b>{{ visit.browser }}</b>
                            <small class="muted">IP: {{ visit.ip_address || 'Không xác định' }}</small>
                        </td>
                        <td>
                            <b>{{ visit.page_label }}</b>
                            <small class="muted">
                                <a
                                    :href="visit.path"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    style="color: inherit; text-decoration: underline"
                                >{{ visit.path }}</a>
                            </small>
                        </td>
                        <td>
                            <div v-if="visit.user">
                                <b>{{ visit.user.name }}</b>
                                <small class="muted">✉ {{ visit.user.email }}</small>
                            </div>
                            <div v-else>
                                <b>Khách vãng lai</b>
                                <small class="muted">Phiên: {{ shortSession(visit.session_id) }}</small>
                            </div>
                        </td>
                        <td class="request-date">
                            <b>{{ formatRelativeTime(visit.updated_at) }}</b>
                            <small class="muted">{{ formatDate(visit.updated_at) }}</small>
                        </td>
                        <td>
                            <div class="row-actions">
                                <button
                                    class="request-eye"
                                    type="button"
                                    :aria-label="`Xem chi tiết lượt truy cập #${visit.id}`"
                                    title="Xem chi tiết thiết bị"
                                    @click="openDetail(visit)"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
                                <button
                                    class="mini danger"
                                    type="button"
                                    :disabled="deletingVisitId === visit.id"
                                    @click="deleteVisit(visit)"
                                >{{ deletingVisitId === visit.id ? 'Đang xóa...' : 'Xóa' }}</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="empty">
            {{
                props.visits.length
                    ? 'Không tìm thấy lượt truy cập phù hợp với bộ lọc.'
                    : 'Chưa có lượt truy cập nào được ghi nhận.'
            }}
        </div>

        <div v-if="selectedVisit" class="modal open" role="presentation" @click.self="closeDetail">
            <section
                class="dialog request-detail-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="visitDetailTitle"
            >
                <button class="close" type="button" aria-label="Đóng chi tiết" @click="closeDetail">
                    <FontAwesomeIcon :icon="['fas', 'xmark']" />
                </button>
                <div class="dialog-head">
                    <div class="eyebrow">VISITOR / DEVICE DETAILS</div>
                    <div class="request-detail-head">
                        <span class="request-detail-avatar">
                            <FontAwesomeIcon :icon="deviceIcon(selectedVisit.device_type)" />
                        </span>
                        <div>
                            <h2 id="visitDetailTitle">{{ selectedVisit.device_model }} ({{ selectedVisit.device_label }})</h2>
                            <p>Mã lượt truy cập: #{{ selectedVisit.id }} · {{ formatRelativeTime(selectedVisit.updated_at) }}</p>
                        </div>
                    </div>
                </div>

                <div class="request-detail-grid">
                    <div class="request-detail-item">
                        <small>Loại thiết bị</small>
                        <div>{{ selectedVisit.device_label }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Tên thiết bị nhận diện</small>
                        <div>{{ selectedVisit.device_model }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Hệ điều hành</small>
                        <div>{{ selectedVisit.os }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Trình duyệt</small>
                        <div>{{ selectedVisit.browser }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Địa chỉ IP</small>
                        <div>{{ selectedVisit.ip_address || '—' }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Trang truy cập gần nhất</small>
                        <div>{{ selectedVisit.page_label }} ({{ selectedVisit.path }})</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Khách hàng</small>
                        <div v-if="selectedVisit.user">
                            {{ selectedVisit.user.name }} ({{ selectedVisit.user.email }})
                        </div>
                        <div v-else>Khách vãng lai</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Mã phiên (Session ID)</small>
                        <div style="word-break: break-all">{{ selectedVisit.session_id || '—' }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Bắt đầu phiên truy cập</small>
                        <div>{{ formatDate(selectedVisit.created_at) }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Hoạt động gần nhất</small>
                        <div>{{ formatDate(selectedVisit.updated_at) }}</div>
                    </div>
                    <div class="request-detail-item full">
                        <small>Chuỗi định danh thiết bị gốc (User-Agent)</small>
                        <div style="word-break: break-word; font-family: monospace; font-size: 12px">
                            {{ selectedVisit.user_agent || 'Không có thông tin User-Agent' }}
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>

<script setup lang="ts">
    import { router } from '@inertiajs/vue3';
    import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
    import AdminLayout from '../../layout/AdminLayout.Vue';

    type VisitUser = {
        id: number;
        name: string;
        email: string;
        phone: string | null;
    };

    type VisitRecord = {
        id: number;
        session_id: string | null;
        ip_address: string | null;
        user_agent: string | null;
        path: string;
        page_label: string;
        visited_date: string;
        created_at: string | null;
        updated_at: string | null;
        device_type: 'mobile' | 'tablet' | 'desktop' | 'bot';
        device_label: string;
        device_model: string;
        os: string;
        browser: string;
        device_summary: string;
        user: VisitUser | null;
    };

    type VisitSummary = {
        total: number;
        maxLimit: number;
        today: number;
        desktop: number;
        mobile: number;
        tablet: number;
        bot: number;
        authenticated: number;
    };

    type AdminStat = {
        label: string;
        count: number;
        description: string;
    };

    const props = defineProps<{
        visits: VisitRecord[];
        visitSummary: VisitSummary;
        stats: AdminStat[];
        openRequestsCount: number;
    }>();

    const search = ref('');
    const deviceFilter = ref<'all' | 'desktop' | 'mobile' | 'tablet' | 'bot'>('all');
    const selectedVisit = ref<VisitRecord | null>(null);
    const deletingVisitId = ref<number | null>(null);
    const isRefreshing = ref(false);
    const isClearing = ref(false);

    const deviceBreakdown = computed(() => [
        {
            label: 'Máy tính (PC / Laptop)',
            description: 'Windows, macOS, Linux...',
            color: '#18352e',
            count: props.visitSummary.desktop,
        },
        {
            label: 'Điện thoại di động',
            description: 'iPhone, Android (Samsung, Xiaomi, OPPO...)',
            color: '#b59a65',
            count: props.visitSummary.mobile,
        },
        {
            label: 'Máy tính bảng (Tablet)',
            description: 'iPad, Android Tablet...',
            color: '#66805a',
            count: props.visitSummary.tablet,
        },
        {
            label: 'Bot / Công cụ tìm kiếm',
            description: 'Googlebot, Bingbot, Facebook...',
            color: '#8e7443',
            count: props.visitSummary.bot,
        },
    ]);

    const filteredVisits = computed(() => {
        const query = search.value.trim().toLocaleLowerCase();

        return props.visits.filter((visit) => {
            if (deviceFilter.value !== 'all' && visit.device_type !== deviceFilter.value) {
                return false;
            }

            if (!query) {
                return true;
            }

            const searchable = [
                visit.device_label,
                visit.device_model,
                visit.os,
                visit.browser,
                visit.ip_address,
                visit.path,
                visit.page_label,
                visit.user?.name,
                visit.user?.email,
                visit.user_agent,
            ]
                .filter(Boolean)
                .join(' ')
                .toLocaleLowerCase();

            return searchable.includes(query);
        });
    });

    function deviceIcon(deviceType: VisitRecord['device_type']): [string, string] {
        if (deviceType === 'mobile') {
            return ['fas', 'mobile-screen-button'];
        }
        if (deviceType === 'tablet') {
            return ['fas', 'tablet-screen-button'];
        }
        if (deviceType === 'bot') {
            return ['fas', 'robot'];
        }

        return ['fas', 'desktop'];
    }

    function shortSession(sessionId: string | null): string {
        if (!sessionId) {
            return '—';
        }

        return sessionId.length > 10 ? `${sessionId.slice(0, 10)}…` : sessionId;
    }

    function formatDate(value: string | null): string {
        if (!value) {
            return '—';
        }

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return '—';
        }

        return new Intl.DateTimeFormat('vi-VN', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        }).format(date);
    }

    function formatRelativeTime(value: string | null): string {
        if (!value) {
            return 'Vừa xong';
        }

        const timestamp = new Date(value).getTime();
        if (Number.isNaN(timestamp)) {
            return '—';
        }

        const diffSeconds = Math.max(0, Math.floor((Date.now() - timestamp) / 1000));
        if (diffSeconds < 60) {
            return 'Vừa xong';
        }

        const diffMinutes = Math.floor(diffSeconds / 60);
        if (diffMinutes < 60) {
            return `${diffMinutes} phút trước`;
        }

        const diffHours = Math.floor(diffMinutes / 60);
        if (diffHours < 24) {
            return `${diffHours} giờ trước`;
        }

        const diffDays = Math.floor(diffHours / 24);
        return `${diffDays} ngày trước`;
    }

    function clearFilters(): void {
        search.value = '';
        deviceFilter.value = 'all';
    }

    function refreshVisits(): void {
        isRefreshing.value = true;
        router.reload({
            onFinish: () => {
                isRefreshing.value = false;
            },
        });
    }

    function openDetail(visit: VisitRecord): void {
        selectedVisit.value = visit;
    }

    function closeDetail(): void {
        selectedVisit.value = null;
    }

    function deleteVisit(visit: VisitRecord): void {
        if (
            deletingVisitId.value !== null
            || !window.confirm(`Bạn có chắc muốn xóa lượt truy cập từ "${visit.device_model}" (${visit.ip_address || 'không rõ IP'}) không?`)
        ) {
            return;
        }

        deletingVisitId.value = visit.id;
        router.delete(`/admin/visits/${visit.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                if (selectedVisit.value?.id === visit.id) {
                    closeDetail();
                }
            },
            onFinish: () => {
                deletingVisitId.value = null;
            },
        });
    }

    function clearAllVisits(): void {
        if (
            isClearing.value
            || !window.confirm('Bạn có chắc muốn xóa toàn bộ danh sách lượt truy cập hiện tại không?')
        ) {
            return;
        }

        isClearing.value = true;
        router.delete('/admin/visits', {
            preserveScroll: true,
            onSuccess: () => {
                closeDetail();
            },
            onFinish: () => {
                isClearing.value = false;
            },
        });
    }

    function onEscape(event: KeyboardEvent): void {
        if (event.key === 'Escape') {
            closeDetail();
        }
    }

    watch(selectedVisit, (detail) => {
        document.body.style.overflow = detail ? 'hidden' : '';
    });

    onMounted(() => {
        window.addEventListener('keydown', onEscape);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('keydown', onEscape);
        document.body.style.overflow = '';
    });
</script>
