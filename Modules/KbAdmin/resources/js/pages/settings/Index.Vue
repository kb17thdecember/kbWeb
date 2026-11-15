<template>
    <AdminLayout
        :stats="props.stats"
        :open-requests-count="props.openRequestsCount"
        :chat-unread-count="props.chatUnreadCount"
        :support-projects-count="props.supportProjectsCount"
    >
        <div class="page-head">
            <div>
                <h2>Cài đặt hệ thống chung</h2>
                <p>Quản lý thông tin thương hiệu, hotline, email, địa chỉ và liên kết mạng xã hội hiển thị trên toàn website.</p>
            </div>
            <div class="actions">
                <button class="btn" type="button" :disabled="form.processing" @click="restoreDefaults">
                    <FontAwesomeIcon icon="fa-solid fa-rotate-left" /> Khôi phục mặc định
                </button>
                <button class="btn primary" type="button" :disabled="form.processing" @click="submitSettings">
                    <FontAwesomeIcon :icon="form.processing ? 'fa-solid fa-spinner' : 'fa-solid fa-floppy-disk'" />
                    {{ form.processing ? 'Đang lưu...' : 'Lưu cài đặt' }}
                </button>
            </div>
        </div>

        <form class="site-settings-grid" @submit.prevent="submitSettings">
            <section class="site-settings-card">
                <div class="site-settings-card-head">
                    <span class="site-settings-card-icon">
                        <FontAwesomeIcon icon="fa-solid fa-building" />
                    </span>
                    <div>
                        <h3>Nhận diện thương hiệu &amp; Thông điệp</h3>
                        <p>Hiển thị trên thanh tiêu đề, đầu trang (announcement bar) và chân trang (footer).</p>
                    </div>
                </div>

                <div class="formgrid">
                    <div class="field full">
                        <label for="settingSiteLogoUrl">LOGO TRANG WEB (ẢNH TẢI LÊN HOẶC ĐƯỜNG DẪN URL)</label>
                        <div class="site-settings-logo-picker">
                            <div class="site-settings-logo-preview">
                                <img
                                    v-if="previewLogoUrl"
                                    :src="previewLogoUrl"
                                    :alt="form.company_name || 'Logo'"
                                    class="site-settings-logo-img"
                                >
                                <span v-else class="mark">HM</span>
                            </div>
                            <div class="site-settings-logo-controls">
                                <div class="site-settings-logo-actions">
                                    <label class="btn site-settings-upload-btn">
                                        <FontAwesomeIcon icon="fa-solid fa-upload" />
                                        Chọn ảnh Logo từ máy tính
                                        <input
                                            ref="logoFileInputRef"
                                            type="file"
                                            accept="image/*"
                                            hidden
                                            @change="onSelectLogoFile"
                                        >
                                    </label>
                                    <button
                                        v-if="previewLogoUrl"
                                        type="button"
                                        class="btn"
                                        @click="clearSiteLogo"
                                    >
                                        <FontAwesomeIcon icon="fa-solid fa-trash-can" />
                                        Xóa logo (Dùng mặc định HM)
                                    </button>
                                </div>
                                <input
                                    id="settingSiteLogoUrl"
                                    v-model="form.site_logo"
                                    type="text"
                                    maxlength="500"
                                    placeholder="Hoặc dán đường dẫn ảnh logo (VD: /storage/settings/logo.png hoặc https://...)"
                                    @input="onManualLogoUrlInput"
                                >
                                <small class="site-settings-logo-hint">
                                    Hỗ trợ PNG, SVG, WEBP, JPG (tối đa 5MB). Để trống sẽ hiển thị biểu tượng chữ "HM" mặc định.
                                </small>
                                <small v-if="form.errors.site_logo || form.errors.site_logo_file" class="field-error">
                                    {{ form.errors.site_logo_file || form.errors.site_logo }}
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="field">
                        <label for="settingCompanyName">TÊN THƯƠNG HIỆU *</label>
                        <input
                            id="settingCompanyName"
                            v-model="form.company_name"
                            type="text"
                            maxlength="120"
                            placeholder="HVAC Mart"
                            required
                        >
                        <small v-if="form.errors.company_name" class="field-error">{{ form.errors.company_name }}</small>
                    </div>

                    <div class="field">
                        <label for="settingCompanyTagline">KHẨU HIỆU PHỤ (TAGLINE) *</label>
                        <input
                            id="settingCompanyTagline"
                            v-model="form.company_tagline"
                            type="text"
                            maxlength="160"
                            placeholder="HVAC & ENGINEERING SUPPLY"
                            required
                        >
                        <small v-if="form.errors.company_tagline" class="field-error">{{ form.errors.company_tagline }}</small>
                    </div>

                    <div class="field full">
                        <label for="settingAnnouncement">THANH THÔNG BÁO ĐẦU TRANG (ANNOUNCEMENT BAR) *</label>
                        <input
                            id="settingAnnouncement"
                            v-model="form.announcement_text"
                            type="text"
                            maxlength="255"
                            placeholder="Giải pháp vật tư & thiết bị · Tư vấn đúng nhu cầu, đồng hành đến hoàn thiện"
                            required
                        >
                        <small v-if="form.errors.announcement_text" class="field-error">{{ form.errors.announcement_text }}</small>
                    </div>

                    <div class="field full">
                        <label for="settingFooterDesc">MÔ TẢ DOANH NGHIỆP Ở CHÂN TRANG *</label>
                        <textarea
                            id="settingFooterDesc"
                            v-model="form.footer_description"
                            rows="3"
                            maxlength="500"
                            placeholder="Đầu mối cung ứng thiết bị và vật tư kỹ thuật..."
                            required
                        ></textarea>
                        <small v-if="form.errors.footer_description" class="field-error">{{ form.errors.footer_description }}</small>
                    </div>

                    <div class="field full">
                        <label for="settingCopyright">DÒNG BẢN QUYỀN (COPYRIGHT) *</label>
                        <input
                            id="settingCopyright"
                            v-model="form.copyright_text"
                            type="text"
                            maxlength="255"
                            placeholder="© 2026 HVAC Mart. Bảo lưu mọi quyền."
                            required
                        >
                        <small v-if="form.errors.copyright_text" class="field-error">{{ form.errors.copyright_text }}</small>
                    </div>
                </div>
            </section>

            <section class="site-settings-card">
                <div class="site-settings-card-head">
                    <span class="site-settings-card-icon">
                        <FontAwesomeIcon icon="fa-solid fa-phone-volume" />
                    </span>
                    <div>
                        <h3>Thông tin Liên hệ &amp; Hỗ trợ khách hàng</h3>
                        <p>Đồng bộ tự động vào trang Liên hệ, nút gọi nhanh, nút Zalo nổi và phiếu in dự toán.</p>
                    </div>
                </div>

                <div class="formgrid">
                    <div class="field">
                        <label for="settingHotline">HOTLINE HIỂN THỊ *</label>
                        <input
                            id="settingHotline"
                            v-model="form.hotline"
                            type="text"
                            maxlength="50"
                            placeholder="0979.807.234"
                            required
                        >
                        <small v-if="form.errors.hotline" class="field-error">{{ form.errors.hotline }}</small>
                    </div>

                    <div class="field">
                        <label for="settingZaloPhone">SỐ ĐIỆN THOẠI ZALO / GỌI NHANH *</label>
                        <input
                            id="settingZaloPhone"
                            v-model="form.zalo_phone"
                            type="text"
                            maxlength="50"
                            placeholder="0979807234"
                            required
                        >
                        <small v-if="form.errors.zalo_phone" class="field-error">{{ form.errors.zalo_phone }}</small>
                    </div>

                    <div class="field">
                        <label for="settingEmail">EMAIL NHẬN BÁO GIÁ &amp; LIÊN HỆ *</label>
                        <input
                            id="settingEmail"
                            v-model="form.contact_email"
                            type="email"
                            maxlength="255"
                            placeholder="kbnguyen0311@gmail.com"
                            required
                        >
                        <small v-if="form.errors.contact_email" class="field-error">{{ form.errors.contact_email }}</small>
                    </div>

                    <div class="field">
                        <label for="settingWorkingHours">GIỜ LÀM VIỆC *</label>
                        <input
                            id="settingWorkingHours"
                            v-model="form.working_hours"
                            type="text"
                            maxlength="160"
                            placeholder="Thứ 2 – Thứ 7 · 08:00–17:30"
                            required
                        >
                        <small v-if="form.errors.working_hours" class="field-error">{{ form.errors.working_hours }}</small>
                    </div>

                    <div class="field full">
                        <label for="settingAddress">ĐỊA CHỈ VĂN PHÒNG / SHOWROOM *</label>
                        <input
                            id="settingAddress"
                            v-model="form.address"
                            type="text"
                            maxlength="255"
                            placeholder="Hà Nội, Việt Nam"
                            required
                        >
                        <small v-if="form.errors.address" class="field-error">{{ form.errors.address }}</small>
                    </div>
                </div>
            </section>

            <section class="site-settings-card">
                <div class="site-settings-card-head">
                    <span class="site-settings-card-icon">
                        <FontAwesomeIcon icon="fa-solid fa-share-nodes" />
                    </span>
                    <div>
                        <h3>Liên kết Mạng xã hội</h3>
                        <p>Đường dẫn khi khách hàng nhấn vào biểu tượng Facebook, LinkedIn và Zalo trên website.</p>
                    </div>
                </div>

                <div class="formgrid">
                    <div class="field">
                        <label for="settingFacebookUrl">LIÊN KẾT FACEBOOK *</label>
                        <input
                            id="settingFacebookUrl"
                            v-model="form.facebook_url"
                            type="text"
                            maxlength="255"
                            placeholder="https://facebook.com/..."
                            required
                        >
                        <small v-if="form.errors.facebook_url" class="field-error">{{ form.errors.facebook_url }}</small>
                    </div>

                    <div class="field">
                        <label for="settingLinkedinUrl">LIÊN KẾT LINKEDIN *</label>
                        <input
                            id="settingLinkedinUrl"
                            v-model="form.linkedin_url"
                            type="text"
                            maxlength="255"
                            placeholder="https://linkedin.com/..."
                            required
                        >
                        <small v-if="form.errors.linkedin_url" class="field-error">{{ form.errors.linkedin_url }}</small>
                    </div>

                    <div class="field full">
                        <label for="settingZaloUrl">LIÊN KẾT CHAT ZALO TRỰC TIẾP *</label>
                        <input
                            id="settingZaloUrl"
                            v-model="form.zalo_url"
                            type="text"
                            maxlength="255"
                            placeholder="https://zalo.me/0979807234"
                            required
                        >
                        <small v-if="form.errors.zalo_url" class="field-error">{{ form.errors.zalo_url }}</small>
                    </div>
                </div>
            </section>

            <section class="site-settings-card site-settings-banners-card">
                <div class="site-settings-card-head site-settings-banners-head">
                    <div class="site-settings-banners-head-main">
                        <span class="site-settings-card-icon">
                            <FontAwesomeIcon icon="fa-solid fa-images" />
                        </span>
                        <div>
                            <h3>Danh sách Banner Trang chủ (Hero Banners)</h3>
                            <p>Quản lý các hình ảnh banner (tỷ lệ 16:9) chạy slide trên màn hình Trang chủ. Có thể tải ảnh mới, thêm hoặc xóa slide linh hoạt.</p>
                        </div>
                    </div>
                    <div class="site-settings-banners-head-actions">
                        <button
                            type="button"
                            class="btn"
                            :disabled="form.hero_banners_list.length >= 10"
                            @click="addBannerSlide"
                        >
                            <FontAwesomeIcon icon="fa-solid fa-plus" />
                            Thêm slide banner
                        </button>
                    </div>
                </div>

                <div class="hero-banners-grid">
                    <article
                        v-for="(_, idx) in form.hero_banners_list"
                        :key="`banner-item-${idx}`"
                        class="hero-banner-item-card"
                    >
                        <div class="hero-banner-item-top">
                            <div class="hero-banner-item-title">
                                <span class="hero-banner-item-num">{{ formatSlideNumber(idx) }}</span>
                                <div>
                                    <strong>Slide Banner #{{ formatSlideNumber(idx) }}</strong>
                                    <small>{{ hasCustomBannerAt(idx) ? 'Đang dùng ảnh tùy chỉnh' : 'Đang dùng ảnh mặc định' }}</small>
                                </div>
                            </div>
                            <div class="hero-banner-item-top-actions">
                                <button
                                    v-if="hasCustomBannerAt(idx)"
                                    type="button"
                                    class="btn hero-banner-mini-btn"
                                    title="Đặt lại ảnh mặc định cho slide này"
                                    @click="clearBannerImageAt(idx)"
                                >
                                    <FontAwesomeIcon icon="fa-solid fa-rotate-left" />
                                    Mặc định
                                </button>
                                <button
                                    v-if="form.hero_banners_list.length > 1"
                                    type="button"
                                    class="btn hero-banner-mini-btn is-danger"
                                    title="Xóa slide banner này"
                                    @click="removeBannerSlide(idx)"
                                >
                                    <FontAwesomeIcon icon="fa-solid fa-trash-can" />
                                </button>
                            </div>
                        </div>

                        <div class="hero-banner-live-preview">
                            <img
                                :src="getBannerPreviewUrlAt(idx)"
                                :alt="`Slide Banner #${formatSlideNumber(idx)}`"
                                class="hero-banner-live-img"
                            >
                            <span class="hero-banner-live-badge">
                                Slide {{ formatSlideNumber(idx) }} / {{ formatSlideNumber(form.hero_banners_list.length - 1) }}
                            </span>
                        </div>

                        <div class="hero-banner-item-controls">
                            <label class="btn site-settings-upload-btn hero-banner-upload-btn">
                                <FontAwesomeIcon icon="fa-solid fa-upload" />
                                Chọn ảnh từ máy tính
                                <input
                                    :ref="(el) => setBannerInputRef(idx, el as HTMLInputElement | null)"
                                    type="file"
                                    accept="image/*"
                                    hidden
                                    @change="(e) => onSelectBannerFileAt(idx, e)"
                                >
                            </label>

                            <input
                                :id="`settingBannerImage${idx + 1}`"
                                v-model="form.hero_banners_list[idx]"
                                type="text"
                                maxlength="500"
                                placeholder="Hoặc dán đường dẫn URL ảnh banner (tỷ lệ 16:9, để trống sẽ dùng ảnh mặc định)..."
                                @input="onManualBannerUrlInputAt(idx)"
                            >

                            <small class="site-settings-logo-hint">
                                Khuyến nghị ảnh ngang 1920×1080px (16:9), JPG/PNG/WEBP tối đa 8MB.
                            </small>
                        </div>
                    </article>
                </div>
            </section>

            <section class="site-settings-card site-settings-preview-card">
                <div class="site-settings-card-head">
                    <span class="site-settings-card-icon">
                        <FontAwesomeIcon icon="fa-solid fa-eye" />
                    </span>
                    <div>
                        <h3>Xem trước hiển thị (Live Preview)</h3>
                        <p>Mô phỏng giao diện thực tế trên trang khách hàng khi áp dụng cấu hình hiện tại.</p>
                    </div>
                </div>

                <div class="site-settings-preview-box">
                    <div class="site-settings-preview-announcement">
                        {{ form.announcement_text }}
                    </div>
                    <div class="site-settings-preview-body">
                        <div class="site-settings-preview-brand">
                            <img
                                v-if="previewLogoUrl"
                                :src="previewLogoUrl"
                                :alt="form.company_name || 'Logo'"
                                class="mark mark-logo"
                            >
                            <span v-else class="mark">HM</span>
                            <div>
                                <strong>{{ form.company_name }}</strong>
                                <small>{{ form.company_tagline }}</small>
                            </div>
                        </div>
                        <p class="site-settings-preview-desc">{{ form.footer_description }}</p>
                        <div class="site-settings-preview-meta">
                            <span><FontAwesomeIcon icon="fa-solid fa-phone" /> {{ form.hotline }}</span>
                            <span><FontAwesomeIcon icon="fa-solid fa-envelope" /> {{ form.contact_email }}</span>
                            <span><FontAwesomeIcon icon="fa-solid fa-location-dot" /> {{ form.address }}</span>
                            <span><FontAwesomeIcon icon="fa-solid fa-clock" /> {{ form.working_hours }}</span>
                        </div>
                        <div class="site-settings-preview-foot">
                            {{ form.copyright_text }}
                        </div>
                    </div>
                </div>
            </section>
        </form>
    </AdminLayout>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import defaultBannerAn3 from '../../../../../KbUser/resources/assets/images/banners/hero-banner-an3.jpg';
