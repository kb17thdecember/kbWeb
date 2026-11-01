<template>
    <div
        class="modal"
        :class="{ open }"
        role="dialog"
        aria-modal="true"
        aria-labelledby="featuredProjectModalTitle"
        @click.self="closeModal"
    >
        <div class="dialog">
            <button class="close" type="button" aria-label="Đóng" :disabled="isSubmitting || isProcessingImage" @click="closeModal"><FontAwesomeIcon :icon="['fas', 'xmark']" /></button>
            <div class="dialog-head">
                <div class="eyebrow">ADMIN / FEATURED PROJECT</div>
                <h2 id="featuredProjectModalTitle">{{ project ? 'Sửa dự án' : 'Thêm dự án' }}</h2>
                <p>Thông tin công trình, sản phẩm thực tế sử dụng và hình ảnh dự án tiêu biểu.</p>
            </div>
            <form class="form" @submit.prevent="saveProject">
                <div class="formgrid">
                    <div class="field full">
                        <label for="featuredProjectName">Tên dự án</label>
                        <input id="featuredProjectName" v-model="name" maxlength="255" required placeholder="Tên dự án">
                    </div>
                    <div class="field full">
                        <label for="featuredProjectDescription">Mô tả</label>
                        <textarea id="featuredProjectDescription" v-model="description" required placeholder="Mô tả dự án"></textarea>
                    </div>
                    <div class="field full">
                        <label for="featuredProjectLocation">Vị trí</label>
                        <input id="featuredProjectLocation" v-model="location" maxlength="255" required placeholder="Ví dụ: Hà Nội">
                    </div>
                    <div class="field">
                        <label for="featuredProjectCategory">Hạng mục</label>
                        <select id="featuredProjectCategory" v-model="selectedCategory" required>
                            <option value="" disabled>Chọn hạng mục</option>
                            <option v-for="item in categories" :key="item.id" :value="item.name">
                                {{ item.name }}
                            </option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="featuredProjectSubcategory">Nhóm</label>
                        <select
                            id="featuredProjectSubcategory"
                            v-model="selectedSubcategory"
                            required
                            :disabled="!selectedCategory || !visibleSubcategories.length"
                        >
                            <option value="" disabled>{{ selectedCategory ? 'Chọn nhóm' : 'Chọn hạng mục trước' }}</option>
                            <option v-for="item in visibleSubcategories" :key="item.id" :value="item.name">
                                {{ item.name }}
                            </option>
                        </select>
                    </div>
                    <div class="field full">
                        <label for="featuredProjectType">Loại dự án</label>
                        <select id="featuredProjectType" v-model="type" required>
                            <option value="" disabled>Vui lòng chọn loại dự án</option>
                            <option value="1">Chung cư thương mại &amp; NOXH</option>
                            <option value="2">Văn phòng &amp; Khách sạn &amp; Bệnh viện</option>
                            <option value="3">Khu công nghiệp</option>
                        </select>
                    </div>
                    <div class="field full">
                        <div class="product-picker-head">
                            <label for="featuredProjectProductSearch">
                                Vật tư &amp; sản phẩm thực tế sử dụng
                                <span v-if="selectedProducts.length" class="product-picker-badge">
                                    Đã chọn {{ selectedProducts.length }}
                                </span>
                            </label>
                            <div class="product-picker-controls">
                                <button
                                    v-if="selectedCategory"
                                    type="button"
                                    class="product-filter-toggle"
                                    :class="{ active: filterBySelectedCategory }"
                                    @click="filterBySelectedCategory = !filterBySelectedCategory"
                                >
                                    {{ filterBySelectedCategory ? `Đang lọc: ${selectedCategory}` : 'Tất cả danh mục' }}
                                </button>
                                <button
                                    v-if="selectedProducts.length"
                                    type="button"
                                    class="product-clear-btn"
                                    @click="selectedProducts = []"
                                >
                                    Bỏ chọn hết
                                </button>
                            </div>
                        </div>

                        <div v-if="selectedProducts.length" class="selected-products-chips">
                            <span
                                v-for="prodName in selectedProducts"
                                :key="prodName"
                                class="selected-product-chip"
                            >
                                {{ prodName }}
                                <button
                                    type="button"
                                    :aria-label="`Bỏ chọn ${prodName}`"
                                    @click="toggleProductSelection(prodName)"
                                >×</button>
                            </span>
                        </div>

                        <input
                            id="featuredProjectProductSearch"
                            v-model="productSearchQuery"
                            type="search"
                            placeholder="Tìm sản phẩm theo tên hoặc nhóm vật tư..."
                        >

                        <div v-if="filteredPickerProducts.length" class="product-picker-grid" role="group" aria-label="Danh sách sản phẩm">
                            <button
                                v-for="item in filteredPickerProducts"
                                :key="item.id"
                                type="button"
                                class="product-picker-item"
                                :class="{ selected: selectedProducts.includes(item.name) }"
                                :aria-pressed="selectedProducts.includes(item.name)"
                                @click="toggleProductSelection(item.name)"
                            >
                                <span class="product-picker-check" aria-hidden="true">
                                    {{ selectedProducts.includes(item.name) ? '✓' : '+' }}
                                </span>
                                <span class="product-picker-copy">
                                    <b>{{ item.name }}</b>
                                    <small>{{ item.category?.name }}<template v-if="item.subcategory?.name"> · {{ item.subcategory.name }}</template></small>
                                </span>
                            </button>
                        </div>
                        <p v-else class="image-help">
                            Không tìm thấy sản phẩm phù hợp. Bạn có thể tắt bộ lọc hạng mục hoặc đổi từ khóa tìm kiếm.
                        </p>
                        <p class="image-help">
                            Chọn các sản phẩm thực tế đã cung ứng cho dự án. Nếu để trống, hệ thống sẽ tự động gợi ý sản phẩm theo Hạng mục &amp; Nhóm.
                        </p>
                    </div>
                    <div class="field full">
                        <label>Ảnh dự án (tối đa 3 ảnh)</label>
                        <label class="image-upload">
                            <div>
                                <b>＋ Chọn ảnh từ máy tính</b>
                                <small>JPG, PNG, WEBP · tối đa 5 MB/ảnh · có thể chọn nhiều ảnh</small>
                            </div>
                            <span aria-hidden="true">⇧</span>
                            <input
                                type="file"
                                accept="image/*"
                                multiple
                                :disabled="isProcessingImage || isSubmitting"
                                @change="handleImageFiles"
                            >
                        </label>
                        <div class="image-count" aria-live="polite">
                            {{ visibleImages.length }} / 3 ảnh
                            <span v-if="selectedImages.length && project"> · ảnh mới sẽ thay thế bộ ảnh hiện tại</span>
                        </div>
                        <div v-if="visibleImages.length" class="project-images-preview" aria-live="polite">
                            <div v-for="(image, index) in visibleImages" :key="image.key" class="project-image-preview-wrap">
                                <img class="project-image-preview" :src="image.url" :alt="`Ảnh dự án số ${index + 1}`">
                                <button
                                    v-if="selectedImages.length"
                                    type="button"
                                    class="image-remove"
                                    :aria-label="`Bỏ ảnh số ${index + 1}`"
                                    @click="removeSelectedImage(index)"
                                ><FontAwesomeIcon :icon="['fas', 'xmark']" /></button>
                            </div>
                        </div>
                        <p v-if="project" class="image-help">Không chọn ảnh mới để giữ nguyên bộ ảnh hiện tại.</p>
                    </div>
                </div>
                <div class="formfoot">
                    <button type="button" class="btn" :disabled="isSubmitting || isProcessingImage" @click="closeModal">Hủy</button>
                    <button type="submit" class="btn primary" :disabled="isSubmitting || isProcessingImage">
                        {{ isSubmitting ? 'Đang lưu...' : isProcessingImage ? 'Đang xử lý ảnh...' : project ? 'Cập nhật dự án' : 'Lưu dự án' }} <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" />
                    </button>
                </div>
            </form>
        </div>
        <div class="toast" :class="{ show: isToastVisible }" role="status" aria-live="polite">{{ toastMessage }}</div>
    </div>
