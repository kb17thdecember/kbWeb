<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import UserLayout from '../../layout/UserLayout.Vue';

type ArticleComment = {
    id: number;
    body: string;
    created_at: string;
    user?: {
        id: number;
        name: string;
        avatar?: string | null;
    } | null;
};

type NewsArticle = {
    id: number;
    title: string;
    slug: string;
    excerpt: string;
    seo_title?: string | null;
    seo_description?: string | null;
    seo_keywords?: string | null;
    published_at: string | null;
    image_url: string | null;
    comments?: ArticleComment[];
};

const props = defineProps<{
    article: NewsArticle & { content: string };
    related: NewsArticle[];
}>();

const page = usePage<{ auth?: { user?: { id: number; name: string } | null } }>();
const authUser = computed(() => page.props.auth?.user ?? null);

const commentSubmitted = ref(false);
const commentForm = useForm({
    body: '',
});

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString('vi-VN') : '';
}

function openLoginModal(): void {
    window.dispatchEvent(new CustomEvent('kbuser-auth-open', { detail: { mode: 'login' } }));
}

function submitComment(): void {
    if (!authUser.value) {
        openLoginModal();
        return;
    }

    commentSubmitted.value = false;
    commentForm.post(`/news/${props.article.slug}/comments`, {
        preserveScroll: true,
        onSuccess: () => {
            commentForm.reset();
            commentSubmitted.value = true;
        },
    });
}
</script>

<template>
    <div>
        <Head :title="article.seo_title || `${article.title} | HVAC Mart`">
            <meta name="description" :content="article.seo_description || article.excerpt" />
            <meta v-if="article.seo_keywords" name="keywords" :content="article.seo_keywords" />
            <meta property="og:title" :content="article.seo_title || article.title" />
            <meta property="og:description" :content="article.seo_description || article.excerpt" />
            <meta v-if="article.image_url" property="og:image" :content="article.image_url" />
        </Head>

        <UserLayout :cart-count="0" active-page="news">
            <main>
                <article class="section news-article-section">
                    <div class="wrap news-article">
                        <div class="news-article-topbar">
                            <Link class="page-home-link" href="/news">
                                <FontAwesomeIcon :icon="['fas', 'arrow-left']" /> Tất cả tin tức
                            </Link>
                            <div class="news-article-meta">
                                <span class="news-article-badge">CHUYÊN ĐỀ KỸ THUẬT</span>
                                <time v-if="article.published_at">{{ formatDate(article.published_at) }}</time>
                            </div>
                        </div>

                        <header class="news-article-header">
                            <h1>{{ article.title }}</h1>
                            <p v-if="article.excerpt" class="news-lead">{{ article.excerpt }}</p>
                        </header>

                        <figure v-if="article.image_url" class="news-cover-stage">
                            <img class="news-cover" :src="article.image_url" :alt="article.title">
                        </figure>

                        <div class="news-content" v-html="article.content"></div>

                        <section class="kb-article-comments">
                            <div class="kb-article-comments-head">
                                <span class="eyebrow"><span class="dot"></span> THẢO LUẬN CHUYÊN MÔN</span>
                                <h2>Bình luận &amp; Hỏi đáp kỹ thuật ({{ article.comments?.length ?? 0 }})</h2>
                            </div>

                            <form v-if="authUser" class="kb-review-form" @submit.prevent="submitComment">
                                <div class="field">
                                    <label for="articleCommentBody">Ý KIẾN HOẶC CÂU HỎI CỦA BẠN *</label>
                                    <textarea
                                        id="articleCommentBody"
                                        v-model="commentForm.body"
                                        rows="3"
                                        maxlength="2000"
                                        required
                                        placeholder="Chia sẻ góc nhìn kỹ thuật hoặc đặt câu hỏi về chủ đề này..."
                                    ></textarea>
                                    <span v-if="commentForm.errors.body" class="field-error">{{ commentForm.errors.body }}</span>
                                </div>
                                <button type="submit" class="btn btn-primary" :disabled="commentForm.processing">
                                    {{ commentForm.processing ? 'Đang gửi...' : 'Gửi bình luận' }}
                                </button>
                                <p v-if="commentSubmitted" class="form-message show" role="status">
                                    Bình luận của bạn đã được đăng thành công.
                                </p>
                            </form>

                            <div v-else class="kb-login-prompt-box">
                                <p>Vui lòng đăng nhập tài khoản để tham gia bình luận và trao đổi kỹ thuật dưới bài viết.</p>
                                <button type="button" class="btn btn-primary" @click="openLoginModal">
                                    Đăng nhập để bình luận
                                </button>
                            </div>

                            <div class="kb-discussion-list">
                                <div v-if="!article.comments?.length" class="empty-state">
                                    Chưa có bình luận nào cho bài viết này.
                                </div>
                                <article
                                    v-for="item in article.comments"
                                    :key="item.id"
                                    class="kb-comment-card"
                                >
                                    <div class="kb-comment-head">
                                        <strong>{{ item.user?.name ?? 'Độc giả HVAC Mart' }}</strong>
                                        <small>{{ formatDate(item.created_at) }}</small>
                                    </div>
                                    <p>{{ item.body }}</p>
                                </article>
                            </div>
                        </section>
                    </div>
                </article>

                <section v-if="related.length" class="section news-related-section">
                    <div class="wrap">
                        <div class="section-head news-related-head">
                            <div>
                                <div class="eyebrow"><span class="dot"></span> TIN TỨC LIÊN QUAN</div>
                                <h2 class="section-title">Bài viết khác</h2>
                            </div>
                            <Link class="page-home-link" href="/news">
                                Xem tất cả <FontAwesomeIcon :icon="['fas', 'arrow-right']" />
                            </Link>
                        </div>
                        <div class="news-grid is-related">
                            <Link v-for="item in related" :key="item.id" :href="`/news/${item.slug}`" class="news-card">
                                <div class="news-thumb">
                                    <img v-if="item.image_url" :src="item.image_url" :alt="item.title" loading="lazy">
                                    <FontAwesomeIcon v-else :icon="['fas', 'newspaper']" />
                                </div>
                                <div class="news-body">
                                    <time v-if="item.published_at">{{ formatDate(item.published_at) }}</time>
                                    <h3>{{ item.title }}</h3>
                                    <p>{{ item.excerpt }}</p>
                                    <span class="news-more">Đọc tiếp →</span>
                                </div>
                            </Link>
                        </div>
                    </div>
                </section>
            </main>
        </UserLayout>
    </div>
</template>
