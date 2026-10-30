<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import UserLayout from '../../layout/UserLayout.Vue';

type AuthUser = {
    name?: string;
    email?: string;
    phone?: string | null;
};

const props = defineProps<{
    products: { id: number; name: string }[];
}>();

const page = usePage<{ auth?: { user?: AuthUser | null } }>();
const authUser = computed<AuthUser | null>(() => page.props.auth?.user ?? null);

const successMessage = ref('');
const toastMessage = ref('');
const isToastVisible = ref(false);
const cart = ref<Record<number, number>>({});
let toastTimer: ReturnType<typeof setTimeout> | undefined;

const cartItems = computed(() =>
    Object.entries(cart.value)
        .map(([id, quantity]) => {
            const product = props.products.find((item) => item.id === Number(id));

            return product ? { product, quantity } : null;
        })
        .filter((item): item is { product: { id: number; name: string }; quantity: number } => item !== null),
);

const loadCart = (): void => {
    try {
        const parsed: unknown = JSON.parse(sessionStorage.getItem('kbuser-cart') ?? '{}');

        if (typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed)) {
            cart.value = Object.fromEntries(
                Object.entries(parsed).filter(([, quantity]) => Number.isInteger(quantity) && Number(quantity) > 0),
            ) as Record<number, number>;
        }
    } catch {
        sessionStorage.removeItem('kbuser-cart');
    }
};

const attachmentInput = ref<HTMLInputElement | null>(null);

const form = useForm<{
    name: string;
    phone: string;
    email: string;
    category_id: string;
    project_name: string;
    message: string;
    attachment: File | null;
}>({
    name: authUser.value?.name ?? '',
    phone: authUser.value?.phone ?? '',
    email: authUser.value?.email ?? '',
    category_id: '',
    project_name: '',
    message: '',
    attachment: null,
});

const onAttachmentChange = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    form.attachment = input.files?.[0] ?? null;
};

const clearAttachment = (): void => {
    form.attachment = null;
    if (attachmentInput.value) {
        attachmentInput.value.value = '';
    }
};

