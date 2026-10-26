<script setup lang="ts">
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import ChatWidget from '../components/ChatWidget.Vue';

const props = defineProps<{
    cartCount: number;
    activePage?: 'home' | 'categories' | 'products' | 'projects' | 'about' | 'contact';
}>();

const emit = defineEmits<{
    openCart: [];
}>();

const isMenuOpen = ref(false);

const closeMenu = () => {
    isMenuOpen.value = false;
};

const focusProductSearch = () => {
    closeMenu();
    const productSearch = document.querySelector<HTMLInputElement>('#productSearch');

    if (productSearch) {
        productSearch.focus();

        return;
    }

    window.location.href = '/products#products';
};
</script>

<template>
    <div class="announcement">
    Giải pháp vật tư kỹ thuật &amp; công trình · Tư vấn đúng nhu cầu, đồng hành đến hoàn thiện
    </div>
    
    <header class="nav-shell">
        <div class="wrap nav">
            <Link href="/" class="brand" aria-label="KB TECH trang chủ">
                <span class="brandmark">Q</span>
                <span class="brandname">
                    KB TECH
                    <small>ENGINEERING SUPPLY</small>
                </span>
            </Link>

            <nav class="navlinks" :class="{ open: isMenuOpen }" id="navlinks" aria-label="Điều hướng chính">
                <Link href="/" :aria-current="props.activePage === 'home' ? 'page' : undefined" @click="closeMenu">Trang chủ</Link>
                <Link href="/categories" :aria-current="props.activePage === 'categories' ? 'page' : undefined" @click="closeMenu">Danh mục</Link>
                <Link href="/products" :aria-current="props.activePage === 'products' ? 'page' : undefined" @click="closeMenu">Sản phẩm</Link>
                <Link href="/featured-projects" :aria-current="props.activePage === 'projects' ? 'page' : undefined" @click="closeMenu">Dự án tiêu biểu</Link>
                <Link href="/about-us" :aria-current="props.activePage === 'about' ? 'page' : undefined" @click="closeMenu">Về chúng tôi</Link>
                <Link href="/contacts" :aria-current="props.activePage === 'contact' ? 'page' : undefined" @click="closeMenu">Liên hệ</Link>
            </nav>

            <div class="navactions">
                <button class="icon-btn" id="searchFocus" type="button" aria-label="Tìm kiếm" @click="focusProductSearch">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <circle cx="10.8" cy="10.8" r="6.8"></circle>
                        <path d="m16 16 5 5"></path>
                    </svg>
                </button>

                <button class="icon-btn" id="cartOpen" type="button" aria-label="Mở giỏ hàng" @click="emit('openCart')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M3 4h2l2.2 11.1a2 2 0 0 0 2 1.6h8.4a2 2 0 0 0 1.9-1.4L22 8H6"></path>
                        <circle cx="10" cy="20" r="1"></circle>
                        <circle cx="18" cy="20" r="1"></circle>
                    </svg>
                    <span class="cart-count" id="cartCount">{{ props.cartCount }}</span>
                </button>

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
                    aria-label="Mở menu"
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

    <slot />

    <footer class="footer">
        <div class="wrap">
          <div class="footer-grid">
            <div><Link href="/" class="brand"><span class="brandmark">Q</span><span class="brandname">KB TECH<small>ENGINEERING SUPPLY</small></span></Link>
              <p class="footer-desc">Đầu mối cung ứng thiết bị và vật tư kỹ thuật cho các công trình dân dụng, thương mại và công nghiệp.</p>
              <div class="socials"><Link href="/contacts" aria-label="Facebook">f</Link><Link href="/contacts" aria-label="LinkedIn">in</Link><Link href="/contacts" aria-label="Zalo">Z</Link>
              </div>
            </div>
            <div>
              <h4>KHÁM PHÁ</h4>
              <div class="footer-links"><Link href="/about-us">Về chúng tôi</Link><Link href="/categories">Danh mục</Link><Link href="/products">Sản phẩm</Link><Link href="/featured-projects">Dự án</Link>
              </div>
            </div>
            <div>
              <h4>HỖ TRỢ</h4>
              <div class="footer-links"><Link href="/contacts">Yêu cầu báo giá</Link><Link href="/contacts">Tư vấn kỹ thuật</Link><Link href="/contacts">Chính sách giao hàng</Link><Link href="/contacts">Liên hệ hợp tác</Link>
              </div>
            </div>
            <div>
              <h4>THÔNG TIN DOANH NGHIỆP</h4>
              <div class="footer-links"><span>Hà Nội, Việt Nam</span><span>kbnguyen0311@gmail.com</span><span>0979.807.234</span><span>Thứ 2 – Thứ 7 · 08:00–17:30</span>
              </div>
            </div>
          </div>
          <div class="footer-bottom"><span>© 2026 KB TECH. Thiết kế demo — vui lòng cập nhật thông tin pháp lý trước khi sử dụng.</span><span>Chất lượng trong từng chi tiết · Built with intention.</span>
          </div>
        </div>
    </footer>

    <ChatWidget />
</template>
