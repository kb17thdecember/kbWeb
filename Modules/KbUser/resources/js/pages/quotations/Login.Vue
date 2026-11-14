<script setup lang="ts">
import {
    faArrowLeft,
    faArrowRight,
    faEye,
    faEyeSlash,
    faSpinner,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import UserLayout from '../../layout/UserLayout.Vue';

declare global {
    interface Window {
        grecaptcha?: {
            render: (
                container: HTMLElement,
                parameters: {
                    sitekey: string;
                    theme?: 'light' | 'dark';
                    callback?: (token: string) => void;
                    'expired-callback'?: () => void;
                    'error-callback'?: () => void;
                },
            ) => number;
            reset: (widgetId?: number) => void;
        };
        __kbOnRecaptchaLoad?: () => void;
    }
}

const props = defineProps<{
    status?: string | null;
    recaptchaSiteKey?: string;
}>();

const cartCount = ref(0);
const showPassword = ref(false);
const recaptchaContainer = ref<HTMLElement | null>(null);
const recaptchaWidgetId = ref<number | null>(null);
const recaptchaLocalError = ref('');

const form = useForm({
    email: '',
    password: '',
    recaptcha_token: '',
});

const renderRecaptchaWidget = (): void => {
    if (!recaptchaContainer.value || !window.grecaptcha?.render || recaptchaWidgetId.value !== null) {
        return;
    }

    const siteKey = props.recaptchaSiteKey || '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI';

    try {
        recaptchaWidgetId.value = window.grecaptcha.render(recaptchaContainer.value, {
            sitekey: siteKey,
            theme: 'light',
            callback: (token: string) => {
                form.recaptcha_token = token;
                recaptchaLocalError.value = '';
                form.clearErrors('recaptcha_token');
            },
            'expired-callback': () => {
                form.recaptcha_token = '';
            },
            'error-callback': () => {
                form.recaptcha_token = '';
            },
        });
    } catch {
        // Ignore duplicate render attempts
    }
};

const loadGoogleRecaptchaScript = (): void => {
    if (window.grecaptcha?.render) {
        renderRecaptchaWidget();
        return;
    }

    window.__kbOnRecaptchaLoad = () => {
        renderRecaptchaWidget();
    };

    const existingScript = document.getElementById('google-recaptcha-v2-script');
    if (existingScript) {
        return;
    }

    const script = document.createElement('script');
    script.id = 'google-recaptcha-v2-script';
    script.src = 'https://www.google.com/recaptcha/api.js?onload=__kbOnRecaptchaLoad&render=explicit&hl=vi';
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
};

const refreshCartCount = (): void => {
    try {
        const raw = sessionStorage.getItem('kbuser-cart');
        if (!raw) {
            cartCount.value = 0;
            return;
        }
        const parsed = JSON.parse(raw) as Record<string, number>;
        cartCount.value = Object.values(parsed).reduce((sum, qty) => sum + (Number(qty) || 0), 0);
    } catch {
        cartCount.value = 0;
    }
};

const submit = (): void => {
    recaptchaLocalError.value = '';

    if (!form.recaptcha_token) {
        recaptchaLocalError.value = 'Vui lòng tích xác nhận "Tôi không phải là người máy".';
        return;
    }

    form.post('/quotations/login', {
        onFinish: () => {
            form.reset('password');
            if (Object.keys(form.errors).length > 0 && window.grecaptcha && recaptchaWidgetId.value !== null) {
                window.grecaptcha.reset(recaptchaWidgetId.value);
                form.recaptcha_token = '';
            }
        },
    });
};

onMounted(() => {
    refreshCartCount();
    loadGoogleRecaptchaScript();
});

onBeforeUnmount(() => {
    if (window.__kbOnRecaptchaLoad) {
        delete window.__kbOnRecaptchaLoad;
    }
});
</script>

<template>
    <UserLayout :cart-count="cartCount">
        <Head title="Đăng nhập Tạo Báo Giá PDF" />

        <section class="kb-quote-login-section">
            <div class="wrap kb-quote-login-wrap">
                <form class="kb-quote-login-card" @submit.prevent="submit">
                    <div class="kb-auth-modal-head kb-quote-login-head">
                        <div>
                            <span class="eyebrow"><span class="dot"></span> QUOTATION BUILDER PORTAL</span>
                            <h1>Đăng nhập Tạo Báo Giá PDF</h1>
                        </div>
                        <span class="brandmark kb-quote-login-mark" aria-hidden="true">HM</span>
                    </div>

                    <p class="kb-quote-login-desc">
                        Vui lòng đăng nhập tài khoản cấp quyền để sử dụng công cụ lập báo giá dự án Ariston &amp; Bảo ôn K-Flex chuẩn mẫu Star Light.
                    </p>

                    <p v-if="props.status" class="kb-auth-alert is-error" role="alert">{{ props.status }}</p>

                    <div class="field">
                        <label for="quoteEmail">EMAIL TÀI KHOẢN BÁO GIÁ *</label>
                        <input
                            id="quoteEmail"
                            v-model="form.email"
                            type="email"
                            maxlength="255"
                            placeholder="ban@congty.vn"
                            autocomplete="username"
                            :aria-invalid="Boolean(form.errors.email)"
                            required
                            autofocus
                        />
                        <span v-if="form.errors.email" class="field-error">{{ form.errors.email }}</span>
                    </div>

                    <div class="field">
                        <label for="quotePassword">MẬT KHẨU *</label>
                        <div class="kb-password-wrap">
                            <input
                                id="quotePassword"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="Nhập mật khẩu của bạn"
                                autocomplete="current-password"
                                :aria-invalid="Boolean(form.errors.password)"
                                required
                            />
                            <button
                                type="button"
                                class="kb-password-toggle"
                                :aria-label="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                                :title="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                                @click="showPassword = !showPassword"
                            >
                                <FontAwesomeIcon :icon="showPassword ? faEyeSlash : faEye" aria-hidden="true" />
                            </button>
                        </div>
                        <span v-if="form.errors.password" class="field-error">{{ form.errors.password }}</span>
                    </div>

                    <div class="kb-quote-recaptcha-field">
                        <div class="kb-quote-recaptcha-wrap">
                            <div ref="recaptchaContainer" class="kb-quote-recaptcha-widget"></div>
                        </div>
                        <span v-if="recaptchaLocalError || form.errors.recaptcha_token" class="field-error">
                            {{ recaptchaLocalError || form.errors.recaptcha_token }}
                        </span>
                    </div>

                    <button type="submit" class="btn btn-primary kb-auth-submit kb-quote-login-submit" :disabled="form.processing">
                        <span>{{ form.processing ? 'Đang xác thực...' : 'Đăng nhập vào trang Báo Giá' }}</span>
                        <FontAwesomeIcon
                            :icon="form.processing ? faSpinner : faArrowRight"
                            :spin="form.processing"
                            aria-hidden="true"
                        />
                    </button>

                    <div class="kb-quote-login-foot">
                        <Link href="/">
                            <FontAwesomeIcon :icon="faArrowLeft" aria-hidden="true" />
                            <span>Quay lại trang chủ</span>
                        </Link>
                    </div>
                </form>
            </div>
        </section>
    </UserLayout>
</template>

<style scoped>
.kb-quote-login-section {
    padding: 56px 0 72px;
    min-height: calc(100vh - 220px);
    display: flex;
    align-items: center;
}

.kb-quote-login-wrap {
    display: flex;
    justify-content: center;
    width: 100%;
}

.kb-quote-login-card {
    width: 100%;
    max-width: 500px;
    background: var(--surface);
    color: var(--ink);
    border: 1px solid var(--line);
    border-radius: 0;
    padding: 30px 30px 28px;
    box-shadow: var(--shadow);
}

.kb-quote-login-head {
    padding-bottom: 16px;
    margin-bottom: 14px;
    border-bottom: 1px solid var(--line);
    align-items: center;
}

.kb-quote-login-head h1 {
    margin: 6px 0 0;
    font-family: var(--serif);
    font-size: 24px;
    font-weight: 500;
    line-height: 1.25;
    color: var(--ink);
}

.kb-quote-login-mark {
    width: 38px;
    height: 38px;
    flex-shrink: 0;
}

.kb-quote-login-desc {
    margin: 0 0 20px;
    font-size: 13px;
    line-height: 1.6;
    color: var(--muted);
}

.kb-quote-login-card .field {
    margin-bottom: 16px;
}

.kb-quote-login-card .field input:focus {
    border-color: var(--brass);
    box-shadow: 0 0 0 3px rgba(183, 154, 99, 0.16);
}

.kb-quote-recaptcha-field {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    margin: 4px 0 18px;
}

.kb-quote-recaptcha-wrap {
    width: 100%;
    min-height: 78px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: visible;
}

.kb-quote-login-submit {
    justify-content: center;
    background: var(--brass);
    color: var(--dark);
}

.kb-quote-login-submit:hover:not(:disabled) {
    background: var(--champ);
    color: var(--dark);
}

.kb-quote-login-submit:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.kb-quote-login-foot {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--line);
    text-align: center;
}

.kb-quote-login-foot a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12.5px;
    font-weight: 700;
    color: var(--muted);
    text-decoration: none;
    transition: color 0.2s ease;
}

.kb-quote-login-foot a:hover {
    color: var(--brass);
}

@media (max-width: 640px) {
    .kb-quote-login-section {
        padding: 32px 0 48px;
    }

    .kb-quote-login-card {
        padding: 22px 18px 20px;
    }

    .kb-quote-login-head h1 {
        font-size: 20px;
    }
}
</style>
