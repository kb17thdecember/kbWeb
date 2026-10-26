<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import UserLayout from '../../layout/UserLayout.Vue';

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

const activeFilter = ref('all');
const activeSubcategoryId = ref<number | null>(null);
const searchQuery = ref('');
const currentPage = ref(1);
const cart = ref<Record<number, number>>({});
const isCartOpen = ref(false);
const selectedProduct = ref<Product | null>(null);
const selectedImageIndex = ref(0);
const toastMessage = ref('');
const isToastVisible = ref(false);
let toastTimer: ReturnType<typeof setTimeout> | undefined;

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

const normalize = (value: string): string =>
    value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('vi')
        .replace(/[^a-z0-9]/g, '');

const visibleProducts = computed(() => {
    const query = normalize(searchQuery.value.trim());

    return props.products.filter((product) => {
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

        return matchesCategory && matchesSubcategory && matchesSearch;
    });
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
const cartTotal = computed(() =>
    cartItems.value.reduce((total, item) => total + item.product.price * item.quantity, 0),
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
};

const loadCart = (): void => {
    const savedCart = sessionStorage.getItem('kbuser-cart');

    if (!savedCart) {
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
}

const addToCart = (product: Product): void => {
    cart.value[product.id] = (cart.value[product.id] ?? 0) + 1;
    persistCart();
    notify(`Đã thêm ${product.name} vào giỏ hàng.`);
};

const changeQuantity = (productId: number, delta: number): void => {
    const quantity = (cart.value[productId] ?? 0) + delta;

    if (quantity <= 0) {
        delete cart.value[productId];
    } else {
        cart.value[productId] = quantity;
    }

    persistCart();
};

const removeFromCart = (productId: number): void => {
    delete cart.value[productId];
    persistCart();
    notify('Đã xóa sản phẩm khỏi giỏ hàng.');
};

const selectFilter = (filter: string): void => {
    activeFilter.value = filter;
    activeSubcategoryId.value = null;
    currentPage.value = 1;
};

const goToPage = (page: number): void => {
    currentPage.value = Math.min(Math.max(1, page), pageCount.value);
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
        isCartOpen.value = false;
        selectedProduct.value = null;
    }
};

watch([activeFilter, activeSubcategoryId, searchQuery], () => {
    currentPage.value = 1;
});

watch([isCartOpen, selectedProduct], ([cartOpen, product]) => {
    document.body.style.overflow = cartOpen || product ? 'hidden' : '';
});

onMounted(() => {
    loadCart();
    window.addEventListener('keydown', onEscape);

    const params = new URLSearchParams(window.location.search);
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
    window.removeEventListener('keydown', onEscape);
    clearTimeout(toastTimer);
    document.body.style.overflow = '';
});
</script>

<template>
    <Head title="Sản phẩm | NOVA TECH" />

    <UserLayout :cart-count="cartCount" active-page="products" @open-cart="isCartOpen = true">
        <main>
            <section class="page-intro" aria-live="polite">
                <div class="wrap page-intro-inner">
                    <div>
                        <div class="eyebrow"><span class="dot"></span> NOVA TECH / PRODUCTS</div>
                        <h1>Danh mục sản phẩm</h1>
                        <p>Thiết bị và vật tư kỹ thuật phù hợp cho từng hạng mục công trình.</p>
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
                            Tìm kiếm theo tên sản phẩm hoặc lọc theo danh mục, dòng sản phẩm để
                            nhanh chóng tìm được lựa chọn phù hợp.
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
                                <div class="product-meta">{{ product.subcategory?.name ?? 'Danh mục sản phẩm' }}</div>
                                <h3 class="product-name">{{ product.name }}</h3>
                                <p class="product-desc">{{ product.product_title }}</p>
                                <div class="product-bottom">
                                    <span class="price">
                                        {{ formatPrice(product.price) }}
                                        <small v-if="product.price > 0">Giá tham khảo · / sản phẩm</small>
                                    </span>
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
                        </article>
                    </div>
                    <div v-else class="product-grid">
                        <div class="empty-state">
                            {{
                                products.length === 0
                                    ? 'Chưa có sản phẩm nào được cập nhật.'
                                    : 'Không tìm thấy sản phẩm phù hợp. Hãy thử từ khóa hoặc danh mục khác.'
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
                            v-for="page in pageCount"
                            :key="page"
                            class="page-btn"
                            :class="{ active: currentPage === page }"
                            type="button"
                            :aria-current="currentPage === page ? 'page' : undefined"
                            @click="goToPage(page)"
                        >
                            {{ page }}
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

        <div class="overlay" :class="{ open: isCartOpen }" @click="isCartOpen = false"></div>
        <aside class="drawer" :class="{ open: isCartOpen }" :aria-hidden="!isCartOpen">
            <div class="drawer-head">
                <h2>Giỏ hàng <span>({{ cartCount }})</span></h2>
                <button class="close-btn" type="button" aria-label="Đóng giỏ hàng" @click="isCartOpen = false"><FontAwesomeIcon :icon="['fas', 'xmark']" /></button>
            </div>
            <div class="cart-items">
                <div v-if="cartItems.length === 0" class="cart-empty">
                    <strong>Giỏ hàng đang trống</strong>
                    <span>Thêm sản phẩm bạn quan tâm để bắt đầu.</span>
                </div>
                <div v-for="{ product, quantity } in cartItems" :key="product.id" class="cart-row">
                    <div class="cart-thumb">
                        <img v-if="imageUrl(product)" :src="imageUrl(product) ?? undefined" :alt="product.name">
                        <span v-else>{{ product.category?.name ?? 'KB' }}</span>
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
                <Link class="btn btn-primary" href="/contacts" @click="isCartOpen = false">Yêu cầu báo giá <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" /></Link>
                <div class="cart-hint">Giá tham khảo sẽ được xác nhận theo yêu cầu thực tế.</div>
            </div>
        </aside>

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
                        <div v-else class="modal-image-placeholder">{{ selectedProduct.category?.name ?? 'NOVA TECH' }}</div>
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
                        <button
                            class="btn btn-primary"
                            type="button"
                            @click="addToCart(selectedProduct); selectedProduct = null; isCartOpen = true"
                        >Thêm vào giỏ</button>
                        <p class="modal-disclaimer">Thông số và giá bán thực tế cần được xác nhận trước khi đặt hàng.</p>
                    </div>
                </div>
            </section>
        </div>

        <div class="toast" :class="{ show: isToastVisible }" role="status" aria-live="polite">
            <span class="toast-dot"></span>{{ toastMessage }}
        </div>
    </UserLayout>
</template>