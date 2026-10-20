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

        <!-- <div class="filters">
            <em>{{ filteredReports.length }} / {{ props.projectReports.length }} dự án</em>
        </div> -->

        <div v-if="filteredReports.length" class="tablewrap">
            <table class="table project-report-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên dự án</th>
                        <th>Mã dự án</th>
                        <th>Khu vực / Địa chỉ</th>
                        <th>Tiến độ</th>
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
                        <td>{{ report.project_update }}</td>
                        <td>
                            <div class="row-actions">
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
                search
                    ? 'Không tìm thấy dự án phù hợp.'
                    : 'Chưa có báo cáo dự án nào. Hãy thêm dự án đầu tiên.'
            }}
        </div>
        <AdminPagination
            :current-page="currentPage"
            :total-items="filteredReports.length"
            @page-change="goToPage"
        />

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
        project_update: string;
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
    const isModalOpen = ref(false);
    const selectedReport = ref<ProjectReport | null>(null);
    const deletingReportId = ref<number | null>(null);
    const isToastVisible = ref(false);
    const toastMessage = ref('');
    let toastTimeout: ReturnType<typeof setTimeout> | undefined;

    const filteredReports = computed(() => {
        const query = search.value.trim().toLocaleLowerCase();
        if (!query) {
            return props.projectReports;
        }

        return props.projectReports.filter((report) =>
            [
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
                report.project_update,
            ].some((value) => value.toLocaleLowerCase().includes(query)),
        );
    });
    const { currentPage, goToPage, paginatedItems: paginatedReports, resetPage } = useClientPagination(filteredReports);

    watch(search, resetPage);

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
            onSuccess: () => showToast('Đã xóa dự án thành công.'),
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
