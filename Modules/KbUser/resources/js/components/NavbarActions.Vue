<script setup lang="ts">
import { router, Link } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { lockPageScroll } from '../utils/pageScrollLock';

type ProductCategory = {
    id: number;
    name: string;
} | null;

type Product = {
    id: number;
    name: string;
    product_title: string | null;
    description: string;
    price: number;
    image: string[];
    category: ProductCategory;
    subcategory: ProductCategory;
};

const props = defineProps<{
    initialCartCount: number;
    activePage?: string;
}>();

const cart = ref<Record<number, number>>({});
const cartCount = ref(props.initialCartCount);
const cartProducts = ref<Product[]>([]);
const cartError = ref('');
const isCartOpen = ref(false);
const isSearchOpen = ref(false);
const searchQuery = ref('');
const searchResults = ref<Product[]>([]);
const searchError = ref('');
const isSearchLoading = ref(false);
const searchInput = ref<HTMLInputElement | null>(null);
let searchController: AbortController | undefined;
let cartRequestController: AbortController | undefined;
let releasePageScrollLock: (() => void) | null = null;

const cartItems = computed(() =>
    Object.entries(cart.value)
        .map(([id, quantity]) => {
            const product = cartProducts.value.find((item) => item.id === Number(id));

            return product ? { product, quantity } : null;
        })
        .filter((item): item is { product: Product; quantity: number } => item !== null),
);
const cartTotal = computed(() =>
    cartItems.value.reduce((total, item) => total + item.product.price * item.quantity, 0),
);
const searchResultsUrl = computed(
    () => `/products?search=${encodeURIComponent(searchQuery.value.trim())}#products`,
);

const parseProducts = (payload: unknown): Product[] => {
    if (typeof payload !== 'object' || payload === null || !('products' in payload) || !Array.isArray(payload.products)) {
        throw new Error('Dữ liệu tìm kiếm sản phẩm không hợp lệ.');
    }

    return payload.products.map((item: unknown) => {
        if (typeof item !== 'object' || item === null) {
            throw new Error('Thông tin sản phẩm không hợp lệ.');
        }

        const product = item as Record<string, unknown>;
        const isCategory = (category: unknown): category is ProductCategory =>
            category === null ||
            (typeof category === 'object' &&
                category !== null &&
                'id' in category &&
                typeof category.id === 'number' &&
                'name' in category &&
                typeof category.name === 'string');

        if (
            typeof product.id !== 'number' ||
            typeof product.name !== 'string' ||
            (typeof product.product_title !== 'string' && product.product_title !== null) ||
            typeof product.description !== 'string' ||
            typeof product.price !== 'number' ||
            !Array.isArray(product.image) ||
            !product.image.every((image: unknown) => typeof image === 'string') ||
            !isCategory(product.category) ||
            !isCategory(product.subcategory)
        ) {
            throw new Error('Thông tin sản phẩm không đầy đủ.');
        }

        return product as Product;
    });
};

const readCart = (): Record<number, number> => {
    const savedCart = sessionStorage.getItem('kbuser-cart');

    if (!savedCart) {
        return {};
    }

    let parsedCart: unknown;

    try {
        parsedCart = JSON.parse(savedCart);
    } catch (error) {
        if (!(error instanceof SyntaxError)) {
            throw error;
        }

        console.error('Giỏ hàng đã lưu không phải JSON hợp lệ.', error);
        sessionStorage.removeItem('kbuser-cart');

        return {};
    }

    if (typeof parsedCart !== 'object' || parsedCart === null || Array.isArray(parsedCart)) {
        console.error('Định dạng giỏ hàng đã lưu không hợp lệ.');
        sessionStorage.removeItem('kbuser-cart');

        return {};
    }

    return Object.fromEntries(
        Object.entries(parsedCart).filter(
            ([id, quantity]) =>
                Number.isInteger(Number(id)) &&
                Number(id) > 0 &&
                Number.isInteger(quantity) &&
                Number(quantity) > 0,
        ),
    ) as Record<number, number>;
};

