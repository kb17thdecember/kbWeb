<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import UserLayout from '../../layout/UserLayout.Vue';
import { buildProductDetailUrl } from '../../utils/recentlyViewedProducts';

type RelatedProduct = {
    id: number;
    slug?: string;
    name: string;
    product_title: string | null;
    description?: string | null;
    price: number;
    image: string[];
    category: { id: number; name: string } | null;
    subcategory: { id: number; name: string } | null;
};

type FeaturedProject = {
    id: number;
    slug?: string;
    name: string;
    description: string;
    seo_title?: string | null;
    seo_description?: string | null;
    seo_keywords?: string | null;
    location: string;
    category: string;
    subcategory: string;
    type: number | string;
    image: string;
    image_url: string | null;
    images: string[] | null;
    images_url: string[];
    related_products?: RelatedProduct[];
};

const props = defineProps<{
    project: FeaturedProject;
    relatedProjects: FeaturedProject[];
}>();

const selectedImageIndex = ref(0);
const cartCount = ref(0);
const addedProductId = ref<number | null>(null);
const isToastVisible = ref(false);
const toastMessage = ref('');
let toastTimer: ReturnType<typeof setTimeout> | undefined;

const imageUrl = (project: FeaturedProject): string =>
    project.image_url
    || (project.image?.startsWith('http') || project.image?.startsWith('/')
        ? project.image
        : `/storage/${project.image}`);

const galleryImages = computed<string[]>(() => {
    if (props.project.images_url?.length) {
        return props.project.images_url;
    }

    return [imageUrl(props.project)];
});

watch(
    () => props.project.id,
    () => {
        selectedImageIndex.value = 0;
    },
);

const stepGalleryImage = (delta: number): void => {
    const count = galleryImages.value.length;

    if (count > 1) {
        selectedImageIndex.value = (selectedImageIndex.value + delta + count) % count;
    }
};

const projectTypeLabel = (type: number | string): string =>
    ({
        '1': 'Chung cư thương mại & NOXH',
        '2': 'Văn phòng, khách sạn & bệnh viện',
        '3': 'Khu công nghiệp',
    })[String(type)] ?? 'Dự án tiêu biểu';

const descriptionParagraphs = computed<string[]>(() => {
    const raw = (props.project.description || '').trim();

    if (!raw) {
        return ['Thông tin chi tiết hạng mục cung ứng đang được cập nhật theo hồ sơ dự án.'];
    }

    return raw
        .split(/\n+/)
        .map((line) => line.trim())
        .filter(Boolean);
});

const relatedProducts = computed<RelatedProduct[]>(() => props.project.related_products ?? []);

const BookableProducts = computed<RelatedProduct[]>(() =>
    relatedProducts.value.filter((item) => item.id > 0),
);

const productThumbUrl = (product: RelatedProduct): string | null => {
    const first = product.image?.[0];

    if (!first) {
        return null;
    }

    return first.startsWith('http') || first.startsWith('/') ? first : `/storage/${first}`;
};

const formatPrice = (price: number): string =>
    price > 0 ? `${new Intl.NumberFormat('vi-VN').format(price)}₫` : 'Báo giá theo dự án';

const productDetailUrl = (product: RelatedProduct): string =>
    buildProductDetailUrl(product);

const projectDetailUrl = (item: FeaturedProject): string =>
    `/featured-projects/${item.slug || item.id}`;

const projectSummary = (description: string): string => {
    const characters = Array.from(description || '');

    return characters.length > 180 ? `${characters.slice(0, 177).join('')}...` : description;
};

const showToast = (message: string): void => {
    toastMessage.value = message;
    isToastVisible.value = true;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        isToastVisible.value = false;
    }, 2400);
};

const syncCartCount = (): void => {
    try {
        const raw = sessionStorage.getItem('kbuser-cart');
        const parsed = raw ? (JSON.parse(raw) as Record<string, number>) : {};
        cartCount.value = Object.values(parsed).reduce(
            (sum, qty) => sum + (Number.isFinite(qty) ? Number(qty) : 0),
            0,
        );
    } catch {
        cartCount.value = 0;
    }
};

const addProjectProductToCart = (product: RelatedProduct): void => {
    if (!product.id) {
        return;
    }

    try {
        const raw = sessionStorage.getItem('kbuser-cart');
        const current = raw ? (JSON.parse(raw) as Record<string, number>) : {};
        current[String(product.id)] = (current[String(product.id)] ?? 0) + 1;
        sessionStorage.setItem('kbuser-cart', JSON.stringify(current));
        syncCartCount();
        window.dispatchEvent(new Event('kbuser-cart-updated'));
        addedProductId.value = product.id;
        showToast(`Đã thêm "${product.name}" vào giỏ báo giá`);
        setTimeout(() => {
            if (addedProductId.value === product.id) {
                addedProductId.value = null;
            }
        }, 2000);
    } catch {
        // Ignore sessionStorage errors
    }
};

