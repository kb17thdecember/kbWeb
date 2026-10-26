<script setup lang="ts">
    import { computed } from 'vue';
    import { Link } from '@inertiajs/vue3';
    import AdminLayout from '../layout/AdminLayout.Vue';

    type AdminStat = {
        label: string;
        count: number;
        description: string;
    };

    type DailyRequestCount = {
        label: string;
        count: number;
    };

    type RequestStatuses = {
        pending: number;
        processing: number;
        resolved: number;
    };

    const props = defineProps<{
        stats: AdminStat[];
        openRequestsCount: number;
        recentRequests: DailyRequestCount[];
        requestStatuses: RequestStatuses;
    }>();

    const maxDailyRequests = computed(() => Math.max(1, ...props.recentRequests.map((day) => day.count)));
    const requestStatuses = computed(() => [
        { label: 'Mới', description: 'Chờ tiếp nhận', color: '#5066f6', count: props.requestStatuses.pending },
        { label: 'Đang xử lý', description: 'Đang theo dõi', color: '#e6a044', count: props.requestStatuses.processing },
        { label: 'Đã xử lý', description: 'Đã hoàn tất', color: '#32a879', count: props.requestStatuses.resolved },
    ]);
</script>

<template>
    <AdminLayout :stats="props.stats" :open-requests-count="props.openRequestsCount">
        <div class="cardgrid">
            <div class="card">
                <h3>Yêu cầu tư vấn trong 7 ngày gần nhất</h3>
                <p>Thống kê theo ngày tạo của từng yêu cầu.</p>

                <div class="chart">
                    <div v-for="day in props.recentRequests" :key="day.label" class="barcol">
                        <span>{{ day.count }}</span>
                        <div
                            class="bar"
                            :style="{ height: `${Math.max(4, (day.count / maxDailyRequests) * 100)}%` }"
                            :title="`${day.count} yêu cầu`"
                        ></div>
                        <span>{{ day.label }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3>Trạng thái xử lý yêu cầu</h3>
                <p>Theo dõi tiến độ tiếp nhận và xử lý khách hàng.</p>

                <div v-for="status in requestStatuses" :key="status.label" class="statusline">
                    <span class="dot" :style="{ background: status.color }"></span>
                    <div>
                        <b>{{ status.label }}</b>
                        <small>{{ status.description }}</small>
                    </div>
                    <strong>{{ status.count }}</strong>
                </div>

                <div style="margin-top:16px">
                    <Link class="btn primary" href="/admin/consultations">Xem yêu cầu tư vấn <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" /></Link>
                </div>
            </div>

            <div class="card">
                <h3>Thao tác nhanh</h3>
                <p>Cập nhật thông tin website.</p>

                <div class="shortcuts">
                    <Link class="shortcut" href="/admin/products?action=create">
                        ＋ Thêm sản phẩm
                        <small>Giá, mô tả, danh mục và thư viện ảnh</small>
                    </Link>

                    <Link class="shortcut" href="/admin/case-studies?action=create">
                        ＋ Thêm dự án
                        <small>Nhóm, vị trí, hạng mục và hình ảnh</small>
                    </Link>

                    <Link class="shortcut" href="/admin/categories">
                        ✎ Quản lý danh mục
                        <small>Thêm, sửa hoặc xóa nhóm</small>
                    </Link>

                    <Link class="shortcut" href="/admin/consultations">
                        ☷ Xử lý yêu cầu
                        <small>Xem yêu cầu và cập nhật trạng thái</small>
                    </Link>
                </div>
            </div>

            <div class="card">
                <h3>Trạng thái dữ liệu</h3>
                <p>Sản phẩm, dự án, danh mục và yêu cầu tư vấn được lưu trong cơ sở dữ liệu của website.</p>

                <span class="badge">● Lưu trên máy chủ</span>

                <p style="margin-top:13px">
                    Thay đổi trong khu vực quản trị được dùng trực tiếp trên website, không phụ thuộc localStorage của trình duyệt.
                </p>

                <div class="actions">
                    <Link class="btn" href="/admin/products">Quản lý sản phẩm</Link>
                    <Link class="btn" href="/admin/case-studies">Quản lý dự án</Link>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