import defaultBannerMflex from '../../../../../KbUser/resources/assets/images/banners/hero-banner-mflex.jpg';
import defaultBannerSlim3 from '../../../../../KbUser/resources/assets/images/banners/hero-banner-slim3.jpg';
import AdminLayout from '../../layout/AdminLayout.Vue';

type SiteSettingsMap = {
    site_logo?: string;
    company_name: string;
    company_tagline: string;
    announcement_text: string;
    footer_description: string;
    copyright_text: string;
    hotline: string;
    zalo_phone: string;
    contact_email: string;
    address: string;
    working_hours: string;
    facebook_url: string;
    linkedin_url: string;
    zalo_url: string;
    hero_banner_1_image?: string;
    hero_banner_2_image?: string;
    hero_banner_3_image?: string;
    hero_banners?: string;
};

type SiteSettingsForm = {
    site_logo: string;
    site_logo_file: File | null;
    company_name: string;
    company_tagline: string;
    announcement_text: string;
    footer_description: string;
    copyright_text: string;
    hotline: string;
    zalo_phone: string;
    contact_email: string;
    address: string;
    working_hours: string;
    facebook_url: string;
    linkedin_url: string;
    zalo_url: string;
    hero_banners_list: string[];
    hero_banner_files: Array<File | null>;
};

