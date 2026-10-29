<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import UserLayout from '../../layout/UserLayout.Vue';
import { lockPageScroll } from '../../utils/pageScrollLock';

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

const PROJECTS_PER_PAGE = 2;
const activeProjectType = ref<string | null>(null);
const projectPage = ref(1);
const selectedProject = ref<FeaturedProject | null>(null);
const selectedImageIndex = ref(0);
let releasePageScrollLock: (() => void) | null = null;
const selectedProjectImages = computed(() => {
    if (!selectedProject.value) {
        return [];
    }

    return selectedProject.value.images_url.length
        ? selectedProject.value.images_url
        : [imageUrl(selectedProject.value)];
});

const projectGroups = computed(() => [
    {
        value: '1',
        title: 'Chung cư thương mại & NOXH',
        description: 'Giải pháp cấp nước, nước nóng và vật tư kỹ thuật cho các khu chung cư.',
    },
    {
        value: '2',
        title: 'Văn phòng, khách sạn & bệnh viện',
        description: 'Vật tư cơ điện, HVAC và thiết bị cho các công trình dịch vụ.',
    },
    {
        value: '3',
        title: 'Khu công nghiệp',
        description: 'Vật tư bảo ôn, đường ống và thiết bị cho nhà máy, khu công nghiệp.',
    },
].map((group) => ({
    ...group,
    projects: props.projects.filter((project) => String(project.type) === group.value),
})));

const visibleProjects = computed(() =>
    props.projects.filter((project) => String(project.type) === activeProjectType.value),
);

const projectPageCount = computed(() =>
    Math.max(1, Math.ceil(visibleProjects.value.length / PROJECTS_PER_PAGE)),
);

const paginatedProjects = computed(() =>
    visibleProjects.value.slice(
        (projectPage.value - 1) * PROJECTS_PER_PAGE,
        projectPage.value * PROJECTS_PER_PAGE,
    ),
);

const selectProjectGroup = (type: string | null): void => {
    activeProjectType.value = type;
    projectPage.value = 1;
};

const goToProjectPage = (page: number): void => {
    projectPage.value = Math.min(Math.max(page, 1), projectPageCount.value);
};

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

const projectSummary = (description: string): string => {
    const characters = Array.from(description);

    return characters.length > 245 ? `${characters.slice(0, 242).join('')}...` : description;
};

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
    if (project) {
        releasePageScrollLock ??= lockPageScroll();
        return;
    }

    releasePageScrollLock?.();
    releasePageScrollLock = null;
});

const onEscape = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        selectedProject.value = null;
    }
};

onMounted(() => {
    const requestedType = new URLSearchParams(window.location.search).get('type');

    if (requestedType && projectGroups.value.some((group) => group.value === requestedType)) {
        activeProjectType.value = requestedType;
    }

    window.addEventListener('keydown', onEscape);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onEscape);
    releasePageScrollLock?.();
    releasePageScrollLock = null;
});
</script>

