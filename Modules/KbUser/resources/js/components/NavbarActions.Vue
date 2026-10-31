<script setup lang="ts">
import { router, Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { lockPageScroll } from '../utils/pageScrollLock';
import UserAuthPanel from './UserAuthPanel.Vue';

type AuthUser = {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    avatar?: string | null;
    is_admin?: boolean;
};

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

type QuoteRecord = {
    id: number;
    project_name: string | null;
    category_id: string | null;
    product: string | null;
    message: string;
    status: string;
    attachment_name: string | null;
    attachment_url: string | null;
    created_at: string;
};

const props = defineProps<{
    initialCartCount: number;
    activePage?: string;
}>();

const page = usePage<{ auth?: { user?: AuthUser | null; googleError?: string | null; wishlistIds?: number[] } }>();
const authUser = computed<AuthUser | null>(() => page.props.auth?.user ?? null);
const googleError = computed<string | null>(() => page.props.auth?.googleError ?? null);
const wishlistIds = computed<number[]>(() => page.props.auth?.wishlistIds ?? []);
const userInitial = computed(() => {
    const raw = authUser.value?.name?.trim() ?? '';

    return raw ? raw.charAt(0).toUpperCase() : 'U';
});
const avatarFailed = ref(false);

watch(
    () => authUser.value?.avatar,
    () => {
        avatarFailed.value = false;
    },
);

const colorMode = ref<'light' | 'dark'>('light');
const isAccountModalOpen = ref(false);
const accountTab = ref<'quotes' | 'wishlist'>('quotes');
const isLoadingAccount = ref(false);
const myQuotes = ref<QuoteRecord[]>([]);
const myWishlistProducts = ref<Product[]>([]);

const cart = ref<Record<number, number>>({});
const cartCount = ref(props.initialCartCount);
const cartProducts = ref<Product[]>([]);
const cartError = ref('');
const isCartOpen = ref(false);
const isSearchOpen = ref(false);
const isAuthModalOpen = ref(false);
const authModalMode = ref<'login' | 'register'>('login');
const isUserMenuOpen = ref(false);
const searchQuery = ref('');
const searchResults = ref<Product[]>([]);
const searchError = ref('');
const isSearchLoading = ref(false);
const navbarToolsRef = ref<HTMLElement | null>(null);
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
    isUserMenuOpen.value = false;

    if (isCartOpen.value) {
        void refreshCart();
    }
};

const openCart = (): void => {
    isCartOpen.value = true;
    isUserMenuOpen.value = false;
    void refreshCart();
};

const openAuthModal = (mode: 'login' | 'register' = 'login'): void => {
    authModalMode.value = mode;
    isAuthModalOpen.value = true;
    isSearchOpen.value = false;
    isCartOpen.value = false;
};

const onOpenAuthEvent = (event: Event): void => {
    const detail = (event as CustomEvent<{ mode?: 'login' | 'register' }>).detail;
    openAuthModal(detail?.mode ?? 'login');
};

const signOut = (): void => {
    isUserMenuOpen.value = false;
    router.post('/logout');
};

const toggleSearch = async (): Promise<void> => {
    isSearchOpen.value = !isSearchOpen.value;
    isUserMenuOpen.value = false;

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

const applyTheme = (mode: 'light' | 'dark'): void => {
    colorMode.value = mode;
    const root = document.documentElement;
    root.style.colorScheme = mode;
    root.setAttribute('data-theme', mode);
    document.body?.setAttribute('data-theme', mode);
    if (mode === 'dark') {
        root.style.setProperty('--lightningcss-light', ' ');
        root.style.setProperty('--lightningcss-dark', 'initial');
    } else {
        root.style.setProperty('--lightningcss-light', 'initial');
        root.style.setProperty('--lightningcss-dark', ' ');
    }
    try {
        localStorage.setItem('kbuser-theme', mode);
    } catch {
        // Ignore storage errors
    }
};

const toggleTheme = (): void => {
    applyTheme(colorMode.value === 'dark' ? 'light' : 'dark');
};

const openAccountModal = async (tab: 'quotes' | 'wishlist' = 'quotes'): Promise<void> => {
    accountTab.value = tab;
    isAccountModalOpen.value = true;
    isUserMenuOpen.value = false;
    isLoadingAccount.value = true;

    try {
        const response = await fetch('/my-account/overview', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (response.ok) {
            const data = (await response.json()) as {
                quotes: QuoteRecord[];
                wishlistProducts: Product[];
            };
            myQuotes.value = data.quotes ?? [];
            myWishlistProducts.value = data.wishlistProducts ?? [];
        }
    } catch (error) {
        console.error('Không thể tải dữ liệu tài khoản.', error);
    } finally {
        isLoadingAccount.value = false;
    }
};

const removeWishlistItem = (productId: number): void => {
    router.post(
        `/wishlist/${productId}`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                myWishlistProducts.value = myWishlistProducts.value.filter((p) => p.id !== productId);
            },
        },
    );
};

