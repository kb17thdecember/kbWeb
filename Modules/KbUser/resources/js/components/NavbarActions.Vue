<script setup lang="ts">
import { router, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { downloadCartExcelWorkbook } from '../utils/cartExcelExporter';
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

const page = usePage<{
    auth?: { user?: AuthUser | null; loginMethod?: string | null; googleError?: string | null; wishlistIds?: number[] };
    siteSettings?: Record<string, string>;
}>();
const authUser = computed<AuthUser | null>(() => page.props.auth?.user ?? null);
const siteSettings = computed<Record<string, string>>(() => page.props.siteSettings ?? {});
const loginMethod = computed<string>(() => page.props.auth?.loginMethod ?? 'account');
const googleError = computed<string | null>(() => page.props.auth?.googleError ?? null);
const wishlistIds = computed<number[]>(() => page.props.auth?.wishlistIds ?? []);
const userInitial = computed(() => {
    const raw = authUser.value?.name?.trim() ?? '';

    return raw ? raw.charAt(0).toUpperCase() : 'U';
});
const userShortName = computed(() => {
    const parts = (authUser.value?.name ?? '').trim().split(/\s+/).filter(Boolean);

    return parts[parts.length - 1] ?? '';
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
const accountTab = ref<'profile' | 'quotes' | 'wishlist'>('quotes');
const isLoadingAccount = ref(false);
const myQuotes = ref<QuoteRecord[]>([]);
const myWishlistProducts = ref<Product[]>([]);
const profileSuccess = ref('');
const passwordSuccess = ref('');
const showCurrentPassword = ref(false);
const showNewPassword = ref(false);

const profileForm = useForm({
    name: '',
    phone: '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

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

const openAccountModal = async (tab: 'profile' | 'quotes' | 'wishlist' = 'quotes'): Promise<void> => {
    accountTab.value = tab;
    profileForm.name = authUser.value?.name ?? '';
    profileForm.phone = authUser.value?.phone ?? '';
    profileForm.clearErrors();
    passwordForm.reset();
    passwordForm.clearErrors();
    profileSuccess.value = '';
    passwordSuccess.value = '';
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

const submitProfileUpdate = (): void => {
    profileSuccess.value = '';
    profileForm.put('/my-account/profile', {
        preserveScroll: true,
        onSuccess: () => {
            profileSuccess.value = 'Đã cập nhật thông tin cá nhân thành công.';
        },
    });
};

const submitPasswordUpdate = (): void => {
    passwordSuccess.value = '';
    passwordForm.put('/my-account/password', {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
            passwordSuccess.value = 'Đã đổi mật khẩu thành công.';
        },
    });
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

    const rows = cartItems.value.map(({ product, quantity }, idx) => ({
        index: idx + 1,
        name: product.name,
        category: product.category?.name ?? 'Vật tư HVAC',
        quantity,
        unitPrice: Number(product.price) || 0,
        lineTotal: (Number(product.price) || 0) * quantity,
    }));

    downloadCartExcelWorkbook(
        rows,
        cartCount.value,
        cartTotal.value,
        `bang-du-toan-hvac-mart-${new Date().toISOString().slice(0, 10)}.xlsx`,
    );
};

const printCartQuote = (): void => {
    if (cartItems.value.length === 0) {
        return;
    }

    const win = window.open('', '_blank', 'width=860,height=720');
    if (!win) {
        return;
    }

    const brandName = siteSettings.value.company_name || 'HVAC Mart';
    const hotline = siteSettings.value.hotline || '0979.807.234';
    const contactEmail = siteSettings.value.contact_email || 'kbnguyen0311@gmail.com';

    const rowsHtml = cartItems.value
        .map(
            ({ product, quantity }, index) => `
            <tr>
                <td>${index + 1}</td>
                <td><strong>${product.name}</strong><br><small>${product.category?.name ?? 'Vật tư HVAC'}</small></td>
                <td>${quantity}</td>
                <td>${formatPrice(product.price)}</td>
                <td><strong>${formatPrice(product.price * quantity)}</strong></td>
            </tr>`,
        )
        .join('');

    win.document.write(`<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Bảng dự toán vật tư ${brandName}</title>
    <style>
        body { font-family: "Times New Roman", Times, serif; font-size: 14pt; color: #000; padding: 32px; }
        h1 { margin: 0 0 6px; font-size: 18pt; text-align: center; }
        p { margin: 4px 0; font-size: 14pt; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-family: "Times New Roman", Times, serif; font-size: 14pt; }
        th, td { border: 1px solid #000; padding: 10px 8px; vertical-align: middle; text-align: center; }
        th { background: #1e3328; color: #fff; font-weight: bold; }
        .total-row td { font-weight: bold; background: #f4f1ea; }
    </style>
</head>
<body>
    <h1>${brandName.toUpperCase()} — BẢNG TỔNG HỢP DỰ TOÁN VẬT TƯ THAM KHẢO</h1>
    <p>Hotline: ${hotline} · Email: ${contactEmail} · Ngày lập: ${new Date().toLocaleDateString('vi-VN')}</p>
    ${authUser.value ? `<p>Khách hàng: <strong>${authUser.value.name}</strong> (${authUser.value.email})</p>` : ''}
    <table>
        <thead>
            <tr>
                <th style="width:56px;">STT</th>
                <th>Tên thiết bị / Vật tư</th>
                <th style="width:100px;">Số lượng</th>
                <th style="width:160px;">Đơn giá</th>
                <th style="width:170px;">Thành tiền</th>
            </tr>
        </thead>
        <tbody>
            ${rowsHtml}
            <tr class="total-row">
                <td colspan="2">TỔNG GIÁ TRỊ THAM KHẢO</td>
                <td>${cartCount.value}</td>
                <td></td>
                <td>${formatPrice(cartTotal.value)}</td>
            </tr>
        </tbody>
    </table>
    <p style="margin-top:18px;font-style:italic;text-align:left;">* Ghi chú: Đơn giá trên là giá tham khảo. Vui lòng gửi yêu cầu báo giá tại ${brandName} để nhận chiết khấu chính thức theo khối lượng dự án.</p>
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

const openCalculatorFromNavbar = (): void => {
    isSearchOpen.value = false;
    isUserMenuOpen.value = false;
    window.dispatchEvent(new CustomEvent('kbuser-calculator-open'));
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
            id="calculatorToggle"
            type="button"
            aria-label="Công cụ tính chọn thiết bị HVAC"
            title="Công cụ tính chọn thiết bị"
            @click="openCalculatorFromNavbar"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                <rect x="4" y="2" width="16" height="20" rx="2" />
                <line x1="8" y1="6" x2="16" y2="6" />
                <line x1="8" y1="11" x2="10" y2="11" />
                <line x1="14" y1="11" x2="16" y2="11" />
                <line x1="8" y1="15" x2="10" y2="15" />
                <line x1="14" y1="15" x2="16" y2="15" />
                <line x1="8" y1="19" x2="16" y2="19" />
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
                    v-if="loginMethod === 'google' && authUser.avatar && !avatarFailed"
                    :src="authUser.avatar"
                    alt=""
                    referrerpolicy="no-referrer"
                    class="kb-user-avatar"
                    @error="avatarFailed = true"
                >
                <span v-else class="kb-user-initial">{{ userInitial }}</span>
                <span class="kb-user-name">{{ userShortName }}</span>
            </button>

            <div v-if="isUserMenuOpen" class="kb-user-dropdown" role="menu">
                <div class="kb-user-dropdown-head">
                    <strong>{{ authUser.name }}</strong>
                    <small>{{ authUser.email }}</small>
                    <small v-if="authUser.phone">{{ authUser.phone }}</small>
                </div>
                <div class="kb-user-dropdown-links">
                    <button type="button" class="kb-user-dropdown-item" @click="openAccountModal('profile')">
                        {{ loginMethod === 'google' ? 'Hồ sơ cá nhân' : 'Hồ sơ cá nhân & Đổi mật khẩu' }}
                    </button>
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
                            Xuất file Excel (.xlsx)
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
                                <h3>Hồ sơ cá nhân, Báo giá &amp; Sản phẩm đã lưu</h3>
                            </div>
                            <button class="close-btn" type="button" aria-label="Đóng cửa sổ" @click="isAccountModalOpen = false">×</button>
                        </div>

                        <div class="kb-auth-tabs" role="tablist">
                            <button
                                type="button"
                                class="kb-auth-tab"
                                :class="{ active: accountTab === 'profile' }"
                                @click="accountTab = 'profile'"
                            >
                                {{ loginMethod === 'google' ? 'Hồ sơ cá nhân' : 'Hồ sơ & Mật khẩu' }}
                            </button>
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

                        <div
                            v-if="accountTab === 'profile'"
                            class="kb-account-body kb-profile-tab-body"
                            :class="{ 'is-single-col': loginMethod === 'google' }"
                        >
                            <form class="kb-profile-section" @submit.prevent="submitProfileUpdate">
                                <div class="kb-profile-section-head">
                                    <h4 class="kb-profile-heading">Thông tin cá nhân</h4>
                                    <p class="kb-profile-sub">Cập nhật họ tên và số điện thoại liên hệ dự án của bạn.</p>
                                </div>
                                <p v-if="profileSuccess" class="kb-auth-alert is-success" role="status">{{ profileSuccess }}</p>
                                <div class="field">
                                    <label for="kbProfileName">HỌ VÀ TÊN *</label>
                                    <input
                                        id="kbProfileName"
                                        v-model="profileForm.name"
                                        type="text"
                                        maxlength="255"
                                        placeholder="Nguyễn Văn A"
                                        required
                                    >
                                    <span v-if="profileForm.errors.name" class="field-error">{{ profileForm.errors.name }}</span>
                                </div>
                                <div class="field">
                                    <label for="kbProfilePhone">SỐ ĐIỆN THOẠI *</label>
                                    <input
                                        id="kbProfilePhone"
                                        v-model="profileForm.phone"
                                        type="tel"
                                        maxlength="30"
                                        placeholder="09xx xxx xxx"
                                        required
                                    >
                                    <span v-if="profileForm.errors.phone" class="field-error">{{ profileForm.errors.phone }}</span>
                                </div>
                                <div class="field">
                                    <label for="kbProfileEmail">EMAIL ĐĂNG NHẬP</label>
                                    <input
                                        id="kbProfileEmail"
                                        :value="authUser?.email ?? ''"
                                        type="email"
                                        disabled
                                    >
                                </div>
                                <button class="btn kb-auth-submit" type="submit" :disabled="profileForm.processing">
                                    {{ profileForm.processing ? 'Đang lưu...' : 'Lưu thông tin cá nhân' }}
                                </button>
                            </form>

                            <form v-if="loginMethod !== 'google'" class="kb-profile-section" @submit.prevent="submitPasswordUpdate">
                                <div class="kb-profile-section-head">
                                    <h4 class="kb-profile-heading">Đổi mật khẩu</h4>
                                    <p class="kb-profile-sub">Sử dụng mật khẩu tối thiểu 8 ký tự để bảo mật tài khoản.</p>
                                </div>
                                <p v-if="passwordSuccess" class="kb-auth-alert is-success" role="status">{{ passwordSuccess }}</p>
                                <div class="field">
                                    <label for="kbCurrentPassword">MẬT KHẨU HIỆN TẠI *</label>
                                    <div class="kb-password-wrap">
                                        <input
                                            id="kbCurrentPassword"
                                            v-model="passwordForm.current_password"
                                            :type="showCurrentPassword ? 'text' : 'password'"
                                            autocomplete="current-password"
                                            placeholder="Nhập mật khẩu hiện tại"
                                            required
                                        >
                                        <button
                                            type="button"
                                            class="kb-password-toggle"
                                            :aria-label="showCurrentPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                                            @click="showCurrentPassword = !showCurrentPassword"
                                        >
                                            <svg v-if="showCurrentPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94" />
                                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19" />
                                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24" />
                                                <line x1="1" y1="1" x2="23" y2="23" />
                                            </svg>
                                            <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        </button>
                                    </div>
                                    <span v-if="passwordForm.errors.current_password" class="field-error">{{ passwordForm.errors.current_password }}</span>
                                </div>

                                <div class="field">
                                    <label for="kbNewPassword">MẬT KHẨU MỚI *</label>
                                    <div class="kb-password-wrap">
                                        <input
                                            id="kbNewPassword"
                                            v-model="passwordForm.password"
                                            :type="showNewPassword ? 'text' : 'password'"
                                            minlength="8"
                                            autocomplete="new-password"
                                            placeholder="Tối thiểu 8 ký tự"
                                            required
                                        >
                                        <button
                                            type="button"
                                            class="kb-password-toggle"
                                            :aria-label="showNewPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                                            @click="showNewPassword = !showNewPassword"
                                        >
                                            <svg v-if="showNewPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94" />
                                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19" />
                                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24" />
                                                <line x1="1" y1="1" x2="23" y2="23" />
                                            </svg>
                                            <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        </button>
                                    </div>
                                    <span v-if="passwordForm.errors.password" class="field-error">{{ passwordForm.errors.password }}</span>
                                </div>

                                <div class="field">
                                    <label for="kbNewPasswordConfirm">XÁC NHẬN MẬT KHẨU MỚI *</label>
                                    <div class="kb-password-wrap">
                                        <input
                                            id="kbNewPasswordConfirm"
                                            v-model="passwordForm.password_confirmation"
                                            :type="showNewPassword ? 'text' : 'password'"
                                            minlength="8"
                                            autocomplete="new-password"
                                            placeholder="Nhập lại mật khẩu mới"
                                            required
                                        >
                                    </div>
                                </div>

                                <button class="btn kb-auth-submit" type="submit" :disabled="passwordForm.processing">
                                    {{ passwordForm.processing ? 'Đang đổi mật khẩu...' : 'Cập nhật mật khẩu mới' }}
                                </button>
                            </form>
                        </div>

                        <div v-else-if="isLoadingAccount" class="kb-account-body">
                            <div class="empty-state">
                                Đang tải dữ liệu tài khoản...
                            </div>
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
