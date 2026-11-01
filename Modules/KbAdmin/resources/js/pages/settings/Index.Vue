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
                            <span class="mark">HM</span>
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
import AdminLayout from '../../layout/AdminLayout.Vue';

type SiteSettingsMap = {
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

const form = useForm<SiteSettingsMap>({
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
});

function restoreDefaults(): void {
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
}

function submitSettings(): void {
    form.put('/admin/settings', {
        preserveScroll: true,
    });
}
</script>
