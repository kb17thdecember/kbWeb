<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import ChatWidget from '../components/ChatWidget.Vue';
import NavbarActions from '../components/NavbarActions.Vue';
import { lockPageScroll } from '../utils/pageScrollLock';

type AuthUser = {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    is_admin?: boolean;
};

const props = defineProps<{
    cartCount: number;
    activePage?: 'home' | 'categories' | 'products' | 'projects' | 'news' | 'about' | 'contact';
}>();

const page = usePage<{ auth?: { user?: AuthUser | null } }>();
const authUser = computed<AuthUser | null>(() => page.props.auth?.user ?? null);

const isMenuOpen = ref(false);
let releaseMenuScrollLock: (() => void) | null = null;

const closeMenu = (): void => {
    isMenuOpen.value = false;
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
    if (event.key === 'Escape' && isMenuOpen.value) {
        closeMenu();
    }
};

watch(isMenuOpen, (open) => {
    if (open) {
        releaseMenuScrollLock ??= lockPageScroll();
        return;
    }

    releaseMenuScrollLock?.();
    releaseMenuScrollLock = null;
});

onMounted(() => {
    window.addEventListener('keydown', onEscapeKey);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onEscapeKey);
    releaseMenuScrollLock?.();
    releaseMenuScrollLock = null;
});
</script>

<template>
    <div>
        <div class="announcement">
            Giải pháp vật tư &amp; thiết bị · Tư vấn đúng nhu cầu, đồng hành đến hoàn thiện
        </div>

        <header class="nav-shell">
            <div class="wrap nav">
                <Link href="/" class="brand" aria-label="HVAC Mart trang chủ">
                    <span class="brandmark">HM</span>
                    <span class="brandname">
                        HVAC Mart
                        <small>HVAC &amp; ENGINEERING SUPPLY</small>
                    </span>
                </Link>

                <nav class="navlinks" :class="{ open: isMenuOpen }" id="navlinks" aria-label="Điều hướng chính">
                    <Link href="/" :aria-current="props.activePage === 'home' ? 'page' : undefined" @click="closeMenu">Trang chủ</Link>
                    <Link href="/categories" :aria-current="props.activePage === 'categories' ? 'page' : undefined" @click="closeMenu">Danh mục</Link>
                    <Link href="/products" :aria-current="props.activePage === 'products' ? 'page' : undefined" @click="closeMenu">Sản phẩm</Link>
                    <Link href="/featured-projects" :aria-current="props.activePage === 'projects' ? 'page' : undefined" @click="closeMenu">Dự án tiêu biểu</Link>
                    <Link href="/news" :aria-current="props.activePage === 'news' ? 'page' : undefined" @click="closeMenu">Tin tức</Link>
                    <Link href="/about-us" :aria-current="props.activePage === 'about' ? 'page' : undefined" @click="closeMenu">Về chúng tôi</Link>
                    <Link href="/contacts" :aria-current="props.activePage === 'contact' ? 'page' : undefined" @click="closeMenu">Liên hệ</Link>

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
                <div class="footer-grid">
                    <div>
                        <Link href="/" class="brand"><span class="brandmark">HM</span><span class="brandname">HVAC Mart<small>HVAC &amp; ENGINEERING SUPPLY</small></span></Link>
                        <p class="footer-desc">Đầu mối cung ứng thiết bị và vật tư kỹ thuật cho các công trình dân dụng, thương mại và công nghiệp.</p>
                        <div class="socials">
                            <Link href="/contacts" aria-label="Facebook">f</Link>
                            <Link href="/contacts" aria-label="LinkedIn">in</Link>
                            <Link href="/contacts" aria-label="Zalo">Z</Link>
                        </div>
                    </div>
                    <div>
                        <h4>KHÁM PHÁ</h4>
                        <div class="footer-links">
                            <Link href="/about-us">Về chúng tôi</Link>
                            <Link href="/categories">Danh mục</Link>
                            <Link href="/products">Sản phẩm</Link>
                            <Link href="/featured-projects">Dự án</Link>
                            <Link href="/news">Tin tức</Link>
                        </div>
                    </div>
                    <div>
                        <h4>HỖ TRỢ</h4>
                        <div class="footer-links">
                            <Link href="/contacts">Yêu cầu báo giá</Link>
                            <Link href="/contacts">Tư vấn kỹ thuật</Link>
                            <Link href="/contacts">Chính sách giao hàng</Link>
                            <Link href="/contacts">Liên hệ hợp tác</Link>
                        </div>
                    </div>
                    <div>
                        <h4>THÔNG TIN DOANH NGHIỆP</h4>
                        <div class="footer-links">
                            <span>Hà Nội, Việt Nam</span>
                            <span>kbnguyen0311@gmail.com</span>
                            <span>0979.807.234</span>
                            <span>Thứ 2 – Thứ 7 · 08:00–17:30</span>
                        </div>
                    </div>
                </div>
                <div class="footer-bottom">
                    <span>© 2026 HVAC Mart. Bảo lưu mọi quyền.</span>
                    <span>Chất lượng trong từng chi tiết · Built with intention.</span>
                </div>
            </div>
        </footer>

        <ChatWidget />
    </div>
</template>
