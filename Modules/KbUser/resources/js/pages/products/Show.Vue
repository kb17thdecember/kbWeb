<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import UserLayout from '../../layout/UserLayout.Vue';

type AuthUser = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
};

type ReviewItem = {
    id: number;
    rating: number;
    comment: string;
    created_at: string;
    user?: {
        id: number;
        name: string;
        avatar?: string | null;
    } | null;
};

type CommentItem = {
    id: number;
    body: string;
    created_at: string;
    user?: {
        id: number;
        name: string;
        avatar?: string | null;
    } | null;
};

type ProductDetail = {
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
    reviews?: ReviewItem[];
    comments?: CommentItem[];
};

type CatalogueItem = {
    id: number;
    title: string;
    description: string | null;
    file_name: string;
    file_size: number;
    file_url: string;
    image_url: string | null;
};

const props = defineProps<{
    product: ProductDetail;
    relatedProducts: ProductDetail[];
    catalogues: CatalogueItem[];
    averageRating: number;
    reviewsCount: number;
}>();

const page = usePage<{ auth?: { user?: AuthUser | null; wishlistIds?: number[] } }>();
const authUser = computed<AuthUser | null>(() => page.props.auth?.user ?? null);
const wishlistIds = computed<number[]>(() => page.props.auth?.wishlistIds ?? []);
const isWishlisted = computed<boolean>(() => wishlistIds.value.includes(props.product.id));

const selectedImageIndex = ref(0);
const orderQuantity = ref(1);
const activeDetailTab = ref<'reviews' | 'qa' | 'catalogues'>('reviews');
const toastMessage = ref('');
const isToastVisible = ref(false);
let toastTimer: ReturnType<typeof setTimeout> | undefined;

const reviewForm = useForm({
    rating: 5,
    comment: '',
});

const commentForm = useForm({
    body: '',
});

const productImageUrl = (image: string): string =>
    image.startsWith('data:') || image.startsWith('http') || image.startsWith('/')
        ? image
        : `/storage/${image}`;

const galleryImages = computed<string[]>(() =>
    (props.product.image ?? []).map((img) => productImageUrl(img)),
);

const currentImage = computed<string | null>(() =>
    galleryImages.value[selectedImageIndex.value] ?? galleryImages.value[0] ?? null,
);

const formatPrice = (price: number): string =>
    price > 0 ? `${new Intl.NumberFormat('vi-VN').format(price)}₫` : 'Liên hệ báo giá';

const formatDate = (value: string): string =>
    new Date(value).toLocaleDateString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });

