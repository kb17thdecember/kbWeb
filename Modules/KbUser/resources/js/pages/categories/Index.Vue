<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import UserLayout from '../../layout/UserLayout.Vue';

type CategoryItem = {
    id: number;
    name: string;
    parent_id: number | null;
    description: string | null;
    image_url: string | null;
    children_count: number;
    children: {
        id: number;
        name: string;
        parent_id: number;
        description: string | null;
        image_url: string | null;
        products_count: number;
    }[];
};

const props = defineProps<{
    categories: CategoryItem[];
    selectedCategoryId: number | null;
}>();

const selectedCategory = computed(() =>
    props.categories.find((category) => category.id === props.selectedCategoryId) ?? null,
);

const categoryPageUrl = (categoryId: number): string =>
    `/categories?category=${encodeURIComponent(String(categoryId))}`;

const categoryProductsUrl = (categoryId: number, subcategoryId?: number): string => {
    const params = new URLSearchParams({ category: String(categoryId) });

    if (subcategoryId !== undefined) {
        params.set('subcategory', String(subcategoryId));
    }

    return `/products?${params.toString()}#products`;
};
</script>

<template>
    <Head :title=" selectedCategory ? `HVAC Mart | ${selectedCategory.name}` : 'HVAC Mart | Danh mục sản phẩm'" />

    <UserLayout :cart-count="0" active-page="categories">
        <main>
            <section class="page-intro" id="pageIntro" aria-live="polite">
                <div class="wrap page-intro-inner">
                    <div>
                        <div class="eyebrow">
                            <span class="dot"></span>
                            <span id="pageKicker">HVAC MART / DANH MỤC</span>
                        </div>
                        <h1 id="pageTitle">
                            {{ selectedCategory ? `Danh mục ${selectedCategory.name}` : 'Danh mục sản phẩm' }}
                        </h1>
                        <p id="pageDescription">
                            {{
                                selectedCategory?.description
                                    || 'Khám phá các nhóm thiết bị và vật tư kỹ thuật, cùng những dòng sản phẩm phù hợp cho nhu cầu của công trình.'
                            }}
                        </p>
                    </div>
                    <Link
                        v-if="selectedCategory"
                        class="page-home-link"
                        href="/categories"
                    ><FontAwesomeIcon :icon="['fas', 'arrow-left']" /> Tất cả danh mục</Link>
                    <Link v-else class="page-home-link" href="/"><FontAwesomeIcon :icon="['fas', 'arrow-left']" /> Về trang chủ</Link>
                </div>
            </section>

            <section class="section">
                <div class="wrap">
                    <div class="section-head">
                        <div>
                            <div class="eyebrow"><span class="dot"></span> DANH MỤC SẢN PHẨM</div>
                            <h2 class="section-title">
                                {{ selectedCategory ? 'Chọn đúng dòng.' : 'Mọi hạng mục.' }}
                                <br>
                                <em>{{ selectedCategory ? selectedCategory.name : 'Một điểm đến.' }}</em>
                            </h2>
                        </div>
                        <p class="section-desc">
                            {{
                                selectedCategory
                                    ? 'Chọn dòng sản phẩm để xem các mặt hàng phù hợp.'
                                    : 'Chọn danh mục chính để xem những dòng sản phẩm và mặt hàng hiện có.'
                            }}
                        </p>
                    </div>

                    <div v-if="!selectedCategory && categories.length > 0" class="category-grid">
                        <Link
                            v-for="(category, index) in categories"
                            :key="category.id"
                            class="category-card"
                            :href="categoryPageUrl(category.id)"
                        >
                            <span class="cat-number">
                                {{ String(index + 1).padStart(2, '0') }} /
                                {{ category.children_count }} DÒNG SẢN PHẨM
                            </span>
                            <div class="cat-art">
                                <img
                                    v-if="category.image_url"
                                    :src="category.image_url"
                                    :alt="category.name"
                                    loading="lazy"
                                >
                                <span v-else aria-hidden="true">⌂</span>
                            </div>
                            <strong class="cat-title">{{ category.name }}</strong>
                            <span class="cat-bottom">
                                <span>Chọn dòng sản phẩm</span>
                                <span class="circle-arrow" aria-hidden="true"><FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" /></span>
                            </span>
                        </Link>
                    </div>

                    <div v-else-if="!selectedCategory && categories.length === 0" class="empty-state">
                        Chưa có danh mục sản phẩm nào được cập nhật.
                    </div>

                    <section
                        v-if="selectedCategory"
                        class="category-children-section"
                        :aria-labelledby="`category-${selectedCategory.id}`"
                    >
                        <div class="subcategory-page-head">
                            <div>
                                <div class="eyebrow">
                                    <span class="dot"></span>
                                    DANH MỤC / {{ selectedCategory.name.toUpperCase() }}
                                </div>
                                <h3 :id="`category-${selectedCategory.id}`">
                                    {{ selectedCategory.name }} — chọn dòng sản phẩm
                                </h3>
                                <p>
                                    {{
                                        selectedCategory.description
                                            || 'Chọn dòng sản phẩm để xem các mặt hàng phù hợp.'
                                    }}
                                </p>
                            </div>
                            <Link class="text-back" href="/categories"><FontAwesomeIcon :icon="['fas', 'arrow-left']" /> Tất cả danh mục</Link>
                        </div>

                        <div v-if="selectedCategory.children.length > 0" class="subcategory-grid">
                            <Link
                                v-for="(subcategory, index) in selectedCategory.children"
                                :key="subcategory.id"
                                class="subcategory-card"
                                :href="categoryProductsUrl(selectedCategory.id, subcategory.id)"
                            >
                                <span class="subcat-number">
                                    {{ String(index + 1).padStart(2, '0') }} /
                                    {{ selectedCategory.name.toUpperCase() }}
                                </span>
                                <img
                                    v-if="subcategory.image_url"
                                    class="category-child-image"
                                    :src="subcategory.image_url"
                                    :alt="subcategory.name"
                                    loading="lazy"
                                >
                                <strong>{{ subcategory.name }}</strong>
                                <p>
                                    {{
                                        subcategory.description
                                            || 'Khám phá sản phẩm thuộc dòng này.'
                                    }}
                                </p>
                                <span class="subcat-card-foot">
                                    <span>{{ subcategory.products_count }} sản phẩm</span>
                                    <b aria-hidden="true"><FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" /></b>
                                </span>
                            </Link>
                        </div>
                        <p v-else class="empty-state">
                            Danh mục này chưa có dòng sản phẩm.
                        </p>
                    </section>
                </div>
            </section>
        </main>
    </UserLayout>
</template>

<style scoped>
.category-children-section {
    margin-top: 64px;
}

.cat-art > span {
    color: var(--muted);
    font-size: 70px;
    line-height: 1;
}

.category-card .cat-bottom {
    margin-top: 10px;
}

.category-child-image {
    width: 54px;
    height: 54px;
    align-self: flex-end;
    object-fit: contain;
}

.subcategory-card > strong {
    white-space: normal;
}

@media (max-width: 760px) {
    .category-children-section {
        margin-top: 44px;
    }
}
</style>