const refreshCart = async (): Promise<void> => {
    cartRequestController?.abort();
    cart.value = readCart();
    cartCount.value = Object.values(cart.value).reduce((count, quantity) => count + quantity, 0);
    cartError.value = '';

    const productIds = Object.keys(cart.value);

    if (productIds.length === 0) {
        cartProducts.value = [];

        return;
    }

    const params = new URLSearchParams();
    productIds.forEach((id) => params.append('ids[]', id));
    const controller = new AbortController();
    cartRequestController = controller;

    try {
        const response = await fetch(`/products/search?${params.toString()}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(`Không thể tải giỏ hàng (${response.status}).`);
        }

        const products = parseProducts(await response.json());
        const availableProductIds = new Set(products.map((product) => product.id));
        cart.value = Object.fromEntries(
            Object.entries(cart.value).filter(([id]) => availableProductIds.has(Number(id))),
        );
        cartCount.value = Object.values(cart.value).reduce((count, quantity) => count + quantity, 0);
        cartProducts.value = products;
        sessionStorage.setItem('kbuser-cart', JSON.stringify(cart.value));
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        console.error('Không thể tải thông tin sản phẩm trong giỏ hàng.', error);
        cartError.value = 'Không thể tải sản phẩm trong giỏ hàng. Vui lòng thử lại.';
    }
};

const updateCart = (nextCart: Record<number, number>): void => {
    cart.value = nextCart;
    cartCount.value = Object.values(nextCart).reduce((count, quantity) => count + quantity, 0);
    sessionStorage.setItem('kbuser-cart', JSON.stringify(nextCart));
    window.dispatchEvent(new Event('kbuser-cart-updated'));
};

const changeQuantity = (productId: number, delta: number): void => {
    const nextCart = { ...cart.value };
    const quantity = (nextCart[productId] ?? 0) + delta;

    if (quantity <= 0) {
        delete nextCart[productId];
    } else {
        nextCart[productId] = quantity;
    }

    updateCart(nextCart);
};

const removeFromCart = (productId: number): void => {
    const nextCart = { ...cart.value };
    delete nextCart[productId];
    updateCart(nextCart);
};

const toggleCart = (): void => {
    isCartOpen.value = !isCartOpen.value;

    if (isCartOpen.value) {
        void refreshCart();
    }
};

const openCart = (): void => {
    isCartOpen.value = true;
    void refreshCart();
};

const toggleSearch = async (): Promise<void> => {
    isSearchOpen.value = !isSearchOpen.value;

    if (isSearchOpen.value) {
        await nextTick();
        searchInput.value?.focus();
    }
};

const searchProducts = async (query: string): Promise<void> => {
    searchController?.abort();
    searchResults.value = [];
    searchError.value = '';
    isSearchLoading.value = false;

    if (query.trim().length < 2) {
        return;
    }

    const controller = new AbortController();
    searchController = controller;
    isSearchLoading.value = true;

    try {
        const response = await fetch(`/products/search?q=${encodeURIComponent(query.trim())}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(`Tìm kiếm sản phẩm thất bại (${response.status}).`);
        }

        const products = parseProducts(await response.json());

        if (!controller.signal.aborted) {
            searchResults.value = products;
        }
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        console.error('Không thể tìm kiếm sản phẩm.', error);
        searchError.value = 'Không thể tìm sản phẩm lúc này. Vui lòng thử lại.';
    } finally {
        if (!controller.signal.aborted) {
            isSearchLoading.value = false;
        }
    }
};

const onSearchInput = (event: Event): void => {
    const input = event.target;

    if (input instanceof HTMLInputElement) {
        searchQuery.value = input.value;
        void searchProducts(input.value);
    }
};

const submitSearch = (): void => {
    const query = searchQuery.value.trim();

    if (query.length > 0) {
        router.visit(searchResultsUrl.value);
        isSearchOpen.value = false;
    }
};

const formatPrice = (price: number): string =>
    price > 0 ? `${new Intl.NumberFormat('vi-VN').format(price)}₫` : 'Liên hệ';

