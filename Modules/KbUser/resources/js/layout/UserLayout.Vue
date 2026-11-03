<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import ChatWidget from '../components/ChatWidget.Vue';
import HvacCalculatorModal from '../components/HvacCalculatorModal.Vue';
import NavbarActions from '../components/NavbarActions.Vue';
import { lockPageScroll } from '../utils/pageScrollLock';

type AuthUser = {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    is_admin?: boolean;
};

type NavSubcategory = {
    id: number;
    slug?: string;
    name: string;
    parent_id: number;
};

type NavCategory = {
    id: number;
    slug?: string;
    name: string;
    children?: NavSubcategory[];
};

const props = defineProps<{
    cartCount: number;
    activePage?: 'home' | 'categories' | 'products' | 'catalogues' | 'projects' | 'news' | 'about' | 'contact';
}>();

const page = usePage<{
    auth?: { user?: AuthUser | null };
    siteSettings?: Record<string, string>;
    navCategories?: NavCategory[];
    toast?: string | null;
}>();
const authUser = computed<AuthUser | null>(() => page.props.auth?.user ?? null);
const siteSettings = computed<Record<string, string>>(() => page.props.siteSettings ?? {});
const navCategories = computed<NavCategory[]>(() => page.props.navCategories ?? []);

const productCategoryLinks = computed(() => {
    const aristonCat = navCategories.value.find((cat) => cat.name.toLowerCase().includes('ariston'));
    const kflexCat = navCategories.value.find((cat) => cat.name.toLowerCase().includes('flex'));
    const others = navCategories.value.filter(
        (cat) => cat.id !== aristonCat?.id && cat.id !== kflexCat?.id,
    );

    const items = [
        {
            key: 'ariston',
            label: aristonCat ? aristonCat.name : 'Ariston',
            sublabel: 'Bình nước nóng & Bơm nhiệt',
            href: `/products?category=${encodeURIComponent(String(aristonCat?.slug || aristonCat?.id || 'ariston'))}#products`,
        },
        {
            key: 'kflex',
            label: kflexCat ? kflexCat.name : 'K-Flex',
            sublabel: 'Bảo ôn cách nhiệt NBR/PVC',
            href: `/products?category=${encodeURIComponent(String(kflexCat?.slug || kflexCat?.id || 'k-flex'))}#products`,
        },
        ...others.map((cat) => ({
            key: `cat-${cat.id}`,
            label: cat.name,
            sublabel: 'Danh mục sản phẩm',
            href: `/products?category=${encodeURIComponent(String(cat.slug || cat.id))}#products`,
        })),
    ];

    return items;
});

const catalogueCategoryLinks = computed(() => {
    if (navCategories.value.length > 0) {
        return navCategories.value.map((cat) => ({
            key: `cat-${cat.id}`,
            label: cat.name,
            href: `/catalogues?category=${encodeURIComponent(String(cat.slug || cat.id))}`,
        }));
    }

    return [
        {
            key: 'ariston',
            label: 'Ariston',
            href: '/catalogues?category=ariston',
        },
        {
            key: 'kflex',
            label: 'K-Flex',
            href: '/catalogues?category=k-flex',
        },
    ];
});

const projectTypeLinks = [
    {
        value: '1',
        label: 'Chung cư thương mại & NOXH',
        href: '/featured-projects?type=1',
    },
    {
        value: '2',
        label: 'Văn phòng, khách sạn & bệnh viện',
        href: '/featured-projects?type=2',
    },
    {
        value: '3',
        label: 'Khu công nghiệp',
        href: '/featured-projects?type=3',
    },
];

const hotlineHref = computed<string>(() => {
    const raw = siteSettings.value.hotline || '0979.807.234';
    const digits = raw.replace(/[^0-9+]/g, '');

    return `tel:${digits || '0979807234'}`;
});

const emailHref = computed<string>(() => {
    const email = siteSettings.value.contact_email || 'kbnguyen0311@gmail.com';

    return `mailto:${email}`;
});

