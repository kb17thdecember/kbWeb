<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import HvacCalculatorModal from '../../components/HvacCalculatorModal.Vue';
import UserLayout from '../../layout/UserLayout.Vue';
import { lockPageScroll } from '../../utils/pageScrollLock';

type Product = {
    id: number;
    name: string;
    product_title: string | null;
    description: string;
    price: number;
    image: string[] | null;
    category_id: number;
    subcategory_id: number | null;
    category: { id: number; name: string } | null;
    subcategory: { id: number; name: string } | null;
    reviews_avg_rating?: number | null;
    reviews_count?: number;
};

type ProductCategory = {
    id: number;
    name: string;
    children: { id: number; name: string; parent_id: number }[];
};

const props = defineProps<{
    categories: ProductCategory[];
    products: Product[];
}>();

const page = usePage<{ auth?: { user?: { id: number } | null; wishlistIds?: number[] } }>();
const authUser = computed(() => page.props.auth?.user ?? null);
const wishlistIds = computed<number[]>(() => page.props.auth?.wishlistIds ?? []);

const activeFilter = ref('all');
const activeSubcategoryId = ref<number | null>(null);
const priceFilter = ref<'all' | 'under3m' | '3m-10m' | 'over10m' | 'contact'>('all');
const sortBy = ref<'default' | 'price-asc' | 'price-desc' | 'name-asc'>('default');
const searchQuery = ref('');
const currentPage = ref(1);
const cart = ref<Record<number, number>>({});
const selectedProduct = ref<Product | null>(null);
const selectedImageIndex = ref(0);
const compareIds = ref<number[]>([]);
const isCompareModalOpen = ref(false);
const isCalculatorOpen = ref(false);
const toastMessage = ref('');
const isToastVisible = ref(false);
let toastTimer: ReturnType<typeof setTimeout> | undefined;
let releasePageScrollLock: (() => void) | null = null;

const filters = computed(() => [
    { label: 'Tất cả', value: 'all' },
    ...props.categories.map((category) => ({
        label: category.name,
        value: String(category.id),
    })),
]);

const selectedCategory = computed(
    () => props.categories.find((category) => String(category.id) === activeFilter.value) ?? null,
);

const compareProducts = computed<Product[]>(() =>
    compareIds.value
        .map((id) => props.products.find((p) => p.id === id))
        .filter((p): p is Product => Boolean(p)),
);

const normalize = (value: string): string =>
    value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('vi')
        .replace(/[^a-z0-9]/g, '');

const visibleProducts = computed(() => {
    const query = normalize(searchQuery.value.trim());

    const filtered = props.products.filter((product) => {
        const category = product.category?.name ?? '';
        const subcategory = product.subcategory?.name ?? '';
        const matchesCategory =
            activeFilter.value === 'all' || String(product.category_id) === activeFilter.value;
        const matchesSubcategory =
            activeSubcategoryId.value === null ||
            product.subcategory_id === activeSubcategoryId.value;
        const matchesSearch =
            query === '' ||
            normalize(`${product.product_title ?? ''} ${product.name} ${product.description} ${category} ${subcategory}`)
                .includes(query);

        let matchesPrice = true;
        if (priceFilter.value === 'contact') {
            matchesPrice = product.price <= 0;
        } else if (priceFilter.value === 'under3m') {
            matchesPrice = product.price > 0 && product.price < 3_000_000;
        } else if (priceFilter.value === '3m-10m') {
            matchesPrice = product.price >= 3_000_000 && product.price <= 10_000_000;
        } else if (priceFilter.value === 'over10m') {
            matchesPrice = product.price > 10_000_000;
        }

        return matchesCategory && matchesSubcategory && matchesSearch && matchesPrice;
    });

    const sorted = [...filtered];
    if (sortBy.value === 'price-asc') {
        sorted.sort((a, b) => (a.price || Number.MAX_SAFE_INTEGER) - (b.price || Number.MAX_SAFE_INTEGER));
    } else if (sortBy.value === 'price-desc') {
        sorted.sort((a, b) => b.price - a.price);
    } else if (sortBy.value === 'name-asc') {
        sorted.sort((a, b) => a.name.localeCompare(b.name, 'vi'));
    }

    return sorted;
});