type AdminStat = {
    label: string;
    count: number;
    description: string;
};

const props = defineProps<{
    settings: SiteSettingsMap;
    defaults: SiteSettingsMap;
    stats: AdminStat[];
    openRequestsCount: number;
    chatUnreadCount?: number;
    supportProjectsCount?: number;
}>();

const defaultBannerImages = [defaultBannerSlim3, defaultBannerMflex, defaultBannerAn3];

function parseInitialBanners(settings: SiteSettingsMap): string[] {
    if (settings.hero_banners) {
        try {
            const parsed = JSON.parse(settings.hero_banners);
            if (Array.isArray(parsed) && parsed.length > 0) {
                return parsed.map((item) => (typeof item === 'string' ? item : ''));
            }
        } catch {
            // fallback below
        }
    }

    return [
        settings.hero_banner_1_image ?? '',
        settings.hero_banner_2_image ?? '',
        settings.hero_banner_3_image ?? '',
    ];
}

const initialBanners = parseInitialBanners(props.settings);

const logoFileInputRef = ref<HTMLInputElement | null>(null);
const localLogoPreview = ref<string | null>(null);

const bannerFileInputRefs = ref<Array<HTMLInputElement | null>>([]);
const localBannerPreviews = ref<Array<string | null>>(initialBanners.map(() => null));