const formatAttachmentSize = (bytes: number): string => {
    if (bytes >= 1048576) {
        return `${(bytes / 1048576).toFixed(2)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
};

watch(authUser, (user) => {
    if (user) {
        if (!form.name && user.name) {
            form.name = user.name;
        }
        if (!form.phone && user.phone) {
            form.phone = user.phone;
        }
        if (!form.email && user.email) {
            form.email = user.email;
        }
    }
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
    form
        .transform((data) => ({
            ...data,
            items: cartItems.value.map((item) => ({
                id: item.product.id,
                quantity: item.quantity,
            })),
        }))
        .post('/contacts', {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.reset('category_id', 'project_name', 'message', 'attachment');
                clearAttachment();
                cart.value = {};
                sessionStorage.removeItem('kbuser-cart');
                window.dispatchEvent(new Event('kbuser-cart-updated'));
                successMessage.value = 'Yêu cầu báo giá đã được gửi. Chúng tôi sẽ liên hệ với bạn sớm.';
                notify(successMessage.value);
            },
        });
};

onMounted(loadCart);

onUnmounted(() => {
    clearTimeout(toastTimer);
});
</script>

<template>
    <div>
        <Head title="HVAC Mart | Liên hệ và báo giá" />

        <UserLayout :cart-count="0" active-page="contact">
            <main>
                <section class="page-intro" aria-live="polite">
                    <div class="wrap page-intro-inner">
                        <div>
                            <div class="eyebrow"><span class="dot"></span> HVAC MART / LIÊN HỆ</div>
                            <h1>Liên hệ &amp; báo giá</h1>
                            <p>
                                Gửi yêu cầu về mặt hàng, số lượng hoặc dự án để bắt đầu trao đổi với
                                đội ngũ tư vấn.
                            </p>
                        </div>
                        <Link class="page-home-link" href="/"><FontAwesomeIcon :icon="['fas', 'arrow-left']" /> Về trang chủ</Link>
                    </div>
                </section>

                <section class="contact-section">
                    <div class="wrap">
                        <div class="contact-card">
                            <div class="contact-copy">
                                <div class="eyebrow"><span class="dot"></span> BẮT ĐẦU MỘT CUỘC TRAO ĐỔI</div>
                                <h2>
                                    Công trình của bạn.<br>
                                    <span style="font-weight: 400; font-style: italic; color: var(--gold);">
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
                                        <span>kbnguyen0311@gmail.com</span>
                                    </div>
                                    <div>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                            <path d="M7 3h10v18H7zM10 6h4M10 18h4" />
                                        </svg>
                                        <span>0979.807.234</span>
                                    </div>
                                    <div>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                            <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z" />
                                            <circle cx="12" cy="10" r="2.5" />
                                        </svg>
                                        <span>Hà Nội, Việt Nam</span>
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
                                            maxlength="255"
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
                                            maxlength="30"
                                            pattern="[0-9+\s().\-]{8,30}"
                                            title="Số điện thoại gồm 8-30 ký tự: số, dấu +, khoảng trắng, ngoặc, chấm hoặc gạch ngang"
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
                                        maxlength="255"
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
                                <div v-if="cartItems.length" class="field">
                                    <label>SẢN PHẨM YÊU CẦU BÁO GIÁ</label>
                                    <ul class="contact-products">
                                        <li v-for="{ product, quantity } in cartItems" :key="product.id">
                                            {{ product.name }} × {{ quantity }}
                                        </li>
                                    </ul>
                                </div>
                                <div class="field">
                                    <label for="message">MÔ TẢ NHU CẦU {{ cartItems.length ? '' : '*' }}</label>
                                    <textarea
                                        id="message"
                                        v-model="form.message"
                                        placeholder="Sản phẩm, quy cách, số lượng, tiến độ..."
                                        maxlength="5000"
                                        :aria-invalid="Boolean(form.errors.message)"
                                        :required="cartItems.length === 0"
                                    ></textarea>
                                    <span v-if="form.errors.message" class="field-error">{{ form.errors.message }}</span>
                                </div>
                                <div class="field">
                                    <label for="boqAttachment">HỒ SƠ ĐÍNH KÈM (BẢNG KHỐI LƯỢNG BOQ / BẢN VẼ KỸ THUẬT)</label>
                                    <div class="kb-luxury-upload">
                                        <input
                                            id="boqAttachment"
                                            ref="attachmentInput"
                                            type="file"
                                            class="kb-file-input-hidden"
                                            accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.webp"
                                            @change="onAttachmentChange"
                                        >
                                        <label v-if="!form.attachment" for="boqAttachment" class="kb-upload-dropzone">
                                            <span class="kb-upload-icon-box" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                                    <path d="M12 16V4m0 0-4 4m4-4 4 4" />
                                                    <path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
                                                </svg>
                                            </span>
                                            <span class="kb-upload-copy">
                                                <strong>Tải lên bảng khối lượng (BOQ) hoặc bản vẽ</strong>
                                                <small>Định dạng PDF, Excel, Word, Ảnh · Dung lượng tối đa 10MB</small>
                                            </span>
                                            <span class="kb-upload-cta">Chọn tệp</span>
                                        </label>

                                        <div v-else class="kb-upload-file-card">
                                            <span class="kb-upload-file-badge" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                    <path d="M14 2v6h6" />
                                                </svg>
                                            </span>
                                            <div class="kb-upload-file-meta">
                                                <strong>{{ form.attachment.name }}</strong>
                                                <small>{{ formatAttachmentSize(form.attachment.size) }} · Sẵn sàng đính kèm báo giá</small>
                                            </div>
                                            <div class="kb-upload-file-actions">
                                                <label for="boqAttachment" class="kb-upload-change-btn">Đổi tệp</label>
                                                <button
                                                    type="button"
                                                    class="kb-upload-remove-btn"
                                                    aria-label="Xóa tệp đính kèm"
                                                    @click="clearAttachment"
                                                >
                                                    ×
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <span v-if="form.errors.attachment" class="field-error">{{ form.errors.attachment }}</span>
                                </div>
                                <button class="btn" type="submit" :disabled="form.processing">
                                    {{ form.processing ? 'Đang gửi...' : 'Gửi yêu cầu tư vấn' }} <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" />
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
    </div>
</template>
