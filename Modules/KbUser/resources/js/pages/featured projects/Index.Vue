<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import UserLayout from '../../layout/UserLayout.Vue';

type FeaturedProject = {
    id: number;
    name: string;
    description: string;
    location: string;
    category: string;
    subcategory: string;
    type: number | string;
    image: string;
    image_url: string | null;
    images: string[] | null;
    images_url: string[];
};

const props = defineProps<{
    projects: FeaturedProject[];
}>();

const activeFilter = ref('all');
const selectedProject = ref<FeaturedProject | null>(null);
const selectedImageIndex = ref(0);
const selectedProjectImages = computed(() => {
    if (!selectedProject.value) {
        return [];
    }

    return selectedProject.value.images_url.length
        ? selectedProject.value.images_url
        : [imageUrl(selectedProject.value)];
});

const filters = [
    { label: 'Tất cả dự án', value: 'all' },
    { label: 'Chung cư & nhà ở', value: '1' },
    { label: 'Văn phòng, khách sạn & bệnh viện', value: '2' },
    { label: 'Khu công nghiệp', value: '3' },
];

const visibleProjects = computed(() =>
    props.projects.filter(
        (project) => activeFilter.value === 'all' || String(project.type) === activeFilter.value,
    ),
);

const imageUrl = (project: FeaturedProject): string =>
    project.image_url
    || (project.image.startsWith('http') || project.image.startsWith('/')
        ? project.image
        : `/storage/${project.image}`);

const projectTypeLabel = (type: number | string): string =>
    ({
        '1': 'Chung cư thương mại & NOXH',
        '2': 'Văn phòng, khách sạn & bệnh viện',
        '3': 'Khu công nghiệp',
    })[String(type)] ?? 'Dự án tiêu biểu';

const openProject = (project: FeaturedProject): void => {
    selectedProject.value = project;
    selectedImageIndex.value = 0;
};

function stepProjectImage(delta: number): void {
    const imageCount = selectedProjectImages.value.length;
    if (imageCount) {
        selectedImageIndex.value = (selectedImageIndex.value + delta + imageCount) % imageCount;
    }
}

watch(selectedProject, (project) => {
    document.body.style.overflow = project ? 'hidden' : '';
});

const onEscape = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        selectedProject.value = null;
    }
};

onMounted(() => {
    const requestedType = new URLSearchParams(window.location.search).get('type');

    if (requestedType && filters.some((filter) => filter.value === requestedType)) {
        activeFilter.value = requestedType;
    }

    window.addEventListener('keydown', onEscape);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onEscape);
    document.body.style.overflow = '';
});
</script>

