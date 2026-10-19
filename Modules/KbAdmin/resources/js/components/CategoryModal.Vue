<template>
    <div
        class="modal"
        :class="{ open }"
        role="dialog"
        aria-modal="true"
        aria-labelledby="categoryModalTitle"
        @click.self="closeModal"
    >
        <div class="dialog">
            <button class="close" type="button" aria-label="Đóng" :disabled="isSubmitting || isProcessingImage" @click="closeModal">×</button>
            <div class="dialog-head">
                <div class="eyebrow">ADMIN / CATEGORY</div>
                <h2 id="categoryModalTitle">{{ modalTitle }}</h2>
                <p>{{ modalDescription }}</p>
            </div>
            <form class="form" @submit.prevent="saveCategory">
                <div class="formgrid">
                    <div class="field full">
                        <label for="categoryName">Tên danh mục</label>
                        <input
                            id="categoryName"
                            v-model="name"
                            name="name"
                            type="text"
                            maxlength="255"
                            required
                            placeholder="Ví dụ: Ariston"
                        >
                    </div>
                    <div class="field full">
                        <label for="categoryDescription">Mô tả</label>
                        <textarea
                            id="categoryDescription"
                            v-model="description"
                            name="description"
                            maxlength="255"
                            placeholder="Mô tả ngắn về danh mục"
                        ></textarea>
                    </div>
                    <div class="field full">
                        <label for="categoryDescription2">Mô tả bổ sung</label>
                        <textarea
                            id="categoryDescription2"
                            v-model="description2"
                            name="description_2"
                            placeholder="Thông tin bổ sung (không bắt buộc)"
                        ></textarea>
                    </div>
                    <div class="field full">
                        <label>Ảnh danh mục (chọn tối đa 1 ảnh)</label>
                        <label class="image-upload">
                            <div>
                                <b>＋ Chọn ảnh từ máy tính</b>
                                <small>JPG, PNG, WEBP · chỉ chọn 1 ảnh</small>
                            </div>
                            <span aria-hidden="true">⇧</span>
                            <input
                                id="categoryImage"
                                type="file"
                                accept="image/*"
                                aria-label="Chọn một ảnh danh mục"
                                :disabled="isProcessingImage || isSubmitting"
                                @change="handleImageFile"
                            >
                        </label>
                        <div v-if="imagePreviewUrl" class="category-image-preview-wrap">
                            <img class="category-image-preview" :src="imagePreviewUrl" alt="Xem trước ảnh danh mục">
                            <button
                                type="button"
                                class="image-remove"
                                aria-label="Xóa ảnh danh mục"
                                @click="removeImage"
                            >×</button>
                        </div>
                    </div>
                </div>
                <div class="formfoot">
                    <button type="button" class="btn" :disabled="isSubmitting || isProcessingImage" @click="closeModal">Hủy</button>
                    <button type="submit" class="btn primary" :disabled="isSubmitting || isProcessingImage">
                        {{ isSubmitting ? 'Đang lưu...' : isProcessingImage ? 'Đang xử lý ảnh...' : 'Lưu danh mục ↗' }}
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

    const props = defineProps<{
        open: boolean;
        category?: {
            id: number;
            parent_id: number | null;
            name: string;
            description: string | null;
            description_2: string | null;
            image_url: string | null;
        } | null;
        parentId?: number | null;
    }>();

    const emit = defineEmits<{
        'update:open': [open: boolean];
        saved: [action: 'created' | 'updated', isChild: boolean];
    }>();

    const modalTitle = computed(() => {
        if (props.category) {
            return props.category.parent_id ? 'Sửa dòng danh mục' : 'Sửa danh mục chính';
        }

        return props.parentId ? 'Thêm dòng danh mục' : 'Thêm danh mục cha';
    });
    const modalDescription = computed(() => {
        if (props.category) {
            return props.category.parent_id
                ? 'Cập nhật thông tin dòng danh mục.'
                : 'Cập nhật thông tin danh mục chính.';
        }

        return props.parentId
            ? 'Tạo dòng danh mục thuộc danh mục chính đã chọn.'
            : 'Tạo danh mục chính để nhóm các danh mục con.';
    });
    const name = ref('');
    const description = ref('');
    const description2 = ref('');
    const selectedImage = ref<File | null>(null);
    const imagePreviewUrl = ref('');
    const removeExistingImage = ref(false);
    const isProcessingImage = ref(false);
    const isSubmitting = ref(false);
    const toastMessage = ref('');
    const isToastVisible = ref(false);
    let toastTimeout: ReturnType<typeof setTimeout> | undefined;

    watch(
        () => props.open,
        (isOpen) => {
            if (isOpen) {
                resetForm();
            }
        },
    );

    function resetForm(): void {
        name.value = props.category?.name ?? '';
        description.value = props.category?.description ?? '';
        description2.value = props.category?.description_2 ?? '';
        removeImage();
        imagePreviewUrl.value = props.category?.image_url ?? '';
        removeExistingImage.value = false;
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

    async function handleImageFile(event: Event): Promise<void> {
        const input = event.target;
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const file = input.files?.[0];
        if (!file) {
            return;
        }

        if (!file.type.startsWith('image/')) {
            showToast('Tệp đã chọn không phải ảnh.');
            input.value = '';
            return;
        }

        isProcessingImage.value = true;

        try {
            selectedImage.value = await optimizeImage(file);
            removeExistingImage.value = false;
            updateImagePreview();
            showToast('Đã chọn ảnh danh mục.');
        } catch {
            showToast('Không thể xử lý ảnh. Vui lòng chọn ảnh khác.');
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

    function updateImagePreview(): void {
        if (imagePreviewUrl.value.startsWith('blob:')) {
            URL.revokeObjectURL(imagePreviewUrl.value);
        }

        imagePreviewUrl.value = selectedImage.value ? URL.createObjectURL(selectedImage.value) : '';
    }

    function removeImage(): void {
        if (imagePreviewUrl.value.startsWith('blob:')) {
            URL.revokeObjectURL(imagePreviewUrl.value);
        }

        selectedImage.value = null;
        imagePreviewUrl.value = '';
        removeExistingImage.value = Boolean(props.category?.image_url);
    }

    function saveCategory(): void {
        if (isSubmitting.value || isProcessingImage.value) {
            return;
        }

        isSubmitting.value = true;

        const data = {
            name: name.value,
            description: description.value || null,
            description_2: description2.value || null,
            image: selectedImage.value,
            remove_image: removeExistingImage.value,
            parent_id: props.parentId ?? null,
        };
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
                emit('saved', props.category ? 'updated' : 'created', Boolean(props.category?.parent_id || props.parentId));
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                showToast(typeof firstError === 'string' ? firstError : 'Không thể lưu danh mục. Vui lòng kiểm tra thông tin.');
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        };

        if (props.category) {
            router.post(`/admin/categories/${props.category.id}`, { ...data, _method: 'put' }, options);
            return;
        }

        router.post('/admin/categories', data, options);
    }

    onBeforeUnmount(() => {
        if (imagePreviewUrl.value.startsWith('blob:')) {
            URL.revokeObjectURL(imagePreviewUrl.value);
        }

        if (toastTimeout) {
            clearTimeout(toastTimeout);
        }
    });
</script>

<style scoped>
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

    .category-image-preview-wrap {
        position: relative;
        width: fit-content;
        margin-top: 10px;
    }

    .category-image-preview {
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
</style>