const form = useForm<SiteSettingsForm>({
    site_logo: props.settings.site_logo ?? '',
    site_logo_file: null,
    company_name: props.settings.company_name,
    company_tagline: props.settings.company_tagline,
    announcement_text: props.settings.announcement_text,
    footer_description: props.settings.footer_description,
    copyright_text: props.settings.copyright_text,
    hotline: props.settings.hotline,
    zalo_phone: props.settings.zalo_phone,
    contact_email: props.settings.contact_email,
    address: props.settings.address,
    working_hours: props.settings.working_hours,
    facebook_url: props.settings.facebook_url,
    linkedin_url: props.settings.linkedin_url,
    zalo_url: props.settings.zalo_url,
    hero_banners_list: [...initialBanners],
    hero_banner_files: initialBanners.map(() => null),
});

const previewLogoUrl = computed<string>(() => {
    if (localLogoPreview.value) {
        return localLogoPreview.value;
    }

    const raw = (form.site_logo || '').trim();
    if (!raw) {
        return '';
    }

    if (raw.startsWith('data:') || raw.startsWith('http://') || raw.startsWith('https://') || raw.startsWith('/')) {
        return raw;
    }

    return `/storage/${raw}`;
});

function formatSlideNumber(idx: number): string {
    return String(idx + 1).padStart(2, '0');
}

