<template>
    <AdminLayout :stats="props.stats" :open-requests-count="props.openRequestsCount">
        <div class="page-head">
            <div>
                <h2>Quản lý danh mục</h2>
                <p>Quản lý danh mục chính và các dòng danh mục con.</p>
            </div>
            <div class="actions">
                <button class="btn primary" type="button" @click="openCreateParent">＋ Thêm danh mục</button>
                <input v-model="search" class="search" placeholder="Tìm danh mục...">
            </div>
        </div>

        <div v-if="filteredCategories.length" class="cardgrid">
            <article v-for="category in filteredCategories" :key="category.id" class="card">
                <div style="display:flex;align-items:start;justify-content:space-between;gap:10px">
                    <div class="category-heading-main">
                        <img v-if="category.image_url" class="category-image" :src="category.image_url" :alt="category.name">
                        <div>
                            <div class="eyebrow">{{ category.name.toLocaleUpperCase() }} · {{ category.children_count }} DÒNG CON</div>
                            <h3 style="font-size:17px;margin-top:8px">{{ category.name }}</h3>
                        </div>
                    </div>
                    <div class="row-actions">
                        <button class="mini" type="button" @click="openEditCategory(category)">Sửa</button>
                        <button class="mini danger" type="button" :disabled="isDeletingCategoryId === category.id" @click="deleteCategory(category)">
                            {{ isDeletingCategoryId === category.id ? 'Đang xóa...' : 'Xóa' }}
                        </button>
                    </div>
                </div>

                <p v-if="category.description">{{ category.description }}</p>
                <p v-if="category.description_2">{{ category.description_2 }}</p>

                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px">
                    <b style="font-size:10px">Dòng danh mục</b>
                    <button class="mini" type="button" @click="openCreateChild(category)">＋ Thêm dòng con</button>
                </div>
                <div v-if="category.children.length" class="tablewrap">
                    <table class="table" style="min-width:400px">
                        <thead>
                            <tr>
                                <th>Tên dòng danh mục</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="child in category.children" :key="child.id">
                                <td>
                                    <b class="category-child-name">{{ child.name }}</b>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button class="mini" type="button" @click="openEditCategory(child)">Sửa</button>
                                        <button
                                            class="mini danger"
                                            type="button"
                                            :disabled="isDeletingCategoryId === child.id"
                                            @click="deleteCategory(child)"
                                        >{{ isDeletingCategoryId === child.id ? 'Đang xóa...' : 'Xóa' }}</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="empty-children">Chưa có dòng danh mục con.</p>
            </article>
        </div>
        <div v-else class="card">
            {{ search ? 'Không tìm thấy danh mục phù hợp.' : 'Chưa có danh mục nào. Hãy thêm danh mục cha đầu tiên.' }}
        </div>

        <CategoryModal
            v-model:open="isCategoryModalOpen"
            :category="selectedCategory"
            :parent-id="selectedParentId"
            @saved="handleCategorySaved"
        />
        <div class="toast" :class="{ show: isToastVisible }" role="status" aria-live="polite">{{ toastMessage }}</div>
    </AdminLayout>
</template>

<script setup lang="ts">
    import { router } from '@inertiajs/vue3';
    import { computed, onBeforeUnmount, ref } from 'vue';
    import AdminLayout from '../../layout/AdminLayout.Vue';
    import CategoryModal from '../../components/CategoryModal.Vue';

    type Category = {
        id: number;
        parent_id: null;
        name: string;
        description: string | null;
        description_2: string | null;
        image_url: string | null;
        children_count: number;
        children: CategoryChild[];
    };

    type CategoryChild = {
        id: number;
        parent_id: number;
        name: string;
        description: string | null;
        description_2: string | null;
        image_url: string | null;
        products_count: number;
    };

    type CategoryRecord = Category | CategoryChild;

    type AdminStat = {
        label: string;
        count: number;
        description: string;
    };

    const props = defineProps<{
        categories: Category[];
        stats: AdminStat[];
        openRequestsCount: number;
    }>();

    const search = ref('');
    const isCategoryModalOpen = ref(false);
    const selectedCategory = ref<CategoryRecord | null>(null);
    const selectedParentId = ref<number | null>(null);
    const isDeletingCategoryId = ref<number | null>(null);
    const isToastVisible = ref(false);
    const toastMessage = ref('');
    let toastTimeout: ReturnType<typeof setTimeout> | undefined;

    const filteredCategories = computed(() => {
        const query = search.value.trim().toLocaleLowerCase();
        if (!query) {
            return props.categories;
        }

        return props.categories.filter((category) =>
            [category.name, category.description, category.description_2, ...category.children.map((child) => child.name)]
                .some((value) => value?.toLocaleLowerCase().includes(query)),
        );
    });

    function openCreateParent(): void {
        selectedCategory.value = null;
        selectedParentId.value = null;
        isCategoryModalOpen.value = true;
    }

    function openCreateChild(category: Category): void {
        selectedCategory.value = null;
        selectedParentId.value = category.id;
        isCategoryModalOpen.value = true;
    }

    function openEditCategory(category: CategoryRecord): void {
        selectedCategory.value = category;
        selectedParentId.value = null;
        isCategoryModalOpen.value = true;
    }

    function handleCategorySaved(action: 'created' | 'updated', isChild: boolean): void {
        if (action === 'updated') {
            showToast(isChild ? 'Đã cập nhật dòng danh mục thành công.' : 'Đã cập nhật danh mục chính thành công.');
            return;
        }

        showToast(isChild ? 'Đã tạo dòng danh mục thành công.' : 'Đã tạo danh mục cha thành công.');
    }

    function deleteCategory(category: CategoryRecord): void {
        if (isDeletingCategoryId.value !== null) {
            return;
        }

        const isChild = category.parent_id !== null;
        const categoryType = isChild ? 'dòng danh mục' : 'danh mục chính';
        if (!window.confirm(`Bạn có chắc muốn xóa ${categoryType} "${category.name}"? Danh mục đang được sử dụng sẽ không thể xóa.`)) {
            return;
        }

        isDeletingCategoryId.value = category.id;
        router.delete(`/admin/categories/${category.id}`, {
            preserveScroll: true,
            onSuccess: () => showToast(isChild ? 'Đã xóa dòng danh mục thành công.' : 'Đã xóa danh mục chính thành công.'),
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                showToast(typeof firstError === 'string' ? firstError : 'Không thể xóa danh mục.');
            },
            onFinish: () => {
                isDeletingCategoryId.value = null;
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
    .category-child-name {
        white-space: nowrap;
    }

    .category-heading-main {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .category-image {
        width: 48px;
        height: 48px;
        object-fit: cover;
        border-radius: 10px;
    }

    .empty-children {
        margin: 0;
        color: #8994a8;
        font-size: 10px;
    }
</style>
