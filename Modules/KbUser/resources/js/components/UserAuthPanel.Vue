<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        initialMode?: 'login' | 'register' | 'forgot' | 'reset';
        status?: string | null;
        googleError?: string | null;
        compact?: boolean;
    }>(),
    {
        initialMode: 'login',
        status: null,
        googleError: null,
        compact: true,
    },
);

const emit = defineEmits<{
    success: [];
    close: [];
}>();

const mode = ref<'login' | 'register' | 'forgot' | 'reset'>(props.initialMode);
const showLoginPassword = ref(false);
const showRegisterPassword = ref(false);
const showResetPassword = ref(false);
const localStatus = ref<string | null>(null);

watch(
    () => props.initialMode,
    (nextMode) => {
        mode.value = nextMode;
    },
);

const loginForm = useForm({
    email: '',
    password: '',
    remember: false,
});

const registerForm = useForm({
    name: '',
    phone: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const forgotForm = useForm({
    email: '',
});

const resetForm = useForm({
    email: '',
    code: '',
    password: '',
    password_confirmation: '',
});

const switchMode = (nextMode: 'login' | 'register' | 'forgot' | 'reset'): void => {
    if (nextMode === 'forgot' && loginForm.email && !forgotForm.email) {
        forgotForm.email = loginForm.email;
    }

    if (nextMode !== 'reset') {
        localStatus.value = null;
    }

    mode.value = nextMode;
    loginForm.clearErrors();
    registerForm.clearErrors();
    forgotForm.clearErrors();
    resetForm.clearErrors();
};

const submitLogin = (): void => {
    loginForm.post('/login', {
        preserveScroll: true,
        onSuccess: () => {
            loginForm.reset('password');
            emit('success');
        },
        onFinish: () => {
            loginForm.reset('password');
        },
    });
};

const submitRegister = (): void => {
    registerForm.post('/register', {
        preserveScroll: true,
        onSuccess: () => {
            registerForm.reset('password', 'password_confirmation');
            emit('success');
        },
        onFinish: () => {
            registerForm.reset('password', 'password_confirmation');
        },
    });
};

const submitForgot = (): void => {
    forgotForm.post('/forgot-password', {
        preserveScroll: true,
        onSuccess: () => {
            resetForm.email = forgotForm.email.trim();
            resetForm.code = '';
            resetForm.password = '';
            resetForm.password_confirmation = '';
            localStatus.value = `Mã xác thực 6 chữ số đã được gửi tới ${resetForm.email}.`;
            mode.value = 'reset';
        },
    });
};

const submitReset = (): void => {
    resetForm.post('/reset-password', {
        preserveScroll: true,
        onSuccess: () => {
            resetForm.reset('code', 'password', 'password_confirmation');
            localStatus.value = null;
            emit('success');
        },
    });
};
</script>

<template>
    <div class="kb-auth-panel" :class="{ 'is-compact': props.compact }">
        <div class="kb-auth-tabs" role="tablist" aria-label="Chọn đăng nhập hoặc đăng ký">
            <button
                type="button"
                class="kb-auth-tab"
                :class="{ active: mode === 'login' || mode === 'forgot' || mode === 'reset' }"
                role="tab"
                :aria-selected="mode === 'login'"
                @click="switchMode('login')"
            >
                Đăng nhập
            </button>

            <button
                type="button"
                class="kb-auth-tab"
                :class="{ active: mode === 'register' }"
                role="tab"
                :aria-selected="mode === 'register'"
                @click="switchMode('register')"
            >
                Đăng ký tài khoản
            </button>
        </div>

        <p v-if="localStatus || props.status" class="kb-auth-alert is-success" role="status">
            {{ localStatus || props.status }}
        </p>
        <p v-if="props.googleError" class="kb-auth-alert is-error" role="alert">
            {{ props.googleError }}
        </p>

        <div :class="{ 'kb-auth-top-hidden': mode === 'forgot' || mode === 'reset' }">
            <a href="/auth/google/redirect" class="kb-google-btn" :tabindex="mode === 'forgot' || mode === 'reset' ? -1 : 0">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path
                        fill="#EA4335"
                        d="M12 10.2v3.9h5.5c-.24 1.26-.96 2.33-2.04 3.05l3.3 2.56c1.92-1.77 3.04-4.38 3.04-7.46 0-.72-.06-1.42-.19-2.05H12z"
                    />
                    <path
                        fill="#34A853"
                        d="M12 22c2.75 0 5.06-.91 6.75-2.47l-3.3-2.56c-.91.61-2.08.98-3.45.98-2.65 0-4.9-1.79-5.7-4.2H2.89v2.64A10 10 0 0 0 12 22z"
                    />
                    <path
                        fill="#4A90E2"
                        d="M6.3 13.75A6.01 6.01 0 0 1 6 12c0-.61.11-1.2.3-1.75V7.61H2.89A10 10 0 0 0 2 12c0 1.61.39 3.14 1.08 4.39l3.22-2.64z"
                    />
                    <path
                        fill="#FBBC05"
                        d="M12 6.05c1.5 0 2.84.52 3.9 1.53l2.92-2.92C17.05 3.01 14.75 2 12 2 8.09 2 4.71 4.24 2.89 7.61l3.41 2.64c.8-2.41 3.05-4.2 5.7-4.2z"
                    />
                </svg>
                <span>{{ mode === 'register' ? 'Đăng ký nhanh bằng Google' : 'Đăng nhập bằng Google' }}</span>
            </a>

            <div class="kb-auth-divider">
                <span>hoặc sử dụng email</span>
            </div>
        </div>

        <div class="kb-auth-forms-stack">
            <form
                class="kb-auth-form"
                :class="{ 'is-inactive': mode !== 'login' }"
                :inert="mode !== 'login'"
                @submit.prevent="submitLogin"
            >
                <div class="field">
                    <label for="kbLoginEmail">EMAIL *</label>
                    <input
                        id="kbLoginEmail"
                        v-model="loginForm.email"
                        type="email"
                        maxlength="255"
                        autocomplete="email"
                        placeholder="ban@congty.vn"
                        :aria-invalid="Boolean(loginForm.errors.email)"
                        required
                    >
                    <span v-if="loginForm.errors.email" class="field-error">{{ loginForm.errors.email }}</span>
                </div>

                <div class="field">
                    <label for="kbLoginPassword">MẬT KHẨU *</label>
                    <div class="kb-password-wrap">
                        <input
                            id="kbLoginPassword"
                            v-model="loginForm.password"
                            :type="showLoginPassword ? 'text' : 'password'"
                            maxlength="255"
                            autocomplete="current-password"
                            placeholder="Nhập mật khẩu của bạn"
                            :aria-invalid="Boolean(loginForm.errors.password)"
                            required
                        >
                        <button
                            type="button"
                            class="kb-password-toggle"
                            :aria-label="showLoginPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                            :title="showLoginPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                            @click="showLoginPassword = !showLoginPassword"
                        >
                            <svg v-if="showLoginPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
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
                    <span v-if="loginForm.errors.password" class="field-error">{{ loginForm.errors.password }}</span>
                </div>

                <div class="kb-auth-options">
                    <label class="kb-checkbox">
                        <input v-model="loginForm.remember" type="checkbox">
                        <span>Ghi nhớ đăng nhập</span>
                    </label>
                    <button type="button" class="kb-forgot-link" @click="switchMode('forgot')">
                        Quên mật khẩu?
                    </button>
                </div>

                <button class="btn kb-auth-submit" type="submit" :disabled="loginForm.processing">
                    {{ loginForm.processing ? 'Đang đăng nhập...' : 'Đăng nhập' }}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14m-6-6 6 6-6 6"></path>
                    </svg>
                </button>

                <p class="kb-auth-switch">
                    Chưa có tài khoản?
                    <button type="button" @click="switchMode('register')">Đăng ký ngay</button>
                </p>
            </form>

            <form
                class="kb-auth-form"
                :class="{ 'is-inactive': mode !== 'register' }"
                :inert="mode !== 'register'"
                @submit.prevent="submitRegister"
            >
                <div class="form-row">
                    <div class="field">
                        <label for="kbRegisterName">HỌ VÀ TÊN *</label>
                        <input
                            id="kbRegisterName"
                            v-model="registerForm.name"
                            type="text"
                            maxlength="255"
                            autocomplete="name"
                            placeholder="Nguyễn Văn A"
                            :aria-invalid="Boolean(registerForm.errors.name)"
                            required
                        >
                        <span v-if="registerForm.errors.name" class="field-error">{{ registerForm.errors.name }}</span>
                    </div>

                    <div class="field">
                        <label for="kbRegisterPhone">SỐ ĐIỆN THOẠI *</label>
                        <input
                            id="kbRegisterPhone"
                            v-model="registerForm.phone"
                            type="tel"
                            inputmode="tel"
                            maxlength="30"
                            pattern="[0-9+\s().\-]{8,30}"
                            title="Số điện thoại gồm 8-30 ký tự số"
                            autocomplete="tel"
                            placeholder="09xx xxx xxx"
                            :aria-invalid="Boolean(registerForm.errors.phone)"
                            required
                        >
                        <span v-if="registerForm.errors.phone" class="field-error">{{ registerForm.errors.phone }}</span>
                    </div>
                </div>

                <div class="field">
                    <label for="kbRegisterEmail">EMAIL *</label>
                    <input
                        id="kbRegisterEmail"
                        v-model="registerForm.email"
                        type="email"
                        maxlength="255"
                        autocomplete="email"
                        placeholder="ban@congty.vn"
                        :aria-invalid="Boolean(registerForm.errors.email)"
                        required
                    >
                    <span v-if="registerForm.errors.email" class="field-error">{{ registerForm.errors.email }}</span>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="kbRegisterPassword">MẬT KHẨU *</label>
                        <div class="kb-password-wrap">
                            <input
                                id="kbRegisterPassword"
                                v-model="registerForm.password"
                                :type="showRegisterPassword ? 'text' : 'password'"
                                minlength="8"
                                maxlength="255"
                                autocomplete="new-password"
                                placeholder="Tối thiểu 8 ký tự"
                                :aria-invalid="Boolean(registerForm.errors.password)"
                                required
                            >
                            <button
                                type="button"
                                class="kb-password-toggle"
                                :aria-label="showRegisterPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                                :title="showRegisterPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                                @click="showRegisterPassword = !showRegisterPassword"
                            >
                                <svg v-if="showRegisterPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
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
                        <span v-if="registerForm.errors.password" class="field-error">{{ registerForm.errors.password }}</span>
                    </div>

                    <div class="field">
                        <label for="kbRegisterPasswordConfirm">XÁC NHẬN MẬT KHẨU *</label>
                        <div class="kb-password-wrap">
                            <input
                                id="kbRegisterPasswordConfirm"
                                v-model="registerForm.password_confirmation"
                                :type="showRegisterPassword ? 'text' : 'password'"
                                minlength="8"
                                maxlength="255"
                                autocomplete="new-password"
                                placeholder="Nhập lại mật khẩu"
                                required
                            >
                            <button
                                type="button"
                                class="kb-password-toggle"
                                :aria-label="showRegisterPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                                :title="showRegisterPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                                @click="showRegisterPassword = !showRegisterPassword"
                            >
                                <svg v-if="showRegisterPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
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
                    </div>
                </div>

                <button class="btn kb-auth-submit" type="submit" :disabled="registerForm.processing">
                    {{ registerForm.processing ? 'Đang tạo tài khoản...' : 'Tạo tài khoản' }}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14m-6-6 6 6-6 6"></path>
                    </svg>
                </button>

                <p class="kb-auth-switch">
                    Đã có tài khoản?
                    <button type="button" @click="switchMode('login')">Đăng nhập</button>
                </p>
            </form>

            <form
                class="kb-auth-form"
                :class="{ 'is-inactive': mode !== 'forgot' }"
                :inert="mode !== 'forgot'"
                @submit.prevent="submitForgot"
            >
                <p class="kb-auth-hint">
                    Nhập địa chỉ email đã đăng ký tài khoản. Chúng tôi sẽ gửi mã xác thực gồm 6 chữ số để bạn đặt lại mật khẩu mới.
                </p>

                <div class="field">
                    <label for="kbForgotEmail">EMAIL TÀI KHOẢN *</label>
                    <input
                        id="kbForgotEmail"
                        v-model="forgotForm.email"
                        type="email"
                        maxlength="255"
                        autocomplete="email"
                        placeholder="ban@congty.vn"
                        :aria-invalid="Boolean(forgotForm.errors.email)"
                        required
                    >
                    <span v-if="forgotForm.errors.email" class="field-error">{{ forgotForm.errors.email }}</span>
                </div>

                <button class="btn kb-auth-submit" type="submit" :disabled="forgotForm.processing">
                    {{ forgotForm.processing ? 'Đang gửi mã...' : 'Gửi mã xác thực qua Email' }}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14m-6-6 6 6-6 6"></path>
                    </svg>
                </button>

                <p class="kb-auth-switch">
                    Đã nhớ mật khẩu?
                    <button type="button" @click="switchMode('login')">Quay lại đăng nhập</button>
                </p>
            </form>

            <form
                class="kb-auth-form"
                :class="{ 'is-inactive': mode !== 'reset' }"
                :inert="mode !== 'reset'"
                @submit.prevent="submitReset"
            >
                <div class="form-row">
                    <div class="field">
                        <label for="kbResetEmail">EMAIL *</label>
                        <input
                            id="kbResetEmail"
                            v-model="resetForm.email"
                            type="email"
                            maxlength="255"
                            readonly
                        >
                        <span v-if="resetForm.errors.email" class="field-error">{{ resetForm.errors.email }}</span>
                    </div>

                    <div class="field">
                        <label for="kbResetCode">MÃ XÁC THỰC (6 SỐ) *</label>
                        <input
                            id="kbResetCode"
                            v-model="resetForm.code"
                            type="text"
                            inputmode="numeric"
                            maxlength="6"
                            placeholder="Nhập 6 chữ số"
                            :aria-invalid="Boolean(resetForm.errors.code)"
                            required
                        >
                        <span v-if="resetForm.errors.code" class="field-error">{{ resetForm.errors.code }}</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="kbResetPassword">MẬT KHẨU MỚI *</label>
                        <div class="kb-password-wrap">
                            <input
                                id="kbResetPassword"
                                v-model="resetForm.password"
                                :type="showResetPassword ? 'text' : 'password'"
                                minlength="8"
                                maxlength="255"
                                autocomplete="new-password"
                                placeholder="Tối thiểu 8 ký tự"
                                :aria-invalid="Boolean(resetForm.errors.password)"
                                required
                            >
                            <button
                                type="button"
                                class="kb-password-toggle"
                                :aria-label="showResetPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                                :title="showResetPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                                @click="showResetPassword = !showResetPassword"
                            >
                                <svg v-if="showResetPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
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
                        <span v-if="resetForm.errors.password" class="field-error">{{ resetForm.errors.password }}</span>
                    </div>

                    <div class="field">
                        <label for="kbResetPasswordConfirm">XÁC NHẬN MẬT KHẨU *</label>
                        <div class="kb-password-wrap">
                            <input
                                id="kbResetPasswordConfirm"
                                v-model="resetForm.password_confirmation"
                                :type="showResetPassword ? 'text' : 'password'"
                                minlength="8"
                                maxlength="255"
                                autocomplete="new-password"
                                placeholder="Nhập lại mật khẩu mới"
                                required
                            >
                        </div>
                    </div>
                </div>

                <button class="btn kb-auth-submit" type="submit" :disabled="resetForm.processing">
                    {{ resetForm.processing ? 'Đang cập nhật...' : 'Đặt lại mật khẩu & Đăng nhập' }}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14m-6-6 6 6-6 6"></path>
                    </svg>
                </button>

                <p class="kb-auth-switch">
                    Chưa nhận được mã?
                    <button type="button" @click="switchMode('forgot')">Gửi lại mã</button>
                    ·
                    <button type="button" @click="switchMode('login')">Quay lại đăng nhập</button>
                </p>
            </form>
        </div>
    </div>
</template>