function setBannerInputRef(idx: number, el: HTMLInputElement | null): void {
    bannerFileInputRefs.value[idx] = el;
}

function hasCustomBannerAt(idx: number): boolean {
    return Boolean(localBannerPreviews.value[idx] || (form.hero_banners_list[idx] || '').trim());
}

function appendBannerCacheBuster(url: string): string {
    if (url.includes('hero-banner-') && !url.includes('?')) {
        return `${url}?v=lux2026`;
    }
    return url;
}

function getBannerPreviewUrlAt(idx: number): string {
    if (localBannerPreviews.value[idx]) {
        return localBannerPreviews.value[idx] as string;
    }

    const raw = (form.hero_banners_list[idx] || '').trim();
    if (!raw) {
        return defaultBannerImages[idx % defaultBannerImages.length];
    }

    if (raw.startsWith('data:') || raw.startsWith('http://') || raw.startsWith('https://') || raw.startsWith('/')) {
        return appendBannerCacheBuster(raw);
    }

    return appendBannerCacheBuster(`/storage/${raw}`);
}

function revokeLocalPreview(): void {
    if (localLogoPreview.value) {
        URL.revokeObjectURL(localLogoPreview.value);
        localLogoPreview.value = null;
    }
}

function revokeBannerPreviewAt(idx: number): void {
    const preview = localBannerPreviews.value[idx];
    if (preview) {
        URL.revokeObjectURL(preview);
        localBannerPreviews.value[idx] = null;
    }
}