const productsPerPage = 8;
const pageCount = computed(() =>
    Math.max(1, Math.ceil(visibleProducts.value.length / productsPerPage)),
);
const paginatedProducts = computed(() =>
    visibleProducts.value.slice(
        (currentPage.value - 1) * productsPerPage,
        currentPage.value * productsPerPage,
    ),
);

const cartItems = computed(() =>
    Object.entries(cart.value)
        .map(([id, quantity]) => {
            const product = props.products.find((item) => item.id === Number(id));

            return product ? { product, quantity } : null;
        })
        .filter((item): item is { product: Product; quantity: number } => item !== null),
);
const cartCount = computed(() =>
    cartItems.value.reduce((count, item) => count + item.quantity, 0),
);
const selectedImages = computed(() => selectedProduct.value?.image ?? []);
const selectedImage = computed(() => {
    const image = selectedImages.value[selectedImageIndex.value];

    return image ? productImageUrl(image) : null;
});

const imageUrl = (product: Product): string | null => {
    const image = product.image?.[0];

    return image ? productImageUrl(image) : null;
};

const productImageUrl = (image: string): string =>
    image.startsWith('data:') || image.startsWith('http') || image.startsWith('/')
        ? image
        : `/storage/${image}`;

const formatPrice = (price: number): string =>
    price > 0 ? `${new Intl.NumberFormat('vi-VN').format(price)}₫` : 'Liên hệ';

const notify = (message: string): void => {
    toastMessage.value = message;
    isToastVisible.value = true;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        isToastVisible.value = false;
    }, 2400);
};

const persistCart = (): void => {
    sessionStorage.setItem('kbuser-cart', JSON.stringify(cart.value));
    window.dispatchEvent(new Event('kbuser-cart-updated'));
};

const openCartDrawer = (): void => {
    window.dispatchEvent(new Event('kbuser-cart-open'));
};

watch([selectedProduct, isCompareModalOpen, isCalculatorOpen], ([product, compareOpen, calcOpen]) => {
    if (product || compareOpen || calcOpen) {
        releasePageScrollLock ??= lockPageScroll();
        return;
    }

    releasePageScrollLock?.();
    releasePageScrollLock = null;
});

const loadCart = (): void => {
    const savedCart = sessionStorage.getItem('kbuser-cart');

    if (!savedCart) {
        cart.value = {};

        return;
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

        return;
    }

    if (typeof parsedCart !== 'object' || parsedCart === null || Array.isArray(parsedCart)) {
        console.error('Định dạng giỏ hàng đã lưu không hợp lệ.');
        sessionStorage.removeItem('kbuser-cart');

        return;
    }

    const validProductIds = new Set(props.products.map((product) => product.id));
    const restoredCart: Record<number, number> = {};

    for (const [id, quantity] of Object.entries(parsedCart)) {
        const productId = Number(id);

        if (
            Number.isInteger(productId) &&
            validProductIds.has(productId) &&
            Number.isInteger(quantity) &&
            Number(quantity) > 0
        ) {
            restoredCart[productId] = Number(quantity);
        }
    }

    cart.value = restoredCart;
};

const addToCart = (product: Product): void => {
    cart.value[product.id] = (cart.value[product.id] ?? 0) + 1;
    persistCart();
    notify(`Đã thêm ${product.name} vào giỏ hàng.`);
};

const isWishlisted = (productId: number): boolean => wishlistIds.value.includes(productId);

const toggleWishlist = (product: Product): void => {
    if (!authUser.value) {
        window.dispatchEvent(new CustomEvent('kbuser-auth-open', { detail: { mode: 'login' } }));
        return;
    }

    const wasSaved = isWishlisted(product.id);
    router.post(
        `/wishlist/${product.id}`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                notify(
                    wasSaved
                        ? `Đã bỏ lưu ${product.name}.`
                        : `Đã lưu ${product.name} vào danh sách yêu thích.`,
                );
            },
        },
    );
};

