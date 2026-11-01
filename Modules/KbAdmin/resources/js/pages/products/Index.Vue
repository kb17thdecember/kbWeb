<template>
    <AdminLayout :stats="props.stats" :open-requests-count="props.openRequestsCount">
        <div class="page-head">
            <div>
                <h2>Quản lý sản phẩm</h2>
                <p>Thêm, sửa, xóa mặt hàng và quản lý thư viện ảnh.</p>
            </div>
            <div class="actions">
                <button class="btn primary" type="button" @click="createProduct">＋ Thêm sản phẩm</button>
                <input v-model="search" class="search" type="search" placeholder="Tìm sản phẩm..." aria-label="Tìm sản phẩm">
            </div>
        </div>

        <div class="filters">
            <div class="filter-group">
                <span>TỪ NGÀY</span>
                <AdminDatePicker v-model="dateFrom" placeholder="Chọn từ ngày..." aria-label="Lọc sản phẩm từ ngày" />
            </div>
            <div class="filter-group">
                <span>ĐẾN NGÀY</span>
                <AdminDatePicker v-model="dateTo" placeholder="Chọn đến ngày..." aria-label="Lọc sản phẩm đến ngày" />
            </div>
            <button
                v-if="dateFrom || dateTo"
                class="btn"
                type="button"
                @click="clearDates"
            >Xóa lọc ngày</button>
            <em>{{ filteredProducts.length }} / {{ products.length }} sản phẩm</em>
        </div>

        <div v-if="filteredProducts.length" class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>Danh mục</th>
                        <th>Giá tham khảo</th>
                        <th>Ảnh</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="product in paginatedProducts" :key="product.id">
                        <td>
                            <div class="item">
                                <div class="thumb">
                                    <img v-if="product.image?.[0]" :src="product.image[0]" :alt="product.name">
                                    <span v-else>{{ product.name.charAt(0).toLocaleUpperCase() }}</span>
                                </div>
                                <div>
                                    <b>{{ product.name }}</b>
                                    <small>{{ product.product_title }}</small>
                                    <small>{{ product.tag }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            {{ product.category?.name ?? 'Không xác định' }} -
                            <small>{{ product.subcategory?.name ?? 'Chưa có dòng sản phẩm' }}</small>
                        </td>
                        <td>{{ product.price }}</td>
                        <td>{{ product.image?.length ?? 0 }}</td>
                        <td>
                            <div class="row-actions">
                                <button
                                    class="request-eye"
                                    type="button"
                                    :aria-label="`Xem chi tiết sản phẩm ${product.name}`"
                                    title="Xem chi tiết"
                                    @click="openDetail(product)"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
                                <button class="mini" type="button" @click="editProduct(product)">Sửa</button>
                                <button
                                    class="mini danger"
                                    type="button"
                                    :disabled="deletingProductId === product.id"
                                    @click="deleteProduct(product)"
                                >{{ deletingProductId === product.id ? 'Đang xóa...' : 'Xóa' }}</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="empty">
            {{
                products.length
                    ? 'Không tìm thấy sản phẩm phù hợp với điều kiện lọc.'
                    : 'Chưa có sản phẩm nào.'
            }}
        </div>
        <AdminPagination
            :current-page="currentPage"
            :total-items="filteredProducts.length"
            @page-change="goToPage"
        />

        <div v-if="detailProduct" class="modal open" role="presentation" @click.self="closeDetail">
            <section
                class="dialog request-detail-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="productDetailTitle"
            >
                <button class="close" type="button" aria-label="Đóng chi tiết" @click="closeDetail">
                    <FontAwesomeIcon :icon="['fas', 'xmark']" />
                </button>
                <div class="dialog-head">
                    <div class="eyebrow">PRODUCT / DETAILS</div>
                    <div class="request-detail-head">
                        <span class="request-detail-avatar">{{ detailProduct.name.charAt(0).toLocaleUpperCase() }}</span>
                        <div>
                            <h2 id="productDetailTitle">{{ detailProduct.name }}</h2>
                            <p>Mã sản phẩm: #{{ detailProduct.id }}</p>
                        </div>
                    </div>
                </div>
                <div class="request-detail-grid">
                    <div class="request-detail-item">
                        <small>Tên sản phẩm</small>
                        <div>{{ detailProduct.name }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Tiêu đề phụ</small>
                        <div>{{ detailProduct.product_title || '—' }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Danh mục chính</small>
                        <div>{{ detailProduct.category?.name ?? 'Không xác định' }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Dòng sản phẩm</small>
                        <div>{{ detailProduct.subcategory?.name ?? 'Chưa có dòng sản phẩm' }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Giá tham khảo</small>
                        <div>{{ detailProduct.price }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Ngày tạo</small>
                        <div>{{ formatDate(detailProduct.created_at) }}</div>
                    </div>
                    <div class="request-detail-item full">
                        <small>Mô tả sản phẩm</small>
                        <div>{{ detailProduct.description || '—' }}</div>
                    </div>
                </div>
                <div class="request-detail-actions">
                    <button class="btn" type="button" @click="editFromDetail(detailProduct)">Chỉnh sửa</button>
                    <button class="btn primary" type="button" @click="closeDetail">Đóng</button>
                </div>
            </section>
        </div>

        <ProductModal
            v-model:open="isProductModalOpen"
            :product="selectedProduct"
            :categories="categories"
            @saved="showToast($event === 'created' ? 'Đã tạo sản phẩm thành công.' : 'Đã cập nhật sản phẩm thành công.')"
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
    import ProductModal from '../../components/ProductModal.Vue';
    import { useClientPagination } from '../../composables/useClientPagination';

    type Product = {
        id: number;
        name: string;
        product_title: string | null;
        category_id: number;
        subcategory_id: number | null;
        tag: number | null;
        price: number;
        description: string;
        image: string[] | null;
        created_at?: string | null;
        category: { name: string } | null;
        subcategory: { name: string } | null;
    };

    type Category = {
        id: number;
        name: string;
        parent_id: number | null;
        children: Category[];
    };

    type AdminStat = {
        label: string;
        count: number;
        description: string;
    };

    const props = defineProps<{
        products: Product[];
        categories: Category[];
        stats: AdminStat[];
        openRequestsCount: number;
    }>();

    const isProductModalOpen = ref(false);
    const search = ref('');
    const dateFrom = ref('');
    const dateTo = ref('');
    const selectedProduct = ref<Product | null>(null);
    const detailProduct = ref<Product | null>(null);
    const deletingProductId = ref<number | null>(null);
    const isToastVisible = ref(false);
    const toastMessage = ref('');
    let toastTimeout: ReturnType<typeof setTimeout> | undefined;

    const filteredProducts = computed(() => {
        const query = search.value.trim().toLocaleLowerCase();

        return props.products.filter((product) => {
            const matchesQuery = !query || [
                product.name,
                product.product_title ?? '',
                product.category?.name ?? '',
                product.subcategory?.name ?? '',
                product.tag?.toString() ?? '',
            ].some((value) => value.toLocaleLowerCase().includes(query));
            const createdDate = dateKey(product.created_at);

            return (
                matchesQuery
                && (!dateFrom.value || (createdDate && createdDate >= dateFrom.value))
                && (!dateTo.value || (createdDate && createdDate <= dateTo.value))
            );
        });
    });
    const { currentPage, goToPage, paginatedItems: paginatedProducts, resetPage } = useClientPagination(filteredProducts);

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

    function openDetail(product: Product): void {
        detailProduct.value = product;
    }

    function closeDetail(): void {
        detailProduct.value = null;
    }

    function editFromDetail(product: Product): void {
        closeDetail();
        editProduct(product);
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

    function createProduct(): void {
        selectedProduct.value = null;
        isProductModalOpen.value = true;
    }

    onMounted(() => {
        if (new URLSearchParams(window.location.search).get('action') === 'create') {
            createProduct();
        }
    });

    function editProduct(product: Product): void {
        selectedProduct.value = product;
        isProductModalOpen.value = true;
    }

    function deleteProduct(product: Product): void {
        if (deletingProductId.value !== null || !window.confirm(`Bạn có chắc muốn xóa sản phẩm "${product.name}" không?`)) {
            return;
        }

        deletingProductId.value = product.id;
        router.delete(`/admin/products/${product.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                if (detailProduct.value?.id === product.id) {
                    closeDetail();
                }
                showToast('Đã xóa sản phẩm thành công.');
            },
            onError: () => showToast('Không thể xóa sản phẩm. Vui lòng thử lại.'),
            onFinish: () => {
                deletingProductId.value = null;
            },
        });
    }

    onBeforeUnmount(() => {
        if (toastTimeout) {
            clearTimeout(toastTimeout);
        }
    });
</script>