const isExternalUrl = (url?: string): boolean => Boolean(url && /^https?:\/\//i.test(url));

const isToastVisible = ref(false);
const toastMessage = ref('');
let toastTimeout: ReturnType<typeof setTimeout> | undefined;

const showToast = (message: string): void => {
    if (toastTimeout) {
        clearTimeout(toastTimeout);
    }

    toastMessage.value = message;
    isToastVisible.value = true;
    toastTimeout = setTimeout(() => {
        isToastVisible.value = false;
        toastTimeout = undefined;
    }, 3500);
};

watch(
    () => page.props.toast,
    (message) => {
        if (message) {
            showToast(message);
        }
    },
    { immediate: true },
);

const isMenuOpen = ref(false);
const isGlobalCalculatorOpen = ref(false);
let releaseMenuScrollLock: (() => void) | null = null;

const closeMenu = (): void => {
    isMenuOpen.value = false;
};

const openGlobalCalculator = (): void => {
    closeMenu();
    isGlobalCalculatorOpen.value = true;
};

const addGlobalCalculatorProductToCart = (product: { id: number; name: string }): void => {
    try {
        const raw = sessionStorage.getItem('kbuser-cart');
        const current = raw ? (JSON.parse(raw) as Record<number, number>) : {};
        current[product.id] = (current[product.id] ?? 0) + 1;
        sessionStorage.setItem('kbuser-cart', JSON.stringify(current));
        window.dispatchEvent(new Event('kbuser-cart-updated'));
        showToast(`Đã thêm ${product.name} vào giỏ báo giá.`);
    } catch {
        showToast('Không thể cập nhật giỏ hàng lúc này.');
    }
};

const openAuthFromMenu = (mode: 'login' | 'register'): void => {
    closeMenu();
    window.dispatchEvent(new CustomEvent('kbuser-auth-open', { detail: { mode } }));
};

const logoutFromMenu = (): void => {
    closeMenu();
    router.post('/logout');
};

const onEscapeKey = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        if (isMenuOpen.value) {
            closeMenu();
        }
        if (isGlobalCalculatorOpen.value) {
            isGlobalCalculatorOpen.value = false;
        }
    }
};

watch([isMenuOpen, isGlobalCalculatorOpen], ([menuOpen, calcOpen]) => {
    if (menuOpen || calcOpen) {
        releaseMenuScrollLock ??= lockPageScroll();
        return;
    }

    releaseMenuScrollLock?.();
    releaseMenuScrollLock = null;
});

let removeSuccessListener: (() => void) | undefined;

const onCustomToast = (event: Event): void => {
    const message = (event as CustomEvent<{ message?: string }>).detail?.message;
    if (message) {
        showToast(message);
    }
};

onMounted(() => {
    window.addEventListener('keydown', onEscapeKey);
    window.addEventListener('kbuser-calculator-open', openGlobalCalculator);
    window.addEventListener('kbuser-toast', onCustomToast);
    removeSuccessListener = router.on('success', (event) => {
        const flashedToast = (event.detail.page.props as { toast?: string | null }).toast;

        if (flashedToast) {
            showToast(flashedToast);
        }
    });
});

onUnmounted(() => {
    removeSuccessListener?.();
    window.removeEventListener('keydown', onEscapeKey);
    window.removeEventListener('kbuser-calculator-open', openGlobalCalculator);
    window.removeEventListener('kbuser-toast', onCustomToast);
    if (toastTimeout) {
        clearTimeout(toastTimeout);
    }
    releaseMenuScrollLock?.();
    releaseMenuScrollLock = null;
});
</script>

