<template>
    <div class="rte" :class="{ 'is-disabled': disabled }">
        <div class="rte-toolbar" role="toolbar" aria-label="Định dạng nội dung" @mousedown="keepSelection">
            <select aria-label="Kiểu đoạn" @change="applyBlock(($event.target as HTMLSelectElement))">
                <option value="p">Đoạn văn</option>
                <option value="h2">Tiêu đề lớn</option>
                <option value="h3">Tiêu đề vừa</option>
                <option value="h4">Tiêu đề nhỏ</option>
                <option value="blockquote">Trích dẫn</option>
            </select>
            <select aria-label="Phông chữ" @change="applyValue('fontName', $event.target as HTMLSelectElement)">
                <option value="">Phông chữ</option>
                <option v-for="font in FONTS" :key="font" :value="font" :style="{ fontFamily: font }">{{ font }}</option>
            </select>
            <select aria-label="Cỡ chữ" @change="applyValue('fontSize', $event.target as HTMLSelectElement)">
                <option value="">Cỡ chữ</option>
                <option v-for="size in SIZES" :key="size.value" :value="size.value">{{ size.label }}</option>
            </select>

            <span class="rte-sep"></span>
            <button type="button" title="Đậm" aria-label="Đậm" @click="run('bold')"><FontAwesomeIcon :icon="['fas', 'bold']" /></button>
            <button type="button" title="Nghiêng" aria-label="Nghiêng" @click="run('italic')"><FontAwesomeIcon :icon="['fas', 'italic']" /></button>
            <button type="button" title="Gạch chân" aria-label="Gạch chân" @click="run('underline')"><FontAwesomeIcon :icon="['fas', 'underline']" /></button>
            <button type="button" title="Gạch ngang" aria-label="Gạch ngang" @click="run('strikeThrough')"><FontAwesomeIcon :icon="['fas', 'strikethrough']" /></button>

            <span class="rte-sep"></span>
            <label class="rte-color" title="Màu chữ">
                <FontAwesomeIcon :icon="['fas', 'font']" />
                <input type="color" aria-label="Màu chữ" value="#22221d" @input="run('foreColor', ($event.target as HTMLInputElement).value)">
            </label>
            <label class="rte-color" title="Màu nền chữ">
                <FontAwesomeIcon :icon="['fas', 'highlighter']" />
                <input type="color" aria-label="Màu nền chữ" value="#fff3a3" @input="run('hiliteColor', ($event.target as HTMLInputElement).value)">
            </label>

            <span class="rte-sep"></span>
            <button type="button" title="Căn trái" aria-label="Căn trái" @click="run('justifyLeft')"><FontAwesomeIcon :icon="['fas', 'align-left']" /></button>
            <button type="button" title="Căn giữa" aria-label="Căn giữa" @click="run('justifyCenter')"><FontAwesomeIcon :icon="['fas', 'align-center']" /></button>
            <button type="button" title="Căn phải" aria-label="Căn phải" @click="run('justifyRight')"><FontAwesomeIcon :icon="['fas', 'align-right']" /></button>
            <button type="button" title="Danh sách chấm" aria-label="Danh sách chấm" @click="run('insertUnorderedList')"><FontAwesomeIcon :icon="['fas', 'list-ul']" /></button>
            <button type="button" title="Danh sách số" aria-label="Danh sách số" @click="run('insertOrderedList')"><FontAwesomeIcon :icon="['fas', 'list-ol']" /></button>

            <span class="rte-sep"></span>
            <button type="button" title="Chèn liên kết" aria-label="Chèn liên kết" @click="insertLink"><FontAwesomeIcon :icon="['fas', 'link']" /></button>
            <button type="button" title="Chèn ảnh" aria-label="Chèn ảnh" :disabled="isUploading" @click="imageInput?.click()"><FontAwesomeIcon :icon="['fas', 'image']" /></button>
            <button type="button" title="Xóa định dạng" aria-label="Xóa định dạng" @click="run('removeFormat')"><FontAwesomeIcon :icon="['fas', 'eraser']" /></button>
            <input ref="imageInput" class="rte-file" type="file" accept="image/*" tabindex="-1" @change="onImagePicked">
        </div>

        <div
            ref="editor"
            class="rte-body"
            contenteditable="true"
            role="textbox"
            aria-multiline="true"
            aria-label="Nội dung bài viết"
            :data-placeholder="placeholder"
            @input="syncValue"
            @blur="saveRange"
            @keyup="saveRange"
            @mouseup="saveRange"
            @paste="onPaste"
        ></div>
        <p v-if="status" class="rte-status" role="status">{{ status }}</p>
    </div>
</template>