const addAllProjectProductsToCart = (): void => {
    if (!BookableProducts.value.length) {
        return;
    }

    try {
        const raw = sessionStorage.getItem('kbuser-cart');
        const current = raw ? (JSON.parse(raw) as Record<string, number>) : {};

        BookableProducts.value.forEach((product) => {
            current[String(product.id)] = (current[String(product.id)] ?? 0) + 1;
        });

        sessionStorage.setItem('kbuser-cart', JSON.stringify(current));
        syncCartCount();
        window.dispatchEvent(new Event('kbuser-cart-updated'));
        showToast(`Đã thêm ${BookableProducts.value.length} vật tư của dự án vào giỏ báo giá`);
    } catch {
        // Ignore sessionStorage errors
    }
};

onMounted(() => {
    syncCartCount();
    window.addEventListener('kbuser-cart-updated', syncCartCount);
});

onUnmounted(() => {
    window.removeEventListener('kbuser-cart-updated', syncCartCount);
    clearTimeout(toastTimer);
});
</script>

<template>
    <div>
        <Head :title="project.seo_title || `HVAC Mart | ${project.name} - Dự án tiêu biểu`" />

        <UserLayout :cart-count="cartCount" active-page="projects">
            <main>
                <section class="page-intro" aria-live="polite">
                    <div class="wrap page-intro-inner">
                        <div>
                            <div class="eyebrow">
                                <span class="dot"></span>
                                HVAC MART / DỰ ÁN TIÊU BIỂU / {{ projectTypeLabel(project.type).toUpperCase() }}
                            </div>
                            <h1>{{ project.name }}</h1>
                            <p>{{ project.location }} · {{ project.category }} ({{ project.subcategory }})</p>
                        </div>
                        <div class="project-show-intro-actions">
                            <Link class="page-home-link" :href="`/featured-projects?type=${project.type}`">
                                <FontAwesomeIcon :icon="['fas', 'arrow-left']" /> Quay lại danh sách dự án
                            </Link>
                        </div>
                    </div>
                </section>

                <section class="section project-show-section">
                    <div class="wrap">
                        <div class="project-show-hero">
                            <div class="project-show-gallery-card">
                                <div class="project-show-stage">
                                    <img
                                        class="project-show-main-photo"
                                        :src="galleryImages[selectedImageIndex]"
                                        :alt="`${project.name} · ảnh ${selectedImageIndex + 1}`"
                                    >
                                    <span class="project-image-tag">{{ projectTypeLabel(project.type) }}</span>
                                    <div v-if="galleryImages.length > 1" class="project-show-stage-nav">
                                        <button
                                            class="gallery-step"
                                            type="button"
                                            aria-label="Ảnh trước"
                                            @click="stepGalleryImage(-1)"
                                        >
                                            <FontAwesomeIcon :icon="['fas', 'arrow-left']" />
                                        </button>
                                        <span>
                                            {{ String(selectedImageIndex + 1).padStart(2, '0') }} / {{ String(galleryImages.length).padStart(2, '0') }}
                                        </span>
                                        <button
                                            class="gallery-step"
                                            type="button"
                                            aria-label="Ảnh tiếp theo"
                                            @click="stepGalleryImage(1)"
                                        >
                                            <FontAwesomeIcon :icon="['fas', 'arrow-right']" />
                                        </button>
                                    </div>
                                </div>

                                <div v-if="galleryImages.length > 1" class="project-show-thumbs">
                                    <button
                                        v-for="(image, index) in galleryImages"
                                        :key="`${image}-${index}`"
                                        type="button"
                                        class="project-show-thumb"
                                        :class="{ active: selectedImageIndex === index }"
                                        :aria-label="`Xem ảnh ${index + 1}`"
                                        :aria-pressed="selectedImageIndex === index"
                                        @click="selectedImageIndex = index"
                                    >
                                        <img :src="image" :alt="`${project.name} · ảnh ${index + 1}`" loading="lazy">
                                        <span>Ảnh {{ String(index + 1).padStart(2, '0') }}</span>
                                    </button>
                                </div>

                                <div class="project-show-assurance-bar">
                                    <div class="project-show-assurance-item">
                                        <strong>CO / CQ</strong>
                                        <span>Hồ sơ xuất xứ &amp; chất lượng đồng bộ</span>
                                    </div>
                                    <div class="project-show-assurance-item">
                                        <strong>MEP SPEC</strong>
                                        <span>Đáp ứng tiêu chuẩn nghiệm thu công trình</span>
                                    </div>
                                    <div class="project-show-assurance-item">
                                        <strong>B2B SUPPLY</strong>
                                        <span>Phối hợp tiến độ giao hàng theo giai đoạn</span>
                                    </div>
                                </div>
                            </div>

                            <div class="project-show-info-card">
                                <div class="project-show-badges">
                                    <span class="project-detail-type">CASE STUDY · HỒ SƠ CÔNG TRÌNH</span>
                                    <span class="project-show-verified-pill">ĐÃ CUNG ỨNG VẬT TƯ</span>
                                </div>

                                <h2 class="project-show-title">{{ project.name }}</h2>

                                <div class="project-show-meta-grid">
                                    <div class="project-show-meta-box">
                                        <span>Nhóm công trình</span>
                                        <strong>{{ projectTypeLabel(project.type) }}</strong>
                                    </div>
                                    <div class="project-show-meta-box">
                                        <span>Địa điểm triển khai</span>
                                        <strong>{{ project.location }}</strong>
                                    </div>
                                    <div class="project-show-meta-box">
                                        <span>Danh mục cung ứng</span>
                                        <strong>{{ project.category }}</strong>
                                    </div>
                                    <div class="project-show-meta-box">
                                        <span>Hạng mục kỹ thuật</span>
                                        <strong>{{ project.subcategory }}</strong>
                                    </div>
                                </div>

                                <div class="project-show-description">
                                    <h3>Tổng quan hạng mục &amp; giải pháp kỹ thuật</h3>
                                    <p v-for="(paragraph, idx) in descriptionParagraphs" :key="idx">
                                        {{ paragraph }}
                                    </p>
                                </div>

                                <div class="project-show-highlights">
                                    <h3>Giá trị triển khai thực tế</h3>
                                    <ul>
                                        <li>
                                            <span class="check"><FontAwesomeIcon :icon="['fas', 'check']" /></span>
                                            <div>
                                                <strong>Tối ưu thông số kỹ thuật theo thiết kế MEP</strong>
                                                <span>Đề xuất đúng chủng loại {{ project.category }} ({{ project.subcategory }}) phù hợp quy mô vận hành của dự án.</span>
                                            </div>
                                        </li>
                                        <li>
                                            <span class="check"><FontAwesomeIcon :icon="['fas', 'check']" /></span>
                                            <div>
                                                <strong>Hồ sơ Submittal, Catalogue &amp; CO/CQ đầy đủ</strong>
                                                <span>Hỗ trợ nhà thầu cơ điện và chủ đầu tư trong suốt quá trình trình mẫu và nghiệm thu vật tư đầu vào.</span>
                                            </div>
                                        </li>
                                        <li>
                                            <span class="check"><FontAwesomeIcon :icon="['fas', 'check']" /></span>
                                            <div>
                                                <strong>Đồng hành kỹ thuật &amp; tiến độ công trường</strong>
                                                <span>Phối hợp giao hàng linh hoạt tại {{ project.location }} theo từng mốc thi công thực tế.</span>
                                            </div>
                                        </li>
                                    </ul>
                                </div>

                                <div class="project-show-cta-box">
                                    <div class="project-show-cta-copy">
                                        <small>TƯ VẤN &amp; BÁO GIÁ DỰ ÁN TƯƠNG TỰ</small>
                                        <strong>Bạn đang triển khai hạng mục {{ project.subcategory }}?</strong>
                                        <span>Gửi khối lượng BOQ hoặc thông số kỹ thuật để đội ngũ kỹ sư HVAC Mart lập phương án vật tư và báo giá chiết khấu dự án.</span>
                                    </div>
                                    <div class="project-show-cta-buttons">
                                        <Link href="/contacts" class="btn btn-primary">
                                            Nhận báo giá dự án <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" />
                                        </Link>
                                        <Link href="/catalogues" class="btn btn-outline">
                                            Tài liệu Catalogue
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="section project-show-products-section">
                    <div class="wrap">
                        <div class="project-show-products-shell">
                            <div class="section-head project-show-products-head">
                                <div>
                                    <div class="eyebrow"><span class="dot"></span> DANH MỤC VẬT TƯ THỰC TẾ</div>
                                    <h2 class="section-title">
                                        Sản phẩm &amp; thiết bị <em>sử dụng trong dự án.</em>
                                    </h2>
                                </div>
                                <div class="project-show-products-actions">
                                    <button
                                        v-if="BookableProducts.length > 1"
                                        type="button"
                                        class="btn btn-primary"
                                        @click="addAllProjectProductsToCart"
                                    >
                                        + Thêm toàn bộ ({{ BookableProducts.length }}) vào giỏ báo giá
                                    </button>
                                    <Link href="/products" class="btn btn-outline">
                                        Xem toàn bộ sản phẩm <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" />
                                    </Link>
                                </div>
                            </div>

                            <div v-if="relatedProducts.length" class="project-show-products-grid">
                                <article
                                    v-for="prod in relatedProducts"
                                    :key="prod.id || prod.name"
                                    class="project-show-product-card"
                                >
                                    <Link :href="productDetailUrl(prod)" class="project-show-product-media">
                                        <img
                                            v-if="productThumbUrl(prod)"
                                            :src="productThumbUrl(prod) ?? undefined"
                                            :alt="prod.name"
                                            loading="lazy"
                                        >
                                        <div v-else class="project-show-product-fallback">
                                            <span>{{ prod.category?.name || project.category || 'HVAC' }}</span>
                                        </div>
                                        <span class="project-show-product-tag">
                                            {{ prod.category?.name || project.category }}
                                        </span>
                                    </Link>

                                    <div class="project-show-product-body">
                                        <div class="project-show-product-subcat">
                                            {{ prod.subcategory?.name || project.subcategory }}
                                        </div>
                                        <h3>
                                            <Link :href="productDetailUrl(prod)">{{ prod.name }}</Link>
                                        </h3>
                                        <p>
                                            {{ prod.product_title || prod.description || `Vật tư cung ứng cho hạng mục ${project.subcategory} tại ${project.name}.` }}
                                        </p>
                                        <div class="project-show-product-foot">
                                            <div class="project-show-product-price">
                                                <span>Đơn giá tham khảo</span>
                                                <strong>{{ formatPrice(prod.price) }}</strong>
                                            </div>
                                            <div class="project-show-product-btns">
                                                <button
                                                    v-if="prod.id"
                                                    type="button"
                                                    class="project-modal-product-btn"
                                                    :class="{ 'is-added': addedProductId === prod.id }"
                                                    @click="addProjectProductToCart(prod)"
                                                >
                                                    {{ addedProductId === prod.id ? '✓ Đã thêm' : '+ Giỏ báo giá' }}
                                                </button>
                                                <Link
                                                    :href="productDetailUrl(prod)"
                                                    class="project-show-product-link"
                                                >
                                                    Chi tiết <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" />
                                                </Link>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            </div>

                            <div v-else class="empty-state">
                                Danh mục mã vật tư chi tiết của dự án đang được cập nhật. Vui lòng liên hệ để nhận hồ sơ Submittal đầy đủ.
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="relatedProjects.length" class="section project-show-related-section">
                    <div class="wrap">
                        <div class="section-head">
                            <div>
                                <div class="eyebrow"><span class="dot"></span> HỒ SƠ CÔNG TRÌNH KHÁC</div>
                                <h2 class="section-title">Dự án tiêu biểu <em>liên quan.</em></h2>
                            </div>
                            <Link class="btn btn-outline" href="/featured-projects">
                                Xem tất cả dự án <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" />
                            </Link>
                        </div>

                        <div class="project-show-related-grid">
                            <article
                                v-for="item in relatedProjects"
                                :key="item.id"
                                class="project-detail-card"
                            >
                                <Link :href="projectDetailUrl(item)" class="project-detail-art">
                                    <img
                                        class="project-detail-image"
                                        :src="imageUrl(item)"
                                        :alt="item.name"
                                        loading="lazy"
                                    >
                                    <span class="project-image-tag">{{ projectTypeLabel(item.type) }}</span>
                                </Link>
                                <div class="project-detail-copy">
                                    <span class="project-detail-type">CASE STUDY · {{ projectTypeLabel(item.type) }}</span>
                                    <h3>
                                        <Link :href="projectDetailUrl(item)">{{ item.name }}</Link>
                                    </h3>
                                    <p>{{ projectSummary(item.description) }}</p>
                                    <div class="project-detail-meta">
                                        <div><span>Vị trí</span><strong>{{ item.location }}</strong></div>
                                        <div><span>Hạng mục tham chiếu</span><strong>{{ item.category }} · {{ item.subcategory }}</strong></div>
                                    </div>
                                    <div class="project-card-actions">
                                        <Link class="project-view-detail" :href="projectDetailUrl(item)">
                                            <span>Xem chi tiết dự án</span>
                                            <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" />
                                        </Link>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>
            </main>

            <div class="toast" :class="{ show: isToastVisible }" role="status" aria-live="polite">
                <span class="toast-dot"></span>{{ toastMessage }}
            </div>
        </UserLayout>
    </div>
</template>