const addWishlistToCart = (product: Product): void => {
    const nextCart = { ...cart.value };
    nextCart[product.id] = (nextCart[product.id] ?? 0) + 1;
    updateCart(nextCart);
};

const quoteStatusLabel = (status: string): string => {
    const normalized = status.toLowerCase();
    if (normalized === 'completed' || normalized === 'done' || normalized === 'processed') {
        return 'Đã phản hồi báo giá';
    }
    if (normalized === 'in_progress' || normalized === 'processing') {
        return 'Đang xử lý kỹ thuật';
    }
    return 'Đang tiếp nhận';
};

const formatDate = (value: string): string =>
    new Date(value).toLocaleDateString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });

const exportCartCsv = (): void => {
    if (cartItems.value.length === 0) {
        return;
    }

    const headers = ['STT', 'Ten san pham', 'Danh muc', 'So luong', 'Don gia tham khao (VND)', 'Thanh tien (VND)'];
    const rows = cartItems.value.map(({ product, quantity }, idx) => [
        String(idx + 1),
        `"${product.name.replace(/"/g, '""')}"`,
        `"${(product.category?.name ?? 'Vat tu HVAC').replace(/"/g, '""')}"`,
        String(quantity),
        String(product.price),
        String(product.price * quantity),
    ]);
    rows.push(['', '"TONG CONG THAM KHAO"', '', String(cartCount.value), '', String(cartTotal.value)]);

    const csvContent = '\uFEFF' + [headers.join(','), ...rows.map((r) => r.join(','))].join('\r\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `bang-du-toan-hvac-mart-${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
};

const printCartQuote = (): void => {
    if (cartItems.value.length === 0) {
        return;
    }

    const win = window.open('', '_blank', 'width=860,height=720');
    if (!win) {
        return;
    }

    const rowsHtml = cartItems.value
        .map(
            ({ product, quantity }, index) => `
            <tr>
                <td style="padding:8px;border:1px solid #ccc;text-align:center;">${index + 1}</td>
                <td style="padding:8px;border:1px solid #ccc;"><strong>${product.name}</strong><br><small>${product.category?.name ?? ''}</small></td>
                <td style="padding:8px;border:1px solid #ccc;text-align:center;">${quantity}</td>
                <td style="padding:8px;border:1px solid #ccc;text-align:right;">${formatPrice(product.price)}</td>
                <td style="padding:8px;border:1px solid #ccc;text-align:right;"><strong>${formatPrice(product.price * quantity)}</strong></td>
            </tr>`,
        )
        .join('');

    win.document.write(`<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Bảng dự toán vật tư HVAC Mart</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1a211b; padding: 32px; }
        h1 { margin: 0 0 6px; font-size: 22px; }
        p { margin: 4px 0; font-size: 13px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        th { background: #1e3328; color: #fff; padding: 10px 8px; border: 1px solid #1e3328; text-align: left; }
        .total-row td { font-weight: bold; background: #f4f1ea; padding: 10px 8px; border: 1px solid #ccc; }
    </style>
</head>
<body>
    <h1>HVAC MART — BẢNG TỔNG HỢP DỰ TOÁN VẬT TƯ THAM KHẢO</h1>
    <p>Hotline: 0979.807.234 · Email: kbnguyen0311@gmail.com · Ngày lập: ${new Date().toLocaleDateString('vi-VN')}</p>
    ${authUser.value ? `<p>Khách hàng: <strong>${authUser.value.name}</strong> (${authUser.value.email})</p>` : ''}
    <table>
        <thead>
            <tr>
                <th style="width:48px;text-align:center;">STT</th>
                <th>Tên thiết bị / Vật tư</th>
                <th style="width:90px;text-align:center;">Số lượng</th>
                <th style="width:140px;text-align:right;">Đơn giá</th>
                <th style="width:150px;text-align:right;">Thành tiền</th>
            </tr>
        </thead>
        <tbody>
            ${rowsHtml}
            <tr class="total-row">
                <td colspan="2">TỔNG GIÁ TRỊ THAM KHẢO</td>
                <td style="text-align:center;">${cartCount.value}</td>
                <td></td>
                <td style="text-align:right;">${formatPrice(cartTotal.value)}</td>
            </tr>
        </tbody>
    </table>
    <p style="margin-top:18px;font-style:italic;">* Ghi chú: Đơn giá trên là giá tham khảo. Vui lòng gửi yêu cầu báo giá tại HVAC Mart để nhận chiết khấu chính thức theo khối lượng dự án.</p>
</body>
</html>`);
    win.document.close();
    win.focus();
    win.print();
};

const toggleUserMenu = (): void => {
    isUserMenuOpen.value = !isUserMenuOpen.value;
    if (isUserMenuOpen.value) {
        isSearchOpen.value = false;
        isCartOpen.value = false;
    }
};

const onDocumentPointerDown = (event: MouseEvent): void => {
    const target = event.target as Node | null;
    if (target && navbarToolsRef.value && !navbarToolsRef.value.contains(target)) {
        isSearchOpen.value = false;
        isUserMenuOpen.value = false;
    }
};

const onEscape = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        isCartOpen.value = false;
        isSearchOpen.value = false;
        isAuthModalOpen.value = false;
        isAccountModalOpen.value = false;
        isUserMenuOpen.value = false;
    }
};