</template>

<script setup lang="ts">
    import { router } from '@inertiajs/vue3';
    import { computed, onBeforeUnmount, ref, watch } from 'vue';

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

    const props = withDefaults(
        defineProps<{
            open: boolean;
            project: FeaturedProject | null;
            categories: Category[];
            products?: AdminProduct[];
        }>(),
        {
            products: () => [],
        },
    );

    const emit = defineEmits<{
        'update:open': [open: boolean];
        saved: [action: 'created' | 'updated'];
    }>();

    const name = ref('');
    const description = ref('');
    const location = ref('');
    const selectedCategory = ref('');
    const selectedSubcategory = ref('');
    const selectedProducts = ref<string[]>([]);
    const productSearchQuery = ref('');
    const filterBySelectedCategory = ref(true);
    const type = ref('');
    const selectedImages = ref<File[]>([]);
    const selectedImagePreviewUrls = ref<string[]>([]);
    const isProcessingImage = ref(false);
    const isSubmitting = ref(false);
    const toastMessage = ref('');
    const isToastVisible = ref(false);
    let toastTimeout: ReturnType<typeof setTimeout> | undefined;

    const visibleSubcategories = computed(() =>
        props.categories.find((item) => item.name === selectedCategory.value)?.children ?? [],
    );

    const filteredPickerProducts = computed(() => {
        const query = productSearchQuery.value.trim().toLocaleLowerCase();
        const catLower = selectedCategory.value.trim().toLocaleLowerCase();
        const subLower = selectedSubcategory.value.trim().toLocaleLowerCase();

        return [...props.products]
            .filter((product) => {
                if (filterBySelectedCategory.value && catLower !== '') {
                    const pCat = (product.category?.name ?? '').trim().toLocaleLowerCase();
                    if (pCat !== catLower && !selectedProducts.value.includes(product.name)) {
                        return false;
                    }
                }

                if (!query) {
                    return true;
                }

                const haystack = `${product.name} ${product.category?.name ?? ''} ${product.subcategory?.name ?? ''}`.toLocaleLowerCase();

                return haystack.includes(query);
            })
            .sort((a, b) => {
                const aSelected = selectedProducts.value.includes(a.name) ? 1 : 0;
                const bSelected = selectedProducts.value.includes(b.name) ? 1 : 0;
                if (aSelected !== bSelected) {
                    return bSelected - aSelected;
                }

                const aSub = (a.subcategory?.name ?? '').trim().toLocaleLowerCase() === subLower ? 1 : 0;
                const bSub = (b.subcategory?.name ?? '').trim().toLocaleLowerCase() === subLower ? 1 : 0;
                if (aSub !== bSub) {
                    return bSub - aSub;
                }

                return a.name.localeCompare(b.name, 'vi');
            });
    });

    const existingImages = computed(() => {
        if (props.project?.images_url.length) {
            return props.project.images_url;
        }

        return props.project?.image_url ? [props.project.image_url] : [];
    });
    const visibleImages = computed(() => {
        const urls = selectedImagePreviewUrls.value.length
            ? selectedImagePreviewUrls.value
            : existingImages.value;

        return urls.map((url, index) => ({
            key: `${selectedImages.value.length ? 'selected' : 'existing'}-${index}`,
            url,
        }));
    });

    watch(
        selectedCategory,
        () => {
            selectedSubcategory.value = visibleSubcategories.value[0]?.name ?? '';
        },
        { flush: 'sync' },
    );

    watch(
        () => props.open,
        (isOpen) => {
            if (isOpen) {
                resetForm();
            }
        },
    );

    function toggleProductSelection(productName: string): void {
        const index = selectedProducts.value.indexOf(productName);
        if (index >= 0) {
            selectedProducts.value.splice(index, 1);
            return;
        }

        selectedProducts.value.push(productName);
    }

    function resetForm(): void {
        name.value = props.project?.name ?? '';
        description.value = props.project?.description ?? '';
        location.value = props.project?.location ?? '';
        const parentCategory = props.categories.find((item) => item.name === props.project?.category);
        const childCategory = parentCategory?.children.find((item) => item.name === props.project?.subcategory);
        selectedCategory.value = parentCategory?.name ?? '';
        selectedSubcategory.value = childCategory?.name ?? '';
        selectedProducts.value = Array.isArray(props.project?.products) ? [...props.project.products] : [];
        productSearchQuery.value = '';
        filterBySelectedCategory.value = true;
        type.value = props.project?.type ?? '';
        clearSelectedImages();
        toastMessage.value = '';
        isToastVisible.value = false;
    }

    function closeModal(): void {
        if (!isSubmitting.value && !isProcessingImage.value) {
            emit('update:open', false);
        }
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

    async function handleImageFiles(event: Event): Promise<void> {
        const input = event.target;
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const files = Array.from(input.files ?? []);
        if (!files.length) {
            return;
        }

        if (files.length > 3) {
            showToast('Bạn chỉ có thể chọn tối đa 3 ảnh cho mỗi dự án.');
            input.value = '';
            return;
        }

        if (files.some((file) => !file.type.startsWith('image/'))) {
            showToast('Tệp đã chọn không phải ảnh. Vui lòng chỉ chọn tệp ảnh.');
            input.value = '';
            return;
        }

        if (files.some((file) => file.size > 5 * 1024 * 1024)) {
            showToast('Mỗi ảnh phải nhỏ hơn 5 MB.');
            input.value = '';
            return;
        }

        isProcessingImage.value = true;

        try {
            const optimizedImages = await Promise.all(files.map((file) => optimizeImage(file)));
            clearSelectedImages();
            selectedImages.value = optimizedImages;
            selectedImagePreviewUrls.value = optimizedImages.map((image) => URL.createObjectURL(image));
            showToast(`Đã chọn ${optimizedImages.length} ảnh dự án.`);
        } catch {
            showToast('Không thể xử lý ảnh. Vui lòng chọn lại ảnh.');
        } finally {
            input.value = '';
            isProcessingImage.value = false;
        }
    }

    async function optimizeImage(file: File): Promise<File> {
        if (typeof createImageBitmap !== 'function') {
            return file;
        }

        let bitmap: ImageBitmap;

        try {
            bitmap = await createImageBitmap(file);
        } catch {
            return file;
        }

        try {
            const scale = Math.min(1, 1400 / Math.max(bitmap.width, bitmap.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(bitmap.width * scale));
            canvas.height = Math.max(1, Math.round(bitmap.height * scale));

            const context = canvas.getContext('2d');
            if (!context) {
                return file;
            }

            context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            const blob = await new Promise<Blob | null>((resolve) => {
                canvas.toBlob(resolve, 'image/webp', 0.78);
            });

            return blob
                ? new File([blob], `${file.name.replace(/\.[^.]+$/, '')}.webp`, { type: 'image/webp' })
                : file;
        } finally {
            bitmap.close();
        }
    }

    function clearSelectedImages(): void {
        selectedImagePreviewUrls.value.forEach((url) => URL.revokeObjectURL(url));
        selectedImages.value = [];
        selectedImagePreviewUrls.value = [];
    }

    function removeSelectedImage(index: number): void {
        const previewUrl = selectedImagePreviewUrls.value[index];
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }

        selectedImages.value.splice(index, 1);
        selectedImagePreviewUrls.value.splice(index, 1);
    }

    function saveProject(): void {
        if (isSubmitting.value || isProcessingImage.value) {
            return;
        }
        if (!props.project && selectedImages.value.length === 0) {
            showToast('Vui lòng chọn ít nhất một ảnh cho dự án.');
            return;
        }

        isSubmitting.value = true;
        const data = {
            name: name.value,
            description: description.value,
            location: location.value,
            category: selectedCategory.value,
            subcategory: selectedSubcategory.value,
            products: selectedProducts.value,
            type: type.value,
            images: selectedImages.value.length ? selectedImages.value : null,
        };
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
                emit('saved', props.project ? 'updated' : 'created');
            },
            onError: (errors: Record<string, string>) => {
                const firstError = Object.values(errors)[0];
                showToast(typeof firstError === 'string' ? firstError : 'Không thể lưu dự án. Vui lòng kiểm tra thông tin.');
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        };

        if (props.project) {
            router.post(`/admin/case-studies/${props.project.id}`, { ...data, _method: 'put' }, options);
            return;
        }

        router.post('/admin/case-studies', data, options);
    }

    onBeforeUnmount(() => {
        clearSelectedImages();

        if (toastTimeout) {
            clearTimeout(toastTimeout);
        }
    });
