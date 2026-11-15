<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import UserLayout from '../layout/UserLayout.Vue';
import { lockPageScroll } from '../utils/pageScrollLock';
import { buildProductDetailUrl } from '../utils/recentlyViewedProducts';
import heroBannerSlim3 from '../../assets/images/banners/hero-banner-slim3.jpg';
import heroBannerMflex from '../../assets/images/banners/hero-banner-mflex.jpg';
import heroBannerAn3 from '../../assets/images/banners/hero-banner-an3.jpg';

type HomeProduct = {
    id: number;
    slug?: string;
    name: string;
    product_title: string | null;
    origin?: string | null;
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
    slug?: string;
    name: string;
    description: string | null;
    image: string | null;
    children: { id: number; slug?: string; name: string; parent_id: number }[];
};

type RelatedProjectProduct = {
    id: number;
    slug?: string;
    name: string;
    product_title: string | null;
    origin?: string | null;
    price: number;
    image: string[];
    category: { id: number; name: string } | null;
    subcategory: { id: number; name: string } | null;
};

type HomeProject = {
    id: number;
    slug?: string;
    name: string;
    description: string;
    location: string;
    category: string;
    subcategory: string;
    type: number | string;
    image: string;
    image_url: string | null;
    images: string[] | null;
    images_url: string[];
    related_products?: RelatedProjectProduct[];
};

const props = defineProps<{
    categories: HomeCategory[];
    products: HomeProduct[];
    featuredProjects: HomeProject[];
}>();

const activeFilter = ref('all');
const activeSubcategoryId = ref<number | null>(null);
const searchQuery = ref('');
const checkoutMessage = ref('');
const isCheckoutOpen = ref(false);
const isProductModalOpen = ref(false);
const isProjectModalOpen = ref(false);
const cart = ref<Record<number, number>>({});
const selectedProduct = ref<HomeProduct | null>(null);
const selectedProject = ref<HomeProject | null>(null);
const selectedProjectImageIndex = ref(0);
const addedProjectProductId = ref<number | null>(null);
const selectedImageIndex = ref(0);
const currentPage = ref(1);
const activeProjectType = ref<string | null>(null);
const projectPage = ref(1);
const toastMessage = ref('');
const isToastVisible = ref(false);
const contactSuccessMessage = ref('');
let toastTimer: ReturnType<typeof setTimeout> | undefined;
let revealObserver: IntersectionObserver | undefined;
let releasePageScrollLock: (() => void) | null = null;

type HeroBannerSlide = {
    id: string;
    indexLabel: string;
    imageUrl: string;
    altText: string;
    href: string;
};

const page = usePage<{
    siteSettings?: Record<string, string>;
}>();

const siteSettings = computed<Record<string, string>>(() => page.props.siteSettings ?? {});

const defaultBannerImages = [heroBannerSlim3, heroBannerMflex, heroBannerAn3];
const defaultBannerAlts = [
    'Ariston SLIM3 30B VN — Bảo vệ cả gia đình qua mùa đông',
    'Bảo ôn K-Flex M-Flex — Thất thoát nhiệt không còn là nỗi lo',
    'Ariston AN3 30B VN — Thiết kế mới, sang trọng, hiện đại',
];

const appendLuxuryCacheBuster = (url: string): string => {
    if (url.includes('hero-banner-') && !url.includes('?')) {
        return `${url}?v=lux2026`;
    }
    return url;
};

const resolveBannerImageUrl = (rawUrl: string | undefined, fallbackImage: string): string => {
    const trimmed = (rawUrl || '').trim();
    if (!trimmed) {
        return fallbackImage;
    }
    if (
        trimmed.startsWith('data:') ||
        trimmed.startsWith('http://') ||
        trimmed.startsWith('https://') ||
        trimmed.startsWith('/')
    ) {
        return appendLuxuryCacheBuster(trimmed);
    }
    return appendLuxuryCacheBuster(`/storage/${trimmed}`);
};

const heroSlides = computed<HeroBannerSlide[]>(() => {
    const s = siteSettings.value;
    let rawList: string[] | null = null;

    if (s.hero_banners) {
        try {
            const parsed = JSON.parse(s.hero_banners);
            if (Array.isArray(parsed) && parsed.length > 0) {
                rawList = parsed.map((item) => (typeof item === 'string' ? item : ''));
            }
        } catch {
            rawList = null;
        }
    }

    if (!rawList) {
        rawList = [
            s.hero_banner_1_image ?? '',
            s.hero_banner_2_image ?? '',
            s.hero_banner_3_image ?? '',
        ];
    }

    return rawList.map((rawUrl, idx) => {
        const indexLabel = String(idx + 1).padStart(2, '0');
        const fallback = defaultBannerImages[idx % defaultBannerImages.length];
        const defaultAlt = defaultBannerAlts[idx] ?? `Banner trang chủ ${indexLabel}`;

        return {
            id: `banner-${idx + 1}`,
            indexLabel,
            imageUrl: resolveBannerImageUrl(rawUrl, fallback),
            altText: defaultAlt,
            href: '/products',
        };
    });
});

const activeBannerIndex = ref(0);
const isBannerPaused = ref(false);
const bannerProgress = ref(0);
let bannerRafId: number | null = null;
let bannerLastTick = 0;
let touchStartX = 0;
const BANNER_AUTOPLAY_MS = 6800;

const selectBannerSlide = (index: number): void => {
    const total = heroSlides.value.length;
    activeBannerIndex.value = ((index % total) + total) % total;
    bannerProgress.value = 0;
    bannerLastTick = performance.now();
};

const nextBannerSlide = (): void => {
    selectBannerSlide(activeBannerIndex.value + 1);
};

const prevBannerSlide = (): void => {
    selectBannerSlide(activeBannerIndex.value - 1);
};

const stepBannerAutoplay = (now: number): void => {
    if (!bannerLastTick) {
        bannerLastTick = now;
    }
    const delta = now - bannerLastTick;
    bannerLastTick = now;

    if (!isBannerPaused.value) {
        bannerProgress.value += (delta / BANNER_AUTOPLAY_MS) * 100;
        if (bannerProgress.value >= 100) {
            activeBannerIndex.value = (activeBannerIndex.value + 1) % heroSlides.value.length;
            bannerProgress.value = 0;
        }
    }

    bannerRafId = window.requestAnimationFrame(stepBannerAutoplay);
};

const onBannerTouchStart = (event: TouchEvent): void => {
    touchStartX = event.touches[0]?.clientX ?? 0;
};

const onBannerTouchEnd = (event: TouchEvent): void => {
    const endX = event.changedTouches[0]?.clientX ?? 0;
    const diff = endX - touchStartX;
    if (Math.abs(diff) > 44) {
        if (diff < 0) {
            nextBannerSlide();
        } else {
            prevBannerSlide();
        }
    }
};
const attachmentInput = ref<HTMLInputElement | null>(null);
const checkoutForm = useForm({
    name: '',
    phone: '',
    email: '',
});
const contactForm = useForm<{
    name: string;
    phone: string;
    email: string;
    category_id: string;
    project_name: string;
    message: string;
    attachment: File | null;
}>({
    name: '',
    phone: '',
    email: '',
    category_id: '',
    project_name: '',
    message: '',
    attachment: null,
});