<template>
    <Head title="Dự án tiêu biểu | KB TECH" />

    <UserLayout :cart-count="0" active-page="projects">
        <main>
            <section class="page-intro" aria-live="polite">
                <div class="wrap page-intro-inner">
                    <div>
                        <div class="eyebrow"><span class="dot"></span> KB TECH / FEATURED PROJECTS</div>
                        <h1>Dự án tiêu biểu</h1>
                        <p>Khám phá các hạng mục kỹ thuật và giải pháp vật tư trong những công trình tiêu biểu.</p>
                    </div>
                    <Link class="page-home-link" href="/">← Về trang chủ</Link>
                </div>
            </section>

            <section class="section">
                <div class="wrap">
                    <div class="project-section">
                        <div class="wrap">
                            <div class="section-head">
                                <div>
                                    <div class="eyebrow"><span class="dot"></span> DẤU ẤN CÔNG TRÌNH</div>
                                    <h2 class="section-title">Giải pháp cho<br><em>những không gian lớn.</em></h2>
                                </div>
                                <p class="section-desc">
                                    Các dự án được phân theo nhóm công trình, kèm thông tin về địa điểm
                                    và hạng mục cung ứng.
                                </p>
                            </div>

                            <div class="product-toolbar">
                                <div class="filters" aria-label="Lọc theo loại dự án">
                                    <button
                                        v-for="filter in filters"
                                        :key="filter.value"
                                        class="filter-btn"
                                        :class="{ active: activeFilter === filter.value }"
                                        type="button"
                                        @click="activeFilter = filter.value"
                                    >
                                        {{ filter.label }}
                                    </button>
                                </div>
                            </div>

                            <div v-if="visibleProjects.length" class="project-grid">
                                <article
                                    v-for="project in visibleProjects"
                                    :key="project.id"
                                    class="project-card"
                                    role="button"
                                    tabindex="0"
                                    @click="openProject(project)"
                                    @keydown.enter="openProject(project)"
                                    @keydown.space.prevent="openProject(project)"
                                >
                                    <img class="project-image" :src="imageUrl(project)" alt="" loading="lazy">
                                    <div class="project-info">
                                        <span class="project-type">
                                            {{ projectTypeLabel(project.type) }} · {{ project.category }}
                                        </span>
                                        <h3>{{ project.name }}</h3>
                                        <p>{{ project.description }}</p>
                                        <div class="project-foot">
                                            <span>{{ project.subcategory }}</span>
                                            <span class="circle-arrow" aria-hidden="true">↗</span>
                                        </div>
                                    </div>
                                </article>
                            </div>
                            <div v-else class="empty-state">
                                {{
                                    projects.length === 0
                                        ? 'Chưa có dự án tiêu biểu nào được cập nhật.'
                                        : 'Chưa có dự án thuộc nhóm công trình này.'
                                }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <div
            v-if="selectedProject"
            class="modal-wrap open"
            role="presentation"
            @click.self="selectedProject = null"
        >
            <section class="modal project-modal-detail" role="dialog" aria-modal="true" :aria-label="`Chi tiết dự án ${selectedProject.name}`">
                <button class="close-btn modal-close" type="button" aria-label="Đóng" @click="selectedProject = null">×</button>
                <div class="project-modal-layout">
                    <div class="project-modal-art project-modal-gallery">
                        <div class="project-gallery-shell">
                            <div class="project-gallery-stage">
                                <img
                                    class="gallery-photo"
                                    :src="selectedProjectImages[selectedImageIndex]"
                                    :alt="`${selectedProject.name} · ảnh ${selectedImageIndex + 1}`"
                                >
                            </div>
                            <div v-if="selectedProjectImages.length > 1" class="gallery-control-row project-gallery-control">
                                <button class="gallery-step" type="button" aria-label="Ảnh trước" @click="stepProjectImage(-1)">←</button>
                                <span>{{ String(selectedImageIndex + 1).padStart(2, '0') }} / {{ String(selectedProjectImages.length).padStart(2, '0') }}</span>
                                <button class="gallery-step" type="button" aria-label="Ảnh tiếp theo" @click="stepProjectImage(1)">→</button>
                            </div>
                            <div v-if="selectedProjectImages.length > 1" class="gallery-thumbs project-gallery-thumbs">
                                <button
                                    v-for="(image, index) in selectedProjectImages"
                                    :key="image"
                                    class="gallery-thumb"
                                    :class="{ active: selectedImageIndex === index }"
                                    type="button"
                                    :aria-label="`Hiển thị ảnh ${index + 1}`"
                                    :aria-pressed="selectedImageIndex === index"
                                    @click="selectedImageIndex = index"
                                >
                                    <img class="gallery-photo" :src="image" :alt="`${selectedProject.name} · ảnh ${index + 1}`">
                                    <span>Ảnh {{ index + 1 }}</span>
                                </button>
                            </div>
                        </div>
                        <span class="project-image-tag">HÌNH ẢNH DỰ ÁN</span>
                    </div>
                    <div class="project-modal-copy">
                        <div class="project-detail-type">CHI TIẾT DỰ ÁN</div>
                        <h2>{{ selectedProject.name }}</h2>
                        <p>{{ selectedProject.description }}</p>
                        <div class="project-modal-meta">
                            <div><span>Nhóm dự án</span><strong>{{ projectTypeLabel(selectedProject.type) }}</strong></div>
                            <div><span>Vị trí</span><strong>{{ selectedProject.location }}</strong></div>
                            <div><span>Danh mục</span><strong>{{ selectedProject.category }}</strong></div>
                            <div><span>Hạng mục</span><strong>{{ selectedProject.subcategory }}</strong></div>
                        </div>
                        <p class="project-detail-note">
                            Thông tin địa điểm và hạng mục được cung cấp theo dữ liệu dự án. Vui lòng liên hệ để xác nhận chi tiết.
                        </p>
                        <Link class="btn btn-primary" href="/contacts" @click="selectedProject = null">
                            Trao đổi về hạng mục này ↗
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    </UserLayout>
</template>