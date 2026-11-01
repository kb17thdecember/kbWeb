<template>
    <AdminLayout :stats="props.stats" :open-requests-count="props.openRequestsCount">
        <div class="page-head">
            <div>
                <h2>Quản lý catalogue</h2>
                <p>Tải lên catalogue dạng PDF cho khách hàng xem và tải về.</p>
            </div>
            <div class="actions">
                <button class="btn primary" type="button" @click="createCatalogue">＋ Thêm catalogue</button>
                <input v-model="search" class="search" type="search" placeholder="Tìm catalogue..." aria-label="Tìm catalogue">
            </div>
        </div>

        <div class="filters">
            <div class="filter-group">
                <span>TỪ NGÀY</span>
                <AdminDatePicker v-model="dateFrom" placeholder="Chọn từ ngày..." aria-label="Lọc catalogue từ ngày" />
            </div>
            <div class="filter-group">
                <span>ĐẾN NGÀY</span>
                <AdminDatePicker v-model="dateTo" placeholder="Chọn đến ngày..." aria-label="Lọc catalogue đến ngày" />
            </div>
            <button
                v-if="dateFrom || dateTo"
                class="btn"
                type="button"
                @click="clearDates"
            >Xóa lọc ngày</button>
            <em>{{ filteredCatalogues.length }} / {{ catalogues.length }} catalogue</em>
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
                                <button
                                    class="request-eye"
                                    type="button"
                                    :aria-label="`Xem chi tiết catalogue ${catalogue.title}`"
                                    title="Xem chi tiết"
                                    @click="openDetail(catalogue)"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
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
        <div v-else class="empty">
            {{ catalogues.length ? 'Không tìm thấy catalogue phù hợp với điều kiện lọc.' : 'Chưa có catalogue nào. Hãy tải lên catalogue đầu tiên.' }}
        </div>
        <AdminPagination :current-page="currentPage" :total-items="filteredCatalogues.length" @page-change="goToPage" />

        <div v-if="detailCatalogue" class="modal open" role="presentation" @click.self="closeDetail">
            <section
                class="dialog request-detail-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="catalogueDetailTitle"
            >
                <button class="close" type="button" aria-label="Đóng chi tiết" @click="closeDetail">
                    <FontAwesomeIcon :icon="['fas', 'xmark']" />
                </button>
                <div class="dialog-head">
                    <div class="eyebrow">CATALOGUE / DETAILS</div>
                    <div class="request-detail-head">
                        <span class="request-detail-avatar">PDF</span>
                        <div>
                            <h2 id="catalogueDetailTitle">{{ detailCatalogue.title }}</h2>
                            <p>Mã tài liệu: #{{ detailCatalogue.id }}</p>
                        </div>
                    </div>
                </div>
                <div class="request-detail-grid">
                    <div class="request-detail-item">
                        <small>Tên tệp PDF</small>
                        <div>{{ detailCatalogue.file_name }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Dung lượng</small>
                        <div>{{ formatSize(detailCatalogue.file_size) }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Trạng thái</small>
                        <div>{{ detailCatalogue.is_published ? 'Đang hiển thị' : 'Bản nháp' }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Ngày tạo</small>
                        <div>{{ formatDate(detailCatalogue.created_at) }}</div>
                    </div>
                    <div class="request-detail-item full">
                        <small>Mô tả</small>
                        <div>{{ detailCatalogue.description || '—' }}</div>
                    </div>
                    <div class="request-detail-item full">
                        <small>Tệp đính kèm</small>
                        <div class="request-detail-attachment">
                            <span class="request-detail-attachment-name">📎 {{ detailCatalogue.file_name }}</span>
                            <div class="request-detail-attachment-actions">
                                <a class="btn primary" :href="detailCatalogue.file_url" target="_blank" rel="noopener">Mở xem PDF</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="request-detail-actions">
                    <button class="btn" type="button" @click="editFromDetail(detailCatalogue)">Chỉnh sửa</button>
                    <button class="btn primary" type="button" @click="closeDetail">Đóng</button>
                </div>
            </section>
        </div>

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
    import AdminDatePicker from '../../components/AdminDatePicker.Vue';
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
        created_at?: string | null;
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
    const dateFrom = ref('');
    const dateTo = ref('');
    const isModalOpen = ref(false);
    const selectedCatalogue = ref<Catalogue | null>(null);
    const detailCatalogue = ref<Catalogue | null>(null);
    const deletingId = ref<number | null>(null);
    const isToastVisible = ref(false);
    const toastMessage = ref('');
    let toastTimeout: ReturnType<typeof setTimeout> | undefined;

    const filteredCatalogues = computed(() => {
        const query = search.value.trim().toLocaleLowerCase();

        return props.catalogues.filter((catalogue) => {
            const matchesQuery = !query || [catalogue.title, catalogue.description ?? '', catalogue.file_name].some((value) =>
                value.toLocaleLowerCase().includes(query),
            );
            const createdDate = dateKey(catalogue.created_at);

            return (
                matchesQuery
                && (!dateFrom.value || (createdDate && createdDate >= dateFrom.value))
                && (!dateTo.value || (createdDate && createdDate <= dateTo.value))
            );
        });
    });
    const { currentPage, goToPage, paginatedItems: paginatedCatalogues, resetPage } = useClientPagination(filteredCatalogues);

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
        return value ? new Date(value).toLocaleDateString('vi-VN') : '—';
    }

    function clearDates(): void {
        dateFrom.value = '';
        dateTo.value = '';
    }

    function openDetail(catalogue: Catalogue): void {
        detailCatalogue.value = catalogue;
    }

    function closeDetail(): void {
        detailCatalogue.value = null;
    }

    function editFromDetail(catalogue: Catalogue): void {
        closeDetail();
        editCatalogue(catalogue);
    }

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
            onSuccess: () => {
                if (detailCatalogue.value?.id === catalogue.id) {
                    closeDetail();
                }
                showToast('Đã xóa catalogue thành công.');
            },
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