const productImage = (product: Product): string | null => product.image[0] ?? null;

const requestQuote = (): void => {
    if (props.activePage === 'home') {
        isCartOpen.value = false;
        window.dispatchEvent(new Event('kbuser-checkout'));
    }
};

const onEscape = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        isCartOpen.value = false;
        isSearchOpen.value = false;
    }
};

watch(
    () => props.activePage,
    () => {
        void refreshCart();
    },
);

watch(isCartOpen, (isOpen) => {
    if (isOpen) {
        releasePageScrollLock ??= lockPageScroll();
        return;
    }

    releasePageScrollLock?.();
    releasePageScrollLock = null;
});

onMounted(() => {
    window.addEventListener('kbuser-cart-updated', refreshCart);
    window.addEventListener('kbuser-cart-open', openCart);
    window.addEventListener('keydown', onEscape);
    document.addEventListener('visibilitychange', refreshCart);
    void refreshCart();
});

onUnmounted(() => {
    window.removeEventListener('kbuser-cart-updated', refreshCart);
    window.removeEventListener('kbuser-cart-open', openCart);
    window.removeEventListener('keydown', onEscape);
    document.removeEventListener('visibilitychange', refreshCart);
    searchController?.abort();
    cartRequestController?.abort();
    releasePageScrollLock?.();
    releasePageScrollLock = null;
});

</script>

