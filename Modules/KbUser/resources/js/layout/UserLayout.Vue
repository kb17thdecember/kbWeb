<script setup lang="ts">
import { ref } from 'vue';

const isMenuOpen = ref(false);

const closeMenu = () => {
    isMenuOpen.value = false;
};
</script>

<template>
    <div class="announcement">
    Giải pháp vật tư kỹ thuật &amp; công trình · Tư vấn đúng nhu cầu, đồng hành đến hoàn thiện
    </div>
    
    <header class="nav-shell">
        <div class="wrap nav">
            <a href="#home" class="brand" aria-label="NOVA TECH trang chủ">
                <span class="brandmark">N</span>
                <span class="brandname">
                    NOVA TECH
                    <small>ENGINEERING SUPPLY</small>
                </span>
            </a>

            <nav class="navlinks" id="navlinks" aria-label="Điều hướng chính">
                <a href="?page=home" data-page="home">Trang chủ</a>
                <a href="?page=categories" data-page="categories" aria-current="page">Danh mục</a>
                <a href="?page=products" data-page="products">Sản phẩm</a>
                <a href="?page=projects" data-page="projects">Dự án tiêu biểu</a>
                <a href="?page=about" data-page="about">Về chúng tôi</a>
                <a href="?page=contact" data-page="contact">Liên hệ</a>
            </nav>

            <div class="navactions">
                <button class="icon-btn" id="searchFocus" aria-label="Tìm kiếm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <circle cx="10.8" cy="10.8" r="6.8"></circle>
                        <path d="m16 16 5 5"></path>
                    </svg>
                </button>

                <button class="icon-btn" id="cartOpen" aria-label="Mở giỏ hàng">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M3 4h2l2.2 11.1a2 2 0 0 0 2 1.6h8.4a2 2 0 0 0 1.9-1.4L22 8H6"></path>
                        <circle cx="10" cy="20" r="1"></circle>
                        <circle cx="18" cy="20" r="1"></circle>
                    </svg>
                    <span class="cart-count" id="cartCount">0</span>
                </button>

                <a class="btn btn-primary" href="#contact">
                    Nhận báo giá
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14m-6-6 6 6-6 6"></path>
                    </svg>
                </a>

                <button class="icon-btn menu-toggle" id="menuToggle" aria-label="Mở menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M4 7h16M4 12h16M4 17h16"></path>
                    </svg>
                </button>
            </div>
        </div>
    </header>
</template>


