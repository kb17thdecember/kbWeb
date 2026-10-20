<script setup lang="ts">
    import { computed } from 'vue';
    import { ADMIN_PAGE_SIZE } from '../composables/useClientPagination';

    const props = defineProps<{
        currentPage: number;
        totalItems: number;
    }>();

    const emit = defineEmits<{
        pageChange: [page: number];
    }>();

    const pageCount = computed(() => Math.ceil(props.totalItems / ADMIN_PAGE_SIZE));
    const firstPage = computed(() => Math.max(1, Math.min(props.currentPage - 2, pageCount.value - 4)));
    const visiblePages = computed(() => Array.from(
        { length: Math.min(5, pageCount.value) },
        (_, index) => firstPage.value + index,
    ));
    const firstItem = computed(() => (props.currentPage - 1) * ADMIN_PAGE_SIZE + 1);
    const lastItem = computed(() => Math.min(props.currentPage * ADMIN_PAGE_SIZE, props.totalItems));
</script>

<template>
    <nav v-if="totalItems > ADMIN_PAGE_SIZE" class="admin-pagination" aria-label="Phân trang">
        <span class="pagination-summary">Hiển thị {{ firstItem }}–{{ lastItem }} / {{ totalItems }} bản ghi</span>
        <div class="pagination-controls">
            <button
                class="pagination-button"
                type="button"
                :disabled="currentPage === 1"
                aria-label="Trang trước"
                @click="emit('pageChange', currentPage - 1)"
            >
                ‹
            </button>
            <button
                v-for="page in visiblePages"
                :key="page"
                class="pagination-button"
                :class="{ active: page === currentPage }"
                type="button"
                :aria-label="`Trang ${page}`"
                :aria-current="page === currentPage ? 'page' : undefined"
                @click="emit('pageChange', page)"
            >
                {{ page }}
            </button>
            <button
                class="pagination-button"
                type="button"
                :disabled="currentPage === pageCount"
                aria-label="Trang sau"
                @click="emit('pageChange', currentPage + 1)"
            >
                ›
            </button>
        </div>
    </nav>
</template>

<style scoped>
    .admin-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-top: 14px;
        color: #7e899f;
        font-size: 12px;
    }

    .pagination-controls {
        display: flex;
        gap: 5px;
    }

    .pagination-button {
        min-width: 32px;
        height: 32px;
        padding: 0 8px;
        border: 1px solid #e5eaf2;
        border-radius: 7px;
        background: #fff;
        color: #1f2b42;
        cursor: pointer;
    }

    .pagination-button.active {
        border-color: #5066f6;
        background: #5066f6;
        color: #fff;
    }

    .pagination-button:disabled {
        cursor: not-allowed;
        opacity: 0.45;
    }

    @media (max-width: 520px) {
        .admin-pagination {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>