const isCompared = (productId: number): boolean => compareIds.value.includes(productId);

const toggleCompare = (product: Product): void => {
    if (isCompared(product.id)) {
        compareIds.value = compareIds.value.filter((id) => id !== product.id);
        if (compareIds.value.length === 0) {
            isCompareModalOpen.value = false;
        }
        notify(`Đã bỏ ${product.name} khỏi bảng so sánh.`);
        return;
    }

    if (compareIds.value.length >= 2) {
        notify('Bạn chỉ có thể so sánh tối đa 2 sản phẩm cùng lúc.');
        return;
    }

    compareIds.value = [...compareIds.value, product.id];
    notify(`Đã thêm ${product.name} vào danh sách so sánh (${compareIds.value.length}/2).`);
};

const selectFilter = (filter: string): void => {
    activeFilter.value = filter;
    activeSubcategoryId.value = null;
    currentPage.value = 1;
};

const goToPage = (pageNum: number): void => {
    currentPage.value = Math.min(Math.max(1, pageNum), pageCount.value);
    document.querySelector('#products')?.scrollIntoView({ behavior: 'smooth' });
};

const openProduct = (product: Product): void => {
    selectedProduct.value = product;
    selectedImageIndex.value = 0;
};

const stepImage = (delta: number): void => {
    const imageCount = selectedImages.value.length;

    if (imageCount > 0) {
        selectedImageIndex.value =
            (selectedImageIndex.value + delta + imageCount) % imageCount;
    }
};

const onEscape = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        selectedProduct.value = null;
        isCompareModalOpen.value = false;
        isCalculatorOpen.value = false;
    }
};

watch([activeFilter, activeSubcategoryId, searchQuery, priceFilter, sortBy], () => {
    currentPage.value = 1;
});

onMounted(() => {
    loadCart();
    window.addEventListener('kbuser-cart-updated', loadCart);
    window.addEventListener('keydown', onEscape);

    const params = new URLSearchParams(window.location.search);
    searchQuery.value = params.get('search') ?? '';
    const requestedCategoryId = Number(params.get('category'));
    const requestedCategory = props.categories.find(
        (category) => category.id === requestedCategoryId,
    );

    if (requestedCategory) {
        activeFilter.value = String(requestedCategory.id);
        const requestedSubcategoryId = Number(params.get('subcategory'));

        if (requestedCategory.children.some((child) => child.id === requestedSubcategoryId)) {
            activeSubcategoryId.value = requestedSubcategoryId;
        }
    }

    if (window.location.hash === '#products') {
        document.querySelector('#products')?.scrollIntoView({ behavior: 'smooth' });
    }
});

onUnmounted(() => {
    window.removeEventListener('kbuser-cart-updated', loadCart);
    window.removeEventListener('keydown', onEscape);
    clearTimeout(toastTimer);
    releasePageScrollLock?.();
    releasePageScrollLock = null;
});
</script>

