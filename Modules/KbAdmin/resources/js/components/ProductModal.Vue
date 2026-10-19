<template>
    <div class="modal" :class="{ open }" role="dialog" aria-modal="true" aria-labelledby="modalTitle" @click.self="closeModal">
      <div class="dialog">
        <button class="close" type="button" aria-label="Đóng" :disabled="isSubmitting" @click="closeModal">×</button>
        <div class="dialog-head">
          <div class="eyebrow" id="modalEyebrow">ADMIN / EDITOR</div>
          <h2 id="modalTitle">{{ product ? 'Sửa sản phẩm' : 'Thêm sản phẩm' }}</h2>
          <p id="modalDesc">Các trường dưới đây dùng để hiển thị sản phẩm trên website.</p>
        </div>
        <form class="form" id="editorForm" data-type="product" @submit.prevent="saveProduct">
            <div class="formgrid" id="formFields">
                <div class="field full">
                    <label>Tên sản phẩm</label>
                    <input v-model="productName" name="name" type="text" maxlength="255" required placeholder="Tên sản phẩm">
                </div>
                <div class="field ">
                    <label>Danh mục chính</label>
                    <select v-model="selectedCategoryId" name="category_id" required>
                        <option value="" disabled>Chọn danh mục chính</option>
                        <option v-for="category in categories" :key="category.id" :value="String(category.id)">
                            {{ category.name }}
                        </option>
                    </select>
                </div>
                <div class="field ">
                    <label>Dòng sản phẩm</label>
                    <select
                        v-model="selectedSubcategory"
                        name="subcategory_id"
                        required
                        :disabled="!selectedCategoryId || !visibleSubcategories.length"
                    >
                        <option value="" disabled>
                            {{ selectedCategoryId ? 'Danh mục này chưa có dòng sản phẩm' : 'Chọn danh mục chính trước' }}
                        </option>
                        <option v-for="subcategory in visibleSubcategories" :key="subcategory.id" :value="String(subcategory.id)">
                            {{ subcategory.name }}
                        </option>
                    </select>
                </div>
                <div class="field ">
                    <label>Loại hàng hóa</label>
                    <input v-model="productTag" name="tag" type="number" min="0" step="1" placeholder="">
                </div>
                <div class="field ">
                    <label>Giá tham khảo (VND)</label>
                    <input v-model="productPrice" name="price" type="number" min="0" step="1" required>
                </div>
                <div class="field full">
                    <label>Mô tả chi tiết</label>
                    <textarea v-model="productDescription" name="description" required placeholder="Mô tả sản phẩm"></textarea>
                </div>
                <div class="field full image-field">
                    <label>THƯ VIỆN ẢNH — CHỌN NHIỀU ẢNH</label>
                    <div class="image-picker" data-image-picker="images">
                        <label class="image-upload">
                            <div>
                                <b>＋ Chọn ảnh từ máy tính</b>
                                <small>JPG, PNG, WEBP · có thể chọn nhiều ảnh cùng lúc</small>
                            </div>
                            <span aria-hidden="true">⇧</span>
                            <input
                                type="file"
                                accept="image/*"
                                multiple
                                aria-label="Chọn nhiều ảnh cùng lúc"
                                :disabled="isProcessingImages || isSubmitting"
                                @change="handleImageFiles"
                            >
                        </label>
                        <div class="image-count" aria-live="polite">
                            {{ imageEntries.length ? `Đang có ${imageEntries.length} ảnh · ${localImages.length} ảnh từ máy tính · ${imageUrlList.length} URL ảnh` : 'Chưa có ảnh. Bạn có thể chọn nhiều ảnh cùng lúc hoặc nhập URL ở trên.' }}
                        </div>
                        <div class="image-preview" aria-live="polite">
                            <div v-for="image in imageEntries" :key="`${image.kind}-${image.index}`" class="image-preview-card">
                                <img :src="image.src" :alt="image.label">
                                <button
                                    type="button"
                                    class="image-remove"
                                    :aria-label="`Xóa ${image.label}`"
                                    @click="removeImage(image.kind, image.index)"
                                >×</button>
                                <small>{{ image.label }}</small>
                            </div>
                        </div>
                        <div class="image-url-wrap">
                            <label class="image-url-label" for="productImageUrls">HOẶC NHẬP URL ẢNH (MỖI URL MỘT DÒNG)</label>
                            <textarea id="productImageUrls" v-model="imageUrls" name="images" placeholder="https://example.com/image.jpg"></textarea>
                        </div>
                        <p class="image-help">Ảnh tải từ máy sẽ được tối ưu trước khi thêm vào thư viện. Bạn có thể xóa từng ảnh trong phần xem trước.</p>
                    </div>
                </div>
            </div>
            <div class="formfoot">
                <button type="button" class="btn" id="cancelModal" :disabled="isSubmitting" @click="closeModal">Hủy</button>
                <button type="submit" class="btn primary" :disabled="isSubmitting || isProcessingImages">
                    {{ isSubmitting ? 'Đang lưu...' : product ? 'Cập nhật sản phẩm ↗' : 'Lưu sản phẩm ↗' }}
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

    type ImageEntry = {
        src: string;
        kind: 'url' | 'local';
        index: number;
        label: string;
    };

    type Product = {
        id: number;
        name: string;
        category_id: number;
        subcategory_id: number | null;
        tag: number | null;
        price: number;
        description: string;
        image: string[] | null;
    };

    type Category = {
        id: number;
        name: string;
        parent_id: number | null;
        children?: Category[];
    };

    const props = defineProps<{
        open: boolean;
        product: Product | null;
        categories: Category[];
    }>();

    const selectedCategoryId = ref('');
    const selectedSubcategory = ref('');
    const imageUrls = ref('');
    const localImages = ref<string[]>([]);
    const isProcessingImages = ref(false);
    const toastMessage = ref('');
    const isToastVisible = ref(false);
    let toastTimeout: ReturnType<typeof setTimeout> | undefined;

    const visibleSubcategories = computed(() =>
        props.categories.find((category) => String(category.id) === selectedCategoryId.value)?.children ?? [],
    );
    const imageUrlList = computed(() =>
        imageUrls.value.split(/\r?\n/).map((url) => url.trim()).filter(Boolean),
    );
    const imageEntries = computed<ImageEntry[]>(() => [
        ...imageUrlList.value.map((src, index) => ({
            src,
            kind: 'url' as const,
            index,
            label: `URL ảnh ${index + 1}`,
        })),
        ...localImages.value.map((src, index) => ({
            src,
            kind: 'local' as const,
            index,
            label: `Ảnh từ máy ${index + 1}`,
        })),
    ]);

    watch(
        selectedCategoryId,
        () => {
            selectedSubcategory.value = String(visibleSubcategories.value[0]?.id ?? '');
        },
        { flush: 'sync' },
    );

    watch(
        () => props.open,
        (isOpen) => {
            if (isOpen) {
                resetForm(props.product);
            }
        },
    );

    const emit = defineEmits<{
        'update:open': [open: boolean];
        saved: [action: 'created' | 'updated'];
    }>();

    const productName = ref('');
    const productTag = ref('1');
    const productPrice = ref('0');
    const productDescription = ref('');
    const isSubmitting = ref(false);

    function closeModal(): void {
        if (isSubmitting.value) {
            return;
        }

        emit('update:open', false);
    }

    function resetForm(product: Product | null): void {
        productName.value = product?.name ?? '';
        selectedCategoryId.value = product ? String(product.category_id) : '';
        selectedSubcategory.value = String(
            product?.subcategory_id ?? visibleSubcategories.value[0]?.id ?? '',
        );
        productTag.value = String(product?.tag ?? 1);
        productPrice.value = String(product?.price ?? 0);
        productDescription.value = product?.description ?? '';
        const existingImages = product?.image ?? [];
        imageUrls.value = existingImages.filter((image) => !image.startsWith('data:image/')).join('\n');
        localImages.value = existingImages.filter((image) => image.startsWith('data:image/'));
        toastMessage.value = '';
        isToastVisible.value = false;
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

    function readImageAsDataUrl(file: File): Promise<string> {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();

            reader.onload = () => {
                if (typeof reader.result === 'string') {
                    resolve(reader.result);
                    return;
                }

                reject(new Error(`Không đọc được ảnh "${file.name}".`));
            };
            reader.onerror = () => reject(new Error(`Không đọc được ảnh "${file.name}".`));
            reader.readAsDataURL(file);
        });
    }

    async function fileToImageData(file: File): Promise<string> {
        if (!file.type.startsWith('image/')) {
            throw new Error(`Tệp "${file.name}" không phải ảnh.`);
        }

        if (typeof createImageBitmap !== 'function') {
            return readImageAsDataUrl(file);
        }

        let bitmap: ImageBitmap;

        try {
            bitmap = await createImageBitmap(file);
        } catch {
            return readImageAsDataUrl(file);
        }

        try {
            const scale = Math.min(1, 1400 / Math.max(bitmap.width, bitmap.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(bitmap.width * scale));
            canvas.height = Math.max(1, Math.round(bitmap.height * scale));

            const context = canvas.getContext('2d');
            if (!context) {
                throw new Error('Không thể xử lý ảnh trên trình duyệt này.');
            }

            context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            return canvas.toDataURL('image/webp', 0.78);
        } catch {
            return readImageAsDataUrl(file);
        } finally {
            bitmap.close();
        }
    }

    async function handleImageFiles(event: Event): Promise<void> {
        const input = event.target;
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const selectedFiles = Array.from(input.files ?? []);
        if (!selectedFiles.length) {
            return;
        }

        const imageFiles = selectedFiles.filter((file) => file.type.startsWith('image/'));
        const rejectedFileCount = selectedFiles.length - imageFiles.length;

        if (!imageFiles.length) {
            showToast('Tệp đã chọn không phải ảnh. Vui lòng chọn JPG, PNG hoặc WEBP.');
            input.value = '';
            return;
        }

        isProcessingImages.value = true;

        try {
            const results = await Promise.all(imageFiles.map(async (file) => {
                try {
                    return await fileToImageData(file);
                } catch {
                    return null;
                }
            }));
            const addedImages = results.filter((result): result is string => result !== null);
            const failedImageCount = results.length - addedImages.length;

            localImages.value.push(...addedImages);

            const messages: string[] = [];
            if (addedImages.length) {
                messages.push(`Đã thêm ${addedImages.length} ảnh vào thư viện`);
            }
            if (failedImageCount) {
                messages.push(`Không đọc được ${failedImageCount} ảnh, vui lòng thử lại`);
            }
            if (rejectedFileCount) {
                messages.push(`${rejectedFileCount} tệp không phải ảnh nên đã bị bỏ qua`);
            }
            if (messages.length) {
                showToast(messages.join('. ') + '.');
            }
        } finally {
            input.value = '';
            isProcessingImages.value = false;
        }
    }

    function saveProduct(): void {
        if (isSubmitting.value) {
            return;
        }

        isSubmitting.value = true;

        const data = {
            name: productName.value,
            category_id: Number(selectedCategoryId.value),
            subcategory_id: Number(selectedSubcategory.value),
            tag: productTag.value === '' ? null : Number(productTag.value),
            price: Number(productPrice.value),
            description: productDescription.value,
            images: [...imageUrlList.value, ...localImages.value],
        };
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
                emit('saved', props.product ? 'updated' : 'created');
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                showToast(typeof firstError === 'string' ? firstError : 'Không thể lưu sản phẩm. Vui lòng kiểm tra lại thông tin.');
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        };

        if (props.product) {
            router.put(`/admin/products/${props.product.id}`, data, options);
            return;
        }

        router.post('/admin/products', data, options);
    }

    onBeforeUnmount(() => {
        if (toastTimeout) {
            clearTimeout(toastTimeout);
        }
    });

    function removeImage(kind: ImageEntry['kind'], index: number): void {
        if (kind === 'local') {
            localImages.value.splice(index, 1);
            return;
        }

        const urls = imageUrlList.value;
        urls.splice(index, 1);
        imageUrls.value = urls.join('\n');
    }
</script>