const formatSize = (bytes: number): string => {
    if (bytes >= 1048576) {
        return `${(bytes / 1048576).toFixed(1)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
};

const notify = (message: string): void => {
    toastMessage.value = message;
    isToastVisible.value = true;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        isToastVisible.value = false;
    }, 2500);
};

const addToCart = (product: ProductDetail, qty = 1): void => {
    try {
        const raw = sessionStorage.getItem('kbuser-cart');
        const current = raw ? (JSON.parse(raw) as Record<number, number>) : {};
        current[product.id] = (current[product.id] ?? 0) + Math.max(1, qty);
        sessionStorage.setItem('kbuser-cart', JSON.stringify(current));
        window.dispatchEvent(new Event('kbuser-cart-updated'));
        notify(`Đã thêm ${qty} × ${product.name} vào giỏ báo giá.`);
    } catch {
        notify('Không thể cập nhật giỏ hàng lúc này.');
    }
};

const openLoginModal = (): void => {
    window.dispatchEvent(new CustomEvent('kbuser-auth-open', { detail: { mode: 'login' } }));
};

const toggleWishlist = (): void => {
    if (!authUser.value) {
        openLoginModal();
        return;
    }

    router.post(
        `/wishlist/${props.product.id}`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                notify(
                    isWishlisted.value
                        ? `Đã lưu ${props.product.name} vào danh sách yêu thích.`
                        : `Đã bỏ lưu ${props.product.name}.`,
                );
            },
        },
    );
};

const submitReview = (): void => {
    if (!authUser.value) {
        openLoginModal();
        return;
    }

    reviewForm.post(`/products/${props.product.id}/reviews`, {
        preserveScroll: true,
        onSuccess: () => {
            reviewForm.reset('comment');
            notify('Cảm ơn bạn đã gửi đánh giá sản phẩm!');
        },
    });
};

const submitComment = (): void => {
    if (!authUser.value) {
        openLoginModal();
        return;
    }

    commentForm.post(`/products/${props.product.id}/comments`, {
        preserveScroll: true,
        onSuccess: () => {
            commentForm.reset();
            notify('Câu hỏi kỹ thuật của bạn đã được đăng!');
        },
    });
};

const stepImage = (delta: number): void => {
    const total = galleryImages.value.length;
    if (total > 1) {
        selectedImageIndex.value = (selectedImageIndex.value + delta + total) % total;
    }
};

onMounted(() => {
    selectedImageIndex.value = 0;
});

onUnmounted(() => {
    clearTimeout(toastTimer);
});
</script>

<template>
    <div>
        <Head :title="`${product.product_title || product.name} | Sản phẩm HVAC Mart`" />

        <UserLayout :cart-count="0" active-page="products">
            <main>
                <section class="page-intro">
                    <div class="wrap page-intro-inner">
                        <div>
                            <div class="eyebrow">
                                <span class="dot"></span>
                                <Link href="/products">SẢN PHẨM</Link>
                                <template v-if="product.category"> / {{ product.category.name }}</template>
                            </div>
                            <h1>{{ product.product_title || product.name }}</h1>
                            <p v-if="product.subcategory">
                                Dòng sản phẩm: {{ product.subcategory.name }} · Mã tham chiếu #SP-{{ product.id }}
                            </p>
                        </div>
                        <Link class="page-home-link" href="/products">
                            <FontAwesomeIcon :icon="['fas', 'arrow-left']" /> Tất cả sản phẩm
                        </Link>
                    </div>
                </section>

                <section class="section kb-product-detail-section">
                    <div class="wrap">
                        <div class="kb-product-detail-grid">
                            <div class="kb-product-gallery">
                                <div class="kb-product-stage">
                                    <span class="pill">{{ product.category?.name ?? 'Thiết bị kỹ thuật' }}</span>
                                    <img
                                        v-if="currentImage"
                                        :src="currentImage"
                                        :alt="product.name"
                                        class="kb-product-main-img"
                                    >
                                    <div v-else class="modal-image-placeholder">
                                        {{ product.category?.name ?? 'HVAC Mart' }}
                                    </div>
                                    <div v-if="galleryImages.length > 1" class="product-gallery-controls">
                                        <button type="button" aria-label="Ảnh trước" @click="stepImage(-1)">
                                            <FontAwesomeIcon :icon="['fas', 'arrow-left']" />
                                        </button>
                                        <span>{{ selectedImageIndex + 1 }} / {{ galleryImages.length }}</span>
                                        <button type="button" aria-label="Ảnh tiếp theo" @click="stepImage(1)">
                                            <FontAwesomeIcon :icon="['fas', 'arrow-right']" />
                                        </button>
                                    </div>
                                </div>

                                <div v-if="galleryImages.length > 1" class="kb-product-thumbs">
                                    <button
                                        v-for="(img, idx) in galleryImages"
                                        :key="img"
                                        type="button"
                                        class="kb-product-thumb"
                                        :class="{ active: selectedImageIndex === idx }"
                                        @click="selectedImageIndex = idx"
                                    >
                                        <img :src="img" :alt="`${product.name} - ảnh ${idx + 1}`">
                                    </button>
                                </div>
                            </div>

                            <div class="kb-product-info">
                                <div class="kb-product-rating-bar">
                                    <div class="kb-stars" aria-label="Đánh giá trung bình">
                                        <span
                                            v-for="star in 5"
                                            :key="star"
                                            :class="{ filled: star <= Math.round(averageRating || 5) }"
                                        >★</span>
                                    </div>
                                    <span v-if="reviewsCount > 0" class="kb-rating-summary">
                                        <strong>{{ averageRating.toFixed(1) }}/5</strong> ({{ reviewsCount }} đánh giá)
                                    </span>
                                    <span v-else class="kb-rating-summary">Chưa có đánh giá · Sản phẩm chính hãng</span>
                                </div>

                                <h2 class="kb-product-title">{{ product.name }}</h2>
                                <p v-if="product.product_title && product.product_title !== product.name" class="kb-product-subtitle">
                                    {{ product.product_title }}
                                </p>

                                <div class="kb-product-price-box">
                                    <span class="kb-price-label">Đơn giá tham khảo</span>
                                    <strong class="kb-price-value">{{ formatPrice(product.price) }}</strong>
                                    <small>Báo giá chiết khấu tốt hơn theo số lượng công trình / BOQ dự án</small>
                                </div>

                                <div class="kb-product-specs">
                                    <div class="kb-spec-item">
                                        <span>Danh mục chính</span>
                                        <strong>{{ product.category?.name ?? 'Vật tư HVAC' }}</strong>
                                    </div>
                                    <div class="kb-spec-item">
                                        <span>Phân nhóm kỹ thuật</span>
                                        <strong>{{ product.subcategory?.name ?? 'Tiêu chuẩn dự án' }}</strong>
                                    </div>
                                    <div class="kb-spec-item">
                                        <span>Tình trạng cung ứng</span>
                                        <strong>Sẵn sàng báo giá &amp; cấp hàng công trình</strong>
                                    </div>
                                    <div class="kb-spec-item">
                                        <span>Chứng chỉ CO / CQ</span>
                                        <strong>Đầy đủ hồ sơ nghiệm thu MEP</strong>
                                    </div>
                                </div>

                                <div class="kb-product-description-box">
                                    <h3>Mô tả &amp; thông số kỹ thuật</h3>
                                    <p>{{ product.description }}</p>
                                </div>

                                <div class="kb-product-actions-row">
                                    <div class="qty kb-detail-qty">
                                        <button type="button" aria-label="Giảm số lượng" @click="orderQuantity = Math.max(1, orderQuantity - 1)">−</button>
                                        <span>{{ orderQuantity }}</span>
                                        <button type="button" aria-label="Tăng số lượng" @click="orderQuantity++">+</button>
                                    </div>

                                    <button
                                        type="button"
                                        class="btn btn-primary"
                                        @click="addToCart(product, orderQuantity)"
                                    >
                                        Thêm vào giỏ báo giá
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-outline kb-wishlist-btn"
                                        :class="{ active: isWishlisted }"
                                        :aria-pressed="isWishlisted"
                                        @click="toggleWishlist"
                                    >
                                        <span>{{ isWishlisted ? '♥ Đã lưu' : '♡ Lưu sản phẩm' }}</span>
                                    </button>
                                </div>

                                <div class="kb-product-quick-quote">
                                    <Link href="/contacts" class="kb-quick-quote-link" @click="addToCart(product, orderQuantity)">
                                        Cần báo giá số lượng lớn cho dự án? Gửi yêu cầu ngay <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" />
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <div class="kb-product-tabs-wrap">
                            <div class="kb-auth-tabs" role="tablist">
                                <button
                                    type="button"
                                    class="kb-auth-tab"
                                    :class="{ active: activeDetailTab === 'reviews' }"
                                    @click="activeDetailTab = 'reviews'"
                                >
                                    Đánh giá &amp; Xếp hạng ({{ product.reviews?.length ?? 0 }})
                                </button>
                                <button
                                    type="button"
                                    class="kb-auth-tab"
                                    :class="{ active: activeDetailTab === 'qa' }"
                                    @click="activeDetailTab = 'qa'"
                                >
                                    Hỏi đáp kỹ thuật ({{ product.comments?.length ?? 0 }})
                                </button>
                                <button
                                    type="button"
                                    class="kb-auth-tab"
                                    :class="{ active: activeDetailTab === 'catalogues' }"
                                    @click="activeDetailTab = 'catalogues'"
                                >
                                    Catalogue &amp; Tài liệu ({{ catalogues.length }})
                                </button>
                            </div>

                            <div v-if="activeDetailTab === 'reviews'" class="kb-tab-panel">
                                <div class="kb-discussion-grid">
                                    <div class="kb-discussion-form-card">
                                        <h3>Đánh giá sản phẩm này</h3>
                                        <p>Chia sẻ trải nghiệm thực tế về chất lượng vật tư và hiệu quả lắp đặt tại công trình.</p>

                                        <form v-if="authUser" class="kb-review-form" @submit.prevent="submitReview">
                                            <div class="field">
                                                <label>MỨC ĐỘ HÀI LÒNG *</label>
                                                <div class="kb-star-picker">
                                                    <button
                                                        v-for="star in 5"
                                                        :key="star"
                                                        type="button"
                                                        class="kb-star-btn"
                                                        :class="{ active: star <= reviewForm.rating }"
                                                        @click="reviewForm.rating = star"
                                                    >
                                                        ★ {{ star }} sao
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="field">
                                                <label for="reviewComment">NHẬN XÉT CỦA BẠN *</label>
                                                <textarea
                                                    id="reviewComment"
                                                    v-model="reviewForm.comment"
                                                    rows="4"
                                                    required
                                                    maxlength="2000"
                                                    placeholder="Nhập nhận xét về thông số, độ bền, đóng gói hoặc dịch vụ..."
                                                ></textarea>
                                                <span v-if="reviewForm.errors.comment" class="field-error">{{ reviewForm.errors.comment }}</span>
                                            </div>
                                            <button type="submit" class="btn btn-primary" :disabled="reviewForm.processing">
                                                {{ reviewForm.processing ? 'Đang gửi...' : 'Gửi đánh giá' }}
                                            </button>
                                        </form>

                                        <div v-else class="kb-login-prompt-box">
                                            <p>Vui lòng đăng nhập tài khoản để gửi đánh giá và xếp hạng sản phẩm.</p>
                                            <button type="button" class="btn btn-primary" @click="openLoginModal">
                                                Đăng nhập để đánh giá
                                            </button>
                                        </div>
                                    </div>

                                    <div class="kb-discussion-list">
                                        <div v-if="!product.reviews?.length" class="empty-state">
                                            Chưa có đánh giá nào cho sản phẩm này. Hãy là người đầu tiên đánh giá!
                                        </div>
                                        <article
                                            v-for="review in product.reviews"
                                            :key="review.id"
                                            class="kb-comment-card"
                                        >
                                            <div class="kb-comment-head">
                                                <strong>{{ review.user?.name ?? 'Khách hàng HVAC Mart' }}</strong>
                                                <span class="kb-comment-stars">
                                                    <template v-for="n in 5" :key="n">{{ n <= review.rating ? '★' : '☆' }}</template>
                                                </span>
                                                <small>{{ formatDate(review.created_at) }}</small>
                                            </div>
                                            <p>{{ review.comment }}</p>
                                        </article>
                                    </div>
                                </div>
                            </div>

                            <div v-else-if="activeDetailTab === 'qa'" class="kb-tab-panel">
                                <div class="kb-discussion-grid">
                                    <div class="kb-discussion-form-card">
                                        <h3>Đặt câu hỏi kỹ thuật</h3>
                                        <p>Cần làm rõ quy cách lắp đặt, tiêu chuẩn chống cháy hay thông số kỹ thuật? Đội ngũ kỹ sư sẽ giải đáp.</p>

                                        <form v-if="authUser" class="kb-review-form" @submit.prevent="submitComment">
                                            <div class="field">
                                                <label for="qaBody">NỘI DUNG CÂU HỎI / BÌNH LUẬN *</label>
                                                <textarea
                                                    id="qaBody"
                                                    v-model="commentForm.body"
                                                    rows="4"
                                                    required
                                                    maxlength="2000"
                                                    placeholder="Ví dụ: Sản phẩm này có kèm phụ kiện van một chiều và chứng chỉ CO/CQ cho dự án không?"
                                                ></textarea>
                                                <span v-if="commentForm.errors.body" class="field-error">{{ commentForm.errors.body }}</span>
                                            </div>
                                            <button type="submit" class="btn btn-primary" :disabled="commentForm.processing">
                                                {{ commentForm.processing ? 'Đang gửi...' : 'Gửi câu hỏi kỹ thuật' }}
                                            </button>
                                        </form>

                                        <div v-else class="kb-login-prompt-box">
                                            <p>Đăng nhập để đặt câu hỏi kỹ thuật và nhận phản hồi từ chuyên viên HVAC Mart.</p>
                                            <button type="button" class="btn btn-primary" @click="openLoginModal">
                                                Đăng nhập để đặt câu hỏi
                                            </button>
                                        </div>
                                    </div>

                                    <div class="kb-discussion-list">
                                        <div v-if="!product.comments?.length" class="empty-state">
                                            Chưa có câu hỏi kỹ thuật nào. Hãy để lại câu hỏi nếu bạn cần tư vấn thông số!
                                        </div>
                                        <article
                                            v-for="item in product.comments"
                                            :key="item.id"
                                            class="kb-comment-card"
                                        >
                                            <div class="kb-comment-head">
                                                <strong>{{ item.user?.name ?? 'Khách hàng' }}</strong>
                                                <small>{{ formatDate(item.created_at) }}</small>
                                            </div>
                                            <p>{{ item.body }}</p>
                                        </article>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="kb-tab-panel">
                                <div v-if="catalogues.length === 0" class="empty-state">
                                    Tài liệu kỹ thuật đang được cập nhật. Vui lòng liên hệ để nhận bản PDF thông số chi tiết.
                                </div>
                                <div v-else class="catalogue-grid">
                                    <article v-for="catalogue in catalogues" :key="catalogue.id" class="catalogue-card">
                                        <div class="catalogue-thumb">
                                            <img v-if="catalogue.image_url" :src="catalogue.image_url" :alt="catalogue.title" loading="lazy">
                                            <span v-else class="catalogue-badge">PDF</span>
                                        </div>
                                        <div class="catalogue-body">
                                            <span class="catalogue-meta">PDF · {{ formatSize(catalogue.file_size) }}</span>
                                            <h3>{{ catalogue.title }}</h3>
                                            <p v-if="catalogue.description">{{ catalogue.description }}</p>
                                            <div class="catalogue-actions">
                                                <a class="btn btn-outline" :href="catalogue.file_url" target="_blank" rel="noopener">
                                                    Xem tài liệu
                                                </a>
                                                <a class="btn btn-primary" :href="catalogue.file_url" :download="catalogue.file_name">
                                                    Tải PDF
                                                </a>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            </div>
                        </div>

                        <div v-if="relatedProducts.length > 0" class="kb-related-products">
                            <div class="section-head">
                                <div>
                                    <div class="eyebrow"><span class="dot"></span> SẢN PHẨM CÙNG DANH MỤC</div>
                                    <h2 class="section-title">Thiết bị liên quan</h2>
                                </div>
                            </div>
                            <div class="product-grid">
                                <article v-for="rel in relatedProducts" :key="rel.id" class="product-card">
                                    <div class="product-photo">
                                        <span class="pill">{{ rel.category?.name ?? 'Sản phẩm' }}</span>
                                        <img
                                            v-if="rel.image?.[0]"
                                            class="product-image"
                                            :src="productImageUrl(rel.image[0])"
                                            :alt="rel.name"
                                            loading="lazy"
                                        >
                                    </div>
                                    <div class="product-body">
                                        <div class="product-meta">{{ rel.subcategory?.name ?? 'Thiết bị HVAC' }}</div>
                                        <h3 class="product-name">
                                            <Link :href="`/products/${rel.id}`">{{ rel.name }}</Link>
                                        </h3>
                                        <p class="product-desc">{{ rel.product_title }}</p>
                                        <div class="product-bottom">
                                            <span class="price">{{ formatPrice(rel.price) }}</span>
                                            <Link :href="`/products/${rel.id}`" class="btn btn-outline kb-card-detail-link">
                                                Chi tiết
                                            </Link>
                                        </div>
                                    </div>
                                </article>
                            </div>
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
