<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onUnmounted, ref } from 'vue';
import UserLayout from '../../layout/UserLayout.Vue';

const successMessage = ref('');
const toastMessage = ref('');
const isToastVisible = ref(false);
let toastTimer: ReturnType<typeof setTimeout> | undefined;

const form = useForm({
    name: '',
    phone: '',
    email: '',
    category_id: '',
    project_name: '',
    message: '',
});

const notify = (message: string): void => {
    toastMessage.value = message;
    isToastVisible.value = true;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        isToastVisible.value = false;
    }, 2400);
};

const submitContactForm = (): void => {
    successMessage.value = '';
    form.post('/contacts', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            successMessage.value = 'Yêu cầu báo giá đã được gửi. Chúng tôi sẽ liên hệ với bạn sớm.';
            notify(successMessage.value);
        },
    });
};

onUnmounted(() => {
    clearTimeout(toastTimer);
});
</script>

<template>
    <Head title="Liên hệ & báo giá | NOVA TECH" />

    <UserLayout :cart-count="0" active-page="contact">
        <main>
            <section class="page-intro" aria-live="polite">
                <div class="wrap page-intro-inner">
                    <div>
                        <div class="eyebrow"><span class="dot"></span> NOVA TECH / CONTACT</div>
                        <h1>Liên hệ &amp; báo giá</h1>
                        <p>
                            Gửi yêu cầu về mặt hàng, số lượng hoặc dự án để bắt đầu trao đổi với
                            đội ngũ tư vấn.
                        </p>
                    </div>
                    <Link class="page-home-link" href="/">← Về trang chủ</Link>
                </div>
            </section>

            <section class="contact-section">
                <div class="wrap">
                    <div class="contact-card">
                        <div class="contact-copy">
                            <div class="eyebrow"><span class="dot"></span> BẮT ĐẦU MỘT CUỘC TRAO ĐỔI</div>
                            <h2>
                                Công trình của bạn.<br>
                                <span style="font-family: Georgia, serif; font-weight: 400; font-style: italic">
                                    Giải pháp của chúng tôi.
                                </span>
                            </h2>
                            <p>
                                Gửi yêu cầu về mặt hàng, số lượng hoặc bản vẽ. Đội ngũ tư vấn sẽ có
                                cơ sở để hỗ trợ bạn tốt hơn.
                            </p>
                            <div class="contact-details">
                                <div>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                        <path d="M4 5h16v14H4z" />
                                        <path d="m4 7 8 6 8-6" />
                                    </svg>
                                    <span>sales@novatech.vn <small>(thông tin mẫu)</small></span>
                                </div>
                                <div>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                        <path d="M7 3h10v18H7zM10 6h4M10 18h4" />
                                    </svg>
                                    <span>0900 000 000 <small>(thông tin mẫu)</small></span>
                                </div>
                                <div>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                        <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z" />
                                        <circle cx="12" cy="10" r="2.5" />
                                    </svg>
                                    <span>Hà Nội, Việt Nam <small>(địa chỉ mẫu)</small></span>
                                </div>
                            </div>
                        </div>

                        <form class="contact-form" @submit.prevent="submitContactForm">
                            <div class="form-row">
                                <div class="field">
                                    <label for="fullName">HỌ VÀ TÊN *</label>
                                    <input
                                        id="fullName"
                                        v-model="form.name"
                                        autocomplete="name"
                                        placeholder="Tên của bạn"
                                        :aria-invalid="Boolean(form.errors.name)"
                                        required
                                    >
                                    <span v-if="form.errors.name" class="field-error">{{ form.errors.name }}</span>
                                </div>
                                <div class="field">
                                    <label for="phone">SỐ ĐIỆN THOẠI *</label>
                                    <input
                                        id="phone"
                                        v-model="form.phone"
                                        type="tel"
                                        autocomplete="tel"
                                        inputmode="tel"
                                        placeholder="09xx xxx xxx"
                                        :aria-invalid="Boolean(form.errors.phone)"
                                        required
                                    >
                                    <span v-if="form.errors.phone" class="field-error">{{ form.errors.phone }}</span>
                                </div>
                            </div>
                            <div class="field">
                                <label for="email">EMAIL CÔNG VIỆC *</label>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    autocomplete="email"
                                    placeholder="ban@congty.vn"
                                    :aria-invalid="Boolean(form.errors.email)"
                                    required
                                >
                                <span v-if="form.errors.email" class="field-error">{{ form.errors.email }}</span>
                            </div>
                            <div class="field">
                                <label for="interest">HẠNG MỤC QUAN TÂM</label>
                                <select id="interest" v-model="form.category_id" :aria-invalid="Boolean(form.errors.category_id)">
                                    <option value="">Chọn hạng mục</option>
                                    <option value="Bình nước nóng Ariston">Bình nước nóng Ariston</option>
                                    <option value="Bảo ôn K-FLEX">Bảo ôn K-FLEX</option>
                                    <option value="Vật tư HVAC">Vật tư HVAC</option>
                                    <option value="Vật tư MEP tổng hợp">Vật tư MEP tổng hợp</option>
                                    <option value="Cung ứng cho dự án">Cung ứng cho dự án</option>
                                </select>
                                <span v-if="form.errors.category_id" class="field-error">{{ form.errors.category_id }}</span>
                            </div>
                            <div class="field">
                                <label for="projectName">DỰ ÁN CẦN BÁO GIÁ</label>
                                <input
                                    id="projectName"
                                    v-model="form.project_name"
                                    placeholder="Tên dự án hoặc công trình"
                                    maxlength="255"
                                    :aria-invalid="Boolean(form.errors.project_name)"
                                >
                                <span v-if="form.errors.project_name" class="field-error">{{ form.errors.project_name }}</span>
                            </div>
                            <div class="field">
                                <label for="message">MÔ TẢ NHU CẦU *</label>
                                <textarea
                                    id="message"
                                    v-model="form.message"
                                    placeholder="Sản phẩm, quy cách, số lượng, tiến độ..."
                                    maxlength="5000"
                                    :aria-invalid="Boolean(form.errors.message)"
                                    required
                                ></textarea>
                                <span v-if="form.errors.message" class="field-error">{{ form.errors.message }}</span>
                            </div>
                            <button class="btn" type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Đang gửi...' : 'Gửi yêu cầu tư vấn' }} <span>↗</span>
                            </button>
                            <p v-if="successMessage" class="form-message show" role="status" aria-live="polite">
                                {{ successMessage }}
                            </p>
                            <p class="contact-form-note">
                                Các trường có dấu * là bắt buộc. Thông tin dự án giúp chúng tôi chuẩn bị báo giá phù hợp.
                            </p>
                        </form>
                    </div>
                </div>
            </section>
        </main>
        <div class="toast" :class="{ show: isToastVisible }" role="status" aria-live="polite">
            <span class="toast-dot"></span>{{ toastMessage }}
        </div>
    </UserLayout>
</template>