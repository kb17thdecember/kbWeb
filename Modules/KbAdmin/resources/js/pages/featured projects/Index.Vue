<template>
    <AdminLayout :stats="props.stats" :open-requests-count="props.openRequestsCount">
        <div class="page-head">
            <div>
                <h2>Quản lý dự án tiêu biểu</h2>
                <p>Cập nhật thông tin công trình, sản phẩm sử dụng thực tế và hình ảnh dự án.</p>
            </div>
            <div class="actions">
                <button class="btn primary" type="button" @click="createProject">＋ Thêm dự án</button>
                <input v-model="search" class="search" type="search" placeholder="Tìm dự án..." aria-label="Tìm dự án tiêu biểu">
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
            <em>{{ filteredProjects.length }} / {{ projects.length }} dự án</em>
        </div>

        <div v-if="filteredProjects.length" class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Dự án</th>
                        <th>Hạng mục</th>
                        <th>Nhóm</th>
                        <th>Vật tư &amp; Sản phẩm</th>
                        <th>Ảnh</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="project in paginatedProjects" :key="project.id">
                        <td>
                            <div class="item">
                                <div class="thumb">
                                    <img v-if="project.image_url" :src="project.image_url" :alt="project.name">
                                    <span v-else>{{ project.name.charAt(0).toLocaleUpperCase() }}</span>
                                </div>
                                <div>
                                    <b>{{ project.name }}</b>
                                    <small>{{ project.location }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="project-admin-products">
                                <span v-for="catName in splitCsv(project.category)" :key="catName" class="project-admin-product-chip">
                                    {{ catName }}
                                </span>
                            </div>
                            <small v-if="project.subcategory" class="muted">{{ project.subcategory }}</small>
                        </td>
                        <td>{{ projectTypeLabel(project.type) }}</td>
                        <td>
                            <div v-if="project.products?.length" class="project-admin-products">
                                <span v-for="prodName in project.products.slice(0, 3)" :key="prodName" class="project-admin-product-chip">
                                    {{ prodName }}
                                </span>
                                <small v-if="project.products.length > 3" class="muted">+{{ project.products.length - 3 }} khác</small>
                            </div>
                            <span v-else class="muted">Tự động theo hạng mục</span>
                        </td>
                        <td>{{ project.images_url.length }} ảnh</td>
                        <td>
                            <div class="row-actions">
                                <button
                                    class="request-eye"
                                    type="button"
                                    :aria-label="`Xem chi tiết dự án ${project.name}`"
                                    title="Xem chi tiết"
                                    @click="openDetail(project)"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
                                <button class="mini" type="button" @click="editProject(project)">Sửa</button>
                                <button
                                    class="mini danger"
                                    type="button"
                                    :disabled="deletingProjectId === project.id"
                                    @click="deleteProject(project)"
                                >{{ deletingProjectId === project.id ? 'Đang xóa...' : 'Xóa' }}</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="empty">
            {{ projects.length ? 'Không tìm thấy dự án phù hợp với điều kiện lọc.' : 'Chưa có dự án tiêu biểu nào. Hãy thêm dự án đầu tiên.' }}
        </div>
        <AdminPagination
            :current-page="currentPage"
            :total-items="filteredProjects.length"
            @page-change="goToPage"
        />

        <div v-if="detailProject" class="modal open" role="presentation" @click.self="closeDetail">
            <section
                class="dialog request-detail-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="featuredProjectDetailTitle"
            >
                <button class="close" type="button" aria-label="Đóng chi tiết" @click="closeDetail">
                    <FontAwesomeIcon :icon="['fas', 'xmark']" />
                </button>
                <div class="dialog-head">
                    <div class="eyebrow">FEATURED PROJECT / DETAILS</div>
                    <div class="request-detail-head">
                        <span class="request-detail-avatar">{{ detailProject.name.charAt(0).toLocaleUpperCase() }}</span>
                        <div>
                            <h2 id="featuredProjectDetailTitle">{{ detailProject.name }}</h2>
                            <p>Địa điểm: {{ detailProject.location }}</p>
                        </div>
                    </div>
                </div>
                <div class="request-detail-grid">
                    <div class="request-detail-item">
                        <small>Tên công trình</small>
                        <div>{{ detailProject.name }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Địa điểm</small>
                        <div>{{ detailProject.location }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Hạng mục chính</small>
                        <div>{{ detailProject.category }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Dòng sản phẩm</small>
                        <div>{{ detailProject.subcategory }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Nhóm công trình</small>
                        <div>{{ projectTypeLabel(detailProject.type) }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Ngày tạo</small>
                        <div>{{ formatDate(detailProject.created_at) }}</div>
                    </div>
                    <div class="request-detail-item full">
                        <small>Vật tư &amp; sản phẩm sử dụng</small>
                        <div>{{ detailProject.products?.length ? detailProject.products.join(', ') : 'Tự động theo hạng mục' }}</div>
                    </div>
                    <div class="request-detail-item full">
                        <small>Mô tả dự án</small>
                        <div>{{ detailProject.description }}</div>
                    </div>
                </div>
                <div class="request-detail-actions">
                    <button class="btn" type="button" @click="editFromDetail(detailProject)">Chỉnh sửa</button>
                    <button class="btn primary" type="button" @click="closeDetail">Đóng</button>
                </div>
            </section>
        </div>

        <FeaturedProjectModal
            v-model:open="isProjectModalOpen"
            :project="selectedProject"
            :categories="props.categories"
            :products="props.products ?? []"
            @saved="showToast($event === 'created' ? 'Đã tạo dự án thành công.' : 'Đã cập nhật dự án thành công.')"
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
    import FeaturedProjectModal from '../../components/FeaturedProjectModal.Vue';
    import { useAdminSearchParam, useClientPagination } from '../../composables/useClientPagination';

    type FeaturedProject = {
        id: number;
        name: string;
        description: string;
        location: string;
        category: string;
        subcategory: string;
        products?: string[] | null;
        type: string;
        image: string;
        image_url: string | null;
        images: string[] | null;
        images_url: string[];
        created_at?: string | null;
    };

    type Category = {
        id: number;
        name: string;
        children: Array<{
            id: number;
            name: string;
            parent_id: number;
        }>;
    };

    type AdminProduct = {
        id: number;
        name: string;
        category_id: number;
        subcategory_id: number | null;
        category?: { id: number; name: string } | null;
        subcategory?: { id: number; name: string } | null;
    };

    type AdminStat = {
        label: string;
        count: number;
        description: string;
    };

    const props = defineProps<{
        projects: FeaturedProject[];
        categories: Category[];
        products?: AdminProduct[];
        stats: AdminStat[];
        openRequestsCount: number;
    }>();

    const search = useAdminSearchParam();
    const dateFrom = ref('');
    const dateTo = ref('');
    const isProjectModalOpen = ref(false);
    const selectedProject = ref<FeaturedProject | null>(null);
    const detailProject = ref<FeaturedProject | null>(null);
    const deletingProjectId = ref<number | null>(null);
    const isToastVisible = ref(false);
    const toastMessage = ref('');
    let toastTimeout: ReturnType<typeof setTimeout> | undefined;

    const filteredProjects = computed(() => {
        const query = search.value.trim().toLocaleLowerCase();

        return props.projects.filter((project) => {
            const matchesQuery = !query || [
                project.name,
                project.description,
                project.location,
                project.category,
                project.subcategory,
                ...(project.products ?? []),
                projectTypeLabel(project.type),
            ].some((value) => value.toLocaleLowerCase().includes(query));
            const createdDate = dateKey(project.created_at);

            return (
                matchesQuery
                && (!dateFrom.value || (createdDate && createdDate >= dateFrom.value))
                && (!dateTo.value || (createdDate && createdDate <= dateTo.value))
            );
        });
    });
    const { currentPage, goToPage, paginatedItems: paginatedProjects, resetPage } = useClientPagination(filteredProjects);

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

    function formatDate(value?: string | null): string {
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
        }).format(date);
    }

    function clearDates(): void {
        dateFrom.value = '';
        dateTo.value = '';
    }

    function openDetail(project: FeaturedProject): void {
        detailProject.value = project;
    }

    function closeDetail(): void {
        detailProject.value = null;
    }

    function editFromDetail(project: FeaturedProject): void {
        closeDetail();
        editProject(project);
    }

    function projectTypeLabel(type: string): string {
        const labels: Record<string, string> = {
            '1': 'Chung cư thương mại & NOXH',
            '2': 'Văn phòng & Khách sạn & Bệnh viện',
            '3': 'Khu công nghiệp',
        };

        return labels[type] ?? type;
    }

    function splitCsv(value?: string | null): string[] {
        if (!value) {
            return [];
        }

        return value
            .split(',')
            .map((item) => item.trim())
            .filter(Boolean);
    }

    function createProject(): void {
        selectedProject.value = null;
        isProjectModalOpen.value = true;
    }

    onMounted(() => {
        if (new URLSearchParams(window.location.search).get('action') === 'create') {
            createProject();
        }
    });

    function editProject(project: FeaturedProject): void {
        selectedProject.value = project;
        isProjectModalOpen.value = true;
    }

    function deleteProject(project: FeaturedProject): void {
        if (deletingProjectId.value !== null || !window.confirm(`Bạn có chắc muốn xóa dự án "${project.name}" không?`)) {
            return;
        }

        deletingProjectId.value = project.id;
        router.delete(`/admin/case-studies/${project.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                if (detailProject.value?.id === project.id) {
                    closeDetail();
                }
                showToast('Đã xóa dự án thành công.');
            },
            onError: () => showToast('Không thể xóa dự án. Vui lòng thử lại.'),
            onFinish: () => {
                deletingProjectId.value = null;
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
    .project-admin-products {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 5px;
        max-width: 280px;
    }

    .project-admin-product-chip {
        display: inline-flex;
        align-items: center;
        padding: 3px 8px;
        border: 1px solid #dfd5c0;
        border-radius: 3px;
        background: #f8f4ec;
        color: #554b37;
        font-size: 11px;
        font-weight: 700;
        line-height: 1.3;
    }
</style>