</script>

<style scoped>
    .product-picker-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 6px;
    }

    .product-picker-head label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }

    .product-picker-badge {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 999px;
        background: #b59a65;
        color: #fff;
        font-size: 10px;
        font-weight: 800;
    }

    .product-picker-controls {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .product-filter-toggle,
    .product-clear-btn {
        border: 1px solid #d8cfbd;
        border-radius: 3px;
        background: #fffdf8;
        color: #5f5641;
        padding: 4px 9px;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        transition: all .18s;
    }

    .product-filter-toggle.active {
        border-color: #b59a65;
        background: #f5efe1;
        color: #6f582c;
    }

    .product-clear-btn:hover {
        border-color: #efc3c3;
        background: #fff5f5;
        color: #b94242;
    }

    .selected-products-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 8px;
    }

    .selected-product-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 9px;
        border: 1px solid #cdb78a;
        border-radius: 3px;
        background: #f6f0e2;
        color: #4a3f28;
        font-size: 11.5px;
        font-weight: 700;
    }

    .selected-product-chip button {
        border: 0;
        background: transparent;
        color: #8e7443;
        padding: 0;
        font-size: 14px;
        font-weight: 800;
        line-height: 1;
        cursor: pointer;
    }

    .selected-product-chip button:hover {
        color: #b42318;
    }

    .product-picker-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 7px;
        max-height: 196px;
        overflow-y: auto;
        margin-top: 8px;
        padding: 8px;
        border: 1px solid #e2dbcc;
        border-radius: 4px;
        background: #faf7f0;
    }

    .product-picker-item {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 8px 10px;
        border: 1px solid #e5decb;
        border-radius: 3px;
        background: #fffdf9;
        color: #353026;
        text-align: left;
        cursor: pointer;
        transition: border-color .18s, background .18s, box-shadow .18s;
    }

    .product-picker-item:hover {
        border-color: #b59a65;
        background: #fcf8ef;
    }

    .product-picker-item.selected {
        border-color: #b59a65;
        background: #f4ecda;
        box-shadow: inset 0 0 0 1px #b59a65;
    }

    .product-picker-check {
        width: 20px;
        height: 20px;
        border: 1px solid #cbb996;
        border-radius: 3px;
        background: #fff;
        color: #8e7443;
        font-size: 11px;
        font-weight: 900;
        display: grid;
        place-items: center;
        flex: none;
    }

    .product-picker-item.selected .product-picker-check {
        border-color: #9e824c;
        background: #9e824c;
        color: #fff;
    }

    .product-picker-copy {
        min-width: 0;
        display: grid;
        gap: 2px;
    }

    .product-picker-copy b {
        font-size: 12px;
        font-weight: 700;
        color: #2f2b21;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .product-picker-copy small {
        font-size: 10.5px;
        color: #7c7463;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    @media (max-width: 560px) {
        .product-picker-grid {
            grid-template-columns: 1fr;
        }
    }

    .image-upload {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        min-height: 62px;
        padding: 12px 14px;
        border: 1px dashed #cbd5e1;
        border-radius: 9px;
        background: #f8fafc;
        cursor: pointer;
    }

    .image-upload small {
        display: block;
        margin-top: 4px;
        color: #8994a8;
    }

    .image-upload input[type='file'] {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }

    .project-image-preview-wrap {
        position: relative;
        width: fit-content;
        margin-top: 10px;
    }

    .project-image-preview {
        display: block;
        width: min(220px, 100%);
        max-height: 160px;
        object-fit: contain;
        border: 1px solid #e5eaf2;
        border-radius: 9px;
    }

    .image-remove {
        position: absolute;
        top: 5px;
        right: 5px;
        width: 26px;
        height: 26px;
        border: 0;
        border-radius: 50%;
        background: #fff;
        color: #b42318;
        font-size: 20px;
        cursor: pointer;
    }

    .project-images-preview {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 9px;
        margin-top: 10px;
    }

    .project-images-preview .project-image-preview-wrap {
        width: 100%;
        min-width: 0;
        margin-top: 0;
    }

    .project-images-preview .project-image-preview {
        width: 100%;
        height: 125px;
        max-height: none;
        object-fit: cover;
    }
</style>
