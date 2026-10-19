<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import UserLayout from '../layout/UserLayout.Vue';

type HomeProduct = {
    id: number;
    name: string;
    description: string;
    price: number;
    tag: number | null;
    image: string[] | null;
    category_id: number;
    subcategory_id: number | null;
    category: { id: number; name: string } | null;
    subcategory: { id: number; name: string } | null;
};

type HomeCategory = {
    id: number;
    name: string;
    description: string | null;
    image: string | null;
    children: { id: number; name: string; parent_id: number }[];
};

type HomeProject = {
    id: number;
    name: string;
    description: string;
    location: string;
    category: string;
    subcategory: string;
    type: number;
    image: string;
};

const props = defineProps<{
    categories: HomeCategory[];
    products: HomeProduct[];
    featuredProjects: HomeProject[];
}>();

const activeFilter = ref('all');
const activeSubcategoryId = ref<number | null>(null);
const searchQuery = ref('');
const contactMessage = ref('');
const checkoutMessage = ref('');
const isCartOpen = ref(false);
const isCheckoutOpen = ref(false);
const isProductModalOpen = ref(false);
const isProjectModalOpen = ref(false);
const cart = ref<Record<number, number>>({});
const selectedProduct = ref<HomeProduct | null>(null);
const selectedProject = ref<HomeProject | null>(null);
const selectedImageIndex = ref(0);
const currentPage = ref(1);
const toastMessage = ref('');
const isToastVisible = ref(false);
let toastTimer: ReturnType<typeof setTimeout> | undefined;
let revealObserver: IntersectionObserver | undefined;

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
        const matchesFilter =
            activeFilter.value === 'all' ||
            String(product.category_id) === activeFilter.value;
        const matchesSubcategory =
            activeSubcategoryId.value === null ||
            product.subcategory_id === activeSubcategoryId.value;
        const matchesSearch =
            query === '' ||
            normalize(`${product.name} ${product.description} ${category} ${subcategory}`)
                .includes(query);

        return matchesFilter && matchesSubcategory && matchesSearch;
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
        .filter((item): item is { product: HomeProduct; quantity: number } => item !== null),
);
const cartCount = computed(() =>
    cartItems.value.reduce((count, item) => count + item.quantity, 0),
);
const cartTotal = computed(() =>
    cartItems.value.reduce(
        (total, item) => total + item.product.price * item.quantity,
        0,
    ),
);

const selectedProductImages = computed(() => selectedProduct.value?.image ?? []);
const selectedProductImage = computed(() => {
    if (selectedProductImages.value.length === 0) {
        return null;
    }

    return productImageUrl(
        selectedProductImages.value[
            selectedImageIndex.value % selectedProductImages.value.length
        ],
    );
});

const productImage = (product: HomeProduct): string | null => {
    const image = product.image?.[0];

    if (!image) {
        return null;
    }

    return productImageUrl(image);
};

const productImageUrl = (image: string): string =>
    image.startsWith('data:') || image.startsWith('http') || image.startsWith('/')
        ? image
        : `/storage/${image}`;

const categoryImageUrl = (category: HomeCategory): string | null =>
    category.image ? productImageUrl(category.image) : null;

const categoryPageUrl = (categoryId: number): string =>
    `/categories?category=${encodeURIComponent(String(categoryId))}`;

const formatPrice = (price: number): string =>
    price > 0 ? `${new Intl.NumberFormat('vi-VN').format(price)}₫` : 'Liên hệ';

const projectTypeLabel = (type: number): string =>
    ({
        1: 'Chung cư thương mại & NOXH',
        2: 'Văn phòng, khách sạn & bệnh viện',
        3: 'Khu công nghiệp',
    })[type] ?? 'Dự án tiêu biểu';

const selectFilter = (filter: string): void => {
    activeFilter.value = filter;
    activeSubcategoryId.value = null;
    currentPage.value = 1;
};