function revokeAllBannerPreviews(): void {
    localBannerPreviews.value.forEach((_, idx) => revokeBannerPreviewAt(idx));
}

function onSelectLogoFile(event: Event): void {
    const input = event.target as HTMLInputElement | null;
    const file = input?.files?.[0] ?? null;
    revokeLocalPreview();

    if (!file) {
        form.site_logo_file = null;
        return;
    }

    form.site_logo_file = file;
    localLogoPreview.value = URL.createObjectURL(file);
}

function onManualLogoUrlInput(): void {
    if (form.site_logo_file) {
        form.site_logo_file = null;
        revokeLocalPreview();
        if (logoFileInputRef.value) {
            logoFileInputRef.value.value = '';
        }
    }
}

function clearSiteLogo(): void {
    form.site_logo = '';
    form.site_logo_file = null;
    revokeLocalPreview();
    if (logoFileInputRef.value) {
        logoFileInputRef.value.value = '';
    }
}

function addBannerSlide(): void {
    if (form.hero_banners_list.length >= 10) {
        return;
    }
    form.hero_banners_list.push('');
    form.hero_banner_files.push(null);
    localBannerPreviews.value.push(null);
}

function removeBannerSlide(idx: number): void {
    if (form.hero_banners_list.length <= 1) {
        return;
    }
    revokeBannerPreviewAt(idx);
    form.hero_banners_list.splice(idx, 1);
    form.hero_banner_files.splice(idx, 1);
    localBannerPreviews.value.splice(idx, 1);
}

function onSelectBannerFileAt(idx: number, event: Event): void {
    const input = event.target as HTMLInputElement | null;
    const file = input?.files?.[0] ?? null;
    revokeBannerPreviewAt(idx);

    if (!file) {
        form.hero_banner_files[idx] = null;
        return;
    }

    form.hero_banner_files[idx] = file;
    localBannerPreviews.value[idx] = URL.createObjectURL(file);
}

function onManualBannerUrlInputAt(idx: number): void {
    if (form.hero_banner_files[idx]) {
        form.hero_banner_files[idx] = null;
        revokeBannerPreviewAt(idx);
        if (bannerFileInputRefs.value[idx]) {
            bannerFileInputRefs.value[idx]!.value = '';
        }
    }
}

