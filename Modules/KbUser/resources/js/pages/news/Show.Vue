<script setup lang="ts">
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

defineProps<{
    article: NewsArticle & { content: string };
    related: NewsArticle[];
}>();

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString('vi-VN') : '';
}
</script>

<template>
    <Head>
        <title>{{ article.title }}</title>
        <meta name="description" :content="article.excerpt" />
        <meta property="og:title" :content="article.title" />
        <meta property="og:description" :content="article.excerpt" />
        <meta v-if="article.image_url" property="og:image" :content="article.image_url" />
    </Head>

    <UserLayout :cart-count="0" active-page="news">
        <main>
            <article class="section">
                <div class="wrap news-article">
                    <Link class="page-home-link" href="/news"><FontAwesomeIcon :icon="['fas', 'arrow-left']" /> Tất cả tin tức</Link>
                    <time v-if="article.published_at">{{ formatDate(article.published_at) }}</time>
                    <h1>{{ article.title }}</h1>
                    <p class="news-lead">{{ article.excerpt }}</p>
                    <img v-if="article.image_url" class="news-cover" :src="article.image_url" :alt="article.title">
                    <div class="news-content" v-html="article.content"></div>
                </div>
            </article>

            <section v-if="related.length" class="section">
                <div class="wrap">
                    <h2 class="section-title">Bài viết khác</h2>
                    <div class="news-grid">
                        <Link v-for="item in related" :key="item.id" :href="`/news/${item.slug}`" class="news-card">
                            <div class="news-thumb">
                                <img v-if="item.image_url" :src="item.image_url" :alt="item.title" loading="lazy">
                                <FontAwesomeIcon v-else :icon="['fas', 'newspaper']" />
                            </div>
                            <div class="news-body">
                                <time v-if="item.published_at">{{ formatDate(item.published_at) }}</time>
                                <h3>{{ item.title }}</h3>
                                <p>{{ item.excerpt }}</p>
                            </div>
                        </Link>
                    </div>
                </div>
            </section>
        </main>
    </UserLayout>
</template>