<template>
    <Head title="HVAC Mart | Dự án tiêu biểu" />

    <UserLayout :cart-count="0" active-page="projects">
        <main>
            <section class="page-intro" aria-live="polite">
                <div class="wrap page-intro-inner">
                    <div>
                        <div class="eyebrow"><span class="dot"></span> HVAC MART / DỰ ÁN TIÊU BIỂU</div>
                        <h1>Dự án tiêu biểu</h1>
                        <p>Khám phá các hạng mục kỹ thuật và giải pháp vật tư trong những công trình tiêu biểu.</p>
                    </div>
                    <Link class="page-home-link" href="/"><FontAwesomeIcon :icon="['fas', 'arrow-left']" /> Về trang chủ</Link>
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

                            <div v-if="activeProjectType === null" class="project-directory-grid">
                                <button
                                    v-for="(group, index) in projectGroups"
                                    :key="group.value"
                                    class="project-group-card"
                                    type="button"
                                    @click="selectProjectGroup(group.value)"
                                >
                                    <div class="project-group-art">
                                        <img
                                            v-if="group.projects[0]"
                                            class="project-group-image"
                                            :src="imageUrl(group.projects[0])"
                                            :alt="group.projects[0].name"
                                            loading="lazy"
                                        >
                                        <span class="project-image-tag">DỰ ÁN TIÊU BIỂU</span>
                                    </div>
                                    <div class="project-group-copy">
                                        <span class="project-detail-type">NHÓM DỰ ÁN · 0{{ index + 1 }}</span>
                                        <h3>{{ group.title }}</h3>
                                        <p>{{ group.description }}</p>
                                        <div class="project-group-footer">
                                            <span>{{ group.projects.length }} dự án</span>
                                            <span class="circle-arrow" aria-hidden="true"><FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" /></span>
                                        </div>
                                    </div>
                                </button>
                            </div>
                            <div v-else class="project-directory-details">
                                <div class="project-detail-back">
                                    <button class="text-back" type="button" @click="selectProjectGroup(null)">
                                        <FontAwesomeIcon :icon="['fas', 'arrow-left']" /> Tất cả nhóm dự án
                                    </button>
                                    <span>
                                        {{ projectGroups.find((group) => group.value === activeProjectType)?.title }}
                                        · {{ visibleProjects.length }} hồ sơ dự án
                                    </span>
                                </div>
                                <div v-if="paginatedProjects.length" class="project-detail-grid">
                                    <article
                                        v-for="project in paginatedProjects"
                                        :key="project.id"
                                        class="project-detail-card"
                                    >
                                        <div class="project-detail-art">
                                            <img
                                                class="project-detail-image"
                                                :src="imageUrl(project)"
                                                :alt="project.name"
                                                loading="lazy"
                                            >
                                            <span class="project-image-tag">{{ projectTypeLabel(project.type) }}</span>
                                        </div>
                                        <div class="project-detail-copy">
                                            <span class="project-detail-type">CASE STUDY · {{ projectTypeLabel(project.type) }}</span>
                                            <h3>{{ project.name }}</h3>
                                            <p>{{ projectSummary(project.description) }}</p>
                                            <div class="project-detail-meta">
                                                <div><span>Vị trí</span><strong>{{ project.location }}</strong></div>
                                                <div><span>Hạng mục tham chiếu</span><strong>{{ project.category }} · {{ project.subcategory }}</strong></div>
                                            </div>
                                            <div class="project-detail-note">
                                                Thông tin dự án được cập nhật theo dữ liệu hiện có. Vui lòng liên hệ để xác nhận phạm vi cung ứng.
                                            </div>
                                            <div class="project-card-actions">
                                                <button class="project-view-detail" type="button" @click="openProject(project)">
                                                    Xem chi tiết dự án <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" />
                                                </button>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                                <div v-else class="empty-state">
                                    Chưa có dự án thuộc nhóm công trình này.
                                </div>
                                <nav v-if="projectPageCount > 1" class="pagination" aria-label="Phân trang dự án">
                                    <button
                                        class="page-btn"
                                        type="button"
                                        aria-label="Trang trước"
                                        :disabled="projectPage === 1"
                                        @click="goToProjectPage(projectPage - 1)"
                                    ><FontAwesomeIcon :icon="['fas', 'arrow-left']" /></button>
                                    <button
                                        v-for="page in projectPageCount"
                                        :key="page"
                                        class="page-btn"
                                        :class="{ active: projectPage === page }"
                                        type="button"
                                        :aria-current="projectPage === page ? 'page' : undefined"
                                        @click="goToProjectPage(page)"
                                    >{{ page }}</button>
                                    <span class="page-status">Trang {{ projectPage }} / {{ projectPageCount }}</span>
                                    <button
                                        class="page-btn"
                                        type="button"
                                        aria-label="Trang sau"
                                        :disabled="projectPage === projectPageCount"
                                        @click="goToProjectPage(projectPage + 1)"
                                    ><FontAwesomeIcon :icon="['fas', 'arrow-right']" /></button>
                                </nav>
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
                <button class="close-btn modal-close" type="button" aria-label="Đóng" @click="selectedProject = null"><FontAwesomeIcon :icon="['fas', 'xmark']" /></button>
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
                                <button class="gallery-step" type="button" aria-label="Ảnh trước" @click="stepProjectImage(-1)"><FontAwesomeIcon :icon="['fas', 'arrow-left']" /></button>
                                <span>{{ String(selectedImageIndex + 1).padStart(2, '0') }} / {{ String(selectedProjectImages.length).padStart(2, '0') }}</span>
                                <button class="gallery-step" type="button" aria-label="Ảnh tiếp theo" @click="stepProjectImage(1)"><FontAwesomeIcon :icon="['fas', 'arrow-right']" /></button>
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
                            Trao đổi về hạng mục này <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" />
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    </UserLayout>
</template>