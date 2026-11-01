<template>
    <AdminLayout :stats="props.stats" :open-requests-count="props.openRequestsCount">
        <div class="page-head">
            <div>
                <h2>Báo cáo dự án</h2>
                <p>Quản lý thông tin dự án và tiến độ triển khai.</p>
            </div>
            <div class="actions">
                <button class="btn primary" type="button" @click="createReport">＋ Thêm dự án</button>
                <input
                    v-model="search"
                    class="search"
                    type="search"
                    placeholder="Tìm dự án..."
                    aria-label="Tìm báo cáo dự án"
                >
            </div>
        </div>

        <div class="filters">
            <div class="filter-group">
                <span>TỪ NGÀY</span>
                <AdminDatePicker v-model="dateFrom" placeholder="Chọn từ ngày..." aria-label="Lọc dự án từ ngày" />
            </div>
            <div class="filter-group">
                <span>ĐẾN NGÀY</span>
                <AdminDatePicker v-model="dateTo" placeholder="Chọn đến ngày..." aria-label="Lọc dự án đến ngày" />
            </div>
            <button
                v-if="dateFrom || dateTo"
                class="btn"
                type="button"
                @click="clearDates"
            >Xóa lọc ngày</button>
            <em>{{ filteredReports.length }} / {{ props.projectReports.length }} dự án</em>
        </div>

        <div v-if="filteredReports.length" class="tablewrap">
            <table class="table project-report-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên dự án</th>
                        <th>Mã dự án</th>
                        <th>Khu vực / Địa chỉ</th>
                        <th>Tiến độ</th>
                        <th>Tiến độ làm việc</th>
                        <th>Cập nhật gần nhất</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="report in paginatedReports" :key="report.id">
                        <td>{{ report.id }}</td>
                        <td>
                            <div class="item">
                                <div class="thumb">{{ report.project_name.charAt(0).toLocaleUpperCase() }}</div>
                                <div>
                                    <b>{{ report.project_name }}</b>
                                    <small>{{ report.developer || 'Chưa có đơn vị phát triển' }}</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ report.project_code }}</td>
                        <td>
                            <b>{{ report.project_area }}</b>
                            <small>{{ report.project_location || 'Chưa cập nhật địa chỉ' }}</small>
                        </td>
                        <td>{{ report.progress }}</td>
                        <td>{{ statusLabel(report.project_status) }}</td>
                        <td>{{ report.project_update }}</td>
                        <td>
                            <div class="row-actions">
                                <button
                                    class="request-eye"
                                    type="button"
                                    :aria-label="`Xem chi tiết báo cáo dự án ${report.project_name}`"
                                    title="Xem chi tiết"
                                    @click="openDetail(report)"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
                                <button class="mini" type="button" @click="editReport(report)">Sửa</button>
                                <button
                                    class="mini danger"
                                    type="button"
                                    :disabled="deletingReportId === report.id"
                                    @click="deleteReport(report)"
                                >{{ deletingReportId === report.id ? 'Đang xóa...' : 'Xóa' }}</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="empty">
            {{
                props.projectReports.length
                    ? 'Không tìm thấy dự án phù hợp với điều kiện lọc.'
                    : 'Chưa có báo cáo dự án nào. Hãy thêm dự án đầu tiên.'
            }}
        </div>
        <AdminPagination
            :current-page="currentPage"
            :total-items="filteredReports.length"
            @page-change="goToPage"
        />

        <div v-if="detailReport" class="modal open" role="presentation" @click.self="closeDetail">
            <section
                class="dialog request-detail-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="projectReportDetailTitle"
            >
                <button class="close" type="button" aria-label="Đóng chi tiết" @click="closeDetail">
                    <FontAwesomeIcon :icon="['fas', 'xmark']" />
                </button>
                <div class="dialog-head">
                    <div class="eyebrow">PROJECT REPORT / DETAILS</div>
                    <div class="request-detail-head">
                        <span class="request-detail-avatar">{{ detailReport.project_name.charAt(0).toLocaleUpperCase() }}</span>
                        <div>
                            <h2 id="projectReportDetailTitle">{{ detailReport.project_name }}</h2>
                            <p>Mã dự án: {{ detailReport.project_code }} · Khu vực: {{ detailReport.project_area }}</p>
                        </div>
                    </div>
                </div>
                <div class="request-detail-grid">
                    <div class="request-detail-item">
                        <small>Chủ đầu tư / Phát triển</small>
                        <div>{{ detailReport.developer || '—' }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Địa chỉ chi tiết</small>
                        <div>{{ detailReport.project_location || '—' }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Tư vấn M&amp;E</small>
                        <div>{{ detailReport.me_consultant || '—' }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Nhà thầu M&amp;E</small>
                        <div>{{ detailReport.me_contractor || '—' }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Tiến độ công trình</small>
                        <div>{{ detailReport.progress || '—' }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Tiến độ làm việc</small>
                        <div>{{ statusLabel(detailReport.project_status) }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Giá trị dự toán</small>
                        <div>{{ detailReport.estimate_value || '—' }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Người liên hệ</small>
                        <div>{{ detailReport.contact_person || '—' }}</div>
                    </div>
                    <div class="request-detail-item full">
                        <small>Sản phẩm tham gia</small>
                        <div>{{ detailReport.participating_products || '—' }}</div>
                    </div>
                    <div class="request-detail-item full">
                        <small>Cập nhật gần nhất</small>
                        <div>{{ detailReport.project_update || '—' }}</div>
                    </div>
                </div>
                <div class="request-detail-actions">
                    <button class="btn" type="button" @click="editFromDetail(detailReport)">Chỉnh sửa</button>
                    <button class="btn primary" type="button" @click="closeDetail">Đóng</button>
                </div>
            </section>
        </div>

        <ProjectReportModal
            v-model:open="isModalOpen"
            :project-report="selectedReport"
            :province-options="props.provinceOptions"
            @saved="showToast($event === 'created' ? 'Đã thêm dự án thành công.' : 'Đã cập nhật dự án thành công.')"
        />
        <div class="toast" :class="{ show: isToastVisible }" role="status" aria-live="polite">{{ toastMessage }}</div>
    </AdminLayout>
</template>

<script setup lang="ts">
    import { router } from '@inertiajs/vue3';
    import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
    import AdminLayout from '../../layout/AdminLayout.Vue';
    import AdminDatePicker from '../../components/AdminDatePicker.Vue';
    import AdminPagination from '../../components/AdminPagination.Vue';
    import ProjectReportModal from '../../components/ProjectReportModal.Vue';
    import { useClientPagination } from '../../composables/useClientPagination';

    type ProjectReport = {
        id: number;
        project_name: string;
        project_code: string;
        project_area: string;
        project_location: string | null;
        developer: string | null;
        me_consultant: string | null;
        me_contractor: string | null;
        progress: string | null;
        estimate_value: string | null;
        contact_person: string | null;
        participating_products: string | null;
        project_status: string;
        project_update: string;
        created_at?: string | null;
    };

    type AdminStat = {
        label: string;
        count: number;
        description: string;
    };

    const props = defineProps<{
        projectReports: ProjectReport[];
        provinceOptions: string[];
        stats: AdminStat[];
        openRequestsCount: number;
    }>();

    const search = ref('');
    const dateFrom = ref('');
    const dateTo = ref('');
    const isModalOpen = ref(false);
    const selectedReport = ref<ProjectReport | null>(null);
    const detailReport = ref<ProjectReport | null>(null);
    const deletingReportId = ref<number | null>(null);
    const isToastVisible = ref(false);
    const toastMessage = ref('');
    let toastTimeout: ReturnType<typeof setTimeout> | undefined;

    const filteredReports = computed(() => {
        const query = search.value.trim().toLocaleLowerCase();

        return props.projectReports.filter((report) => {
            const matchesQuery = !query || [
                report.project_name,
                report.project_code,
                report.project_area,
                report.project_location ?? '',
                report.developer ?? '',
                report.me_consultant ?? '',
                report.me_contractor ?? '',
                report.progress ?? '',
                report.estimate_value ?? '',
                report.contact_person ?? '',
                report.participating_products ?? '',
                report.project_update,
            ].some((value) => value.toLocaleLowerCase().includes(query));
            const createdDate = dateKey(report.created_at);

            return (
                matchesQuery
                && (!dateFrom.value || (createdDate && createdDate >= dateFrom.value))
                && (!dateTo.value || (createdDate && createdDate <= dateTo.value))
            );
        });
    });
    const { currentPage, goToPage, paginatedItems: paginatedReports, resetPage } = useClientPagination(filteredReports);

    watch([search, dateFrom, dateTo], resetPage);

    function dateKey(value?: string | null): string {
        if (!value) {
            return '';
        }

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return '';
        }

        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    }

    function clearDates(): void {
        dateFrom.value = '';
        dateTo.value = '';
    }

    function statusLabel(status: string): string {
        return {
            '1': 'Đang triển khai',
            '2': 'Dự án đang nóng',
            '3': 'Dự án cần hỗ trợ',
        }[status] ?? 'Đang triển khai';
    }

    function openDetail(report: ProjectReport): void {
        detailReport.value = report;
    }

    function closeDetail(): void {
        detailReport.value = null;
    }

    function editFromDetail(report: ProjectReport): void {
        closeDetail();
        editReport(report);
    }

    function createReport(): void {
        selectedReport.value = null;
        isModalOpen.value = true;
    }

    onMounted(() => {
        if (new URLSearchParams(window.location.search).get('action') === 'create') {
            createReport();
        }
    });

    function editReport(report: ProjectReport): void {
        selectedReport.value = report;
        isModalOpen.value = true;
    }

    function deleteReport(report: ProjectReport): void {
        if (
            deletingReportId.value !== null
            || !window.confirm(`Bạn có chắc muốn xóa báo cáo dự án "${report.project_name}" không?`)
        ) {
            return;
        }

        deletingReportId.value = report.id;
        router.delete(`/admin/project-reports/${report.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                if (detailReport.value?.id === report.id) {
                    closeDetail();
                }
                showToast('Đã xóa dự án thành công.');
            },
            onError: () => showToast('Không thể xóa dự án. Vui lòng thử lại.'),
            onFinish: () => {
                deletingReportId.value = null;
            },
        });
    }

    function showToast(message: string): void {
        if (toastTimeout) {
            clearTimeout(toastTimeout);
        }

        toastMessage.value = message;
        isToastVisible.value = true;
        toastTimeout = setTimeout(() => {
            isToastVisible.value = false;
            toastTimeout = undefined;
        }, 2400);
    }

    onBeforeUnmount(() => {
        if (toastTimeout) {
            clearTimeout(toastTimeout);
        }
    });
</script>

<style scoped>
    .project-report-table {
        min-width: 920px;
    }

    .project-report-table td > small {
        display: block;
        margin-top: 4px;
        color: #929db0;
        font-size: 9px;
    }
</style>