<template>
    <div class="navbar-tools">
        <button
            class="icon-btn"
            id="searchFocus"
            type="button"
            aria-label="Tìm kiếm sản phẩm"
            :aria-expanded="isSearchOpen"
            @click="toggleSearch"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                <circle cx="10.8" cy="10.8" r="6.8"></circle>
                <path d="m16 16 5 5"></path>
            </svg>
        </button>

        <form v-if="isSearchOpen" class="navbar-search-panel" role="search" @submit.prevent="submitSearch">
            <label class="navbar-search-label" for="navbarProductSearch">Tìm kiếm sản phẩm</label>
            <input
                id="navbarProductSearch"
                ref="searchInput"
                :value="searchQuery"
                type="search"
                placeholder="Tên sản phẩm, danh mục..."
                autocomplete="off"
                @input="onSearchInput"
            >
            <button type="submit" aria-label="Xem tất cả kết quả">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="10.8" cy="10.8" r="6.8"></circle>
                    <path d="m16 16 5 5"></path>
                </svg>
            </button>
            <div v-if="searchQuery.trim().length > 0" class="navbar-search-results" role="listbox" aria-label="Kết quả tìm kiếm sản phẩm">
                <p v-if="searchQuery.trim().length < 2" class="navbar-search-message">Nhập ít nhất 2 ký tự để tìm sản phẩm.</p>
                <p v-else-if="isSearchLoading" class="navbar-search-message">Đang tìm sản phẩm...</p>
                <p v-else-if="searchError" class="navbar-search-message" role="alert">{{ searchError }}</p>
                <p v-else-if="searchResults.length === 0" class="navbar-search-message">Không tìm thấy sản phẩm phù hợp.</p>
                <template v-else>
                    <Link
                        v-for="product in searchResults"
                        :key="product.id"
                        class="navbar-search-result"
                        :href="`/products?search=${encodeURIComponent(searchQuery.trim())}#products`"
                        role="option"
                        @click="isSearchOpen = false"
                    >
                        <span class="navbar-search-result-image">
                            <img v-if="productImage(product)" :src="productImage(product) ?? undefined" :alt="product.name">
                            <span v-else>{{ product.category?.name ?? 'HM' }}</span>
                        </span>
                        <span class="navbar-search-result-copy">
                            <strong>{{ product.product_title || product.name }}</strong>
                            <small>{{ product.category?.name ?? 'Sản phẩm' }} · {{ formatPrice(product.price) }}</small>
                        </span>
                    </Link>
                    <button class="navbar-search-all" type="submit">
                        Xem tất cả kết quả cho “{{ searchQuery.trim() }}”
                    </button>
                </template>
            </div>
        </form>

        <button
            class="icon-btn"
            id="cartOpen"
            type="button"
            aria-label="Mở giỏ hàng"
            :aria-expanded="isCartOpen"
            @click="toggleCart"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                <path d="M3 4h2l2.2 11.1a2 2 0 0 0 2 1.6h8.4a2 2 0 0 0 1.9-1.4L22 8H6"></path>
                <circle cx="10" cy="20" r="1"></circle>
                <circle cx="18" cy="20" r="1"></circle>
            </svg>
            <span class="cart-count" id="cartCount">{{ cartCount }}</span>
        </button>

        <Teleport to="body">
            <Transition name="cart-backdrop">
                <div v-if="isCartOpen" class="overlay open" aria-label="Đóng giỏ hàng" @click="isCartOpen = false"></div>
            </Transition>
            <aside class="drawer" :class="{ open: isCartOpen }" :aria-hidden="!isCartOpen" aria-label="Giỏ hàng">
                <div class="drawer-head">
                    <h2>Giỏ hàng <span>({{ cartCount }})</span></h2>
                    <button class="close-btn" type="button" aria-label="Đóng giỏ hàng" @click="isCartOpen = false">×</button>
                </div>
                <div class="cart-items">
                    <p v-if="cartError" class="navbar-search-message" role="alert">{{ cartError }}</p>
                    <div v-else-if="cartItems.length === 0" class="cart-empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3">
                            <path d="M3 4h2l2.2 11.1a2 2 0 0 0 2 1.6h8.4a2 2 0 0 0 1.9-1.4L22 8H6"></path>
                            <circle cx="10" cy="20" r="1"></circle>
                            <circle cx="18" cy="20" r="1"></circle>
                        </svg>
                        <strong>Giỏ hàng đang trống</strong>
                        <span>Thêm sản phẩm bạn quan tâm để bắt đầu.</span>
                    </div>
                    <div v-for="{ product, quantity } in cartItems" :key="product.id" class="cart-row">
                        <div class="cart-thumb">
                            <img v-if="productImage(product)" :src="productImage(product) ?? undefined" :alt="product.name">
                            <span v-else>{{ product.category?.name ?? 'HM' }}</span>
                        </div>
                        <div>
                            <div class="cart-product">{{ product.name }}</div>
                            <div class="cart-price">{{ formatPrice(product.price) }}</div>
                            <div class="qty">
                                <button type="button" :aria-label="`Giảm số lượng ${product.name}`" @click="changeQuantity(product.id, -1)">−</button>
                                <span>{{ quantity }}</span>
                                <button type="button" :aria-label="`Tăng số lượng ${product.name}`" @click="changeQuantity(product.id, 1)">+</button>
                            </div>
                            <button class="remove" type="button" @click="removeFromCart(product.id)">Xóa sản phẩm</button>
                        </div>
                        <strong class="cart-row-total">{{ formatPrice(product.price * quantity) }}</strong>
                    </div>
                </div>
                <div v-if="cartItems.length > 0" class="cart-summary">
                    <div class="summary-line"><span>Tạm tính ({{ cartCount }} sản phẩm)</span><strong>{{ formatPrice(cartTotal) }}</strong></div>
                    <div class="summary-line"><span>Vận chuyển</span><span>Báo sau</span></div>
                    <div class="summary-line total"><span>Tổng tham khảo</span><span>{{ formatPrice(cartTotal) }}</span></div>
                    <button
                        v-if="props.activePage === 'home'"
                        class="btn btn-primary"
                        type="button"
                        @click="requestQuote"
                    >Yêu cầu báo giá</button>
                    <Link v-else class="btn btn-primary" href="/contacts" @click="isCartOpen = false">Yêu cầu báo giá</Link>
                    <div class="cart-hint">Giá hiển thị chỉ để tham khảo. Báo giá thực tế sẽ được xác nhận theo yêu cầu.</div>
                </div>
            </aside>
        </Teleport>
    </div>
</template>
