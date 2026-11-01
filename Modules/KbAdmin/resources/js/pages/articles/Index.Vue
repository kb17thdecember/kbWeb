<template>
    <AdminLayout :stats="props.stats" :open-requests-count="props.openRequestsCount">
        <div class="page-head">
            <div>
                <h2>Quản lý bài viết</h2>
                <p>Đăng tin tức và bài viết chia sẻ kiến thức cho khách hàng.</p>
            </div>
            <div class="actions">
                <button class="btn primary" type="button" @click="createArticle">＋ Thêm bài viết</button>
                <input v-model="search" class="search" type="search" placeholder="Tìm bài viết..." aria-label="Tìm bài viết">
            </div>
        </div>

        <div class="filters">
            <div class="filter-group">
                <span>TỪ NGÀY</span>
                <AdminDatePicker v-model="dateFrom" placeholder="Chọn từ ngày..." aria-label="Lọc bài viết từ ngày" />
            </div>
            <div class="filter-group">
                <span>ĐẾN NGÀY</span>
                <AdminDatePicker v-model="dateTo" placeholder="Chọn đến ngày..." aria-label="Lọc bài viết đến ngày" />
            </div>
            <button
                v-if="dateFrom || dateTo"
                class="btn"
                type="button"
                @click="clearDates"
            >Xóa lọc ngày</button>
            <em>{{ filteredArticles.length }} / {{ articles.length }} bài viết</em>
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
                        <td class="request-date">{{ formatDate(article.published_at || article.created_at) }}</td>
                        <td>
                            <div class="row-actions">
                                <button
                                    class="request-eye"
                                    type="button"
                                    :aria-label="`Xem chi tiết bài viết ${article.title}`"
                                    title="Xem chi tiết"
                                    @click="openDetail(article)"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
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
        <div v-else class="empty">
            {{ articles.length ? 'Không tìm thấy bài viết phù hợp với điều kiện lọc.' : 'Chưa có bài viết nào. Hãy thêm bài viết đầu tiên.' }}
        </div>
        <AdminPagination :current-page="currentPage" :total-items="filteredArticles.length" @page-change="goToPage" />

        <div v-if="detailArticle" class="modal open" role="presentation" @click.self="closeDetail">
            <section
                class="dialog request-detail-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="articleDetailTitle"
            >
                <button class="close" type="button" aria-label="Đóng chi tiết" @click="closeDetail">
                    <FontAwesomeIcon :icon="['fas', 'xmark']" />
                </button>
                <div class="dialog-head">
                    <div class="eyebrow">ARTICLE / DETAILS</div>
                    <div class="request-detail-head">
                        <span class="request-detail-avatar">{{ detailArticle.title.charAt(0).toLocaleUpperCase() }}</span>
                        <div>
                            <h2 id="articleDetailTitle">{{ detailArticle.title }}</h2>
                            <p>Mã bài viết: #{{ detailArticle.id }}</p>
                        </div>
                    </div>
                </div>
                <div class="request-detail-grid">
                    <div class="request-detail-item">
                        <small>Trạng thái</small>
                        <div>{{ detailArticle.is_published ? 'Đang hiển thị' : 'Bản nháp' }}</div>
                    </div>
                    <div class="request-detail-item">
                        <small>Ngày đăng</small>
                        <div>{{ formatDate(detailArticle.published_at || detailArticle.created_at) }}</div>
                    </div>
                    <div class="request-detail-item full">
                        <small>Tóm tắt</small>
                        <div>{{ detailArticle.excerpt }}</div>
                    </div>
                </div>
                <div class="request-detail-actions">
                    <button class="btn" type="button" @click="editFromDetail(detailArticle)">Chỉnh sửa</button>
                    <button class="btn primary" type="button" @click="closeDetail">Đóng</button>
                </div>
            </section>
        </div>

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
    import AdminDatePicker from '../../components/AdminDatePicker.Vue';
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
        created_at?: string | null;
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
    const dateFrom = ref('');
    const dateTo = ref('');
    const isModalOpen = ref(false);
    const selectedArticle = ref<Article | null>(null);
    const detailArticle = ref<Article | null>(null);
    const deletingId = ref<number | null>(null);
    const isToastVisible = ref(false);
    const toastMessage = ref('');
    let toastTimeout: ReturnType<typeof setTimeout> | undefined;

    const filteredArticles = computed(() => {
        const query = search.value.trim().toLocaleLowerCase();

        return props.articles.filter((article) => {
            const matchesQuery = !query || [article.title, article.excerpt].some((value) =>
                value.toLocaleLowerCase().includes(query),
            );
            const articleDate = dateKey(article.published_at || article.created_at);

            return (
                matchesQuery
                && (!dateFrom.value || (articleDate && articleDate >= dateFrom.value))
                && (!dateTo.value || (articleDate && articleDate <= dateTo.value))
            );
        });
    });
    const { currentPage, goToPage, paginatedItems: paginatedArticles, resetPage } = useClientPagination(filteredArticles);

    watch([search, dateFrom, dateTo], resetPage);

    function dateKey(value?: string | null): string {
        if (!value) {
            return '';
        }

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return '';
        }

        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    }

    function formatDate(value?: string | null): string {
        return value ? new Date(value).toLocaleDateString('vi-VN') : '—';
    }

    function clearDates(): void {
        dateFrom.value = '';
        dateTo.value = '';
    }

    function openDetail(article: Article): void {
        detailArticle.value = article;
    }

    function closeDetail(): void {
        detailArticle.value = null;
    }

    function editFromDetail(article: Article): void {
        closeDetail();
        editArticle(article);
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
            onSuccess: () => {
                if (detailArticle.value?.id === article.id) {
                    closeDetail();
                }
                showToast('Đã xóa bài viết thành công.');
            },
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
