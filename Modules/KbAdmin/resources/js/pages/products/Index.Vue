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
        <div class="tablewrap">
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
                                    <span>T</span>
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
        <AdminPagination
            :current-page="currentPage"
            :total-items="filteredProducts.length"
            @page-change="goToPage"
        />
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
    const selectedProduct = ref<Product | null>(null);
    const deletingProductId = ref<number | null>(null);
    const isToastVisible = ref(false);
    const toastMessage = ref('');
    let toastTimeout: ReturnType<typeof setTimeout> | undefined;

    const filteredProducts = computed(() => {
        const query = search.value.trim().toLocaleLowerCase();
        if (!query) {
            return props.products;
        }

        return props.products.filter((product) => [
            product.name,
            product.product_title ?? '',
            product.category?.name ?? '',
            product.subcategory?.name ?? '',
            product.tag?.toString() ?? '',
        ].some((value) => value.toLocaleLowerCase().includes(query)));
    });
    const { currentPage, goToPage, paginatedItems: paginatedProducts, resetPage } = useClientPagination(filteredProducts);

    watch(search, resetPage);

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
            onSuccess: () => showToast('Đã xóa sản phẩm thành công.'),
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