const goToPage = (page: number): void => {
    currentPage.value = Math.min(Math.max(1, page), pageCount.value);
    document.querySelector('#products')?.scrollIntoView({ behavior: 'smooth' });
};

const showAllProducts = (): void => {
    searchQuery.value = '';
    selectFilter('all');
};

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

const addToCart = (product: HomeProduct): void => {
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

const openProduct = (product: HomeProduct): void => {
    selectedProduct.value = product;
    selectedImageIndex.value = 0;
    isProductModalOpen.value = true;
};

const closeProduct = (): void => {
    isProductModalOpen.value = false;
    selectedProduct.value = null;
};

const stepProductImage = (delta: number): void => {
    const imageCount = selectedProductImages.value.length;

    if (imageCount > 0) {
        selectedImageIndex.value =
            (selectedImageIndex.value + delta + imageCount) % imageCount;
    }
};

const openCheckout = (): void => {
    if (cartCount.value === 0) {
        notify('Hãy thêm sản phẩm vào giỏ trước nhé.');

        return;
    }

    checkoutMessage.value = '';
    isCartOpen.value = false;
    isCheckoutOpen.value = true;
};

const submitCheckout = (): void => {
    checkoutMessage.value =
        'Đây là yêu cầu báo giá mô phỏng trên giao diện; chưa có đơn hàng hoặc thông tin nào được gửi đi.';
    notify('Đã hoàn tất mô phỏng yêu cầu báo giá.');
};

const submitContactForm = (): void => {
    contactMessage.value =
        'Đây là biểu mẫu giao diện; hiện chưa kết nối hệ thống gửi yêu cầu nên thông tin chưa được gửi đi.';
    notify('Biểu mẫu chưa được kết nối hệ thống gửi yêu cầu.');
};

const onEscape = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        isCartOpen.value = false;
        isCheckoutOpen.value = false;
        closeProduct();
        isProjectModalOpen.value = false;
    }
};

watch([activeFilter, activeSubcategoryId, searchQuery], () => {
    currentPage.value = 1;
});

watch([isCartOpen, isCheckoutOpen, isProductModalOpen, isProjectModalOpen], (states) => {
    document.body.style.overflow = states.some(Boolean) ? 'hidden' : '';
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

        if (
            requestedCategory.children.some(
                (subcategory) => subcategory.id === requestedSubcategoryId,
            )
        ) {
            activeSubcategoryId.value = requestedSubcategoryId;
        }
    }

    if (window.location.hash === '#products') {
        void nextTick(() => {
            document.querySelector('#products')?.scrollIntoView({ behavior: 'smooth' });
        });
    }

    if ('IntersectionObserver' in window) {
        revealObserver = new IntersectionObserver(
            (entries, observer) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.08 },
        );

        document.querySelectorAll('.reveal').forEach((element) => {
            revealObserver?.observe(element);
        });
    } else {
        document.querySelectorAll('.reveal').forEach((element) => {
            element.classList.add('visible');
        });
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', onEscape);
    revealObserver?.disconnect();
    clearTimeout(toastTimer);
    document.body.style.overflow = '';
});
</script>