<template>
    <div>
        <Head title="HVAC Mart | Sản Phẩm" />

        <UserLayout :cart-count="cartCount" active-page="products">
            <main>
                <section class="page-intro" aria-live="polite">
                    <div class="wrap page-intro-inner">
                        <div>
                            <div class="eyebrow"><span class="dot"></span> HVAC MART / SẢN PHẨM</div>
                            <h1>Danh sách sản phẩm</h1>
                            <p>Bảo ôn K-FLEX và bình nước nóng Ariston cho các hạng mục công trình.</p>
                        </div>
                        <Link class="page-home-link" href="/"><FontAwesomeIcon :icon="['fas', 'arrow-left']" /> Về trang chủ</Link>
                    </div>
                </section>

                <section id="products" class="product-section">
                    <div class="wrap">
                        <div class="section-head">
                            <div>
                                <div class="eyebrow"><span class="dot"></span> BỘ SƯU TẬP SẢN PHẨM</div>
                                <h2 class="section-title">Chọn đúng vật tư.<br><em>Đúng nhu cầu.</em></h2>
                            </div>
                            <p class="section-desc">
                                Tìm kiếm theo tên sản phẩm, lọc theo mức giá, so sánh thông số kỹ thuật
                                hoặc sử dụng công cụ tính chọn nhanh cho công trình.
                            </p>
                        </div>

                        <div class="product-toolbar">
                            <div class="filters" aria-label="Lọc theo danh mục">
                                <button
                                    v-for="filter in filters"
                                    :key="filter.value"
                                    class="filter-btn"
                                    :class="{ active: activeFilter === filter.value }"
                                    type="button"
                                    @click="selectFilter(filter.value)"
                                >
                                    {{ filter.label }}
                                </button>
                            </div>
                            <label class="searchbox">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <circle cx="10.8" cy="10.8" r="6.8" />
                                    <path d="m16 16 5 5" />
                                </svg>
                                <input
                                    id="productSearch"
                                    v-model="searchQuery"
                                    type="search"
                                    placeholder="Tìm sản phẩm, danh mục..."
                                    aria-label="Tìm sản phẩm"
                                >
                            </label>
                        </div>

                        <div class="kb-advanced-filter-bar">
                            <div class="kb-adv-controls">
                                <div class="kb-adv-group">
                                    <label for="priceRangeFilter" class="kb-adv-label">KHOẢNG GIÁ</label>
                                    <div class="kb-adv-select-shell">
                                        <select id="priceRangeFilter" v-model="priceFilter">
                                            <option value="all">Tất cả mức giá</option>
                                            <option value="under3m">Dưới 3.000.000₫</option>
                                            <option value="3m-10m">Từ 3.000.000₫ – 10.000.000₫</option>
                                            <option value="over10m">Trên 10.000.000₫</option>
                                            <option value="contact">Báo giá theo dự án</option>
                                        </select>
                                        <svg class="kb-adv-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path d="m6 9 6 6 6-6" />
                                        </svg>
                                    </div>
                                </div>

                                <div class="kb-adv-group">
                                    <label for="sortBySelect" class="kb-adv-label">SẮP XẾP THEO</label>
                                    <div class="kb-adv-select-shell">
                                        <select id="sortBySelect" v-model="sortBy">
                                            <option value="default">Mặc định (Mới nhất)</option>
                                            <option value="price-asc">Giá: Thấp đến Cao</option>
                                            <option value="price-desc">Giá: Cao đến Thấp</option>
                                            <option value="name-asc">Tên sản phẩm: A → Z</option>
                                        </select>
                                        <svg class="kb-adv-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path d="m6 9 6 6 6-6" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="btn btn-primary kb-calc-trigger-btn"
                                @click="isCalculatorOpen = true"
                            >
                                Công cụ tính chọn thiết bị
                            </button>
                        </div>

                        <div v-if="selectedCategory?.children.length" class="subcategory-filters">
                            <span class="subcat-filter-label">Lọc theo dòng</span>
                            <button
                                class="subcat-filter"
                                :class="{ active: activeSubcategoryId === null }"
                                type="button"
                                @click="activeSubcategoryId = null"
                            >
                                Tất cả {{ selectedCategory.name }}
                            </button>
                            <button
                                v-for="subcategory in selectedCategory.children"
                                :key="subcategory.id"
                                class="subcat-filter"
                                :class="{ active: activeSubcategoryId === subcategory.id }"
                                type="button"
                                @click="activeSubcategoryId = subcategory.id"
                            >
                                {{ subcategory.name }}
                            </button>
                        </div>

                        <div v-if="paginatedProducts.length" class="product-grid">
                            <article
                                v-for="product in paginatedProducts"
                                :key="product.id"
                                class="product-card"
                            >
                                <div class="product-photo">
                                    <span class="pill">{{ product.category?.name ?? 'Sản phẩm kỹ thuật' }}</span>
                                    <div class="kb-card-top-actions">
                                        <button
                                            class="quick-btn kb-wish-icon-btn"
                                            :class="{ active: isWishlisted(product.id) }"
                                            type="button"
                                            :aria-label="`Lưu yêu thích ${product.name}`"
                                            @click="toggleWishlist(product)"
                                        >
                                            <span>{{ isWishlisted(product.id) ? '♥' : '♡' }}</span>
                                        </button>
                                        <button
                                            class="quick-btn kb-compare-icon-btn"
                                            :class="{ active: isCompared(product.id) }"
                                            type="button"
                                            :aria-label="`So sánh ${product.name}`"
                                            title="So sánh sản phẩm"
                                            @click="toggleCompare(product)"
                                        >
                                            <span>⇄</span>
                                        </button>
                                        <button
                                            class="quick-btn"
                                            type="button"
                                            :aria-label="`Xem nhanh ${product.name}`"
                                            @click="openProduct(product)"
                                        >
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        </button>
                                    </div>
                                    <img
                                        v-if="imageUrl(product)"
                                        class="product-image"
                                        :src="imageUrl(product) ?? undefined"
                                        :alt="product.name"
                                        loading="lazy"
                                    >
                                    <svg v-else class="product-placeholder" viewBox="0 0 110 110" fill="none" aria-hidden="true">
                                        <rect x="29" y="17" width="52" height="76" rx="12" fill="#d1d9c5" stroke="#879b7e" stroke-width="2" />
                                        <path d="M29 43h52v20H29z" fill="#345342" />
                                        <path d="M43 77h24" stroke="#879b7e" stroke-width="3" stroke-linecap="round" />
                                    </svg>
                                </div>
                                <div class="product-body">
                                    <div class="product-meta">
                                        {{ product.subcategory?.name ?? 'Danh mục sản phẩm' }}
                                        <span v-if="product.reviews_count && product.reviews_count > 0" class="kb-card-rating">
                                            · ★ {{ Number(product.reviews_avg_rating ?? 5).toFixed(1) }} ({{ product.reviews_count }})
                                        </span>
                                    </div>
                                    <h3 class="product-name">
                                        <Link :href="`/products/${product.id}`">{{ product.name }}</Link>
                                    </h3>
                                    <p class="product-desc">{{ product.product_title }}</p>
                                    <div class="product-bottom">
                                        <span class="price">
                                            {{ formatPrice(product.price) }}
                                            <small v-if="product.price > 0">Giá tham khảo · / sản phẩm</small>
                                        </span>
                                        <div class="kb-card-bottom-btns">
                                            <Link :href="`/products/${product.id}`" class="kb-card-detail-link">
                                                Chi tiết
                                            </Link>
                                            <button
                                                class="add-btn"
                                                type="button"
                                                :aria-label="`Thêm ${product.name} vào giỏ`"
                                                @click="addToCart(product)"
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
                        <div v-else class="product-grid">
                            <div class="empty-state">
                                {{
                                    products.length === 0
                                        ? 'Chưa có sản phẩm nào được cập nhật.'
                                        : 'Không tìm thấy sản phẩm phù hợp. Hãy thử từ khóa hoặc bộ lọc khác.'
                                }}
                            </div>
                        </div>

                        <nav v-if="pageCount > 1" class="pagination" aria-label="Phân trang sản phẩm">
                            <button
                                class="page-btn"
                                type="button"
                                aria-label="Trang trước"
                                :disabled="currentPage === 1"
                                @click="goToPage(currentPage - 1)"
                            ><FontAwesomeIcon :icon="['fas', 'arrow-left']" /></button>
                            <button
                                v-for="p in pageCount"
                                :key="p"
                                class="page-btn"
                                :class="{ active: currentPage === p }"
                                type="button"
                                :aria-current="currentPage === p ? 'page' : undefined"
                                @click="goToPage(p)"
                            >
                                {{ p }}
                            </button>
                            <span class="page-status">Trang {{ currentPage }} / {{ pageCount }}</span>
                            <button
                                class="page-btn"
                                type="button"
                                aria-label="Trang sau"
                                :disabled="currentPage === pageCount"
                                @click="goToPage(currentPage + 1)"
                            ><FontAwesomeIcon :icon="['fas', 'arrow-right']" /></button>
                        </nav>

                        <p class="note">
                            Giá và thông số hiển thị cần được xác nhận theo từng model, tồn kho và yêu
                            cầu thực tế của công trình.
                        </p>
                    </div>
                </section>
            </main>

            <div v-if="compareProducts.length > 0" class="kb-compare-bar" role="region" aria-label="Thanh so sánh sản phẩm">
                <div class="wrap kb-compare-bar-inner">
                    <div class="kb-compare-bar-items">
                        <strong class="kb-compare-bar-title">So sánh ({{ compareProducts.length }}/2):</strong>
                        <div class="kb-compare-chips-scroll">
                            <span v-for="item in compareProducts" :key="item.id" class="kb-compare-chip">
                                <span class="kb-compare-chip-text">{{ item.name }}</span>
                                <button type="button" aria-label="Bỏ sản phẩm" @click="toggleCompare(item)">×</button>
                            </span>
                        </div>
                    </div>
                    <div class="kb-compare-bar-actions">
                        <button type="button" class="btn btn-outline kb-compare-clear-btn" @click="compareIds = []">Xóa hết</button>
                        <button type="button" class="btn btn-primary kb-compare-open-btn" @click="isCompareModalOpen = true">
                            Xem bảng so sánh
                        </button>
                    </div>
                </div>
            </div>

            <Transition name="kb-modal">
                <div
                    v-if="isCompareModalOpen && compareProducts.length > 0"
                    class="modal-wrap open kb-compare-modal-wrap"
                    role="presentation"
                    @click.self="isCompareModalOpen = false"
                >
                    <section class="modal kb-compare-modal" role="dialog" aria-modal="true" aria-label="So sánh thông số sản phẩm">
                        <div class="kb-auth-modal-head kb-compare-modal-head">
                            <div class="kb-compare-modal-title-group">
                                <span class="eyebrow"><span class="dot"></span> PRODUCT COMPARISON</span>
                                <h3>So sánh chi tiết sản phẩm ({{ compareProducts.length }} thiết bị)</h3>
                            </div>
                            <div class="kb-compare-modal-head-actions">
                                <button
                                    v-if="compareProducts.length > 1"
                                    type="button"
                                    class="kb-compare-reset-link"
                                    @click="compareIds = []; isCompareModalOpen = false"
                                >
                                    Xóa tất cả
                                </button>
                                <button class="close-btn" type="button" aria-label="Đóng bảng so sánh" @click="isCompareModalOpen = false">×</button>
                            </div>
                        </div>

                        <div class="kb-compare-modal-body">
                            <!-- Desktop & Tablet Table View -->
                            <div class="kb-compare-table-wrap">
                                <table class="kb-compare-table" :class="`cols-${compareProducts.length}`">
                                    <thead>
                                        <tr>
                                            <th>Tiêu chí kỹ thuật</th>
                                            <th v-for="(item, idx) in compareProducts" :key="item.id">
                                                <div class="kb-compare-col-head">
                                                    <div class="kb-compare-col-top">
                                                        <span class="kb-compare-index-badge">0{{ idx + 1 }}</span>
                                                        <button
                                                            type="button"
                                                            class="kb-compare-remove-btn"
                                                            :aria-label="`Bỏ ${item.name} khỏi bảng so sánh`"
                                                            @click="toggleCompare(item)"
                                                        >
                                                            × Bỏ
                                                        </button>
                                                    </div>
                                                    <div class="kb-compare-col-media">
                                                        <img v-if="imageUrl(item)" :src="imageUrl(item) ?? undefined" :alt="item.name">
                                                        <span v-else class="kb-compare-col-placeholder">{{ item.category?.name ?? 'HVAC' }}</span>
                                                    </div>
                                                    <span class="kb-compare-col-cat">{{ item.category?.name ?? 'Sản phẩm kỹ thuật' }}</span>
                                                    <strong class="kb-compare-col-name">{{ item.name }}</strong>
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Tên thương mại</td>
                                            <td v-for="item in compareProducts" :key="item.id">
                                                <span class="kb-compare-title-text">{{ item.product_title || item.name }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Danh mục</td>
                                            <td v-for="item in compareProducts" :key="item.id">{{ item.category?.name ?? '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td>Dòng sản phẩm</td>
                                            <td v-for="item in compareProducts" :key="item.id">{{ item.subcategory?.name ?? '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td>Giá tham khảo</td>
                                            <td v-for="item in compareProducts" :key="item.id">
                                                <strong class="kb-compare-price">{{ formatPrice(item.price) }}</strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Mô tả &amp; thông số</td>
                                            <td v-for="item in compareProducts" :key="item.id">
                                                <div class="kb-compare-desc-cell">{{ item.description || 'Đang cập nhật thông số kỹ thuật.' }}</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Thao tác</td>
                                            <td v-for="item in compareProducts" :key="item.id">
                                                <div class="kb-compare-cell-actions">
                                                    <button type="button" class="btn btn-primary" @click="addToCart(item)">+ Giỏ báo giá</button>
                                                    <Link :href="`/products/${item.id}`" class="btn btn-outline">Xem chi tiết</Link>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Mobile Responsive View -->
                            <div class="kb-compare-mobile">
                                <div class="kb-compare-mobile-cards">
                                    <article
                                        v-for="(item, idx) in compareProducts"
                                        :key="item.id"
                                        class="kb-compare-mobile-card"
                                    >
                                        <div class="kb-compare-mobile-card-top">
                                            <div class="kb-compare-mobile-thumb">
                                                <img v-if="imageUrl(item)" :src="imageUrl(item) ?? undefined" :alt="item.name">
                                                <span v-else class="kb-compare-col-placeholder">HVAC</span>
                                            </div>
                                            <div class="kb-compare-mobile-meta">
                                                <div class="kb-compare-mobile-badges">
                                                    <span class="kb-compare-index-badge">0{{ idx + 1 }}</span>
                                                    <span class="kb-compare-mobile-cat">{{ item.category?.name ?? 'Thiết bị' }}</span>
                                                </div>
                                                <h4 class="kb-compare-mobile-name">{{ item.name }}</h4>
                                                <strong class="kb-compare-price">{{ formatPrice(item.price) }}</strong>
                                            </div>
                                            <button
                                                type="button"
                                                class="kb-compare-mobile-remove"
                                                :aria-label="`Bỏ ${item.name} khỏi bảng so sánh`"
                                                @click="toggleCompare(item)"
                                            >
                                                ×
                                            </button>
                                        </div>
                                        <div class="kb-compare-mobile-card-actions">
                                            <button type="button" class="btn btn-primary" @click="addToCart(item)">+ Giỏ báo giá</button>
                                            <Link :href="`/products/${item.id}`" class="btn btn-outline">Xem chi tiết</Link>
                                        </div>
                                    </article>
                                </div>

                                <div class="kb-compare-mobile-specs">
                                    <div class="kb-compare-spec-block">
                                        <div class="kb-compare-spec-head">Giá tham khảo</div>
                                        <div class="kb-compare-spec-list">
                                            <div v-for="(item, idx) in compareProducts" :key="`price-${item.id}`" class="kb-compare-spec-row is-inline">
                                                <span class="kb-compare-spec-prod">
                                                    <b>0{{ idx + 1 }}</b> {{ item.name }}
                                                </span>
                                                <strong class="kb-compare-price">{{ formatPrice(item.price) }}</strong>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="kb-compare-spec-block">
                                        <div class="kb-compare-spec-head">Danh mục &amp; Dòng sản phẩm</div>
                                        <div class="kb-compare-spec-list">
                                            <div v-for="(item, idx) in compareProducts" :key="`cat-${item.id}`" class="kb-compare-spec-row is-inline">
                                                <span class="kb-compare-spec-prod">
                                                    <b>0{{ idx + 1 }}</b> {{ item.name }}
                                                </span>
                                                <span class="kb-compare-spec-val">
                                                    {{ item.category?.name ?? '—' }} · {{ item.subcategory?.name ?? '—' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="kb-compare-spec-block">
                                        <div class="kb-compare-spec-head">Tên thương mại</div>
                                        <div class="kb-compare-spec-list">
                                            <div v-for="(item, idx) in compareProducts" :key="`title-${item.id}`" class="kb-compare-spec-row">
                                                <span class="kb-compare-spec-prod">
                                                    <b>0{{ idx + 1 }}</b> {{ item.name }}
                                                </span>
                                                <p class="kb-compare-spec-text">{{ item.product_title || item.name }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="kb-compare-spec-block">
                                        <div class="kb-compare-spec-head">Mô tả &amp; thông số kỹ thuật</div>
                                        <div class="kb-compare-spec-list">
                                            <div v-for="(item, idx) in compareProducts" :key="`desc-${item.id}`" class="kb-compare-spec-row">
                                                <span class="kb-compare-spec-prod">
                                                    <b>0{{ idx + 1 }}</b> {{ item.name }}
                                                </span>
                                                <p class="kb-compare-spec-text is-desc">{{ item.description || 'Đang cập nhật thông số kỹ thuật.' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </Transition>

            <HvacCalculatorModal
                :open="isCalculatorOpen"
                :products="products"
                @close="isCalculatorOpen = false"
                @add-to-cart="(prod) => addToCart(prod as Product)"
            />

            <Transition name="kb-modal">
                <div
                    v-if="selectedProduct"
                    class="modal-wrap open"
                    role="presentation"
                    @click.self="selectedProduct = null"
                >
                    <section class="modal" role="dialog" aria-modal="true" :aria-label="`Thông tin ${selectedProduct.name}`">
                        <button class="close-btn modal-close" type="button" aria-label="Đóng" @click="selectedProduct = null"><FontAwesomeIcon :icon="['fas', 'xmark']" /></button>
                        <div class="quick-modal">
                            <div class="modal-art">
                                <img v-if="selectedImage" :src="selectedImage" :alt="selectedProduct.name">
                                <div v-else class="modal-image-placeholder">{{ selectedProduct.category?.name ?? 'HVAC Mart' }}</div>
                                <div v-if="selectedImages.length > 1" class="product-gallery-controls">
                                    <button type="button" aria-label="Ảnh trước" @click="stepImage(-1)"><FontAwesomeIcon :icon="['fas', 'arrow-left']" /></button>
                                    <span>{{ selectedImageIndex + 1 }} / {{ selectedImages.length }}</span>
                                    <button type="button" aria-label="Ảnh tiếp theo" @click="stepImage(1)"><FontAwesomeIcon :icon="['fas', 'arrow-right']" /></button>
                                </div>
                            </div>
                            <div class="modal-copy">
                                <div class="product-meta">{{ selectedProduct.category?.name }} · {{ selectedProduct.subcategory?.name }}</div>
                                <h2>{{ selectedProduct.product_title || selectedProduct.name }}</h2>
                                <p class="product-description">{{ selectedProduct.description }}</p>
                                <div class="modal-price">{{ formatPrice(selectedProduct.price) }} <span>/ sản phẩm · giá tham khảo</span></div>
                                <div class="kb-modal-btn-row">
                                    <button
                                        class="btn btn-primary"
                                        type="button"
                                        @click="addToCart(selectedProduct); selectedProduct = null; openCartDrawer()"
                                    >Thêm vào giỏ</button>
                                    <Link
                                        class="btn btn-outline"
                                        :href="`/products/${selectedProduct.id}`"
                                        @click="selectedProduct = null"
                                    >Xem chi tiết &amp; Đánh giá</Link>
                                </div>
                                <p class="modal-disclaimer">Thông số và giá bán thực tế cần được xác nhận trước khi đặt hàng.</p>
                            </div>
                        </div>
                    </section>
                </div>
            </Transition>

            <div class="toast" :class="{ show: isToastVisible }" role="status" aria-live="polite">
                <span class="toast-dot"></span>{{ toastMessage }}
            </div>
        </UserLayout>
    </div>
</template>