const onAttachmentChange = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    contactForm.attachment = input.files?.[0] ?? null;
};

const clearAttachment = (): void => {
    contactForm.attachment = null;
    if (attachmentInput.value) {
        attachmentInput.value.value = '';
    }
};

const formatAttachmentSize = (bytes: number): string => {
    if (bytes >= 1048576) {
        return `${(bytes / 1048576).toFixed(2)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
};

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

    return props.products
        .filter((product) => Number(product.tag ?? 1) === 1)
        .slice(0, 8)
        .filter((product) => {
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
                normalize(`${product.product_title ?? ''} ${product.origin ?? ''} ${product.name} ${product.description} ${category} ${subcategory}`)
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

const categoryPageUrl = (category: Pick<HomeCategory, 'id' | 'slug'>): string =>
    `/categories/${encodeURIComponent(String(category.slug || category.id))}`;

const formatPrice = (price: number): string =>
    price > 0 ? `${new Intl.NumberFormat('vi-VN').format(price)}₫` : 'Liên hệ';

const projectTypeLabel = (type: number | string): string =>
    ({
        '1': 'Chung cư thương mại & NOXH',
        '2': 'Văn phòng, khách sạn & bệnh viện',
        '3': 'Khu công nghiệp',
    })[String(type)] ?? 'Dự án tiêu biểu';

const projectDetailUrl = (project: HomeProject): string =>
    `/featured-projects/${project.slug || project.id}`;

const projectSummary = (description: string): string => {
    const characters = Array.from(description);

    return characters.length > 245 ? `${characters.slice(0, 242).join('')}...` : description;
};

const projectGroups = computed(() => [
    {
        value: '1',
        title: 'Chung cư thương mại & NOXH',
        description: 'Giải pháp cấp nước, nước nóng và vật tư kỹ thuật cho các khu chung cư.',
    },
    {
        value: '2',
        title: 'Văn phòng, khách sạn & bệnh viện',
        description: 'Vật tư cơ điện, HVAC và thiết bị cho các công trình dịch vụ.',
    },
    {
        value: '3',
        title: 'Khu công nghiệp',
        description: 'Vật tư bảo ôn, đường ống và thiết bị cho nhà máy, khu công nghiệp.',
    },
].map((group) => ({
    ...group,
    projects: props.featuredProjects.filter(
        (project) => String(project.type) === group.value,
    ),
})));

const visibleFeaturedProjects = computed(() =>
    props.featuredProjects.filter(
        (project) => String(project.type) === activeProjectType.value,
    ),
);
const projectPageCount = computed(() =>
    Math.max(1, Math.ceil(visibleFeaturedProjects.value.length / 2)),
);
const paginatedFeaturedProjects = computed(() =>
    visibleFeaturedProjects.value.slice((projectPage.value - 1) * 2, projectPage.value * 2),
);
const projectImageUrl = (project: HomeProject): string => {
    if (project.image_url) {
        return project.image_url;
    }

    return project.image.startsWith('http') || project.image.startsWith('/')
        ? project.image
        : `/storage/${project.image}`;
};
const selectedProjectImages = computed(() => {
    if (!selectedProject.value) {
        return [];
    }

    return selectedProject.value.images_url.length
        ? selectedProject.value.images_url
        : [projectImageUrl(selectedProject.value)];
});

const selectProjectGroup = (type: string): void => {
    activeProjectType.value = type;
    projectPage.value = 1;
};

const goToProjectPage = (page: number): void => {
    projectPage.value = Math.min(Math.max(page, 1), projectPageCount.value);
};

const openProject = (project: HomeProject): void => {
    selectedProject.value = project;
    selectedProjectImageIndex.value = 0;
    isProjectModalOpen.value = true;
};

const selectProjectImage = (index: number): void => {
    selectedProjectImageIndex.value = index;
};

const stepProjectImage = (delta: number): void => {
    const imageCount = selectedProjectImages.value.length;
    if (imageCount) {
        selectedProjectImageIndex.value =
            (selectedProjectImageIndex.value + delta + imageCount) % imageCount;
    }
};

const closeProject = (): void => {
    isProjectModalOpen.value = false;
    selectedProject.value = null;
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
    window.dispatchEvent(new Event('kbuser-cart-updated'));
};

const openCartDrawer = (): void => {
    window.dispatchEvent(new Event('kbuser-cart-open'));
};

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
}

const addToCart = (product: HomeProduct): void => {
    cart.value[product.id] = (cart.value[product.id] ?? 0) + 1;
    persistCart();
    notify(`Đã thêm ${product.name} vào giỏ hàng.`);
};

const projectProductThumbUrl = (product: RelatedProjectProduct): string | null => {
    const first = product.image?.[0];

    if (!first) {
        return null;
    }

    return productImageUrl(first);
};

const projectProductDetailUrl = (product: RelatedProjectProduct): string =>
    buildProductDetailUrl(product);

const goToProjectProductDetail = (product: RelatedProjectProduct): void => {
    releasePageScrollLock?.();
    releasePageScrollLock = null;
    closeProject();
    router.visit(projectProductDetailUrl(product));
};

const addProjectProductToCart = (product: RelatedProjectProduct): void => {
    if (!product.id) {
        return;
    }

    cart.value[product.id] = (cart.value[product.id] ?? 0) + 1;
    persistCart();
    addedProjectProductId.value = product.id;
    notify(`Đã thêm ${product.name} vào giỏ báo giá.`);
    setTimeout(() => {
        if (addedProjectProductId.value === product.id) {
            addedProjectProductId.value = null;
        }
    }, 2000);
};

const openProduct = (product: HomeProduct): void => {
    selectedProduct.value = product;
    selectedImageIndex.value = 0;
    isProductModalOpen.value = true;
};

const openCalculatorFromHome = (): void => {
    window.dispatchEvent(new CustomEvent('kbuser-calculator-open'));
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
    isCheckoutOpen.value = true;
};

const submitCheckout = (): void => {
    checkoutMessage.value = '';
    checkoutForm
        .transform((data) => ({
            ...data,
            from_checkout: true,
            items: cartItems.value.map((item) => ({
                id: item.product.id,
                quantity: item.quantity,
            })),
        }))
        .post('/contacts?from=home', {
            preserveScroll: true,
            onSuccess: () => {
                checkoutForm.reset();
                cart.value = {};
                persistCart();
                isCheckoutOpen.value = false;
                notify('Yêu cầu báo giá đã được gửi. Chúng tôi sẽ liên hệ với bạn sớm.');
            },
        });
};

const submitContactForm = (): void => {
    contactSuccessMessage.value = '';
    contactForm.post('/contacts?from=home', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            contactForm.reset();
            clearAttachment();
            contactSuccessMessage.value = 'Yêu cầu báo giá đã được gửi. Chúng tôi sẽ liên hệ với bạn sớm.';
            notify(contactSuccessMessage.value);
        },
    });
};

const onEscape = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        isCheckoutOpen.value = false;
        closeProduct();
        closeProject();
    }
};

watch([activeFilter, activeSubcategoryId, searchQuery], () => {
    currentPage.value = 1;
});

watch([isProductModalOpen, isCheckoutOpen, isProjectModalOpen], (states) => {
    if (states.some(Boolean)) {
        releasePageScrollLock ??= lockPageScroll();
        return;
    }

    releasePageScrollLock?.();
    releasePageScrollLock = null;
});

onMounted(() => {
    loadCart();
    window.addEventListener('kbuser-cart-updated', loadCart);
    window.addEventListener('kbuser-checkout', openCheckout);
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

    bannerLastTick = performance.now();
    bannerRafId = window.requestAnimationFrame(stepBannerAutoplay);
});

onUnmounted(() => {
    window.removeEventListener('kbuser-cart-updated', loadCart);
    window.removeEventListener('kbuser-checkout', openCheckout);
    window.removeEventListener('keydown', onEscape);
    revealObserver?.disconnect();
    clearTimeout(toastTimer);
    if (bannerRafId !== null) {
        window.cancelAnimationFrame(bannerRafId);
        bannerRafId = null;
    }
    releasePageScrollLock?.();
    releasePageScrollLock = null;
});
</script>

<template>
    <UserLayout :cart-count="cartCount" active-page="home">
        <Head>
        <title>HVAC Mart - Bình Nước Nóng Ariston và Bảo Ôn K-Flex</title>
        <meta
            name="description"
            content="HVAC Mart cung cấp giải pháp bình nước nóng Ariston và bảo ôn K-Flex cho dự án xây dựng trên toàn quốc. Hỗ trợ kỹ thuật, tư vấn kỹ thuật và báo giá cho công trình."
        />
    </Head>
        <main>
        <section id="home" class="hero">
          <div class="wrap">
            <div
              class="hero-grid hero-supersports-banner"
              @mouseenter="isBannerPaused = true"
              @mouseleave="isBannerPaused = false"
              @touchstart.passive="onBannerTouchStart"
              @touchend.passive="onBannerTouchEnd"
            >
              <!-- Supersports-Style Horizontal Image Slider Track -->
              <div class="ss-banner-viewport">
                <div
                  class="ss-banner-track"
                  :style="{
                    transform: `translate3d(-${activeBannerIndex * 100}%, 0, 0)`,
                  }"
                >
                  <article
                    v-for="(slide, sIdx) in heroSlides"
                    :key="slide.id"
                    class="ss-banner-slide"
                    :class="{ 'is-active': sIdx === activeBannerIndex }"
                    :aria-hidden="sIdx !== activeBannerIndex"
                  >
                    <Link
                      :href="slide.href"
                      class="ss-banner-media-link"
                      :aria-label="slide.altText"
                    >
                      <img
                        :src="slide.imageUrl"
                        :alt="slide.altText"
                        class="ss-banner-img"
                        :loading="sIdx === 0 ? 'eager' : 'lazy'"
                      />
                    </Link>
                  </article>
                </div>

                <!-- Supersports-Style Floating Left / Right Circular Navigation Arrows -->
                <button
                  type="button"
                  class="ss-banner-nav-btn is-prev"
                  aria-label="Banner trước"
                  @click="prevBannerSlide"
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m15 18-6-6 6-6"/></svg>
                </button>
                <button
                  type="button"
                  class="ss-banner-nav-btn is-next"
                  aria-label="Banner tiếp theo"
                  @click="nextBannerSlide"
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m9 18 6-6-6-6"/></svg>
                </button>

                <!-- Circular Dot Indicators -->
                <div class="ss-banner-dots" role="tablist" aria-label="Chọn slide banner">
                  <button
                    v-for="(slide, idx) in heroSlides"
                    :key="`dot-${slide.id}`"
                    type="button"
                    role="tab"
                    class="ss-banner-dot"
                    :class="{ 'is-active': idx === activeBannerIndex }"
                    :aria-label="slide.altText"
                    :aria-selected="idx === activeBannerIndex"
                    @click="selectBannerSlide(idx)"
                  ></button>
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
            <div v-if="activeProjectType === null" class="section-head reveal visible">
              <div>
                <div class="eyebrow"><span class="dot"></span> Danh mục sản phẩm</div>
                <h2 class="section-title">Mọi hạng mục.<br><em>Một điểm đến.</em>
                </h2>
              </div>
              <p class="section-desc">Danh mục được tổ chức rõ ràng giúp bạn tìm nhanh thiết bị phù hợp cho nhà ở, thương mại và công trình công nghiệp.</p>
            </div>
            <div v-if="categories.length > 0" class="category-grid">
              <Link
                  v-for="(category, index) in categories"
                  :key="category.id"
                  class="category-card reveal visible"
                  :href="categoryPageUrl(category)"
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
                  <span class="circle-arrow" aria-hidden="true"><FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" /></span>
                </span>
              </Link>
            </div>
            <div v-else class="empty-state">
              Chưa có danh mục sản phẩm nào được cập nhật.
            </div>
            <div v-if="activeProjectType === null" class="view-all">
              <Link class="btn btn-dark" href="/categories">Khám phá tất cả danh mục <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" /></Link>
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
                  <div class="product-meta">
                    {{ product.subcategory?.name ?? 'Danh mục sản phẩm' }}
                    <template v-if="product.origin"> · Xuất xứ: {{ product.origin }}</template>
                  </div>
                  <h3 class="product-name">
                    <Link :href="buildProductDetailUrl(product)">{{ product.name }}</Link>
                  </h3>
                  <p class="product-desc">{{ product.product_title }}</p>
                  <div class="product-bottom">
                    <span class="price">
                      {{ formatPrice(product.price) }}
                      <small v-if="product.price > 0">Giá tham khảo · / sản phẩm</small>
                    </span>
                    <div class="kb-card-bottom-btns">
                      <Link :href="buildProductDetailUrl(product)" class="kb-card-detail-link">Chi tiết</Link>
                      <button class="add-btn" type="button" :aria-label="`Thêm ${product.name} vào giỏ`" @click="addToCart(product)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M3 4h2l2.2 11.1a2 2 0 0 0 2 1.6h8.4a2 2 0 0 0 1.9-1.4L22 8H6"/>
                          <circle cx="10" cy="20" r="1"/>
                          <circle cx="18" cy="20" r="1"/>
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>
              </article>
              <div v-if="visibleProducts.length === 0" class="empty-state">
                Không tìm thấy sản phẩm phù hợp. Hãy thử từ khóa hoặc danh mục khác.
              </div>
            </div>
            <nav v-if="pageCount > 1" class="pagination" aria-label="Phân trang sản phẩm">
              <button class="page-btn" type="button" aria-label="Trang trước" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)"><FontAwesomeIcon :icon="['fas', 'arrow-left']" /></button>
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
              <button class="page-btn" type="button" aria-label="Trang sau" :disabled="currentPage === pageCount" @click="goToPage(currentPage + 1)"><FontAwesomeIcon :icon="['fas', 'arrow-right']" /></button>
            </nav>
            <div class="view-all">
              <Link class="btn btn-dark" href="/products">Khám phá tất cả sản phẩm <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" /></Link>
            </div>
            <p class="note">* Giá hiển thị là giá minh họa cho bản demo, chưa phải báo giá thương mại. Vui lòng liên hệ để xác nhận tồn kho, thông số và giá thực tế.</p>
          </div>
        </section>
        <section id="about" class="section">
          <div class="wrap split-feature">
            <div class="feature-visual reveal visible">
              <div class="feature-lines" aria-hidden="true"></div>
              <div class="feature-sunlight" aria-hidden="true"></div>

              <div class="feature-visual-top">
                <span class="feature-visual-pill">
                  <span class="feature-visual-dot" aria-hidden="true"></span>
                  HVAC MART · SIGNATURE
                </span>
                <div class="feature-big">
                  Less loss. <em>More life.</em>
                </div>
              </div>

              <div class="feature-iconic-stage">
                <div class="feature-orbit-ring is-outer" aria-hidden="true"></div>
                <div class="feature-orbit-ring is-inner" aria-hidden="true"></div>

                <div class="feature-iconic-duo">
                  <div class="feature-iconic-item is-ariston">
                    <div class="feature-iconic-pedestal">
                      <img
                        src="../../assets/images/ariston_water_heater.svg"
                        alt="Ariston"
                        loading="lazy"
                      >
                    </div>
                    <span>ARISTON</span>
                  </div>

                  <div class="feature-iconic-emblem" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                      <path d="M12 3c2.8 3.2 4.2 5.8 4.2 8.2a4.2 4.2 0 1 1-8.4 0C7.8 8.8 9.2 6.2 12 3Z" />
                      <path d="M4 18c2.2-1.4 4.8-1.4 7 0s4.8 1.4 7 0" />
                    </svg>
                  </div>

                  <div class="feature-iconic-item is-kflex">
                    <div class="feature-iconic-pedestal">
                      <img
                        src="../../assets/images/foam.png"
                        alt="K-FLEX"
                        loading="lazy"
                      >
                    </div>
                    <span>K-FLEX</span>
                  </div>
                </div>
              </div>

              <div class="feature-label">
                <span class="feature-label-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="m12 3 8 4v5c0 5-3.5 7.8-8 9-4.5-1.2-8-4-8-9V7z" />
                    <path d="m8.5 12 2.2 2.2 4.8-5" />
                  </svg>
                </span>
                <div>
                  <strong>GIẢI PHÁP NHIỆT &amp; BẢO ÔN</strong>
                  <span>Biểu tượng chất lượng cho mọi công trình</span>
                </div>
              </div>
            </div>
            <div class="feature-copy reveal visible">
              <div class="eyebrow"><span class="dot"></span> Chọn giải pháp, không chỉ chọn hàng</div>
              <h2 class="section-title">Từ bản vẽ đến<br><em>công trình thực tế.</em>
              </h2>
              <p>Chúng tôi hướng đến việc trở thành đầu mối cung ứng tin cậy cho nhà thầu, đơn vị cơ điện và chủ đầu tư — bằng danh mục rõ ràng, tư vấn phù hợp và quy trình làm việc chuyên nghiệp.</p>
              <ul class="check-list">
                <li><span class="check"><FontAwesomeIcon :icon="['fas', 'check']" /></span> Hỗ trợ tra cứu thông số và lựa chọn sản phẩm</li>
                <li><span class="check"><FontAwesomeIcon :icon="['fas', 'check']" /></span> Tổng hợp nhu cầu đa danh mục trong một đầu mối</li>
                <li><span class="check"><FontAwesomeIcon :icon="['fas', 'check']" /></span> Đồng hành báo giá, tiến độ và hồ sơ vật tư</li>
                <li><span class="check"><FontAwesomeIcon :icon="['fas', 'check']" /></span> Ưu tiên giải pháp bền vững, tối ưu vận hành</li>
              </ul><Link href="/contacts" class="btn btn-primary">Trao đổi với chuyên viên <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" /></Link>
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
                  <p class="section-desc">Một số dự án tiêu biểu và hạng mục kỹ thuật được HVAC Mart cập nhật.</p>
                </div>
                <div v-if="activeProjectType === null" class="project-directory-grid">
                  <button
                    v-for="(group, index) in projectGroups"
                    :key="group.value"
                    class="project-group-card"
                    type="button"
                    @click="selectProjectGroup(group.value)"
                  >
                    <div class="project-group-art">
                      <img
                        v-if="group.projects[0]"
                        class="project-group-image"
                        :src="projectImageUrl(group.projects[0])"
                        :alt="group.projects[0].name"
                        loading="lazy"
                      >
                      <span class="project-image-tag">DỰ ÁN TIÊU BIỂU</span>
                    </div>
                    <div class="project-group-copy">
                      <span class="project-detail-type">NHÓM DỰ ÁN · 0{{ index + 1 }}</span>
                      <h3>{{ group.title }}</h3>
                      <p>{{ group.description }}</p>
                      <div class="project-group-footer">
                        <span>{{ group.projects.length }} dự án</span>
                        <span class="circle-arrow" aria-hidden="true"><FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" /></span>
                      </div>
                    </div>
                  </button>
                </div>
                <div v-else class="project-directory-details">
                  <div class="project-detail-back">
                    <button class="text-back" type="button" @click="activeProjectType = null">
                      <FontAwesomeIcon :icon="['fas', 'arrow-left']" /> Tất cả nhóm dự án
                    </button>
                    <span>
                      {{ projectGroups.find((group) => group.value === activeProjectType)?.title }}
                      · {{ visibleFeaturedProjects.length }} hồ sơ dự án
                    </span>
                  </div>
                  <div v-if="paginatedFeaturedProjects.length" class="project-detail-grid">
                    <article
                      v-for="project in paginatedFeaturedProjects"
                      :key="project.id"
                      class="project-detail-card"
                    >
                      <Link :href="projectDetailUrl(project)" class="project-detail-art">
                        <img
                          class="project-detail-image"
                          :src="projectImageUrl(project)"
                          :alt="project.name"
                          loading="lazy"
                        >
                        <span class="project-image-tag">{{ projectTypeLabel(project.type) }}</span>
                      </Link>
                      <div class="project-detail-copy">
                        <span class="project-detail-type">CASE STUDY · {{ projectTypeLabel(project.type) }}</span>
                        <h3>
                          <Link :href="projectDetailUrl(project)">{{ project.name }}</Link>
                        </h3>
                        <p>{{ projectSummary(project.description) }}</p>
                        <div class="project-detail-meta">
                          <div><span>Vị trí</span><strong>{{ project.location }}</strong></div>
                          <div><span>Hạng mục tham chiếu</span><strong>{{ project.category }} · {{ project.subcategory }}</strong></div>
                        </div>
                        <div v-if="project.related_products?.length" class="project-linked-products-inline">
                          <span class="project-linked-label">Vật tư thực tế sử dụng:</span>
                          <div class="project-linked-chips">
                            <Link
                              v-for="prod in project.related_products"
                              :key="prod.id || prod.name"
                              :href="projectProductDetailUrl(prod)"
                              class="project-linked-chip"
                            >
                              {{ prod.name }}
                            </Link>
                          </div>
                        </div>
                        <div class="project-detail-note">
                          Thông tin dự án được cập nhật theo dữ liệu hiện có. Vui lòng liên hệ để xác nhận phạm vi cung ứng.
                        </div>
                        <div class="project-card-actions">
                          <Link
                            class="project-view-detail"
                            :href="projectDetailUrl(project)"
                          >
                            <span>Xem chi tiết dự án</span>
                            <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" />
                          </Link>
                          <button
                            class="project-quick-view-btn"
                            type="button"
                            @click="openProject(project)"
                          >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                              <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" />
                              <circle cx="12" cy="12" r="3" />
                            </svg>
                            <span>Xem nhanh</span>
                          </button>
                        </div>
                      </div>
                    </article>
                  </div>
                  <div v-else class="empty-state">
                    Chưa có dự án thuộc nhóm công trình này.
                  </div>
                  <nav v-if="projectPageCount > 1" class="pagination" aria-label="Phân trang dự án">
                    <button
                      class="page-btn"
                      type="button"
                      aria-label="Trang trước"
                      :disabled="projectPage === 1"
                      @click="goToProjectPage(projectPage - 1)"
                    ><FontAwesomeIcon :icon="['fas', 'arrow-left']" /></button>
                    <button
                      v-for="page in projectPageCount"
                      :key="page"
                      class="page-btn"
                      :class="{ active: projectPage === page }"
                      type="button"
                      :aria-current="projectPage === page ? 'page' : undefined"
                      @click="goToProjectPage(page)"
                    >{{ page }}</button>
                    <span class="page-status">Trang {{ projectPage }} / {{ projectPageCount }}</span>
                    <button
                      class="page-btn"
                      type="button"
                      aria-label="Trang sau"
                      :disabled="projectPage === projectPageCount"
                      @click="goToProjectPage(projectPage + 1)"
                    ><FontAwesomeIcon :icon="['fas', 'arrow-right']" /></button>
                  </nav>
                </div>
                <div class="view-all">
                  <Link class="btn btn-primary" href="/featured-projects">Xem tất cả dự án <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" /></Link>
                </div>
              </div>
            </div>
            <div v-if="activeProjectType === null" class="section-head reveal" style="margin-top:68px">
              <div>
                <div class="eyebrow"><span class="dot"></span> Cách chúng tôi làm việc</div>
                <h2 class="section-title">Rõ ràng từ bước đầu.</h2>
              </div>
              <p class="section-desc">Một quy trình tinh gọn giúp giảm thời gian trao đổi và làm rõ yêu cầu trước khi chốt đơn hàng.</p>
            </div>
            <div v-if="activeProjectType === null" class="process">
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
            <div class="testimonial reveal" style="background: light-dark(#e7e1d4, #24241f);">
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
                <h2>Công trình của bạn.<br><span style="font-weight:400;font-style:italic;color:var(--gold)">Giải pháp của chúng tôi.</span>
                </h2>
                <p>Gửi yêu cầu về mặt hàng, số lượng hoặc bản vẽ. Đội ngũ tư vấn sẽ có cơ sở để hỗ trợ bạn tốt hơn.</p>
                <div class="contact-details">
                  <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 5h16v14H4z"/><path d="m4 7 8 6 8-6"/></svg> kbnguyen0311@gmail.com
                  </div>
                  <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M7 3h10v18H7zM10 6h4M10 18h4"/></svg> 0979.807.234
                  </div>
                  <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg> Hà Nội, Việt Nam
                  </div>
                </div>
              </div>
              <form class="contact-form" id="contactForm" novalidate @submit.prevent="submitContactForm">
                <div class="form-row">
                  <div class="field">
                    <label for="fullName">HỌ VÀ TÊN *</label>
                    <input
                      id="fullName"
                      v-model="contactForm.name"
                      name="name"
                      maxlength="255"
                      autocomplete="name"
                      placeholder="Tên của bạn"
                      required
                      :aria-invalid="Boolean(contactForm.errors.name)"
                    >
                    <span v-if="contactForm.errors.name" class="field-error">{{ contactForm.errors.name }}</span>
                  </div>
                  <div class="field">
                    <label for="phone">SỐ ĐIỆN THOẠI *</label>
                    <input
                      id="phone"
                      v-model="contactForm.phone"
                      name="phone"
                      maxlength="30"
                      pattern="[0-9+\s().\-]{8,30}"
                      title="Số điện thoại gồm 8-30 ký tự: số, dấu +, khoảng trắng, ngoặc, chấm hoặc gạch ngang"
                      type="tel"
                      autocomplete="tel"
                      placeholder="09xx xxx xxx"
                      required
                      inputmode="tel"
                      :aria-invalid="Boolean(contactForm.errors.phone)"
                    >
                    <span v-if="contactForm.errors.phone" class="field-error">{{ contactForm.errors.phone }}</span>
                  </div>
                </div>
                <div class="field">
                  <label for="email">EMAIL CÔNG VIỆC *</label>
                  <input
                    id="email"
                    v-model="contactForm.email"
                    name="email"
                    maxlength="255"
                    type="email"
                    autocomplete="email"
                    placeholder="ban@congty.vn"
                    required
                    :aria-invalid="Boolean(contactForm.errors.email)"
                  >
                  <span v-if="contactForm.errors.email" class="field-error">{{ contactForm.errors.email }}</span>
                </div>
                <div class="field">
                  <label for="interest">HẠNG MỤC QUAN TÂM *</label>
                  <select
                    id="interest"
                    v-model="contactForm.category_id"
                    name="category_id"
                    required
                    :aria-invalid="Boolean(contactForm.errors.category_id)"
                  >
                    <option value="">Chọn hạng mục</option>
                    <option value="Bình nước nóng Ariston">Bình nước nóng Ariston</option>
                    <option value="Bảo ôn K-FLEX">Bảo ôn K-FLEX</option>
                    <option value="Vật tư HVAC">Vật tư HVAC</option>
                    <option value="Vật tư MEP tổng hợp">Vật tư MEP tổng hợp</option>
                    <option value="Cung ứng cho dự án">Cung ứng cho dự án</option>
                  </select>
                  <span v-if="contactForm.errors.category_id" class="field-error">{{ contactForm.errors.category_id }}</span>
                </div>
                <div class="field">
                  <label for="projectName">DỰ ÁN CẦN BÁO GIÁ *</label>
                  <input
                    id="projectName"
                    v-model="contactForm.project_name"
                    name="project_name"
                    placeholder="Tên dự án hoặc công trình"
                    maxlength="255"
                    required
                    :aria-invalid="Boolean(contactForm.errors.project_name)"
                  >
                  <span v-if="contactForm.errors.project_name" class="field-error">{{ contactForm.errors.project_name }}</span>
                </div>
                <div class="field">
                  <label for="message">MÔ TẢ NHU CẦU</label>
                  <textarea
                    id="message"
                    v-model="contactForm.message"
                    name="message"
                    placeholder="Sản phẩm, quy cách, số lượng, tiến độ..."
                    maxlength="5000"
                    :aria-invalid="Boolean(contactForm.errors.message)"
                  ></textarea>
                  <span v-if="contactForm.errors.message" class="field-error">{{ contactForm.errors.message }}</span>
                </div>
                <div class="field">
                  <label for="homeBoqAttachment">HỒ SƠ ĐÍNH KÈM (BẢNG KHỐI LƯỢNG BOQ / BẢN VẼ KỸ THUẬT)</label>
                  <div class="kb-luxury-upload">
                    <input
                      id="homeBoqAttachment"
                      ref="attachmentInput"
                      type="file"
                      class="kb-file-input-hidden"
                      accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.webp"
                      @change="onAttachmentChange"
                    >
                    <label v-if="!contactForm.attachment" for="homeBoqAttachment" class="kb-upload-dropzone">
                      <span class="kb-upload-icon-box" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                          <path d="M12 16V4m0 0-4 4m4-4 4 4" />
                          <path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
                        </svg>
                      </span>
                      <span class="kb-upload-copy">
                        <strong>Tải lên bảng khối lượng (BOQ) hoặc bản vẽ</strong>
                        <small>Định dạng PDF, Excel, Word, Ảnh · Dung lượng tối đa 10MB</small>
                      </span>
                      <span class="kb-upload-cta">Chọn tệp</span>
                    </label>

                    <div v-else class="kb-upload-file-card">
                      <span class="kb-upload-file-badge" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                          <path d="M14 2v6h6" />
                        </svg>
                      </span>
                      <div class="kb-upload-file-meta">
                        <strong>{{ contactForm.attachment.name }}</strong>
                        <small>{{ formatAttachmentSize(contactForm.attachment.size) }} · Sẵn sàng đính kèm báo giá</small>
                      </div>
                      <div class="kb-upload-file-actions">
                        <label for="homeBoqAttachment" class="kb-upload-change-btn">Đổi tệp</label>
                        <button
                          type="button"
                          class="kb-upload-remove-btn"
                          aria-label="Xóa tệp đính kèm"
                          @click="clearAttachment"
                        >
                          ×
                        </button>
                      </div>
                    </div>
                  </div>
                  <span v-if="contactForm.errors.attachment" class="field-error">{{ contactForm.errors.attachment }}</span>
                </div>
                <button class="btn" type="submit" :disabled="contactForm.processing">
                  {{ contactForm.processing ? 'Đang gửi...' : 'Gửi yêu cầu tư vấn' }} <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" />
                </button>
                <div v-if="contactSuccessMessage" class="form-message show" id="formMessage" role="status" aria-live="polite">
                  {{ contactSuccessMessage }}
                </div>
                <p class="contact-form-note">
                  Các trường có dấu * là bắt buộc. Thông tin dự án giúp chúng tôi chuẩn bị báo giá phù hợp.
                </p>
              </form>
            </div>
          </div>
        </section>
    </main>

    <Transition name="kb-modal">
    <div v-if="isProductModalOpen && selectedProduct" class="modal-wrap open" role="presentation" @click.self="closeProduct">
      <section class="modal" role="dialog" aria-modal="true" :aria-label="`Thông tin ${selectedProduct.name}`">
        <button class="close-btn modal-close" type="button" aria-label="Đóng" @click="closeProduct"><FontAwesomeIcon :icon="['fas', 'xmark']" /></button>
        <div class="quick-modal">
          <div class="modal-art">
            <img v-if="selectedProductImage" :src="selectedProductImage" :alt="selectedProduct.name">
            <div v-else class="modal-image-placeholder">{{ selectedProduct.category?.name ?? 'HVAC Mart' }}</div>
            <div v-if="selectedProductImages.length > 1" class="product-gallery-controls">
              <button type="button" aria-label="Ảnh trước" @click="stepProductImage(-1)"><FontAwesomeIcon :icon="['fas', 'arrow-left']" /></button>
              <span>{{ selectedImageIndex + 1 }} / {{ selectedProductImages.length }}</span>
              <button type="button" aria-label="Ảnh tiếp theo" @click="stepProductImage(1)"><FontAwesomeIcon :icon="['fas', 'arrow-right']" /></button>
            </div>
          </div>
          <div class="modal-copy">
            <div class="product-meta">
              {{ selectedProduct.category?.name }} · {{ selectedProduct.subcategory?.name }}
              <template v-if="selectedProduct.origin"> · Xuất xứ: {{ selectedProduct.origin }}</template>
            </div>
            <h2>{{ selectedProduct.product_title || selectedProduct.name }}</h2>
            <p class="product-description">{{ selectedProduct.description }}</p>
            <div class="modal-price">{{ formatPrice(selectedProduct.price) }} <span>/ sản phẩm · giá tham khảo</span></div>
            <div class="kb-modal-btn-row">
              <button class="btn btn-primary" type="button" @click="addToCart(selectedProduct); closeProduct(); openCartDrawer()">
                Thêm vào giỏ
              </button>
              <Link class="btn btn-outline" :href="buildProductDetailUrl(selectedProduct)" @click="closeProduct()">
                Xem chi tiết &amp; Đánh giá
              </Link>
            </div>
            <p class="modal-disclaimer">Thông số và giá bán thực tế cần được xác nhận trước khi đặt hàng.</p>
          </div>
        </div>
      </section>
    </div>
    </Transition>

    <Transition name="kb-modal">
    <div v-if="isCheckoutOpen" class="modal-wrap open" role="presentation" @click.self="isCheckoutOpen = false">
      <section class="modal checkout-modal" role="dialog" aria-modal="true" aria-label="Yêu cầu báo giá">
        <button class="close-btn modal-close" type="button" aria-label="Đóng" @click="isCheckoutOpen = false"><FontAwesomeIcon :icon="['fas', 'xmark']" /></button>
        <form class="checkout-form" @submit.prevent="submitCheckout">
          <h3>Yêu cầu báo giá</h3>
          <div class="checkout-summary">
            <strong>{{ cartCount }} sản phẩm trong giỏ hàng</strong>
            <span>Tổng giá trị tham khảo: {{ formatPrice(cartTotal) }}</span>
          </div>
          <div class="field">
            <label for="checkoutName">HỌ VÀ TÊN *</label>
            <input id="checkoutName" v-model="checkoutForm.name" name="name" maxlength="255" required autocomplete="name" placeholder="Tên của bạn">
            <span v-if="checkoutForm.errors.name" class="field-error">{{ checkoutForm.errors.name }}</span>
          </div>
          <div class="field">
            <label for="checkoutPhone">SỐ ĐIỆN THOẠI *</label>
            <input id="checkoutPhone" v-model="checkoutForm.phone" name="phone" maxlength="30" pattern="[0-9+\s().\-]{8,30}" title="Số điện thoại gồm 8-30 ký tự: số, dấu +, khoảng trắng, ngoặc, chấm hoặc gạch ngang" required autocomplete="tel" inputmode="tel" placeholder="09xx xxx xxx">
            <span v-if="checkoutForm.errors.phone" class="field-error">{{ checkoutForm.errors.phone }}</span>
          </div>
          <div class="field">
            <label for="checkoutEmail">EMAIL *</label>
            <input id="checkoutEmail" v-model="checkoutForm.email" name="email" type="email" maxlength="255" required autocomplete="email" placeholder="ban@congty.vn">
            <span v-if="checkoutForm.errors.email" class="field-error">{{ checkoutForm.errors.email }}</span>
            <span v-if="(checkoutForm.errors as Record<string, string>).items" class="field-error">{{ (checkoutForm.errors as Record<string, string>).items }}</span>
          </div>
          <button class="btn btn-primary" type="submit" :disabled="checkoutForm.processing">
            {{ checkoutForm.processing ? 'Đang gửi...' : 'Gửi yêu cầu báo giá' }}
          </button>
          <p v-if="checkoutMessage" class="form-message show" role="status">{{ checkoutMessage }}</p>
        </form>
      </section>
    </div>
    </Transition>

    <Transition name="kb-modal">
    <div
      v-if="isProjectModalOpen && selectedProject"
      class="modal-wrap open"
      role="presentation"
      @click.self="closeProject"
    >
      <section class="modal project-modal-detail" role="dialog" aria-modal="true" :aria-label="`Chi tiết dự án ${selectedProject.name}`">
        <button class="close-btn modal-close" type="button" aria-label="Đóng" @click="closeProject"><FontAwesomeIcon :icon="['fas', 'xmark']" /></button>
        <div class="project-modal-layout">
          <div class="project-modal-art project-modal-gallery">
            <div class="project-gallery-shell">
              <div class="project-gallery-stage">
                <img
                  class="gallery-photo"
                  :src="selectedProjectImages[selectedProjectImageIndex]"
                  :alt="`${selectedProject.name} · ảnh ${selectedProjectImageIndex + 1}`"
                >
              </div>
              <div v-if="selectedProjectImages.length > 1" class="gallery-control-row project-gallery-control">
                <button class="gallery-step" type="button" aria-label="Ảnh trước" @click="stepProjectImage(-1)"><FontAwesomeIcon :icon="['fas', 'arrow-left']" /></button>
                <span>{{ String(selectedProjectImageIndex + 1).padStart(2, '0') }} / {{ String(selectedProjectImages.length).padStart(2, '0') }}</span>
                <button class="gallery-step" type="button" aria-label="Ảnh tiếp theo" @click="stepProjectImage(1)"><FontAwesomeIcon :icon="['fas', 'arrow-right']" /></button>
              </div>
              <div v-if="selectedProjectImages.length > 1" class="gallery-thumbs project-gallery-thumbs">
                <button
                  v-for="(image, index) in selectedProjectImages"
                  :key="image"
                  class="gallery-thumb"
                  :class="{ active: selectedProjectImageIndex === index }"
                  type="button"
                  :aria-label="`Hiển thị ảnh ${index + 1}`"
                  :aria-pressed="selectedProjectImageIndex === index"
                  @click="selectProjectImage(index)"
                >
                  <img class="gallery-photo" :src="image" :alt="`${selectedProject.name} · ảnh ${index + 1}`">
                  <span>Ảnh {{ index + 1 }}</span>
                </button>
              </div>
            </div>
            <span class="project-image-tag">HÌNH ẢNH DỰ ÁN</span>
          </div>
          <div class="project-modal-copy">
            <div class="project-detail-type">CHI TIẾT DỰ ÁN</div>
            <h2>{{ selectedProject.name }}</h2>
            <p>{{ selectedProject.description }}</p>
            <div class="project-modal-meta">
              <div><span>Nhóm dự án</span><strong>{{ projectTypeLabel(selectedProject.type) }}</strong></div>
              <div><span>Vị trí</span><strong>{{ selectedProject.location }}</strong></div>
              <div><span>Danh mục</span><strong>{{ selectedProject.category }}</strong></div>
              <div><span>Hạng mục</span><strong>{{ selectedProject.subcategory }}</strong></div>
            </div>

            <div v-if="selectedProject.related_products?.length" class="project-modal-products">
              <div class="project-modal-products-head">
                <div class="project-modal-products-title">
                  <span class="project-modal-products-dot" aria-hidden="true"></span>
                  <span>VẬT TƯ &amp; SẢN PHẨM THỰC TẾ TRONG DỰ ÁN</span>
                  <strong class="project-modal-products-count">{{ selectedProject.related_products.length }}</strong>
                </div>
                <Link href="/products" class="project-modal-products-more" @click="closeProject">
                  Xem toàn bộ danh mục →
                </Link>
              </div>
              <div class="project-modal-products-list">
                <article
                  v-for="prod in selectedProject.related_products"
                  :key="prod.id || prod.name"
                  class="project-modal-product-item"
                  role="link"
                  tabindex="0"
                  @click="goToProjectProductDetail(prod)"
                  @keydown.enter.prevent="goToProjectProductDetail(prod)"
                >
                  <Link
                    :href="projectProductDetailUrl(prod)"
                    class="project-modal-product-thumb-wrap"
                    @click.stop
                  >
                    <img
                      v-if="projectProductThumbUrl(prod)"
                      :src="projectProductThumbUrl(prod) ?? undefined"
                      :alt="prod.name"
                      class="project-modal-product-thumb"
                    >
                    <span v-else class="project-modal-product-thumb-fallback">
                      {{ prod.category?.name ?? 'HVAC' }}
                    </span>
                  </Link>
                  <div class="project-modal-product-info">
                    <span class="project-modal-product-meta">
                      {{ prod.category?.name }}<template v-if="prod.subcategory?.name"> · {{ prod.subcategory.name }}</template>
                    </span>
                    <Link
                      :href="projectProductDetailUrl(prod)"
                      class="project-modal-product-name"
                      @click.stop
                    >
                      {{ prod.name }}
                    </Link>
                    <small class="project-modal-product-sub">
                      {{ prod.product_title || formatPrice(prod.price) }}
                    </small>
                  </div>
                  <button
                    v-if="prod.id"
                    type="button"
                    class="project-modal-product-btn"
                    :class="{ 'is-added': addedProjectProductId === prod.id }"
                    @click.stop="addProjectProductToCart(prod)"
                    @keydown.enter.stop
                  >
                    {{ addedProjectProductId === prod.id ? '✓ Đã thêm' : '+ Giỏ báo giá' }}
                  </button>
                  <Link
                    v-else
                    href="/contacts"
                    class="project-modal-product-btn"
                    @click.stop
                  >
                    Nhận báo giá
                  </Link>
                </article>
              </div>
            </div>

            <p class="project-detail-note">
              Thông tin địa điểm và hạng mục được cung cấp theo dữ liệu dự án. Vui lòng liên hệ để xác nhận chi tiết.
            </p>
            <div class="project-modal-footer-actions">
              <Link class="btn btn-primary" :href="projectDetailUrl(selectedProject)" @click="closeProject">
                Xem trang chi tiết dự án <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" />
              </Link>
              <Link class="btn btn-outline" href="/contacts" @click="closeProject">
                Trao đổi về hạng mục này
              </Link>
            </div>
          </div>
        </div>
      </section>
    </div>
    </Transition>

    <div class="toast" :class="{ show: isToastVisible }" role="status" aria-live="polite">
      <span class="toast-dot"></span>{{ toastMessage }}
    </div>
    </UserLayout>
</template>

<style scoped>
.hero {
    padding: 28px 0 56px;
}

.hero-grid.hero-supersports-banner {
    display: block;
    width: 100%;
    min-height: 0 !important;
    aspect-ratio: 16 / 7.5;
    padding: 0;
    border: 1px solid rgba(223, 205, 169, 0.34);
    border-radius: 24px;
    background: #0f0d0a;
    box-shadow:
        0 38px 88px -20px rgba(15, 12, 8, 0.46),
        0 16px 36px -12px rgba(15, 12, 8, 0.24),
        0 0 0 1px rgba(183, 154, 99, 0.14);
    position: relative;
    overflow: hidden;
    isolation: isolate;
}

.hero-grid.hero-supersports-banner::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    pointer-events: none;
    z-index: 4;
    box-shadow:
        inset 0 1px 0 rgba(255, 245, 220, 0.22),
        inset 0 -1px 0 rgba(223, 205, 169, 0.12);
}

/* 100% Pure Image Slider Viewport & Track */
.ss-banner-viewport {
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
    border-radius: inherit;
}

.ss-banner-track {
    display: flex;
    width: 100%;
    height: 100%;
    will-change: transform;
    transition: transform 0.88s cubic-bezier(0.22, 1, 0.36, 1);
}

.ss-banner-slide {
    position: relative;
    flex: 0 0 100%;
    width: 100%;
    height: 100%;
    overflow: hidden;
}

.ss-banner-media-link {
    display: block;
    width: 100%;
    height: 100%;
}

.ss-banner-img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center center;
    transform: scale(1.016);
    transition: transform 6.8s cubic-bezier(0.22, 1, 0.36, 1);
}

.ss-banner-slide.is-active .ss-banner-img {
    transform: scale(1);
}

/* Smoked-Glass & Brushed Champagne-Gold Navigation Buttons */
.ss-banner-nav-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 6;
    width: 46px;
    height: 46px;
    border-radius: 50%;
    border: 1px solid rgba(223, 205, 169, 0.42);
    background: rgba(16, 13, 10, 0.66);
    backdrop-filter: blur(14px);
    color: #f3e4c6;
    display: grid;
    place-items: center;
    cursor: pointer;
    box-shadow:
        0 14px 32px -6px rgba(0, 0, 0, 0.55),
        inset 0 1px 0 rgba(255, 246, 224, 0.16);
    transition:
        transform 0.32s cubic-bezier(0.22, 1, 0.36, 1),
        background 0.28s ease,
        border-color 0.28s ease,
        color 0.28s ease,
        opacity 0.32s ease,
        box-shadow 0.28s ease;
}

.ss-banner-nav-btn.is-prev {
    left: 20px;
}

.ss-banner-nav-btn.is-next {
    right: 20px;
}

.ss-banner-nav-btn svg {
    width: 19px;
    height: 19px;
}

@media (hover: hover) and (pointer: fine) {
    .ss-banner-nav-btn {
        opacity: 0;
        transform: translateY(-50%) scale(0.92);
    }

    .hero-supersports-banner:hover .ss-banner-nav-btn,
    .ss-banner-nav-btn:focus-visible {
        opacity: 1;
        transform: translateY(-50%) scale(1);
    }

    .ss-banner-nav-btn:hover {
        transform: translateY(-50%) scale(1.08);
        background: linear-gradient(135deg, #ecd39d 0%, #c4974b 55%, #9f732b 100%);
        border-color: #f5e2b8;
        color: #15110a;
        box-shadow: 0 16px 34px rgba(196, 151, 75, 0.42);
    }
}

/* Luxury Circular Dot Indicators */
.ss-banner-dots {
    position: absolute;
    bottom: 16px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 6;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 7px 14px;
    border-radius: 999px;
    border: 1px solid rgba(223, 205, 169, 0.28);
    background: rgba(14, 12, 9, 0.62);
    backdrop-filter: blur(12px);
    box-shadow: 0 10px 26px rgba(0, 0, 0, 0.42);
}

.ss-banner-dot {
    width: 9px;
    height: 9px;
    padding: 0;
    border-radius: 50%;
    border: 1.5px solid rgba(235, 222, 198, 0.68);
    background: rgba(235, 222, 198, 0.25);
    cursor: pointer;
    transition:
        transform 0.28s cubic-bezier(0.22, 1, 0.36, 1),
        background 0.25s ease,
        border-color 0.25s ease,
        box-shadow 0.25s ease;
}

@media (hover: hover) and (pointer: fine) {
    .ss-banner-dot:hover {
        background: rgba(248, 234, 204, 0.78);
        border-color: #f6e6c2;
        transform: scale(1.16);
    }
}

.ss-banner-dot.is-active {
    background: linear-gradient(135deg, #f3deb0 0%, #c99a49 100%);
    border-color: #fff6df;
    transform: scale(1.24);
    box-shadow: 0 0 10px rgba(216, 174, 96, 0.75);
}

/* Responsive Breakpoints */
@media (max-width: 760px) {
    .hero {
        padding: 16px 0 36px;
    }

    .hero-grid.hero-supersports-banner {
        border-radius: 16px;
    }

    .ss-banner-nav-btn {
        width: 32px;
        height: 32px;
    }

    .ss-banner-nav-btn svg {
        width: 15px;
        height: 15px;
    }

    .ss-banner-nav-btn.is-prev {
        left: 8px;
    }

    .ss-banner-nav-btn.is-next {
        right: 8px;
    }

    .ss-banner-dots {
        bottom: 10px;
        gap: 8px;
        padding: 5px 10px;
    }

    .ss-banner-dot {
        width: 7px;
        height: 7px;
    }
}
</style>