function clearBannerImageAt(idx: number): void {
    form.hero_banners_list[idx] = '';
    form.hero_banner_files[idx] = null;
    revokeBannerPreviewAt(idx);
    if (bannerFileInputRefs.value[idx]) {
        bannerFileInputRefs.value[idx]!.value = '';
    }
}

onBeforeUnmount(() => {
    revokeLocalPreview();
    revokeAllBannerPreviews();
});

function restoreDefaults(): void {
    clearSiteLogo();
    revokeAllBannerPreviews();

    form.site_logo = props.defaults.site_logo ?? '';
    form.company_name = props.defaults.company_name;
    form.company_tagline = props.defaults.company_tagline;
    form.announcement_text = props.defaults.announcement_text;
    form.footer_description = props.defaults.footer_description;
    form.copyright_text = props.defaults.copyright_text;
    form.hotline = props.defaults.hotline;
    form.zalo_phone = props.defaults.zalo_phone;
    form.contact_email = props.defaults.contact_email;
    form.address = props.defaults.address;
    form.working_hours = props.defaults.working_hours;
    form.facebook_url = props.defaults.facebook_url;
    form.linkedin_url = props.defaults.linkedin_url;
    form.zalo_url = props.defaults.zalo_url;

    form.hero_banners_list = ['', '', ''];
    form.hero_banner_files = [null, null, null];
    localBannerPreviews.value = [null, null, null];
}

function submitSettings(): void {
    const hasUploadedFiles = Boolean(
        form.site_logo_file || form.hero_banner_files.some((file) => file !== null),
    );

    if (hasUploadedFiles) {
        form.post('/admin/settings', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.site_logo_file = null;
                form.hero_banner_files = form.hero_banners_list.map(() => null);
                revokeLocalPreview();
                revokeAllBannerPreviews();
                if (logoFileInputRef.value) {
                    logoFileInputRef.value.value = '';
                }
                bannerFileInputRefs.value.forEach((input) => {
                    if (input) {
                        input.value = '';
                    }
                });
            },
        });

        return;
    }

    form.put('/admin/settings', {
        preserveScroll: true,
    });
}
</script>

<style scoped>
.site-settings-banners-card {
    grid-column: 1 / -1;
}

.site-settings-banners-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}

.site-settings-banners-head-main {
    display: flex;
    align-items: center;
    gap: 14px;
}

.site-settings-banners-head-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.hero-banners-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 18px;
}

.hero-banner-item-card {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 16px;
    border-radius: 16px;
    border: 1px solid var(--line, #e2e8f0);
    background: var(--surface-muted, #f8fafc);
}

.hero-banner-item-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.hero-banner-item-title {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.hero-banner-item-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 10px;
    background: linear-gradient(135deg, #c99a49 0%, #a57526 100%);
    font-size: 12.5px;
    font-weight: 800;
    color: #fff;
    flex-shrink: 0;
}

.hero-banner-item-title strong {
    display: block;
    font-size: 14px;
    font-weight: 800;
    color: #1e293b;
}

.hero-banner-item-title small {
    display: block;
    font-size: 12px;
    color: #64748b;
}

.hero-banner-item-top-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

.hero-banner-mini-btn {
    padding: 6px 10px;
    font-size: 12px;
}

.hero-banner-mini-btn.is-danger {
    color: #dc2626;
    border-color: rgba(220, 38, 38, 0.25);
}

.hero-banner-mini-btn.is-danger:hover {
    background: #fef2f2;
    border-color: #dc2626;
}

.hero-banner-live-preview {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 7.5;
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid rgba(223, 205, 169, 0.35);
    background: #100e0b;
    box-shadow: 0 14px 30px -12px rgba(15, 12, 8, 0.28);
}

.hero-banner-live-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center center;
    display: block;
}

.hero-banner-live-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 3;
    padding: 4px 10px;
    border-radius: 999px;
    background: rgba(255, 253, 248, 0.9);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(184, 137, 58, 0.3);
    color: #574932;
    font-size: 11px;
    font-weight: 700;
}

.hero-banner-item-controls {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.hero-banner-upload-btn {
    justify-content: center;
    width: 100%;
}

@media (max-width: 760px) {
    .hero-banners-grid {
        grid-template-columns: 1fr;
    }
}
</style>
