<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import UserLayout from '../../layout/UserLayout.Vue';
import { lockPageScroll } from '../../utils/pageScrollLock';

type CatalogueItem = {
    id: number;
    title: string;
    description: string | null;
    file_name: string;
    file_size: number;
    file_url: string;
    image_url: string | null;
    created_at?: string;
};

const props = defineProps<{
    catalogues: CatalogueItem[];
}>();

const activeBrand = ref<'all' | 'ariston' | 'kflex'>('all');
const searchQuery = ref('');
const previewCatalogue = ref<CatalogueItem | null>(null);
const cartCount = ref(0);
let releasePageScrollLock: (() => void) | null = null;

const refreshCartCount = (): void => {
    try {
        const raw = sessionStorage.getItem('kbuser-cart');
        if (!raw) {
            cartCount.value = 0;
            return;
        }
        const parsed = JSON.parse(raw) as Record<string, number>;
        cartCount.value = Object.values(parsed).reduce((sum, qty) => sum + (Number(qty) || 0), 0);
    } catch {
        cartCount.value = 0;
    }
};

const detectBrand = (item: CatalogueItem): 'ariston' | 'kflex' | 'other' => {
    const text = `${item.title} ${item.description ?? ''} ${item.file_name}`.toLowerCase();
    if (text.includes('ariston') || text.includes('nước nóng') || text.includes('bơm nhiệt')) {
        return 'ariston';
    }
    if (text.includes('k-flex') || text.includes('kflex') || text.includes('m-flex') || text.includes('bảo ôn') || text.includes('k-protect')) {
        return 'kflex';
    }
    return 'other';
};

const brandBadgeLabel = (item: CatalogueItem): string => {
    const brand = detectBrand(item);
    if (brand === 'ariston') {
        return 'ARISTON · NHIỆT & NƯỚC NÓNG';
    }
    if (brand === 'kflex') {
        return 'K-FLEX · BẢO ÔN CÁCH NHIỆT';
    }
    return 'TÀI LIỆU KỸ THUẬT HVAC';
};

const filteredCatalogues = computed<CatalogueItem[]>(() => {
    const q = searchQuery.value.trim().toLowerCase();

    return props.catalogues.filter((item) => {
        const brand = detectBrand(item);
        const matchesBrand = activeBrand.value === 'all' || brand === activeBrand.value;
        const matchesSearch =
            q === '' ||
            `${item.title} ${item.description ?? ''} ${item.file_name}`.toLowerCase().includes(q);

        return matchesBrand && matchesSearch;
    });
});