watch(
    () => props.activePage,
    () => {
        void refreshCart();
    },
);

watch(
    googleError,
    (errorMsg) => {
        if (errorMsg) {
            openAuthModal('login');
        }
    },
    { immediate: true },
);

watch([isCartOpen, isAuthModalOpen, isAccountModalOpen], ([cartOpen, authOpen, accountOpen]) => {
    if (cartOpen || authOpen || accountOpen) {
        releasePageScrollLock ??= lockPageScroll();
        return;
    }

    releasePageScrollLock?.();
    releasePageScrollLock = null;
});

onMounted(() => {
    try {
        const savedTheme = localStorage.getItem('kbuser-theme');
        if (savedTheme === 'dark' || savedTheme === 'light') {
            applyTheme(savedTheme);
        } else if (window.matchMedia?.('(prefers-color-scheme: dark)').matches) {
            applyTheme('dark');
        } else {
            applyTheme('light');
        }
    } catch {
        applyTheme('light');
    }

    window.addEventListener('kbuser-cart-updated', refreshCart);
    window.addEventListener('kbuser-cart-open', openCart);
    window.addEventListener('kbuser-auth-open', onOpenAuthEvent);
    window.addEventListener('keydown', onEscape);
    document.addEventListener('mousedown', onDocumentPointerDown);
    document.addEventListener('visibilitychange', refreshCart);
    void refreshCart();
});

onUnmounted(() => {
    window.removeEventListener('kbuser-cart-updated', refreshCart);
    window.removeEventListener('kbuser-cart-open', openCart);
    window.removeEventListener('kbuser-auth-open', onOpenAuthEvent);
    window.removeEventListener('keydown', onEscape);
    document.removeEventListener('mousedown', onDocumentPointerDown);
    document.removeEventListener('visibilitychange', refreshCart);
    searchController?.abort();
    cartRequestController?.abort();
    releasePageScrollLock?.();
    releasePageScrollLock = null;
});
</script>

