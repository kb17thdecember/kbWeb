<template>
    <div class="modal" :class="{ open }" role="dialog" aria-modal="true" aria-labelledby="catalogueModalTitle" @click.self="closeModal">
        <div class="dialog">
            <button class="close" type="button" aria-label="Đóng" :disabled="isSubmitting" @click="closeModal"><FontAwesomeIcon :icon="['fas', 'xmark']" /></button>
            <div class="dialog-head">
                <div class="eyebrow">ADMIN / CATALOGUE</div>
                <h2 id="catalogueModalTitle">{{ catalogue ? 'Sửa catalogue' : 'Thêm catalogue' }}</h2>
                <p>Tải lên file PDF để khách hàng xem và tải về.</p>
            </div>
            <form class="form" @submit.prevent="saveCatalogue">
                <div class="formgrid">
                    <div class="field full">
                        <label for="catalogueTitle">Tên catalogue</label>
                        <input id="catalogueTitle" v-model="title" maxlength="255" required placeholder="Ví dụ: Catalogue bình nước nóng Ariston 2026">
                    </div>
                    <div class="field full">
                        <label for="catalogueDescription">Mô tả</label>
                        <textarea id="catalogueDescription" v-model="description" maxlength="2000" placeholder="Mô tả ngắn"></textarea>
                    </div>
                    <div class="field full">
                        <label>File PDF</label>
                        <label class="image-upload">
                            <div>
                                <b>＋ Chọn tệp PDF từ máy tính</b>
                                <small>
                                    <template v-if="file">{{ file.name }} · {{ formatSize(file.size) }}</template>
                                    <template v-else>PDF · tối đa 30 MB · chỉ chọn 1 tệp</template>
                                </small>
                            </div>
                            <span aria-hidden="true">⇧</span>
                            <input
                                id="catalogueFile"
                                type="file"
                                accept="application/pdf,.pdf"
                                aria-label="Chọn tệp PDF catalogue"
                                :disabled="isSubmitting"
                                @change="onFileChange"
                            >
                        </label>
                        <p v-if="catalogue && !file" class="image-help">Hiện tại: {{ catalogue.file_name }} · {{ formatSize(catalogue.file_size) }}. Không chọn tệp mới để giữ nguyên.</p>
                    </div>
                    <div class="field full">
                        <label>Ảnh bìa (không bắt buộc)</label>
                        <label class="image-upload">
                            <div>
                                <b>＋ Chọn ảnh từ máy tính</b>
                                <small>JPG, PNG, WEBP · chỉ chọn 1 ảnh</small>
                            </div>
                            <span aria-hidden="true">⇧</span>
                            <input
                                id="catalogueCover"
                                type="file"
                                accept="image/*"
                                aria-label="Chọn ảnh bìa catalogue"
                                :disabled="isSubmitting"
                                @change="onCoverChange"
                            >
                        </label>
                        <div v-if="coverPreview" class="cover-preview-wrap">
                            <img class="cover-preview" :src="coverPreview" alt="Xem trước ảnh bìa">
                            <button v-if="cover" type="button" class="image-remove" aria-label="Bỏ ảnh bìa" @click="clearCover"><FontAwesomeIcon :icon="['fas', 'xmark']" /></button>
                        </div>
                    </div>
                    <div class="field full">
                        <label class="check"><input v-model="isPublished" type="checkbox"> Hiển thị công khai</label>
                    </div>
                </div>
                <p v-if="errorMessage" class="image-help" role="alert">{{ errorMessage }}</p>
                <div class="formfoot">
                    <button type="button" class="btn" :disabled="isSubmitting" @click="closeModal">Hủy</button>
                    <button type="submit" class="btn primary" :disabled="isSubmitting">
                        {{ isSubmitting ? 'Đang tải lên...' : catalogue ? 'Cập nhật catalogue' : 'Lưu catalogue' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
    import { router } from '@inertiajs/vue3';
    import { onBeforeUnmount, ref, watch } from 'vue';

    type Catalogue = {
        id: number;
        title: string;
        description: string | null;
        file_name: string;
        file_size: number;
        is_published: boolean;
        image_url: string | null;
    };

    const MAX_PDF_BYTES = 30 * 1024 * 1024;

    const props = defineProps<{
        open: boolean;
        catalogue: Catalogue | null;
    }>();

    const emit = defineEmits<{
        'update:open': [open: boolean];
        saved: [action: 'created' | 'updated'];
    }>();

    const title = ref('');
    const description = ref('');
    const isPublished = ref(true);
    const file = ref<File | null>(null);
    const cover = ref<File | null>(null);
    const coverPreview = ref<string | null>(null);
    const isSubmitting = ref(false);
    const errorMessage = ref('');

    watch(
        () => props.open,
        (isOpen) => {
            if (!isOpen) {
                return;
            }

            title.value = props.catalogue?.title ?? '';
            description.value = props.catalogue?.description ?? '';
            isPublished.value = props.catalogue?.is_published ?? true;
            errorMessage.value = '';
            file.value = null;
            clearCover();
            coverPreview.value = props.catalogue?.image_url ?? null;
        },
    );

    function formatSize(bytes: number): string {
        return bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    function clearCover(): void {
        if (cover.value && coverPreview.value) {
            URL.revokeObjectURL(coverPreview.value);
        }

        cover.value = null;
        coverPreview.value = null;
    }

    function onFileChange(event: Event): void {
        const input = event.target as HTMLInputElement;
        const selected = input.files?.[0] ?? null;

        if (selected && selected.size > MAX_PDF_BYTES) {
            errorMessage.value = 'File PDF phải nhỏ hơn 30 MB.';
            input.value = '';
            file.value = null;

            return;
        }

        errorMessage.value = '';
        file.value = selected;
    }

    function onCoverChange(event: Event): void {
        const selected = (event.target as HTMLInputElement).files?.[0];

        if (!selected) {
            return;
        }

        clearCover();
        cover.value = selected;
        coverPreview.value = URL.createObjectURL(selected);
    }

    function closeModal(): void {
        if (!isSubmitting.value) {
            emit('update:open', false);
        }
    }

    function saveCatalogue(): void {
        if (isSubmitting.value) {
            return;
        }

        if (!props.catalogue && !file.value) {
            errorMessage.value = 'Vui lòng chọn tệp PDF cho catalogue.';

            return;
        }

        isSubmitting.value = true;
        errorMessage.value = '';
        const data: Record<string, unknown> = {
            title: title.value,
            description: description.value,
            is_published: isPublished.value,
        };

        if (file.value) {
            data.file = file.value;
        }

        if (cover.value) {
            data.cover = cover.value;
        }

        const options = {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                emit('update:open', false);
                emit('saved', props.catalogue ? 'updated' : 'created');
            },
            onError: (errors: Record<string, string>) => {
                errorMessage.value = Object.values(errors)[0] ?? 'Không thể lưu catalogue. Vui lòng kiểm tra thông tin.';
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        };

        if (props.catalogue) {
            router.post(`/admin/catalogues/${props.catalogue.id}`, { ...data, _method: 'put' }, options);

            return;
        }

        router.post('/admin/catalogues', data, options);
    }

    onBeforeUnmount(clearCover);
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

    .cover-preview-wrap {
        position: relative;
        width: fit-content;
        margin-top: 10px;
    }

    .cover-preview {
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

    .check {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .check input {
        width: auto;
    }
</style>