const formatSize = (bytes: number): string => {
    if (bytes >= 1048576) {
        return `${(bytes / 1048576).toFixed(1)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
};

const openCalculator = (): void => {
    window.dispatchEvent(new CustomEvent('kbuser-calculator-open'));
};

const onEscape = (event: KeyboardEvent): void => {
    if (event.key === 'Escape' && previewCatalogue.value) {
        previewCatalogue.value = null;
    }
};

watch(previewCatalogue, (item) => {
    if (item) {
        releasePageScrollLock ??= lockPageScroll();
        return;
    }

    releasePageScrollLock?.();
    releasePageScrollLock = null;
});

onMounted(() => {
    refreshCartCount();
    window.addEventListener('kbuser-cart-updated', refreshCartCount);
    window.addEventListener('keydown', onEscape);
});

onUnmounted(() => {
    window.removeEventListener('kbuser-cart-updated', refreshCartCount);
    window.removeEventListener('keydown', onEscape);
    releasePageScrollLock?.();
    releasePageScrollLock = null;
});
</script>

<template>
    <div>
        <Head title="Thư viện Catalogue & Tài liệu Kỹ thuật | HVAC Mart" />

        <UserLayout :cart-count="cartCount" active-page="catalogues">
            <main>
                <section class="page-intro">
                    <div class="wrap page-intro-inner">
                        <div>
                            <div class="eyebrow">
                                <span class="dot"></span>
                                TECHNICAL DOCUMENT &amp; SUBMITTAL CENTER
                            </div>
                            <h1>Thư viện Catalogue &amp; Tài liệu Kỹ thuật</h1>
                            <p>
                                Tra cứu và tải trọn bộ Catalogue, Datasheet thông số kỹ thuật, hướng dẫn chọn độ dày bảo ôn K-FLEX và bình nước nóng / bơm nhiệt Ariston phục vụ trình duyệt vật tư dự án M&amp;E.
                            </p>
                        </div>
                        <div class="kb-cat-center-head-actions">
                            <button type="button" class="btn btn-outline" @click="openCalculator">
                                Công cụ tính chọn thiết bị
                            </button>
                            <Link class="page-home-link" href="/products">
                                Xem danh mục sản phẩm →
                            </Link>
                        </div>
                    </div>
                </section>

                <section class="section">
                    <div class="wrap">
                        <div class="product-toolbar kb-catalogue-toolbar">
                            <div class="filters" role="tablist" aria-label="Lọc tài liệu theo thương hiệu">
                                <button
                                    type="button"
                                    class="filter-btn"
                                    :class="{ active: activeBrand === 'all' }"
                                    @click="activeBrand = 'all'"
                                >
                                    Tất cả tài liệu ({{ catalogues.length }})
                                </button>
                                <button
                                    type="button"
                                    class="filter-btn"
                                    :class="{ active: activeBrand === 'ariston' }"
                                    @click="activeBrand = 'ariston'"
                                >
                                    Ariston · Nhiệt &amp; Nước nóng
                                </button>
                                <button
                                    type="button"
                                    class="filter-btn"
                                    :class="{ active: activeBrand === 'kflex' }"
                                    @click="activeBrand = 'kflex'"
                                >
                                    K-FLEX · Bảo ôn cách nhiệt
                                </button>
                            </div>

                            <label class="searchbox">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <circle cx="10.8" cy="10.8" r="6.8" />
                                    <path d="m16 16 5 5" />
                                </svg>
                                <input
                                    v-model="searchQuery"
                                    type="search"
                                    placeholder="Tìm tên tài liệu, model (ST, M-Flex, Ariston...)"
                                    aria-label="Tìm kiếm tài liệu kỹ thuật"
                                >
                            </label>
                        </div>

                        <div v-if="filteredCatalogues.length === 0" class="empty-state">
                            Không tìm thấy tài liệu kỹ thuật phù hợp với bộ lọc hiện tại.
                        </div>

                        <div v-else class="kb-doc-center-grid">
                            <article
                                v-for="catalogue in filteredCatalogues"
                                :key="catalogue.id"
                                class="kb-doc-card"
                            >
                                <div class="kb-doc-card-cover" @click="previewCatalogue = catalogue">
                                    <span class="kb-doc-type-badge">PDF · {{ formatSize(catalogue.file_size) }}</span>
                                    <img
                                        v-if="catalogue.image_url"
                                        :src="catalogue.image_url"
                                        :alt="catalogue.title"
                                        loading="lazy"
                                    >
                                    <div v-else class="kb-doc-cover-fallback">
                                        <span>PDF</span>
                                    </div>
                                </div>

                                <div class="kb-doc-card-body">
                                    <span class="kb-doc-brand-tag">{{ brandBadgeLabel(catalogue) }}</span>
                                    <h2 class="kb-doc-card-title">{{ catalogue.title }}</h2>
                                    <p class="kb-doc-card-desc">
                                        {{ catalogue.description || 'Tài liệu thông số kỹ thuật, tiêu chuẩn chất lượng và hướng dẫn lựa chọn thiết bị chính hãng.' }}
                                    </p>
                                    <div class="kb-doc-file-meta">
                                        <span>Tệp: <strong>{{ catalogue.file_name }}</strong></span>
                                        <span>Dung lượng: <strong>{{ formatSize(catalogue.file_size) }}</strong></span>
                                    </div>

                                    <div class="kb-doc-card-actions">
                                        <button
                                            type="button"
                                            class="btn btn-outline kb-doc-action-btn"
                                            @click="previewCatalogue = catalogue"
                                        >
                                            Xem trực tuyến
                                        </button>
                                        <a
                                            class="btn btn-primary kb-doc-action-btn"
                                            :href="catalogue.file_url"
                                            :download="catalogue.file_name"
                                        >
                                            Tải PDF
                                        </a>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <div class="kb-about-cta-banner kb-submittal-banner">
                            <div class="kb-about-cta-copy">
                                <span class="eyebrow"><span class="dot"></span> PROJECT SUBMITTAL &amp; CO/CQ DOSSIER</span>
                                <h2>Cần bộ hồ sơ trình duyệt vật tư (Submittal) hoặc CO/CQ theo dự án?</h2>
                                <p>
                                    Ngoài Catalogue tiêu chuẩn trên website, phòng Kỹ thuật &amp; Dự án HVAC Mart hỗ trợ cung cấp trọn bộ chứng chỉ xuất xứ (CO), chứng chỉ chất lượng (CQ), kết quả thử nghiệm chống cháy (FM Approved, UL94, BS 476 Class 0, ASTM E84) và bảng compliance theo đúng hồ sơ mời thầu.
                                </p>
                                <div class="kb-about-cta-buttons">
                                    <Link href="/contacts" class="btn btn-primary">
                                        Yêu cầu hồ sơ trình duyệt &amp; Báo giá
                                    </Link>
                                    <button type="button" class="btn btn-outline" @click="openCalculator">
                                        Mở công cụ tính chọn thiết bị
                                    </button>
                                </div>
                            </div>

                            <div class="kb-about-cta-info">
                                <div class="kb-about-info-row">
                                    <span>CHỨNG CHỈ BẢO ÔN K-FLEX</span>
                                    <strong>FM Approved · UL94 V-0 · BS 476 Part 6 &amp; 7 Class 0 · ASTM E84</strong>
                                </div>
                                <div class="kb-about-info-row">
                                    <span>HỒ SƠ BÌNH NƯỚC NÓNG &amp; HEATPUMP ARISTON</span>
                                    <strong>Catalogue kỹ thuật · Bản vẽ kích thước lắp đặt · Hồ sơ CO/CQ chính hãng</strong>
                                </div>
                                <div class="kb-about-info-row">
                                    <span>HỖ TRỢ BÓC TÁCH KHỐI LƯỢNG</span>
                                    <strong>Tư vấn độ dày chống đọng sương &amp; công suất cấp nhiệt tối ưu</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </main>

            <Transition name="kb-modal">
                <div
                    v-if="previewCatalogue"
                    class="modal-wrap open kb-pdf-preview-modal-wrap"
                    role="presentation"
                    @click.self="previewCatalogue = null"
                >
                    <section
                        class="modal kb-pdf-preview-modal"
                        role="dialog"
                        aria-modal="true"
                        :aria-label="`Xem tài liệu ${previewCatalogue.title}`"
                    >
                        <div class="kb-auth-modal-head kb-pdf-preview-head">
                            <div>
                                <span class="eyebrow"><span class="dot"></span> PDF DOCUMENT VIEWER</span>
                                <h3>{{ previewCatalogue.title }}</h3>
                            </div>
                            <div class="kb-pdf-preview-actions">
                                <a
                                    class="btn btn-outline kb-pdf-head-btn"
                                    :href="previewCatalogue.file_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Mở tab mới
                                </a>
                                <a
                                    class="btn btn-primary kb-pdf-head-btn"
                                    :href="previewCatalogue.file_url"
                                    :download="previewCatalogue.file_name"
                                >
                                    Tải PDF ({{ formatSize(previewCatalogue.file_size) }})
                                </a>
                                <button
                                    type="button"
                                    class="close-btn"
                                    aria-label="Đóng trình xem tài liệu"
                                    @click="previewCatalogue = null"
                                >
                                    ×
                                </button>
                            </div>
                        </div>

                        <div class="kb-pdf-preview-body">
                            <iframe
                                :src="previewCatalogue.file_url"
                                :title="previewCatalogue.title"
                                class="kb-pdf-iframe"
                            ></iframe>
                        </div>
                    </section>
                </div>
            </Transition>
        </UserLayout>
    </div>
</template>