<template>
    <div ref="navbarToolsRef" class="navbar-tools">
        <button
            class="icon-btn"
            id="themeToggle"
            type="button"
            :aria-label="colorMode === 'dark' ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối'"
            :title="colorMode === 'dark' ? 'Chế độ sáng' : 'Chế độ tối'"
            @click="toggleTheme"
        >
            <svg v-if="colorMode === 'dark'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                <circle cx="12" cy="12" r="4.5"></circle>
                <path d="M12 2v2.5M12 19.5V22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M2 12h2.5M19.5 12H22M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8"></path>
            </svg>
            <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                <path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.8 6.8 0 0 0 9.8 9.8Z"></path>
            </svg>
        </button>

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

        <div v-if="authUser" class="kb-user-menu-wrap">
            <button
                class="kb-user-trigger"
                type="button"
                aria-label="Menu tài khoản"
                :aria-expanded="isUserMenuOpen"
                @click="toggleUserMenu"
            >
                <img
                    v-if="authUser.avatar && !avatarFailed"
                    :src="authUser.avatar"
                    alt=""
                    referrerpolicy="no-referrer"
                    class="kb-user-avatar"
                    @error="avatarFailed = true"
                >
                <span v-else class="kb-user-initial">{{ userInitial }}</span>
                <span class="kb-user-name">{{ authUser.name }}</span>
            </button>

            <div v-if="isUserMenuOpen" class="kb-user-dropdown" role="menu">
                <div class="kb-user-dropdown-head">
                    <strong>{{ authUser.name }}</strong>
                    <small>{{ authUser.email }}</small>
                    <small v-if="authUser.phone">{{ authUser.phone }}</small>
                </div>
                <div class="kb-user-dropdown-links">
                    <a v-if="authUser.is_admin" href="/admin/dashboard" class="kb-user-dropdown-item">
                        Trang quản trị
                    </a>
                    <button type="button" class="kb-user-dropdown-item" @click="openAccountModal('quotes')">
                        Lịch sử yêu cầu báo giá
                    </button>
                    <button type="button" class="kb-user-dropdown-item" @click="openAccountModal('wishlist')">
                        Sản phẩm đã lưu ({{ wishlistIds.length }})
                    </button>
                    <Link href="/contacts" class="kb-user-dropdown-item" @click="isUserMenuOpen = false">
                        Gửi yêu cầu báo giá
                    </Link>
                    <button type="button" class="kb-user-dropdown-item is-danger" @click="signOut">
                        Đăng xuất
                    </button>
                </div>
            </div>
        </div>

        <button
            v-else
            id="userAuthOpen"
            class="kb-auth-trigger"
            type="button"
            aria-label="Đăng nhập hoặc đăng ký tài khoản"
            @click="openAuthModal('login')"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <circle cx="12" cy="8" r="4"></circle>
                <path d="M4 20c1.8-3.8 5-6 8-6s6.2 2.2 8 6"></path>
            </svg>
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
                    <div class="kb-cart-export-actions">
                        <button type="button" class="kb-export-btn" @click="printCartQuote">
                            In / Lưu PDF dự toán
                        </button>
                        <button type="button" class="kb-export-btn" @click="exportCartCsv">
                            Xuất file Excel (.csv)
                        </button>
                    </div>
                    <div class="cart-hint">Giá hiển thị chỉ để tham khảo. Báo giá thực tế sẽ được xác nhận theo yêu cầu.</div>
                </div>
            </aside>

            <Transition name="kb-modal">
                <div
                    v-if="isAccountModalOpen"
                    class="modal-wrap open"
                    role="presentation"
                    @click.self="isAccountModalOpen = false"
                >
                    <div class="modal kb-account-modal" role="dialog" aria-modal="true" aria-label="Hồ sơ khách hàng HVAC Mart">
                        <div class="kb-auth-modal-head">
                            <div>
                                <span class="eyebrow"><span class="dot"></span> CUSTOMER PORTAL</span>
                                <h3>Hồ sơ báo giá &amp; Sản phẩm đã lưu</h3>
                            </div>
                            <button class="close-btn" type="button" aria-label="Đóng cửa sổ" @click="isAccountModalOpen = false">×</button>
                        </div>

                        <div class="kb-auth-tabs" role="tablist">
                            <button
                                type="button"
                                class="kb-auth-tab"
                                :class="{ active: accountTab === 'quotes' }"
                                @click="accountTab = 'quotes'"
                            >
                                Lịch sử báo giá ({{ myQuotes.length }})
                            </button>
                            <button
                                type="button"
                                class="kb-auth-tab"
                                :class="{ active: accountTab === 'wishlist' }"
                                @click="accountTab = 'wishlist'"
                            >
                                Sản phẩm yêu thích ({{ myWishlistProducts.length }})
                            </button>
                        </div>

                        <div v-if="isLoadingAccount" class="empty-state">
                            Đang tải dữ liệu tài khoản...
                        </div>

                        <div v-else-if="accountTab === 'quotes'" class="kb-account-body">
                            <div v-if="myQuotes.length === 0" class="empty-state">
                                Bạn chưa có yêu cầu báo giá nào. Hãy thêm sản phẩm vào giỏ hoặc gửi yêu cầu tại trang Liên hệ.
                            </div>
                            <div v-else class="kb-quote-history-list">
                                <article v-for="quote in myQuotes" :key="quote.id" class="kb-quote-item">
                                    <div class="kb-quote-item-head">
                                        <div>
                                            <strong>#BG-{{ quote.id }} · {{ quote.project_name || quote.category_id || 'Yêu cầu báo giá vật tư' }}</strong>
                                            <small>Ngày gửi: {{ formatDate(quote.created_at) }}</small>
                                        </div>
                                        <span class="kb-quote-status" :class="`status-${quote.status}`">
                                            {{ quoteStatusLabel(quote.status) }}
                                        </span>
                                    </div>
                                    <p v-if="quote.product" class="kb-quote-products">{{ quote.product }}</p>
                                    <p class="kb-quote-msg">{{ quote.message }}</p>
                                    <a
                                        v-if="quote.attachment_url"
                                        :href="quote.attachment_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="kb-quote-attachment"
                                    >
                                        📎 File đính kèm: {{ quote.attachment_name || 'Tài liệu BOQ' }}
                                    </a>
                                </article>
                            </div>
                        </div>

                        <div v-else class="kb-account-body">
                            <div v-if="myWishlistProducts.length === 0" class="empty-state">
                                Bạn chưa lưu sản phẩm nào. Nhấn biểu tượng ♡ trên sản phẩm để lưu lại.
                            </div>
                            <div v-else class="kb-wishlist-list">
                                <article v-for="item in myWishlistProducts" :key="item.id" class="kb-wishlist-row">
                                    <div>
                                        <Link :href="`/products/${item.id}`" class="kb-calc-prod-link" @click="isAccountModalOpen = false">
                                            {{ item.name }}
                                        </Link>
                                        <small>{{ item.category?.name ?? 'Sản phẩm' }} · {{ formatPrice(item.price) }}</small>
                                    </div>
                                    <div class="kb-wishlist-row-actions">
                                        <button type="button" class="btn btn-primary kb-calc-add-btn" @click="addWishlistToCart(item)">
                                            + Giỏ hàng
                                        </button>
                                        <button type="button" class="btn btn-outline kb-calc-add-btn" @click="removeWishlistItem(item.id)">
                                            Bỏ lưu
                                        </button>
                                    </div>
                                </article>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>

            <Transition name="kb-modal">
                <div
                    v-if="isAuthModalOpen"
                    class="modal-wrap open"
                    role="presentation"
                    @click.self="isAuthModalOpen = false"
                >
                    <div class="modal kb-auth-modal" role="dialog" aria-modal="true" aria-label="Tài khoản HVAC Mart">
                        <div class="kb-auth-modal-head">
                            <div>
                                <span class="eyebrow"><span class="dot"></span> HVAC MART ACCOUNT</span>
                                <h3>Tài khoản khách hàng &amp; đối tác</h3>
                            </div>
                            <button class="close-btn" type="button" aria-label="Đóng cửa sổ" @click="isAuthModalOpen = false">×</button>
                        </div>

                        <UserAuthPanel
                            :initial-mode="authModalMode"
                            :google-error="googleError"
                            :compact="true"
                            @success="isAuthModalOpen = false"
                        />
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