<template>
    <div>
        <div class="announcement">
            {{ siteSettings.announcement_text || 'Giải pháp vật tư & thiết bị · Tư vấn đúng nhu cầu, đồng hành đến hoàn thiện' }}
        </div>

        <header class="nav-shell">
            <div class="wrap nav">
                <Link href="/" class="brand" aria-label="HVAC Mart trang chủ">
                    <span class="brandmark">HM</span>
                    <span class="brandname">
                        {{ siteSettings.company_name || 'HVAC Mart' }}
                        <small>{{ siteSettings.company_tagline || 'HVAC & ENGINEERING SUPPLY' }}</small>
                    </span>
                </Link>

                <nav class="navlinks" :class="{ open: isMenuOpen }" id="navlinks" aria-label="Điều hướng chính">
                    <Link href="/" class="nav-top-link" :aria-current="props.activePage === 'home' ? 'page' : undefined" @click="closeMenu">Trang chủ</Link>

                    <div class="nav-dropdown">
                        <Link
                            href="/categories"
                            class="nav-top-link"
                            :aria-current="props.activePage === 'categories' ? 'page' : undefined"
                            @click="closeMenu"
                        >
                            <span>Danh mục</span>
                            <span class="nav-caret" aria-hidden="true">▾</span>
                        </Link>
                        <div class="nav-dropdown-menu" role="menu" aria-label="Danh mục cha và con">
                            <template v-if="navCategories.length > 0">
                                <div
                                    v-for="cat in navCategories"
                                    :key="cat.id"
                                    class="nav-dropdown-item"
                                    :class="{ 'has-sub': Boolean(cat.children?.length) }"
                                >
                                    <Link
                                        :href="`/categories/${encodeURIComponent(String(cat.slug || cat.id))}`"
                                        class="nav-dropdown-link"
                                        @click="closeMenu"
                                    >
                                        <span>{{ cat.name }}</span>
                                        <span v-if="cat.children?.length" class="nav-sub-caret" aria-hidden="true">›</span>
                                    </Link>
                                    <div v-if="cat.children?.length" class="nav-dropdown-submenu" role="menu">
                                        <Link
                                            v-for="child in cat.children"
                                            :key="child.id"
                                            :href="`/categories/${encodeURIComponent(String(cat.slug || cat.id))}/${encodeURIComponent(String(child.slug || child.id))}#products`"
                                            class="nav-dropdown-sublink"
                                            @click="closeMenu"
                                        >
                                            <span>{{ child.name }}</span>
                                        </Link>
                                    </div>
                                </div>
                            </template>
                            <Link v-else href="/categories" class="nav-dropdown-link" @click="closeMenu">
                                <span>Tất cả danh mục</span>
                            </Link>
                        </div>
                    </div>

                    <div class="nav-dropdown">
                        <Link
                            href="/products"
                            class="nav-top-link"
                            :aria-current="props.activePage === 'products' ? 'page' : undefined"
                            @click="closeMenu"
                        >
                            <span>Sản phẩm</span>
                            <span class="nav-caret" aria-hidden="true">▾</span>
                        </Link>
                        <div class="nav-dropdown-menu" role="menu" aria-label="Sản phẩm theo danh mục cha">
                            <div
                                v-for="item in productCategoryLinks"
                                :key="item.key"
                                class="nav-dropdown-item"
                            >
                                <Link :href="item.href" class="nav-dropdown-link" @click="closeMenu">
                                    <div class="nav-dropdown-label-stack">
                                        <strong>{{ item.label }}</strong>
                                        <small v-if="item.sublabel">{{ item.sublabel }}</small>
                                    </div>
                                    <span class="nav-sub-caret" aria-hidden="true">→</span>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div class="nav-dropdown">
                        <Link
                            href="/catalogues"
                            class="nav-top-link"
                            :aria-current="props.activePage === 'catalogues' ? 'page' : undefined"
                            @click="closeMenu"
                        >
                            <span>Catalogue</span>
                            <span class="nav-caret" aria-hidden="true">▾</span>
                        </Link>
                        <div class="nav-dropdown-menu" role="menu" aria-label="Catalogue theo danh mục cha">
                            <div
                                v-for="cat in catalogueCategoryLinks"
                                :key="cat.key"
                                class="nav-dropdown-item"
                            >
                                <Link :href="cat.href" class="nav-dropdown-link" @click="closeMenu">
                                    <span>Catalogue {{ cat.label }}</span>
                                    <span class="nav-sub-caret" aria-hidden="true">→</span>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div class="nav-dropdown">
                        <Link
                            href="/featured-projects"
                            class="nav-top-link"
                            :aria-current="props.activePage === 'projects' ? 'page' : undefined"
                            @click="closeMenu"
                        >
                            <span>Dự án tiêu biểu</span>
                            <span class="nav-caret" aria-hidden="true">▾</span>
                        </Link>
                        <div class="nav-dropdown-menu" role="menu" aria-label="Các loại dự án tiêu biểu">
                            <div
                                v-for="projType in projectTypeLinks"
                                :key="projType.value"
                                class="nav-dropdown-item"
                            >
                                <Link :href="projType.href" class="nav-dropdown-link" @click="closeMenu">
                                    <span>{{ projType.label }}</span>
                                    <span class="nav-sub-caret" aria-hidden="true">→</span>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <Link href="/news" class="nav-top-link" :aria-current="props.activePage === 'news' ? 'page' : undefined" @click="closeMenu">Tin tức</Link>
                    <Link href="/about-us" class="nav-top-link" :aria-current="props.activePage === 'about' ? 'page' : undefined" @click="closeMenu">Về chúng tôi</Link>
                    <Link href="/contacts" class="nav-top-link" :aria-current="props.activePage === 'contact' ? 'page' : undefined" @click="closeMenu">Liên hệ</Link>

                    <div class="navlinks-auth-mobile">
                        <template v-if="authUser">
                            <span class="navlinks-user-badge">{{ authUser.name }}</span>
                            <button type="button" class="navlinks-auth-btn" @click="logoutFromMenu">Đăng xuất</button>
                        </template>
                        <template v-else>
                            <button type="button" class="navlinks-auth-btn" @click="openAuthFromMenu('login')">Đăng nhập</button>
                            <button type="button" class="navlinks-auth-btn" @click="openAuthFromMenu('register')">Đăng ký</button>
                        </template>
                    </div>
                </nav>

                <div class="navactions">
                    <NavbarActions :initial-cart-count="props.cartCount" :active-page="props.activePage" />

                    <Link class="btn btn-primary" href="/contacts">
                        Nhận báo giá
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14m-6-6 6 6-6 6"></path>
                        </svg>
                    </Link>

                    <button
                        class="icon-btn menu-toggle"
                        id="menuToggle"
                        type="button"
                        aria-label="Mở danh mục điều hướng"
                        :aria-expanded="isMenuOpen"
                        @click="isMenuOpen = !isMenuOpen"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M4 7h16M4 12h16M4 17h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </header>

        <Teleport to="body">
            <Transition name="kb-modal">
                <div
                    v-if="isMenuOpen"
                    class="modal-wrap open"
                    role="presentation"
                    @click.self="closeMenu"
                >
                    <div class="modal kb-mobile-nav-modal" role="dialog" aria-modal="true" aria-label="Menu điều hướng HVAC Mart">
                        <div class="kb-auth-modal-head">
                            <div>
                                <span class="eyebrow"><span class="dot"></span> HVAC MART NAVIGATION</span>
                                <h3>Danh mục điều hướng</h3>
                            </div>
                            <button class="close-btn" type="button" aria-label="Đóng menu" @click="closeMenu">×</button>
                        </div>

                        <nav class="kb-mobile-nav-list" aria-label="Điều hướng di động">
                            <Link href="/" class="kb-mobile-nav-item" :class="{ active: props.activePage === 'home' }" @click="closeMenu">
                                <span>Trang chủ</span>
                                <b aria-hidden="true">→</b>
                            </Link>
                            <Link href="/categories" class="kb-mobile-nav-item" :class="{ active: props.activePage === 'categories' }" @click="closeMenu">
                                <span>Danh mục</span>
                                <b aria-hidden="true">→</b>
                            </Link>
                            <Link href="/products" class="kb-mobile-nav-item" :class="{ active: props.activePage === 'products' }" @click="closeMenu">
                                <span>Sản phẩm</span>
                                <b aria-hidden="true">→</b>
                            </Link>
                            <Link href="/catalogues" class="kb-mobile-nav-item" :class="{ active: props.activePage === 'catalogues' }" @click="closeMenu">
                                <span>Thư viện Catalogue &amp; Tài liệu</span>
                                <b aria-hidden="true">→</b>
                            </Link>
                            <Link href="/featured-projects" class="kb-mobile-nav-item" :class="{ active: props.activePage === 'projects' }" @click="closeMenu">
                                <span>Dự án tiêu biểu</span>
                                <b aria-hidden="true">→</b>
                            </Link>
                            <Link href="/news" class="kb-mobile-nav-item" :class="{ active: props.activePage === 'news' }" @click="closeMenu">
                                <span>Tin tức</span>
                                <b aria-hidden="true">→</b>
                            </Link>
                            <Link href="/about-us" class="kb-mobile-nav-item" :class="{ active: props.activePage === 'about' }" @click="closeMenu">
                                <span>Về chúng tôi</span>
                                <b aria-hidden="true">→</b>
                            </Link>
                            <Link href="/contacts" class="kb-mobile-nav-item" :class="{ active: props.activePage === 'contact' }" @click="closeMenu">
                                <span>Liên hệ</span>
                                <b aria-hidden="true">→</b>
                            </Link>
                        </nav>

                        <div class="kb-mobile-nav-footer">
                            <button type="button" class="btn btn-outline kb-mobile-quote-btn" @click="openGlobalCalculator">
                                Công cụ tính chọn thiết bị
                            </button>
                            <Link class="btn btn-primary kb-mobile-quote-btn" href="/contacts" @click="closeMenu">
                                Nhận báo giá công trình
                            </Link>
                            <div class="kb-mobile-auth-row">
                                <template v-if="authUser">
                                    <span class="navlinks-user-badge">{{ authUser.name }}</span>
                                    <button type="button" class="navlinks-auth-btn" @click="logoutFromMenu">Đăng xuất</button>
                                </template>
                                <template v-else>
                                    <button type="button" class="navlinks-auth-btn" @click="openAuthFromMenu('login')">Đăng nhập</button>
                                    <button type="button" class="navlinks-auth-btn" @click="openAuthFromMenu('register')">Đăng ký</button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <slot />

        <footer class="footer">
            <div class="wrap">
                <div class="footer-cta-strip">
                    <div class="footer-cta-copy">
                        <span class="footer-eyebrow">
                            <span class="footer-eyebrow-dot"></span>
                            TƯ VẤN &amp; CUNG ỨNG VẬT TƯ DỰ ÁN (M&amp;E)
                        </span>
                        <h3>Cần bóc tách khối lượng BOQ hoặc báo giá vật tư công trình?</h3>
                        <p>Hỗ trợ tra cứu thông số kỹ thuật, hồ sơ CO/CQ và phản hồi báo giá nhanh trong ngày.</p>
                    </div>
                    <div class="footer-cta-actions">
                        <a :href="hotlineHref" class="footer-hotline-pill">
                            <span class="footer-hotline-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z" />
                                </svg>
                            </span>
                            <span class="footer-hotline-meta">
                                <small>HOTLINE DỰ ÁN</small>
                                <strong>{{ siteSettings.hotline || '0979.807.234' }}</strong>
                            </span>
                        </a>
                        <button type="button" class="btn btn-outline footer-calc-btn" @click="openGlobalCalculator">
                            Tính chọn thiết bị
                        </button>
                        <Link href="/contacts" class="btn btn-primary footer-quote-btn">
                            Gửi yêu cầu báo giá
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 12h14m-6-6 6 6-6 6"></path>
                            </svg>
                        </Link>
                    </div>
                </div>

                <div class="footer-grid">
                    <div class="footer-brand-col">
                        <Link href="/" class="brand footer-brand">
                            <span class="brandmark">HM</span>
                            <span class="brandname">
                                {{ siteSettings.company_name || 'HVAC Mart' }}
                                <small>{{ siteSettings.company_tagline || 'HVAC & ENGINEERING SUPPLY' }}</small>
                            </span>
                        </Link>
                        <p class="footer-desc">
                            {{ siteSettings.footer_description || 'Đầu mối cung ứng thiết bị và vật tư kỹ thuật cho các công trình dân dụng, thương mại và công nghiệp.' }}
                        </p>
                        <div class="footer-badges" aria-label="Cam kết dịch vụ">
                            <span class="footer-badge">CO/CQ Chính hãng</span>
                            <span class="footer-badge">Báo giá BOQ 24h</span>
                            <span class="footer-badge">Giao hàng toàn quốc</span>
                        </div>
                        <div class="footer-social-block">
                            <span class="footer-social-label">KẾT NỐI VỚI CHÚNG TÔI</span>
                            <div class="socials">
                                <a
                                    :href="siteSettings.facebook_url || '/contacts'"
                                    :target="isExternalUrl(siteSettings.facebook_url) ? '_blank' : undefined"
                                    :rel="isExternalUrl(siteSettings.facebook_url) ? 'noopener noreferrer' : undefined"
                                    aria-label="Facebook"
                                    title="Facebook"
                                >
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M14 9h3V6h-3c-2.21 0-4 1.79-4 4v2H8v3h2v6h3v-6h2.5l.5-3H13v-2c0-.55.45-1 1-1Z" />
                                    </svg>
                                    <span>Facebook</span>
                                </a>
                                <a
                                    :href="siteSettings.linkedin_url || '/contacts'"
                                    :target="isExternalUrl(siteSettings.linkedin_url) ? '_blank' : undefined"
                                    :rel="isExternalUrl(siteSettings.linkedin_url) ? 'noopener noreferrer' : undefined"
                                    aria-label="LinkedIn"
                                    title="LinkedIn"
                                >
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M6.94 8.5H4V19h2.94V8.5ZM5.47 4a1.72 1.72 0 1 0 0 3.44 1.72 1.72 0 0 0 0-3.44ZM20 12.93c0-2.82-1.5-4.13-3.51-4.13-1.62 0-2.34.89-2.75 1.52V8.5H10.9c.04.85 0 10.5 0 10.5h2.84v-5.86c0-.31.02-.63.12-.85.25-.63.82-1.28 1.78-1.28 1.26 0 1.76.96 1.76 2.37V19H20v-6.07Z" />
                                    </svg>
                                    <span>LinkedIn</span>
                                </a>
                                <a
                                    :href="siteSettings.zalo_url || '/contacts'"
                                    :target="isExternalUrl(siteSettings.zalo_url) ? '_blank' : undefined"
                                    :rel="isExternalUrl(siteSettings.zalo_url) ? 'noopener noreferrer' : undefined"
                                    aria-label="Zalo"
                                    title="Zalo Official"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" />
                                    </svg>
                                    <span>Zalo</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="footer-nav-col">
                        <h4>KHÁM PHÁ HỆ THỐNG</h4>
                        <div class="footer-links">
                            <Link href="/">Trang chủ</Link>
                            <Link href="/about-us">Về chúng tôi &amp; Năng lực</Link>
                            <Link href="/categories">Danh mục hệ thống</Link>
                            <Link href="/products">Sản phẩm &amp; Thiết bị</Link>
                            <Link href="/catalogues">Thư viện Catalogue</Link>
                            <Link href="/featured-projects">Dự án tiêu biểu</Link>
                            <Link href="/news">Tin tức &amp; Kỹ thuật</Link>
                        </div>
                    </div>

                    <div class="footer-nav-col">
                        <h4>DỊCH VỤ &amp; HỖ TRỢ B2B</h4>
                        <div class="footer-links">
                            <button type="button" class="footer-link-btn" @click="openGlobalCalculator">Công cụ tính chọn thiết bị</button>
                            <Link href="/catalogues">Tải Catalogue &amp; Datasheet</Link>
                            <Link href="/contacts">Yêu cầu báo giá dự án</Link>
                            <Link href="/contacts">Tư vấn giải pháp kỹ thuật</Link>
                            <Link href="/about-us">Hồ sơ năng lực &amp; CO/CQ</Link>
                        </div>
                    </div>

                    <div class="footer-contact-col">
                        <h4>THÔNG TIN DOANH NGHIỆP</h4>
                        <div class="footer-contact-list">
                            <div class="footer-contact-item">
                                <span class="footer-contact-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                        <circle cx="12" cy="10" r="3" />
                                    </svg>
                                </span>
                                <div class="footer-contact-body">
                                    <small>ĐỊA CHỈ VĂN PHÒNG / KHO</small>
                                    <span>{{ siteSettings.address || 'Hà Nội, Việt Nam' }}</span>
                                </div>
                            </div>

                            <div class="footer-contact-item">
                                <span class="footer-contact-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                        <polyline points="22,6 12,13 2,6" />
                                    </svg>
                                </span>
                                <div class="footer-contact-body">
                                    <small>EMAIL PHÒNG DỰ ÁN</small>
                                    <a :href="emailHref">{{ siteSettings.contact_email || 'kbnguyen0311@gmail.com' }}</a>
                                </div>
                            </div>

                            <div class="footer-contact-item">
                                <span class="footer-contact-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z" />
                                    </svg>
                                </span>
                                <div class="footer-contact-body">
                                    <small>ĐIỆN THOẠI LIÊN HỆ</small>
                                    <a :href="hotlineHref">{{ siteSettings.hotline || '0979.807.234' }}</a>
                                </div>
                            </div>

                            <div class="footer-contact-item">
                                <span class="footer-contact-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <circle cx="12" cy="12" r="10" />
                                        <polyline points="12 6 12 12 16 14" />
                                    </svg>
                                </span>
                                <div class="footer-contact-body">
                                    <small>THỜI GIAN LÀM VIỆC</small>
                                    <span>{{ siteSettings.working_hours || 'Thứ 2 – Thứ 7 · 08:00–17:30' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="footer-bottom">
                    <div class="footer-bottom-left">
                        <span>{{ siteSettings.copyright_text || '© 2026 HVAC Mart. Bảo lưu mọi quyền.' }}</span>
                        <span class="footer-bottom-sep" aria-hidden="true">·</span>
                        <span>Cung ứng vật tư kỹ thuật HVAC, cấp thoát nước &amp; bảo ôn công trình.</span>
                    </div>
                    <div class="footer-bottom-right">
                        <Link href="/catalogues">Thư viện Catalogue</Link>
                        <Link href="/about-us">Hồ sơ năng lực</Link>
                        <Link href="/products">Danh mục sản phẩm</Link>
                        <Link href="/contacts">Liên hệ báo giá</Link>
                    </div>
                </div>
            </div>
        </footer>

        <HvacCalculatorModal
            :open="isGlobalCalculatorOpen"
            @close="isGlobalCalculatorOpen = false"
            @add-to-cart="addGlobalCalculatorProductToCart"
        />

        <ChatWidget />

        <div class="toast" :class="{ show: isToastVisible }" role="status" aria-live="polite">
            <span class="toast-dot"></span>{{ toastMessage }}
        </div>
    </div>
</template>
