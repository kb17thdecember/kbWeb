<script setup lang="ts">
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
                    <div class="kb-quote-login-brand">
                        <span class="kb-quote-login-mark">HM</span>
                        <div>
                            <strong>HVAC Mart · Star Light</strong>
                            <small>QUOTATION BUILDER PORTAL</small>
                        </div>
                    </div>

                    <h1>Đăng nhập Tạo Báo Giá PDF</h1>
                    <p class="kb-quote-login-desc">
                        Vui lòng đăng nhập tài khoản cấp quyền để sử dụng công cụ lập báo giá dự án Ariston &amp; Bảo ôn K-Flex chuẩn mẫu Star Light.
                    </p>

                    <p v-if="props.status" class="kb-quote-login-error">{{ props.status }}</p>

                    <div class="kb-quote-login-field">
                        <label for="quoteEmail">Email tài khoản báo giá</label>
                        <input
                            id="quoteEmail"
                            v-model="form.email"
                            type="email"
                            maxlength="255"
                            placeholder="Nhập địa chỉ email..."
                            autocomplete="username"
                            required
                            autofocus
                        />
                        <span v-if="form.errors.email" class="kb-quote-login-error">{{ form.errors.email }}</span>
                    </div>

                    <div class="kb-quote-login-field">
                        <label for="quotePassword">Mật khẩu</label>
                        <div class="kb-quote-password-wrap">
                            <input
                                id="quotePassword"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="Nhập mật khẩu..."
                                autocomplete="current-password"
                                required
                            />
                            <button
                                type="button"
                                class="kb-quote-password-toggle"
                                :aria-label="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                                :title="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                                @click="showPassword = !showPassword"
                            >
                                <svg v-if="showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94" />
                                    <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19" />
                                    <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24" />
                                    <line x1="1" y1="1" x2="23" y2="23" />
                                </svg>
                                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </button>
                        </div>
                        <span v-if="form.errors.password" class="kb-quote-login-error">{{ form.errors.password }}</span>
                    </div>

                    <div class="kb-quote-recaptcha-field">
                        <div class="kb-quote-recaptcha-wrap">
                            <div ref="recaptchaContainer" class="kb-quote-recaptcha-widget"></div>
                        </div>
                        <span v-if="recaptchaLocalError || form.errors.recaptcha_token" class="kb-quote-login-error">
                            {{ recaptchaLocalError || form.errors.recaptcha_token }}
                        </span>
                    </div>

                    <button type="submit" class="kb-quote-login-submit" :disabled="form.processing">
                        {{ form.processing ? 'Đang xác thực...' : 'Đăng nhập vào trang Báo Giá' }}
                    </button>

                    <div class="kb-quote-login-foot">
                        <Link href="/">← Quay lại trang chủ</Link>
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
    max-width: 460px;
    background: #fffdf8;
    color: #29271f;
    border: 1px solid #e6dfd1;
    border-radius: 16px;
    padding: 32px 28px;
    box-shadow: 0 18px 42px rgba(24, 53, 46, 0.1);
}

.kb-quote-login-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 16px;
    margin-bottom: 18px;
    border-bottom: 1px solid #e6dfd1;
}

.kb-quote-login-mark {
    width: 42px;
    height: 42px;
    border-radius: 8px;
    background: #18352e;
    color: #e4d0a0;
    border: 1px solid #b59a65;
    display: grid;
    place-items: center;
    font-weight: 700;
    font-size: 18px;
    flex-shrink: 0;
}

.kb-quote-login-brand strong {
    display: block;
    font-size: 16px;
    color: #18352e;
}

.kb-quote-login-brand small {
    display: block;
    font-size: 10.5px;
    letter-spacing: 0.12em;
    color: #888272;
    margin-top: 2px;
}

.kb-quote-login-card h1 {
    margin: 0 0 8px;
    font-size: 24px;
    font-weight: 700;
    color: #18352e;
}

.kb-quote-login-desc {
    margin: 0 0 20px;
    font-size: 13.5px;
    line-height: 1.55;
    color: #625d50;
}

.kb-quote-login-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 16px;
}

.kb-quote-login-field label {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #5f5641;
}

.kb-quote-login-field input {
    width: 100%;
    border: 1px solid #d8cfbd;
    border-radius: 10px;
    background: #ffffff;
    color: #29271f;
    padding: 11px 14px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.kb-quote-login-field input:focus {
    border-color: #18352e;
    box-shadow: 0 0 0 3px rgba(24, 53, 46, 0.12);
}

.kb-quote-password-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.kb-quote-password-wrap input {
    padding-right: 44px;
}

.kb-quote-password-toggle {
    position: absolute;
    right: 10px;
    border: 0;
    background: transparent;
    color: #77766e;
    border-radius: 6px;
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    cursor: pointer;
    transition: color 0.18s ease, background 0.18s ease;
}

.kb-quote-password-toggle:hover {
    color: #18352e;
    background: #f1e8d6;
}

.kb-quote-password-toggle svg {
    width: 18px;
    height: 18px;
}

.kb-quote-login-error {
    color: #b91c1c;
    font-size: 12.5px;
    font-weight: 600;
    margin: 2px 0 0;
}

.kb-quote-recaptcha-field {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    margin-bottom: 16px;
}

.kb-quote-recaptcha-wrap {
    width: 100%;
    min-height: 78px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow-x: auto;
}

.kb-quote-login-submit {
    width: 100%;
    margin-top: 6px;
    padding: 12px 18px;
    border: 0;
    border-radius: 10px;
    background: linear-gradient(135deg, #18352e 0%, #23473b 100%);
    color: #f5f1e8;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: opacity 0.2s ease, transform 0.15s ease;
}

.kb-quote-login-submit:hover:not(:disabled) {
    opacity: 0.95;
}

.kb-quote-login-submit:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.kb-quote-login-foot {
    margin-top: 18px;
    padding-top: 14px;
    border-top: 1px dashed #e6dfd1;
    text-align: center;
}

.kb-quote-login-foot a {
    font-size: 13px;
    font-weight: 600;
    color: #18352e;
    text-decoration: none;
}

.kb-quote-login-foot a:hover {
    text-decoration: underline;
}
</style>
