<template>
    <AdminLayout :stats="props.stats" :open-requests-count="props.openRequestsCount">
        <div class="page-head">
            <div>
                <h2>Quản lý catalogue</h2>
                <p>Tải lên catalogue dạng PDF cho khách hàng xem và tải về.</p>
            </div>
            <div class="actions">
                <button class="btn primary" type="button" @click="createCatalogue">＋ Thêm catalogue</button>
                <input v-model="search" class="search" placeholder="Tìm catalogue...">
            </div>
        </div>

        <div v-if="filteredCatalogues.length" class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Catalogue</th>
                        <th>File PDF</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="catalogue in paginatedCatalogues" :key="catalogue.id">
                        <td>
                            <div class="item">
                                <div class="thumb">
                                    <img v-if="catalogue.image_url" :src="catalogue.image_url" :alt="catalogue.title">
                                    <span v-else>PDF</span>
                                </div>
                                <div>
                                    <b>{{ catalogue.title }}</b>
                                    <small>{{ catalogue.description }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <a :href="catalogue.file_url" target="_blank" rel="noopener">{{ catalogue.file_name }}</a>
                            <small> · {{ formatSize(catalogue.file_size) }}</small>
                        </td>
                        <td>{{ catalogue.is_published ? 'Đang hiển thị' : 'Bản nháp' }}</td>
                        <td>
                            <div class="row-actions">
                                <button class="mini" type="button" @click="editCatalogue(catalogue)">Sửa</button>
                                <button
                                    class="mini danger"
                                    type="button"
                                    :disabled="deletingId === catalogue.id"
                                    @click="deleteCatalogue(catalogue)"
                                >{{ deletingId === catalogue.id ? 'Đang xóa...' : 'Xóa' }}</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="card">
            {{ search ? 'Không tìm thấy catalogue phù hợp.' : 'Chưa có catalogue nào. Hãy tải lên catalogue đầu tiên.' }}
        </div>
        <AdminPagination :current-page="currentPage" :total-items="filteredCatalogues.length" @page-change="goToPage" />

        <CatalogueModal
            v-model:open="isModalOpen"
            :catalogue="selectedCatalogue"
            @saved="showToast($event === 'created' ? 'Đã tạo catalogue thành công.' : 'Đã cập nhật catalogue thành công.')"
        />
        <div class="toast" :class="{ show: isToastVisible }" role="status" aria-live="polite">{{ toastMessage }}</div>
    </AdminLayout>
</template>

<script setup lang="ts">
    import { router } from '@inertiajs/vue3';
    import { computed, onBeforeUnmount, ref, watch } from 'vue';
    import AdminLayout from '../../layout/AdminLayout.Vue';
    import AdminPagination from '../../components/AdminPagination.Vue';
    import CatalogueModal from '../../components/CatalogueModal.Vue';
    import { useClientPagination } from '../../composables/useClientPagination';

    type Catalogue = {
        id: number;
        title: string;
        description: string | null;
        file_name: string;
        file_size: number;
        file_url: string;
        is_published: boolean;
        image_url: string | null;
    };

    type AdminStat = {
        label: string;
        count: number;
        description: string;
    };

    const props = defineProps<{
        catalogues: Catalogue[];
        stats: AdminStat[];
        openRequestsCount: number;
    }>();

    const search = ref('');
    const isModalOpen = ref(false);
    const selectedCatalogue = ref<Catalogue | null>(null);
    const deletingId = ref<number | null>(null);
    const isToastVisible = ref(false);
    const toastMessage = ref('');
    let toastTimeout: ReturnType<typeof setTimeout> | undefined;

    const filteredCatalogues = computed(() => {
        const query = search.value.trim().toLocaleLowerCase();

        if (!query) {
            return props.catalogues;
        }

        return props.catalogues.filter((catalogue) =>
            [catalogue.title, catalogue.description ?? '', catalogue.file_name].some((value) =>
                value.toLocaleLowerCase().includes(query),
            ),
        );
    });
    const { currentPage, goToPage, paginatedItems: paginatedCatalogues, resetPage } = useClientPagination(filteredCatalogues);

    watch(search, resetPage);

    function formatSize(bytes: number): string {
        return bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    function createCatalogue(): void {
        selectedCatalogue.value = null;
        isModalOpen.value = true;
    }

    function editCatalogue(catalogue: Catalogue): void {
        selectedCatalogue.value = catalogue;
        isModalOpen.value = true;
    }

    function deleteCatalogue(catalogue: Catalogue): void {
        if (deletingId.value !== null || !window.confirm(`Bạn có chắc muốn xóa catalogue "${catalogue.title}" không?`)) {
            return;
        }

        deletingId.value = catalogue.id;
        router.delete(`/admin/catalogues/${catalogue.id}`, {
            preserveScroll: true,
            onSuccess: () => showToast('Đã xóa catalogue thành công.'),
            onError: () => showToast('Không thể xóa catalogue. Vui lòng thử lại.'),
            onFinish: () => {
                deletingId.value = null;
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