<script setup lang="ts">
    import { onMounted, ref, watch } from 'vue';

    const FONTS = ['Arial', 'Times New Roman', 'Georgia', 'Verdana', 'Tahoma', 'Courier New'];
    const SIZES = [
        { label: 'Rất nhỏ', value: '1' },
        { label: 'Nhỏ', value: '2' },
        { label: 'Vừa', value: '3' },
        { label: 'Lớn', value: '5' },
        { label: 'Rất lớn', value: '6' },
        { label: 'Cực lớn', value: '7' },
    ];
    const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    const props = defineProps<{
        modelValue: string;
        uploadUrl: string;
        placeholder?: string;
        disabled?: boolean;
    }>();

    const emit = defineEmits<{
        'update:modelValue': [value: string];
    }>();

    const editor = ref<HTMLDivElement | null>(null);
    const imageInput = ref<HTMLInputElement | null>(null);
    const isUploading = ref(false);
    const status = ref('');
    let savedRange: Range | null = null;

    onMounted(() => {
        if (editor.value) {
            editor.value.innerHTML = props.modelValue;
        }
    });

    watch(
        () => props.modelValue,
        (value) => {
            if (editor.value && editor.value.innerHTML !== value) {
                editor.value.innerHTML = value;
            }
        },
    );

    function syncValue(): void {
        const html = editor.value?.innerHTML ?? '';
        emit('update:modelValue', html === '<br>' ? '' : html);
    }

    function saveRange(): void {
        const selection = window.getSelection();

        if (selection?.rangeCount && editor.value?.contains(selection.anchorNode)) {
            savedRange = selection.getRangeAt(0).cloneRange();
        }
    }

    function restoreRange(): void {
        editor.value?.focus();

        if (!savedRange) {
            return;
        }

        const selection = window.getSelection();
        selection?.removeAllRanges();
        selection?.addRange(savedRange);
    }

    function run(command: string, value?: string): void {
        restoreRange();
        document.execCommand('styleWithCSS', false, 'true');
        document.execCommand(command, false, value);
        saveRange();
        syncValue();
    }

    function keepSelection(event: MouseEvent): void {
        const target = event.target as HTMLElement;

        if (!target.closest('select, input')) {
            event.preventDefault();
        }
    }

    function applyBlock(select: HTMLSelectElement): void {
        run('formatBlock', `<${select.value}>`);
    }

    function applyValue(command: string, select: HTMLSelectElement): void {
        if (select.value) {
            run(command, select.value);
        }

        select.selectedIndex = 0;
    }

    function insertLink(): void {
        saveRange();
        const url = window.prompt('Nhập địa chỉ liên kết (https://...)');

        if (url && /^(https?:\/\/|mailto:|tel:|\/)/i.test(url.trim())) {
            run('createLink', url.trim());
        } else if (url) {
            status.value = 'Liên kết phải bắt đầu bằng http://, https://, mailto: hoặc tel:.';
        }
    }

    function onPaste(event: ClipboardEvent): void {
        const text = event.clipboardData?.getData('text/plain');

        if (text === undefined) {
            return;
        }

        event.preventDefault();
        document.execCommand('insertText', false, text);
    }

    function xsrfToken(): string {
        const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

        return match ? decodeURIComponent(match[1]) : '';
    }

    async function onImagePicked(event: Event): Promise<void> {
        const input = event.target as HTMLInputElement;
        const file = input.files?.[0];
        input.value = '';

        if (!file) {
            return;
        }

        if (!file.type.startsWith('image/')) {
            status.value = 'Tệp đã chọn không phải ảnh.';

            return;
        }

        if (file.size > MAX_IMAGE_BYTES) {
            status.value = 'Ảnh phải nhỏ hơn 5 MB.';

            return;
        }

        isUploading.value = true;
        status.value = 'Đang tải ảnh lên...';

        try {
            const body = new FormData();
            body.append('image', file);
            const response = await fetch(props.uploadUrl, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
            });

            if (!response.ok) {
                throw new Error('upload failed');
            }

            const { url } = (await response.json()) as { url: string };
            run('insertImage', url);
            status.value = '';
        } catch {
            status.value = 'Không thể tải ảnh lên. Vui lòng thử lại.';
        } finally {
            isUploading.value = false;
        }
    }
</script>

<style scoped>
    .rte {
        border: 1px solid #cbd5e1;
        border-radius: 9px;
        background: #fff;
        overflow: hidden;
    }

    .rte-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px;
        padding: 8px;
        border-bottom: 1px solid #e5eaf2;
        background: #f8fafc;
    }

    .rte-toolbar button,
    .rte-color {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        position: relative;
        width: 32px;
        height: 32px;
        padding: 0;
        border: 1px solid transparent;
        border-radius: 6px;
        background: transparent;
        color: #334155;
        cursor: pointer;
    }

    .rte-toolbar button:hover,
    .rte-color:hover {
        border-color: #cbd5e1;
        background: #fff;
    }

    .rte-toolbar select {
        height: 32px;
        max-width: 130px;
        padding: 0 6px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: #fff;
        font-size: 13px;
    }

    .rte-color input {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }

    .rte-sep {
        width: 1px;
        height: 20px;
        margin: 0 4px;
        background: #e2e8f0;
    }

    .rte-file {
        display: none;
    }

    .rte-body {
        min-height: 260px;
        max-height: 55vh;
        padding: 14px;
        overflow-y: auto;
        line-height: 1.7;
        outline: none;
        overflow-wrap: anywhere;
    }

    .rte-body:empty::before {
        content: attr(data-placeholder);
        color: #94a3b8;
    }

    .rte-body :deep(img) {
        max-width: 100%;
        height: auto;
    }

    .rte-body :deep(blockquote) {
        margin: 12px 0;
        padding-left: 14px;
        border-left: 3px solid #cbd5e1;
        color: #475569;
    }

    .rte-body :deep(ul),
    .rte-body :deep(ol) {
        padding-left: 24px;
    }

    .rte-status {
        margin: 0;
        padding: 6px 12px;
        border-top: 1px solid #e5eaf2;
        color: #64748b;
        font-size: 12px;
    }

    .is-disabled {
        opacity: .6;
        pointer-events: none;
    }
</style>
