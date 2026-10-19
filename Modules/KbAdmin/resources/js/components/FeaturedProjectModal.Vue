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
            <button class="close" type="button" aria-label="Đóng" :disabled="isSubmitting || isProcessingImage" @click="closeModal">×</button>
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
                        <label>Ảnh dự án (chọn 1 ảnh)</label>
                        <label class="image-upload">
                            <div>
                                <b>＋ Chọn ảnh từ máy tính</b>
                                <small>JPG, PNG, WEBP · tối đa 5 MB</small>
                            </div>
                            <span aria-hidden="true">⇧</span>
                            <input
                                type="file"
                                accept="image/*"
                                :required="!project && !selectedImage"
                                :disabled="isProcessingImage || isSubmitting"
                                @change="handleImageFile"
                            >
                        </label>
                        <div v-if="imagePreviewUrl" class="project-image-preview-wrap">
                            <img class="project-image-preview" :src="imagePreviewUrl" alt="Xem trước ảnh dự án">
                            <button
                                v-if="selectedImage"
                                type="button"
                                class="image-remove"
                                aria-label="Bỏ ảnh vừa chọn"
                                @click="clearSelectedImage"
                            >×</button>
                        </div>
                    </div>
                </div>
                <div class="formfoot">
                    <button type="button" class="btn" :disabled="isSubmitting || isProcessingImage" @click="closeModal">Hủy</button>
                    <button type="submit" class="btn primary" :disabled="isSubmitting || isProcessingImage">
                        {{ isSubmitting ? 'Đang lưu...' : isProcessingImage ? 'Đang xử lý ảnh...' : project ? 'Cập nhật dự án ↗' : 'Lưu dự án ↗' }}
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
    const selectedImage = ref<File | null>(null);
    const imagePreviewUrl = ref('');
    const isProcessingImage = ref(false);
    const isSubmitting = ref(false);
    const toastMessage = ref('');
    const isToastVisible = ref(false);
    let toastTimeout: ReturnType<typeof setTimeout> | undefined;

    const visibleSubcategories = computed(() =>
        props.categories.find((item) => item.name === selectedCategory.value)?.children ?? [],
    );

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
        selectedImage.value = null;
        setImagePreview(props.project?.image_url ?? '');
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

        if (file.size > 5 * 1024 * 1024) {
            showToast('Ảnh vượt quá 5 MB. Vui lòng chọn ảnh nhỏ hơn.');
            input.value = '';
            return;
        }

        isProcessingImage.value = true;

        try {
            selectedImage.value = await optimizeImage(file);
            setImagePreview(URL.createObjectURL(selectedImage.value));
            showToast('Đã chọn ảnh dự án.');
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

    function setImagePreview(url: string): void {
        if (imagePreviewUrl.value.startsWith('blob:')) {
            URL.revokeObjectURL(imagePreviewUrl.value);
        }

        imagePreviewUrl.value = url;
    }

    function clearSelectedImage(): void {
        selectedImage.value = null;
        setImagePreview(props.project?.image_url ?? '');
    }

    function saveProject(): void {
        if (isSubmitting.value || isProcessingImage.value) {
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
            image: selectedImage.value,
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
</style>