<template>
    <AdminLayout :stats="props.stats" :open-requests-count="props.openRequestsCount">
        <div class="page-head">
            <div>
                <h2>Quản lý bài viết</h2>
                <p>Đăng tin tức và bài viết chia sẻ kiến thức cho khách hàng.</p>
            </div>
            <div class="actions">
                <button class="btn primary" type="button" @click="createArticle">＋ Thêm bài viết</button>
                <input v-model="search" class="search" placeholder="Tìm bài viết...">
            </div>
        </div>

        <div v-if="filteredArticles.length" class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Bài viết</th>
                        <th>Trạng thái</th>
                        <th>Ngày đăng</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="article in paginatedArticles" :key="article.id">
                        <td>
                            <div class="item">
                                <div class="thumb">
                                    <img v-if="article.image_url" :src="article.image_url" :alt="article.title">
                                    <span v-else>{{ article.title.charAt(0).toLocaleUpperCase() }}</span>
                                </div>
                                <div>
                                    <b>{{ article.title }}</b>
                                    <small>{{ article.excerpt }}</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ article.is_published ? 'Đang hiển thị' : 'Bản nháp' }}</td>
                        <td>{{ formatDate(article.published_at) }}</td>
                        <td>
                            <div class="row-actions">
                                <button class="mini" type="button" @click="editArticle(article)">Sửa</button>
                                <button
                                    class="mini danger"
                                    type="button"
                                    :disabled="deletingId === article.id"
                                    @click="deleteArticle(article)"
                                >{{ deletingId === article.id ? 'Đang xóa...' : 'Xóa' }}</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="card">
            {{ search ? 'Không tìm thấy bài viết phù hợp.' : 'Chưa có bài viết nào. Hãy thêm bài viết đầu tiên.' }}
        </div>
        <AdminPagination :current-page="currentPage" :total-items="filteredArticles.length" @page-change="goToPage" />

        <ArticleModal
            v-model:open="isModalOpen"
            :article="selectedArticle"
            @saved="showToast($event === 'created' ? 'Đã tạo bài viết thành công.' : 'Đã cập nhật bài viết thành công.')"
        />
        <div class="toast" :class="{ show: isToastVisible }" role="status" aria-live="polite">{{ toastMessage }}</div>
    </AdminLayout>
</template>

<script setup lang="ts">
    import { router } from '@inertiajs/vue3';
    import { computed, onBeforeUnmount, ref, watch } from 'vue';
    import AdminLayout from '../../layout/AdminLayout.Vue';
    import AdminPagination from '../../components/AdminPagination.Vue';
    import ArticleModal from '../../components/ArticleModal.Vue';
    import { useClientPagination } from '../../composables/useClientPagination';

    type Article = {
        id: number;
        title: string;
        excerpt: string;
        content: string;
        is_published: boolean;
        published_at: string | null;
        image_url: string | null;
    };

    type AdminStat = {
        label: string;
        count: number;
        description: string;
    };

    const props = defineProps<{
        articles: Article[];
        stats: AdminStat[];
        openRequestsCount: number;
    }>();

    const search = ref('');
    const isModalOpen = ref(false);
    const selectedArticle = ref<Article | null>(null);
    const deletingId = ref<number | null>(null);
    const isToastVisible = ref(false);
    const toastMessage = ref('');
    let toastTimeout: ReturnType<typeof setTimeout> | undefined;

    const filteredArticles = computed(() => {
        const query = search.value.trim().toLocaleLowerCase();

        if (!query) {
            return props.articles;
        }

        return props.articles.filter((article) =>
            [article.title, article.excerpt].some((value) => value.toLocaleLowerCase().includes(query)),
        );
    });
    const { currentPage, goToPage, paginatedItems: paginatedArticles, resetPage } = useClientPagination(filteredArticles);

    watch(search, resetPage);

    function formatDate(value: string | null): string {
        return value ? new Date(value).toLocaleDateString('vi-VN') : '—';
    }

    function createArticle(): void {
        selectedArticle.value = null;
        isModalOpen.value = true;
    }

    function editArticle(article: Article): void {
        selectedArticle.value = article;
        isModalOpen.value = true;
    }

    function deleteArticle(article: Article): void {
        if (deletingId.value !== null || !window.confirm(`Bạn có chắc muốn xóa bài viết "${article.title}" không?`)) {
            return;
        }

        deletingId.value = article.id;
        router.delete(`/admin/articles/${article.id}`, {
            preserveScroll: true,
            onSuccess: () => showToast('Đã xóa bài viết thành công.'),
            onError: () => showToast('Không thể xóa bài viết. Vui lòng thử lại.'),
            onFinish: () => {
                deletingId.value = null;
            },
        });
    }

    function showToast(message: string): void {
        if (toastTimeout) {
            clearTimeout(toastTimeout);
        }

        toastMessage.value = message;
        isToastVisible.value = true;
        toastTimeout = setTimeout(() => {
            isToastVisible.value = false;
            toastTimeout = undefined;
        }, 2400);
    }

    onBeforeUnmount(() => {
        if (toastTimeout) {
            clearTimeout(toastTimeout);
        }
    });
</script>