<template>
    <UserLayout :cart-count="cartCount" active-page="home" @open-cart="isCartOpen = true">
        <Head title="Trang chủ | NOVA TECH" />
        <main>
        <section id="home" class="hero">
          <div class="wrap">
            <div class="hero-grid">
              <div class="hero-copy">
                <div class="eyebrow"><span class="dot"></span> Đối tác vật tư kỹ thuật đáng tin cậy</div>
                <h1>Kiến tạo công trình.<br><span>Nâng tầm</span> chất lượng.</h1>
                <p class="hero-lead">Từ thiết bị nước nóng Ariston đến giải pháp bảo ôn K-FLEX và vật tư MEP chuyên dụng — lựa chọn đúng sản phẩm, tối ưu hiệu quả cho từng công trình.</p>
                <div class="hero-ctas"><a href="#products" class="btn btn-primary">Khám phá sản phẩm <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></a><a href="#projects" class="btn btn-outline">Xem dự án <span>↗</span></a>
                </div>
                <div class="hero-bottom">
                  <div class="mini-stat"><strong>MEP</strong><span>Giải pháp kỹ thuật</span>
                  </div>
                  <div class="mini-stat"><strong>360°</strong><span>Hỗ trợ xuyên suốt</span>
                  </div>
                  <div class="mini-stat"><strong>B2B</strong><span>Đồng hành dự án</span>
                  </div>
                </div>
              </div>
              <div class="hero-art" role="img" aria-label="Hình minh họa thiết bị kỹ thuật">
                <div class="orb"></div>
                <div class="art-tag">Technical supply · Since 2014</div>
                <div class="tank">
                  <div class="tank-cap"></div>
                  <div class="tank-body"></div>
                  <div class="tank-band">
                    <div>ARISTON<small>WATER COMFORT</small>
                    </div>
                  </div>
                  <div class="tank-foot"></div>
                </div>
                <div class="coil">
                  <div class="coil-ring"></div><span class="coil-label">K·FLEX INSULATION</span>
                </div>
                <div class="floating-card"><small>ENGINEERED FOR EVERY PROJECT</small><strong>Built to perform.</strong><span>Chất lượng trong từng chi tiết</span>
                  <div class="tinyline"></div>
                </div>
                <div class="art-caption"><strong>Engineered for life.</strong><span>SMARTER MATERIALS · BETTER BUILDS</span>
                </div>
              </div>
            </div>
          </div>
        </section>
        <section class="wrap trustbar reveal visible">
          <div class="trustitem">
            <div class="trust-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m12 3 8 4v5c0 5-3.5 7.8-8 9-4.5-1.2-8-4-8-9V7z"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg>
            </div>
            <div><strong>Chọn lọc chất lượng</strong><span>Sản phẩm & nguồn gốc rõ ràng</span>
            </div>
          </div>
          <div class="trustitem">
            <div class="trust-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 6h11v11H3zM14 10h4l3 4v3h-7z"/><circle cx="7.5" cy="19" r="2"/><circle cx="18" cy="19" r="2"/></svg>
            </div>
            <div><strong>Cung ứng linh hoạt</strong><span>Hỗ trợ theo tiến độ công trình</span>
            </div>
          </div>
          <div class="trustitem">
            <div class="trust-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3v3m0 12v3M3 12h3m12 0h3M5.6 5.6l2.1 2.1m8.6 8.6 2.1 2.1m0-12.8-2.1 2.1m-8.6 8.6-2.1 2.1"/><circle cx="12" cy="12" r="5"/></svg>
            </div>
            <div><strong>Tư vấn kỹ thuật</strong><span>Đúng thông số, đúng nhu cầu</span>
            </div>
          </div>
          <div class="trustitem">
            <div class="trust-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/><path d="m5 3 2 2m12-2-2 2"/></svg>
            </div>
            <div><strong>Đồng hành dài lâu</strong><span>Phản hồi rõ ràng, tận tâm</span>
            </div>
          </div>
        </section>
        <section id="categories" class="section">
          <div class="wrap">
            <div class="section-head reveal visible">
              <div>
                <div class="eyebrow"><span class="dot"></span> Danh mục sản phẩm</div>
                <h2 class="section-title">Mọi hạng mục.<br><em>Một điểm đến.</em>
                </h2>
              </div>
              <p class="section-desc">Danh mục được tổ chức rõ ràng giúp bạn tìm nhanh thiết bị phù hợp cho nhà ở, thương mại và công trình công nghiệp.</p>
            </div>
            <div v-if="categories.length > 0" class="category-grid">
              <a
                  v-for="(category, index) in categories"
                  :key="category.id"
                  class="category-card reveal visible"
                  :href="categoryPageUrl(category.id)"
              >
                <span class="cat-number">
                  {{ String(index + 1).padStart(2, '0') }} /
                  {{ category.children.length }} DÒNG SẢN PHẨM
                </span>
                <div class="cat-art">
                  <img
                      v-if="categoryImageUrl(category)"
                      :src="categoryImageUrl(category) ?? undefined"
                      :alt="category.name"
                      loading="lazy"
                  >
                  <span v-else class="category-art-placeholder" aria-hidden="true">N</span>
                </div>
                <strong class="cat-title">{{ category.name }}</strong>
                <span class="cat-bottom">
                  <span>Xem dòng sản phẩm</span>
                  <span class="circle-arrow" aria-hidden="true">↗</span>
                </span>
              </a>
            </div>
            <div v-else class="empty-state">
              Chưa có danh mục sản phẩm nào được cập nhật.
            </div>
            <div class="view-all">
              <a class="btn btn-dark" href="/categories">Khám phá tất cả danh mục <span>↗</span></a>
            </div>
          </div>
        </section>
        <section id="products" class="product-section">
          <div class="wrap">
            <div class="section-head reveal visible">
              <div>
                <div class="eyebrow"><span class="dot"></span> Bộ sưu tập nổi bật</div>
                <h2 class="section-title">Sản phẩm được<br><em>chọn nhiều.</em>
                </h2>
              </div>
              <p class="section-desc">Thiết bị và vật tư thiết yếu, lựa chọn linh hoạt theo quy mô, thông số kỹ thuật và ngân sách của dự án.</p>
            </div>
            <div class="product-toolbar">
              <div class="filters" id="filters">
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
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg>
                <input v-model="searchQuery" id="productSearch" type="search" placeholder="Tìm sản phẩm, mã hàng..." aria-label="Tìm sản phẩm">
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
            <div class="product-grid" id="productGrid">
              <article v-for="product in paginatedProducts" :key="product.id" class="product-card reveal visible">
                <div class="product-photo">
                  <span class="pill">{{ product.category?.name ?? 'Sản phẩm kỹ thuật' }}</span>
                  <button class="quick-btn" type="button" :aria-label="`Xem nhanh ${product.name}`" @click="openProduct(product)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                      <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/>
                      <circle cx="12" cy="12" r="3"/>
                    </svg>
                  </button>
                  <img
                    v-if="productImage(product)"
                    class="product-image"
                    :src="productImage(product) ?? undefined"
                    :alt="product.name"
                    loading="lazy"
                  >
                  <svg v-else class="product-placeholder" viewBox="0 0 110 110" fill="none" aria-hidden="true">
                    <rect x="29" y="17" width="52" height="76" rx="12" fill="#d1d9c5" stroke="#879b7e" stroke-width="2"/>
                    <path d="M29 43h52v20H29z" fill="#345342"/>
                    <path d="M43 77h24" stroke="#879b7e" stroke-width="3" stroke-linecap="round"/>
                  </svg>
                </div>
                <div class="product-body">
                  <div class="product-meta">{{ product.subcategory?.name ?? 'Danh mục sản phẩm' }}</div>
                  <h3 class="product-name">{{ product.name }}</h3>
                  <p class="product-desc">{{ product.description }}</p>
                  <div class="product-bottom">
                    <span class="price">
                      {{ formatPrice(product.price) }}
                      <small v-if="product.price > 0">Giá tham khảo · / sản phẩm</small>
                    </span>
                    <button class="add-btn" type="button" :aria-label="`Thêm ${product.name} vào giỏ`" @click="addToCart(product)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 4h2l2.2 11.1a2 2 0 0 0 2 1.6h8.4a2 2 0 0 0 1.9-1.4L22 8H6"/>
                        <circle cx="10" cy="20" r="1"/>
                        <circle cx="18" cy="20" r="1"/>
                      </svg>
                    </button>
                  </div>
                </div>
              </article>
              <div v-if="visibleProducts.length === 0" class="empty-state">
                Không tìm thấy sản phẩm phù hợp. Hãy thử từ khóa hoặc danh mục khác.
              </div>
            </div>
            <nav v-if="pageCount > 1" class="pagination" aria-label="Phân trang sản phẩm">
              <button class="page-btn" type="button" aria-label="Trang trước" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">←</button>
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
              <button class="page-btn" type="button" aria-label="Trang sau" :disabled="currentPage === pageCount" @click="goToPage(currentPage + 1)">→</button>
            </nav>
            <div class="view-all">
              <button class="btn btn-dark" type="button" @click="showAllProducts">
                Xem tất cả sản phẩm <span>↗</span>
              </button>
            </div>
            <p class="note">* Giá hiển thị là giá minh họa cho bản demo, chưa phải báo giá thương mại. Vui lòng liên hệ để xác nhận tồn kho, thông số và giá thực tế.</p>
          </div>
        </section>
        <section id="about" class="section">
          <div class="wrap split-feature">
            <div class="feature-visual reveal visible">
              <div class="feature-lines"></div>
              <div class="feature-big">Less loss.<br>More life.<small>Insulation that works.</small>
              </div>
              <div class="insulation-roll">
                <div class="roll-core"></div>
              </div>
              <div class="feature-label"><strong>K-FLEX · THERMAL INSULATION</strong><span>Giải pháp bảo ôn chuyên dụng</span>
              </div>
            </div>
            <div class="feature-copy reveal visible">
              <div class="eyebrow"><span class="dot"></span> Chọn giải pháp, không chỉ chọn hàng</div>
              <h2 class="section-title">Từ bản vẽ đến<br><em>công trình thực tế.</em>
              </h2>
              <p>Chúng tôi hướng đến việc trở thành đầu mối cung ứng tin cậy cho nhà thầu, đơn vị cơ điện và chủ đầu tư — bằng danh mục rõ ràng, tư vấn phù hợp và quy trình làm việc chuyên nghiệp.</p>
              <ul class="check-list">
                <li><span class="check">✓</span> Hỗ trợ tra cứu thông số và lựa chọn sản phẩm</li>
                <li><span class="check">✓</span> Tổng hợp nhu cầu đa danh mục trong một đầu mối</li>
                <li><span class="check">✓</span> Đồng hành báo giá, tiến độ và hồ sơ vật tư</li>
                <li><span class="check">✓</span> Ưu tiên giải pháp bền vững, tối ưu vận hành</li>
              </ul><a href="#contact" class="btn btn-primary">Trao đổi với chuyên viên <span>↗</span></a>
            </div>
          </div>
        </section>
        <section id="projects" class="section">
          <div class="wrap">
            <div class="project-section">
              <div class="wrap">
                <div class="section-head reveal visible">
                  <div>
                    <div class="eyebrow"><span class="dot"></span> Dấu ấn công trình</div>
                    <h2 class="section-title">Giải pháp cho<br><em>những không gian lớn.</em>
                    </h2>
                  </div>
                  <p class="section-desc">Một số dự án tiêu biểu và hạng mục kỹ thuật được KB TECH cập nhật.</p>
                </div>
                <div class="project-grid">
                  <article
                      v-for="project in featuredProjects"
                      :key="project.id"
                      class="project-card reveal"
                      role="button"
                      tabindex="0"
                      @click="selectedProject = project; isProjectModalOpen = true"
                      @keydown.enter="selectedProject = project; isProjectModalOpen = true"
                      @keydown.space.prevent="selectedProject = project; isProjectModalOpen = true"
                  >
                    <img class="project-image" :src="productImageUrl(project.image)" :alt="project.name" loading="lazy">
                    <div class="project-info">
                      <span class="project-type">{{ projectTypeLabel(project.type) }} · {{ project.category }}</span>
                      <h3>{{ project.name }}</h3>
                      <p>{{ project.description }}</p>
                      <div class="project-foot">
                        <span>{{ project.subcategory }}</span>
                        <span class="circle-arrow">↗</span>
                      </div>
                    </div>
                  </article>
                  <div v-if="featuredProjects.length === 0" class="empty-state">
                    Chưa có dự án tiêu biểu được cập nhật.
                  </div>
                </div>
              </div>
            </div>
            <div class="section-head reveal" style="margin-top:68px">
              <div>
                <div class="eyebrow"><span class="dot"></span> Cách chúng tôi làm việc</div>
                <h2 class="section-title">Rõ ràng từ bước đầu.</h2>
              </div>
              <p class="section-desc">Một quy trình tinh gọn giúp giảm thời gian trao đổi và làm rõ yêu cầu trước khi chốt đơn hàng.</p>
            </div>
            <div class="process">
              <div class="process-step reveal"><span class="process-num">01 — DISCOVER</span>
                <h3>Tiếp nhận nhu cầu</h3>
                <p>Làm rõ hạng mục, số lượng, thông số và tiến độ dự kiến.</p>
              </div>
              <div class="process-step reveal"><span class="process-num">02 — CONSULT</span>
                <h3>Tư vấn giải pháp</h3>
                <p>Đề xuất danh mục phù hợp với điều kiện vận hành thực tế.</p>
              </div>
              <div class="process-step reveal"><span class="process-num">03 — PROPOSE</span>
                <h3>Báo giá minh bạch</h3>
                <p>Thống nhất quy cách, giá và các điều kiện cung ứng.</p>
              </div>
              <div class="process-step reveal"><span class="process-num">04 — DELIVER</span>
                <h3>Phối hợp giao hàng</h3>
                <p>Đồng hành xử lý các yêu cầu trong quá trình triển khai.</p>
              </div>
            </div>
          </div>
        </section>
        <section class="section">
          <div class="wrap">
            <div class="testimonial reveal">
              <div class="quote-mark">“</div>
              <div class="quote-copy">Một đối tác cung ứng tốt không chỉ giao đúng hàng — mà còn giúp đội dự án chọn đúng giải pháp ngay từ đầu.</div>
              <div class="quote-meta">
                <div class="stars">★★★★★</div><strong>Cam kết dịch vụ</strong><span>Định hướng trải nghiệm khách hàng<br>Được trình bày như thông điệp thương hiệu</span>
              </div>
            </div>
          </div>
        </section>
        <section id="contact" class="contact-section">
          <div class="wrap">
            <div class="contact-card reveal">
              <div class="contact-copy">
                <div class="eyebrow"><span class="dot"></span> Bắt đầu một cuộc trao đổi</div>
                <h2>Công trình của bạn.<br><span style="font-family:Georgia,serif;font-weight:400;font-style:italic">Giải pháp của chúng tôi.</span>
                </h2>
                <p>Gửi yêu cầu về mặt hàng, số lượng hoặc bản vẽ. Đội ngũ tư vấn sẽ có cơ sở để hỗ trợ bạn tốt hơn.</p>
                <div class="contact-details">
                  <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 5h16v14H4z"/><path d="m4 7 8 6 8-6"/></svg> sales@novatech.vn <small style="font-weight:500">(thông tin mẫu)</small>
                  </div>
                  <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M7 3h10v18H7zM10 6h4M10 18h4"/></svg> 0900 000 000 <small style="font-weight:500">(thông tin mẫu)</small>
                  </div>
                  <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg> Hà Nội, Việt Nam <small style="font-weight:500">(địa chỉ mẫu)</small>
                  </div>
                </div>
              </div>
              <form class="contact-form" id="contactForm" @submit.prevent="submitContactForm">
                <div class="form-row">
                  <div class="field"><label for="fullName">HỌ VÀ TÊN *</label><input id="fullName" name="name" placeholder="Tên của bạn" required>
                  </div>
                  <div class="field"><label for="phone">SỐ ĐIỆN THOẠI *</label><input id="phone" name="phone" placeholder="09xx xxx xxx" required inputmode="tel">
                  </div>
                </div>
                <div class="field"><label for="email">EMAIL CÔNG VIỆC</label><input id="email" name="email" type="email" placeholder="ban@congty.vn">
                </div>
                <div class="field"><label for="interest">HẠNG MỤC QUAN TÂM</label><select id="interest" name="interest"><option value="">Chọn hạng mục</option><option>Bình nước nóng Ariston</option><option>Bảo ôn K-FLEX</option><option>Vật tư HVAC</option><option>Vật tư MEP tổng hợp</option><option>Cung ứng cho dự án</option></select>
                </div>
                <div class="field"><label for="message">MÔ TẢ NHU CẦU</label><textarea id="message" name="message" placeholder="Sản phẩm, quy cách, số lượng, tiến độ..."></textarea>
                </div><button class="btn" type="submit">Gửi yêu cầu tư vấn <span>↗</span></button>
                <div v-if="contactMessage" class="form-message show" id="formMessage" role="status">{{ contactMessage }}</div>
              </form>
            </div>
          </div>
        </section>
    </main>

    <div v-if="isCartOpen" class="overlay open" aria-label="Đóng giỏ hàng" @click="isCartOpen = false"></div>
    <aside class="drawer" :class="{ open: isCartOpen }" :aria-hidden="!isCartOpen" aria-label="Giỏ hàng">
      <div class="drawer-head">
        <h2>Giỏ hàng <span>({{ cartCount }})</span></h2>
        <button class="close-btn" type="button" aria-label="Đóng giỏ hàng" @click="isCartOpen = false">×</button>
      </div>
      <div class="cart-items">
        <div v-if="cartItems.length === 0" class="cart-empty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3">
            <path d="M3 4h2l2.2 11.1a2 2 0 0 0 2 1.6h8.4a2 2 0 0 0 1.9-1.4L22 8H6"/>
            <circle cx="10" cy="20" r="1"/>
            <circle cx="18" cy="20" r="1"/>
          </svg>
          <strong>Giỏ hàng đang trống</strong>
          <span>Thêm sản phẩm bạn quan tâm để bắt đầu.</span>
          <button class="btn btn-primary" type="button" style="margin-top: 17px" @click="isCartOpen = false; document.querySelector('#products')?.scrollIntoView({ behavior: 'smooth' })">
            Khám phá sản phẩm
          </button>
        </div>
        <div v-for="{ product, quantity } in cartItems" :key="product.id" class="cart-row">
          <div class="cart-thumb">
            <img v-if="productImage(product)" :src="productImage(product) ?? undefined" :alt="product.name">
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
        <button class="btn btn-primary" type="button" @click="openCheckout">Yêu cầu báo giá ↗</button>
        <div class="cart-hint">Giá hiển thị chỉ để tham khảo. Báo giá thực tế sẽ được xác nhận theo yêu cầu.</div>
      </div>
    </aside>

    <div v-if="isProductModalOpen && selectedProduct" class="modal-wrap open" role="presentation" @click.self="closeProduct">
      <section class="modal" role="dialog" aria-modal="true" :aria-label="`Thông tin ${selectedProduct.name}`">
        <button class="close-btn modal-close" type="button" aria-label="Đóng" @click="closeProduct">×</button>
        <div class="quick-modal">
          <div class="modal-art">
            <img v-if="selectedProductImage" :src="selectedProductImage" :alt="selectedProduct.name">
            <div v-else class="modal-image-placeholder">{{ selectedProduct.category?.name ?? 'KB TECH' }}</div>
            <div v-if="selectedProductImages.length > 1" class="product-gallery-controls">
              <button type="button" aria-label="Ảnh trước" @click="stepProductImage(-1)">←</button>
              <span>{{ selectedImageIndex + 1 }} / {{ selectedProductImages.length }}</span>
              <button type="button" aria-label="Ảnh tiếp theo" @click="stepProductImage(1)">→</button>
            </div>
          </div>
          <div class="modal-copy">
            <div class="product-meta">{{ selectedProduct.category?.name }} · {{ selectedProduct.subcategory?.name }}</div>
            <h2>{{ selectedProduct.name }}</h2>
            <p>{{ selectedProduct.description }}</p>
            <div class="modal-price">{{ formatPrice(selectedProduct.price) }} <span>/ sản phẩm · giá tham khảo</span></div>
            <button class="btn btn-primary" type="button" @click="addToCart(selectedProduct); closeProduct(); isCartOpen = true">
              Thêm vào giỏ
            </button>
            <p class="modal-disclaimer">Thông số và giá bán thực tế cần được xác nhận trước khi đặt hàng.</p>
          </div>
        </div>
      </section>
    </div>

    <div v-if="isCheckoutOpen" class="modal-wrap open" role="presentation" @click.self="isCheckoutOpen = false">
      <section class="modal checkout-modal" role="dialog" aria-modal="true" aria-label="Yêu cầu báo giá">
        <button class="close-btn modal-close" type="button" aria-label="Đóng" @click="isCheckoutOpen = false">×</button>
        <form class="checkout-form" @submit.prevent="submitCheckout">
          <h3>Yêu cầu báo giá</h3>
          <div class="checkout-summary">
            <strong>{{ cartCount }} sản phẩm trong giỏ hàng</strong>
            <span>Tổng giá trị tham khảo: {{ formatPrice(cartTotal) }}</span>
          </div>
          <div class="field">
            <label for="checkoutName">HỌ VÀ TÊN *</label>
            <input id="checkoutName" name="name" required autocomplete="name" placeholder="Tên của bạn">
          </div>
          <div class="field">
            <label for="checkoutPhone">SỐ ĐIỆN THOẠI *</label>
            <input id="checkoutPhone" name="phone" required autocomplete="tel" inputmode="tel" placeholder="09xx xxx xxx">
          </div>
          <div class="field">
            <label for="checkoutEmail">EMAIL</label>
            <input id="checkoutEmail" name="email" type="email" autocomplete="email" placeholder="ban@congty.vn">
          </div>
          <button class="btn btn-primary" type="submit">Gửi yêu cầu báo giá</button>
          <p v-if="checkoutMessage" class="form-message show" role="status">{{ checkoutMessage }}</p>
          <p class="modal-disclaimer">Biểu mẫu hiện đang ở chế độ giao diện thử nghiệm; chưa gửi thông tin đến máy chủ.</p>
        </form>
      </section>
    </div>

    <div v-if="isProjectModalOpen && selectedProject" class="modal-wrap open" role="presentation" @click.self="isProjectModalOpen = false">
      <section class="modal project-modal" role="dialog" aria-modal="true" :aria-label="selectedProject.name">
        <button class="close-btn modal-close" type="button" aria-label="Đóng" @click="isProjectModalOpen = false">×</button>
        <img class="project-modal-image" :src="productImageUrl(selectedProject.image)" :alt="selectedProject.name">
        <div class="project-modal-copy">
          <span class="project-type">{{ projectTypeLabel(selectedProject.type) }}</span>
          <h2>{{ selectedProject.name }}</h2>
          <p>{{ selectedProject.description }}</p>
          <dl>
            <div><dt>Địa điểm</dt><dd>{{ selectedProject.location }}</dd></div>
            <div><dt>Danh mục</dt><dd>{{ selectedProject.category }}</dd></div>
            <div><dt>Nhóm</dt><dd>{{ selectedProject.subcategory }}</dd></div>
          </dl>
          <a class="btn btn-primary" href="#contact" @click="isProjectModalOpen = false">Trao đổi về dự án ↗</a>
        </div>
      </section>
    </div>

    <div class="toast" :class="{ show: isToastVisible }" role="status" aria-live="polite">
      <span class="toast-dot"></span>{{ toastMessage }}
    </div>
    </UserLayout>
</template>
