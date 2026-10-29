<template>
    <div class="modal" :class="{ open }" role="dialog" aria-modal="true" aria-labelledby="articleModalTitle" @click.self="closeModal">
        <div class="dialog">
            <button class="close" type="button" aria-label="Đóng" :disabled="isSubmitting" @click="closeModal"><FontAwesomeIcon :icon="['fas', 'xmark']" /></button>
            <div class="dialog-head">
                <div class="eyebrow">ADMIN / ARTICLE</div>
                <h2 id="articleModalTitle">{{ article ? 'Sửa bài viết' : 'Thêm bài viết' }}</h2>
                <p>Nội dung bài viết hiển thị ở mục Tin tức.</p>
            </div>
            <form class="form" @submit.prevent="saveArticle">
                <div class="formgrid">
                    <div class="field full">
                        <label for="articleTitle">Tiêu đề</label>
                        <input id="articleTitle" v-model="title" maxlength="255" required placeholder="Tiêu đề bài viết">
                    </div>
                    <div class="field full">
                        <label for="articleExcerpt">Mô tả ngắn</label>
                        <textarea id="articleExcerpt" v-model="excerpt" maxlength="500" required placeholder="Đoạn tóm tắt hiển thị ở danh sách"></textarea>
                    </div>
                    <div class="field full">
                        <label>Nội dung</label>
                        <RichTextEditor
                            v-model="content"
                            upload-url="/admin/articles/images"
                            placeholder="Nhập nội dung bài viết, dùng thanh công cụ để chỉnh font, màu chữ, chèn ảnh..."
                            :disabled="isSubmitting"
                        />
                    </div>
                    <div class="field full">
                        <label>Ảnh bìa</label>
                        <label class="image-upload">
                            <div>
                                <b>＋ Chọn ảnh từ máy tính</b>
                                <small>JPG, PNG, WEBP · tối đa 5 MB · chỉ chọn 1 ảnh</small>
                            </div>
                            <span aria-hidden="true">⇧</span>
                            <input
                                id="articleCover"
                                type="file"
                                accept="image/*"
                                aria-label="Chọn ảnh bìa bài viết"
                                :disabled="isSubmitting"
                                @change="onCoverChange"
                            >
                        </label>
                        <div v-if="coverPreview" class="cover-preview-wrap">
                            <img class="cover-preview" :src="coverPreview" alt="Xem trước ảnh bìa">
                            <button v-if="cover" type="button" class="image-remove" aria-label="Bỏ ảnh bìa" @click="removeCover"><FontAwesomeIcon :icon="['fas', 'xmark']" /></button>
                        </div>
                        <p v-if="article" class="image-help">Không chọn ảnh mới để giữ nguyên ảnh bìa hiện tại.</p>
                    </div>
                    <div class="field full">
                        <label class="check"><input v-model="isPublished" type="checkbox"> Hiển thị công khai</label>
                    </div>
                </div>
                <p v-if="errorMessage" class="image-help" role="alert">{{ errorMessage }}</p>
                <div class="formfoot">
                    <button type="button" class="btn" :disabled="isSubmitting" @click="closeModal">Hủy</button>
                    <button type="submit" class="btn primary" :disabled="isSubmitting">
                        {{ isSubmitting ? 'Đang lưu...' : article ? 'Cập nhật bài viết' : 'Lưu bài viết' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
    import { router } from '@inertiajs/vue3';
    import { onBeforeUnmount, ref, watch } from 'vue';
    import RichTextEditor from './RichTextEditor.Vue';

    type Article = {
        id: number;
        title: string;
        excerpt: string;
        content: string;
        is_published: boolean;
        image_url: string | null;
    };

    const props = defineProps<{
        open: boolean;
        article: Article | null;
    }>();

    const emit = defineEmits<{
        'update:open': [open: boolean];
        saved: [action: 'created' | 'updated'];
    }>();

    const title = ref('');
    const excerpt = ref('');
    const content = ref('');
    const isPublished = ref(true);
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

            title.value = props.article?.title ?? '';
            excerpt.value = props.article?.excerpt ?? '';
            content.value = props.article?.content ?? '';
            isPublished.value = props.article?.is_published ?? true;
            errorMessage.value = '';
            clearCover();
            coverPreview.value = props.article?.image_url ?? null;
        },
    );

    function clearCover(): void {
        if (cover.value && coverPreview.value) {
            URL.revokeObjectURL(coverPreview.value);
        }

        cover.value = null;
        coverPreview.value = null;
    }

    function removeCover(): void {
        clearCover();
    }

    function onCoverChange(event: Event): void {
        const file = (event.target as HTMLInputElement).files?.[0];

        if (!file) {
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            errorMessage.value = 'Ảnh bìa phải nhỏ hơn 5 MB.';
            (event.target as HTMLInputElement).value = '';

            return;
        }

        errorMessage.value = '';
        clearCover();
        cover.value = file;
        coverPreview.value = URL.createObjectURL(file);
    }

    function closeModal(): void {
        if (!isSubmitting.value) {
            emit('update:open', false);
        }
    }

    function saveArticle(): void {
        if (isSubmitting.value) {
            return;
        }

        isSubmitting.value = true;
        errorMessage.value = '';
        const data: Record<string, unknown> = {
            title: title.value,
            excerpt: excerpt.value,
            content: content.value,
            is_published: isPublished.value,
        };

        if (cover.value) {
            data.cover = cover.value;
        }

        const options = {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                emit('update:open', false);
                emit('saved', props.article ? 'updated' : 'created');
            },
            onError: (errors: Record<string, string>) => {
                errorMessage.value = Object.values(errors)[0] ?? 'Không thể lưu bài viết. Vui lòng kiểm tra thông tin.';
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        };

        if (props.article) {
            router.post(`/admin/articles/${props.article.id}`, { ...data, _method: 'put' }, options);

            return;
        }

        router.post('/admin/articles', data, options);
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
