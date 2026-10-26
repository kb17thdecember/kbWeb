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
                <p>Thông tin công trình và hình ảnh dự án tiêu biểu.</p>
            </div>
            <form class="form" @submit.prevent="saveProject">
                <div class="formgrid">
                    <div class="field full">
                        <label for="featuredProjectName">Tên dự án</label>
                        <input id="featuredProjectName" v-model="name" maxlength="255" required placeholder="Tên dự án">
                    </div>
                    <div class="field full">
                        <label for="featuredProjectDescription">Mô tả</label>
                        <textarea id="featuredProjectDescription" v-model="description" maxlength="255" required placeholder="Mô tả dự án"></textarea>
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

    const props = defineProps<{
        open: boolean;
        project: FeaturedProject | null;
        categories: Category[];
    }>();

    const emit = defineEmits<{
        'update:open': [open: boolean];
        saved: [action: 'created' | 'updated'];
    }>();

    const name = ref('');
    const description = ref('');
    const location = ref('');
    const selectedCategory = ref('');
    const selectedSubcategory = ref('');
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

    function resetForm(): void {
        name.value = props.project?.name ?? '';
        description.value = props.project?.description ?? '';
        location.value = props.project?.location ?? '';
        const parentCategory = props.categories.find((item) => item.name === props.project?.category);
        const childCategory = parentCategory?.children.find((item) => item.name === props.project?.subcategory);
        selectedCategory.value = parentCategory?.name ?? '';
        selectedSubcategory.value = childCategory?.name ?? '';
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
            type: type.value,
            images: selectedImages.value.length ? selectedImages.value : null,
        };
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
                emit('saved', props.project ? 'updated' : 'created');
            },
            onError: (errors) => {
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