<template>
    <div class="modal" :class="{ open }" role="dialog" aria-modal="true" aria-labelledby="modalTitle" @click.self="closeModal">
      <div class="dialog">
        <button class="close" type="button" aria-label="Đóng" @click="closeModal">×</button>
        <div class="dialog-head">
          <div class="eyebrow" id="modalEyebrow">ADMIN / EDITOR</div>
          <h2 id="modalTitle">Thêm sản phẩm</h2>
          <p id="modalDesc">Các trường dưới đây dùng để hiển thị sản phẩm trên website.</p>
        </div>
        <form class="form" id="editorForm" data-type="product">
            <div class="formgrid" id="formFields">
                <div class="field full">
                    <label>Tên sản phẩm</label>
                    <input name="name" type="text" value="" required="" placeholder="">
                </div>
                <div class="field ">
                    <label>Danh mục chính</label>
                    <select v-model="selectedCategoryId" name="categories_id" required="">
                        <option value="1">Ariston</option>
                        <option value="2">K-FLEX</option>
                    </select>
                </div>
                <div class="field ">
                    <label>Dòng sản phẩm</label>
                    <select v-model="selectedSubcategory" name="subcategory" required="">
                        <option v-for="subcategory in visibleSubcategories" :key="subcategory.value" :value="subcategory.value">
                            {{ subcategory.label }}
                        </option>
                    </select>
                </div>
                <div class="field ">
                    <label>Loại hàng hóa</label>
                    <input name="tag" type="text" value=1 placeholder="">
                </div>
                <div class="field ">
                    <label>Giá tham khảo (VND)</label>
                    <input name="price" type="number" value="0" min="0" step="1" placeholder="">
                </div>
                <div class="field full">
                    <label>Mô tả chi tiết</label>
                    <textarea name="description" placeholder=""></textarea>
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
                            <input type="file" accept="image/*" multiple="" data-image-files="" aria-label="Chọn nhiều ảnh cùng lúc">
                        </label>
                        <div class="image-count" data-image-count="">Chưa có ảnh. Bạn có thể chọn nhiều ảnh cùng lúc hoặc nhập URL ở trên.</div>
                        <div class="image-preview" data-image-preview="" aria-live="polite"></div>
                        <p class="image-help">Ảnh chọn từ máy sẽ được tối ưu trước khi lưu trong trình duyệt. Bạn có thể xóa từng ảnh trong phần xem trước.</p>
                    </div>
                </div>
            </div>
            <div class="formfoot">
                <button type="button" class="btn" id="cancelModal">Hủy</button>
                <button type="submit" class="btn primary">Lưu thay đổi ↗</button>
            </div>
        </form>
      </div>
    </div>
</template>

<script setup lang="ts">
    import { computed, ref, watch } from 'vue';

    const selectedCategoryId = ref('1');
    const selectedSubcategory = ref('1');
    const subcategories = [
        { categoryId: '1', value: '1', label: 'Ariston — Bình nước nóng trực tiếp' },
        { categoryId: '1', value: '2', label: 'Ariston — Bình nước nóng gián tiếp' },
        { categoryId: '1', value: '3', label: 'Ariston — Bơm nhiệt nước nóng' },
        { categoryId: '1', value: '4', label: 'Ariston — Thái dương năng' },
        { categoryId: '2', value: '5', label: 'K-FLEX — Cao su lưu hóa NBR' },
        { categoryId: '2', value: '6', label: 'K-FLEX — Polyolefin XLPE' },
        { categoryId: '2', value: '7', label: 'K-FLEX — Phụ kiện bảo ôn' },
    ];

    const visibleSubcategories = computed(() =>
        subcategories.filter((subcategory) => subcategory.categoryId === selectedCategoryId.value),
    );

    watch(selectedCategoryId, () => {
        selectedSubcategory.value = visibleSubcategories.value[0]?.value ?? '';
    });

    const props = defineProps<{
        open: boolean;
    }>();

    watch(
        () => props.open,
        (isOpen) => {
            if (isOpen) {
                selectedSubcategory.value = visibleSubcategories.value[0]?.value ?? '';
            }
        },
    );

    const emit = defineEmits<{
        'update:open': [open: boolean];
    }>();

    function closeModal(): void {
        emit('update:open', false);
    }
</script>