<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import UserLayout from '../../layout/UserLayout.Vue';

type NewsArticle = {
    id: number;
    title: string;
    slug: string;
    excerpt: string;
    published_at: string | null;
    image_url: string | null;
};

type NewsCatalogue = {
    id: number;
    title: string;
    description: string | null;
    file_name: string;
    file_size: number;
    file_url: string;
    image_url: string | null;
};

const props = defineProps<{
    articles: NewsArticle[];
    catalogues: NewsCatalogue[];
}>();

const initialTab = typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('tab') === 'catalogue'
    ? 'catalogue'
    : 'articles';
const activeTab = ref<'articles' | 'catalogue'>(initialTab);

const tabs = computed(() => [
    { value: 'articles' as const, label: 'Bài viết', count: props.articles.length },
    { value: 'catalogue' as const, label: 'Catalogue', count: props.catalogues.length },
]);

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString('vi-VN') : '';
}

function formatSize(bytes: number): string {
    return bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}
</script>

<template>
    <div>
        <Head title="HVAC Mart | Tin tức & Catalogue" />

        <UserLayout :cart-count="0" active-page="news">
            <main>
                <section class="page-intro">
                    <div class="wrap page-intro-inner">
                        <div>
                            <div class="eyebrow"><span class="dot"></span> HVAC MART / TIN TỨC</div>
                            <h1>Tin tức &amp; Catalogue</h1>
                            <p>Bài viết chia sẻ kiến thức kỹ thuật và catalogue sản phẩm Bình nước nóng Ariston và Bảo ôn K-Flex.</p>
                        </div>
                        <Link class="page-home-link" href="/"><FontAwesomeIcon :icon="['fas', 'arrow-left']" /> Về trang chủ</Link>
                    </div>
                </section>

                <section class="section">
                    <div class="wrap">
                        <div class="news-tabs" role="tablist">
                            <button
                                v-for="tab in tabs"
                                :key="tab.value"
                                type="button"
                                role="tab"
                                class="news-tab"
                                :class="{ active: activeTab === tab.value }"
                                :aria-selected="activeTab === tab.value"
                                @click="activeTab = tab.value"
                            >{{ tab.label }} <span>{{ tab.count }}</span></button>
                        </div>

                        <div v-if="activeTab === 'articles'">
                            <div v-if="articles.length" class="news-grid">
                                <Link v-for="article in articles" :key="article.id" :href="`/news/${article.slug}`" class="news-card">
                                    <div class="news-thumb">
                                        <img v-if="article.image_url" :src="article.image_url" :alt="article.title" loading="lazy">
                                        <FontAwesomeIcon v-else :icon="['fas', 'newspaper']" />
                                    </div>
                                    <div class="news-body">
                                        <time v-if="article.published_at">{{ formatDate(article.published_at) }}</time>
                                        <h3>{{ article.title }}</h3>
                                        <p>{{ article.excerpt }}</p>
                                        <span class="news-more">Đọc tiếp →</span>
                                    </div>
                                </Link>
                            </div>
                            <p v-else class="news-empty">Chưa có bài viết nào.</p>
                        </div>

                        <div v-else>
                            <div v-if="catalogues.length" class="catalogue-grid">
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
                                            <a class="btn btn-outline" :href="catalogue.file_url" target="_blank" rel="noopener">Xem tài liệu</a>
                                            <a class="btn btn-primary" :href="catalogue.file_url" :download="catalogue.file_name">Tải PDF</a>
                                        </div>
                                    </div>
                                </article>
                            </div>
                            <p v-else class="news-empty">Chưa có catalogue nào.</p>
                        </div>
                    </div>
                </section>
            </main>
        </UserLayout>
    </div>
</template>
