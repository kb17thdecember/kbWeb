<template>
    <AdminLayout :stats="props.stats" :open-requests-count="props.openRequestsCount">
        <div class="page-head">
            <div>
                <h2>Quản lý dự án tiêu biểu</h2>
                <p>Cập nhật thông tin công trình và hình ảnh dự án.</p>
            </div>
            <div class="actions">
                <button class="btn primary" type="button" @click="createProject">＋ Thêm dự án</button>
                <input v-model="search" class="search" placeholder="Tìm dự án...">
            </div>
        </div>

        <div v-if="filteredProjects.length" class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Dự án</th>
                        <th>Hạng mục</th>
                        <th>Nhóm</th>
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
                        <td>{{ project.category }}</td>
                        <td>{{ project.subcategory }} · {{ projectTypeLabel(project.type) }}</td>
                        <td>{{ project.images_url.length }} ảnh</td>
                        <td>
                            <div class="row-actions">
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
        <div v-else class="card">
            {{ search ? 'Không tìm thấy dự án phù hợp.' : 'Chưa có dự án tiêu biểu nào. Hãy thêm dự án đầu tiên.' }}
        </div>
        <AdminPagination
            :current-page="currentPage"
            :total-items="filteredProjects.length"
            @page-change="goToPage"
        />

        <FeaturedProjectModal
            v-model:open="isProjectModalOpen"
            :project="selectedProject"
            :categories="props.categories"
            @saved="showToast($event === 'created' ? 'Đã tạo dự án thành công.' : 'Đã cập nhật dự án thành công.')"
        />
        <div class="toast" :class="{ show: isToastVisible }" role="status" aria-live="polite">{{ toastMessage }}</div>
    </AdminLayout>
</template>

<script setup lang="ts">
    import { router } from '@inertiajs/vue3';
    import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
    import AdminLayout from '../../layout/AdminLayout.Vue';
    import AdminPagination from '../../components/AdminPagination.Vue';
    import FeaturedProjectModal from '../../components/FeaturedProjectModal.Vue';
    import { useClientPagination } from '../../composables/useClientPagination';

    type FeaturedProject = {
        id: number;
        name: string;
        description: string;
        location: string;
        category: string;
        subcategory: string;
        type: string;
        image: string;
        image_url: string | null;
        images: string[] | null;
        images_url: string[];
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

    type AdminStat = {
        label: string;
        count: number;
        description: string;
    };

    const props = defineProps<{
        projects: FeaturedProject[];
        categories: Category[];
        stats: AdminStat[];
        openRequestsCount: number;
    }>();

    const search = ref('');
    const isProjectModalOpen = ref(false);
    const selectedProject = ref<FeaturedProject | null>(null);
    const deletingProjectId = ref<number | null>(null);
    const isToastVisible = ref(false);
    const toastMessage = ref('');
    let toastTimeout: ReturnType<typeof setTimeout> | undefined;

    const filteredProjects = computed(() => {
        const query = search.value.trim().toLocaleLowerCase();
        if (!query) {
            return props.projects;
        }

        return props.projects.filter((project) =>
            [
                project.name,
                project.description,
                project.location,
                project.category,
                project.subcategory,
                projectTypeLabel(project.type),
            ].some((value) => value.toLocaleLowerCase().includes(query)),
        );
    });
    const { currentPage, goToPage, paginatedItems: paginatedProjects, resetPage } = useClientPagination(filteredProjects);

    watch(search, resetPage);

    function projectTypeLabel(type: string): string {
        const labels: Record<string, string> = {
            '1': 'Chung cư thương mại & NOXH',
            '2': 'Văn phòng & Khách sạn & Bệnh viện',
            '3': 'Khu công nghiệp',
        };

        return labels[type] ?? type;
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
            onSuccess: () => showToast('Đã xóa dự án thành công.'),
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
