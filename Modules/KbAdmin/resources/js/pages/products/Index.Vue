<template>
    <AdminLayout>
        <div class="page-head">
            <div>
                <h2>Quản lý sản phẩm</h2>
                <p>Thêm, sửa, xóa mặt hàng và quản lý thư viện ảnh.</p>
            </div>
            <div class="actions">
                <button class="btn primary" type="button" @click="isProductModalOpen = true">＋ Thêm sản phẩm</button>
                <input class="search" id="localSearch" placeholder="Tìm sản phẩm..." value="">
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
                    <tr v-for="product in products" :key="product.id">
                        <td>
                            <div class="item">
                                <div class="thumb">
                                    <span>T</span>
                                </div>
                                <div>
                                    <b>{{ product.name }}</b>
                                    <small>{{ product.tag }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            Ariston<small>{{ product.category_id }}</small>
                        </td>
                        <td>{{ product.price }}</td>
                        <td>{{ product.image.length }}</td>
                        <td>
                            <div class="row-actions">
                                <button class="mini" data-edit-product="1">Sửa</button>
                                <button class="mini danger" data-delete-product="1">Xóa</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <ProductModal v-model:open="isProductModalOpen" />
    </AdminLayout>
</template>

<script setup lang="ts">
    import { ref } from 'vue';
    import AdminLayout from '../../layout/AdminLayout.Vue';
    import ProductModal from '../../components/ProductModal.Vue';

    const props = defineProps({
        products: {
            type: Array,
            default: () => [],
        },
    });

    const isProductModalOpen = ref(false);

</script>
