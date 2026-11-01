<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import {
    clearRecentlyViewedProducts,
    getRecentlyViewedProducts,
    type RecentlyViewedProductItem,
} from '../utils/recentlyViewedProducts';

const props = defineProps<{
    excludeProductId?: number;
}>();

const emit = defineEmits<{
    (event: 'add-to-cart', product: RecentlyViewedProductItem): void;
}>();

const items = ref<RecentlyViewedProductItem[]>([]);

const refresh = (): void => {
    items.value = getRecentlyViewedProducts(props.excludeProductId).slice(0, 4);
};

watch(
    () => props.excludeProductId,
    () => {
        refresh();
    },
);

const productImageUrl = (image?: string | null): string | null => {
    if (!image) {
        return null;
    }

    return image.startsWith('data:') || image.startsWith('http') || image.startsWith('/')
        ? image
        : `/storage/${image}`;
};

const formatPrice = (price: number): string =>
    price > 0 ? `${new Intl.NumberFormat('vi-VN').format(price)}₫` : 'Liên hệ';

const handleClear = (): void => {
    clearRecentlyViewedProducts();
    items.value = [];
};

onMounted(() => {
    refresh();
    window.addEventListener('kbuser-recently-viewed-updated', refresh);
});

onUnmounted(() => {
    window.removeEventListener('kbuser-recently-viewed-updated', refresh);
});
</script>

<template>
    <div v-if="items.length > 0" class="kb-recently-viewed">
        <div class="section-head kb-recently-head">
            <div>
                <div class="eyebrow"><span class="dot"></span> RECENTLY VIEWED</div>
                <h2 class="section-title">Sản phẩm đã xem gần đây</h2>
            </div>
            <button type="button" class="btn btn-outline kb-recently-clear-btn" @click="handleClear">
                Xóa lịch sử xem
            </button>
        </div>

        <div class="product-grid">
            <article v-for="item in items" :key="item.id" class="product-card">
                <div class="product-photo">
                    <span class="pill">{{ item.category?.name ?? 'Thiết bị HVAC' }}</span>
                    <img
                        v-if="productImageUrl(item.image?.[0])"
                        class="product-image"
                        :src="productImageUrl(item.image?.[0]) ?? undefined"
                        :alt="item.name"
                        loading="lazy"
                    >
                    <svg v-else class="product-placeholder" viewBox="0 0 110 110" fill="none" aria-hidden="true">
                        <rect x="29" y="17" width="52" height="76" rx="12" fill="#d1d9c5" stroke="#879b7e" stroke-width="2" />
                        <path d="M29 43h52v20H29z" fill="#345342" />
                        <path d="M43 77h24" stroke="#879b7e" stroke-width="3" stroke-linecap="round" />
                    </svg>
                </div>

                <div class="product-body">
                    <div class="product-meta">{{ item.subcategory?.name ?? 'Vật tư kỹ thuật' }}</div>
                    <h3 class="product-name">
                        <Link :href="`/products/${item.id}`">{{ item.name }}</Link>
                    </h3>
                    <p class="product-desc">{{ item.product_title || item.name }}</p>
                    <div class="product-bottom">
                        <span class="price">{{ formatPrice(item.price) }}</span>
                        <div class="kb-card-bottom-btns">
                            <Link :href="`/products/${item.id}`" class="kb-card-detail-link">Chi tiết</Link>
                            <button
                                class="add-btn"
                                type="button"
                                :aria-label="`Thêm ${item.name} vào giỏ báo giá`"
                                @click="emit('add-to-cart', item)"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 4h2l2.2 11.1a2 2 0 0 0 2 1.6h8.4a2 2 0 0 0 1.9-1.4L22 8H6" />
                                    <circle cx="10" cy="20" r="1" />
                                    <circle cx="18" cy="20" r="1" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    </div>
</template>
