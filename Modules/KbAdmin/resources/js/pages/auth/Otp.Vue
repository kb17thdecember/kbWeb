<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';

const props = defineProps<{
    email: string | null;
    validForMinutes: number;
    status: string | null;
}>();

const OTP_LENGTH = 6;

const form = useForm({
    code: '',
});

const digits = ref<string[]>(Array.from({ length: OTP_LENGTH }, () => ''));
const inputs: (HTMLInputElement | null)[] = [];

const setInputRef = (el: unknown, index: number): void => {
    inputs[index] = el as HTMLInputElement | null;
};

const focusInput = (index: number): void => {
    inputs[Math.min(Math.max(index, 0), OTP_LENGTH - 1)]?.focus();
};

const fillFrom = (startIndex: number, value: string): void => {
    const chars = value.replace(/\D/g, '').split('');

    chars.slice(0, OTP_LENGTH - startIndex).forEach((char, offset) => {
        digits.value[startIndex + offset] = char;
    });

    focusInput(startIndex + chars.length);
};

const onInput = (index: number): void => {
    const value = digits.value[index].replace(/\D/g, '');

    digits.value[index] = value.slice(0, 1);

    if (value.length > 1) {
        fillFrom(index, value);
    } else if (value) {
        focusInput(index + 1);
    }
};

const onKeydown = (event: KeyboardEvent, index: number): void => {
    if (event.key === 'Backspace' && !digits.value[index]) {
        focusInput(index - 1);
    } else if (event.key === 'ArrowLeft') {
        focusInput(index - 1);
    } else if (event.key === 'ArrowRight') {
        focusInput(index + 1);
    }
};

const onPaste = (event: ClipboardEvent): void => {
    fillFrom(0, event.clipboardData?.getData('text') ?? '');
};

const submit = (): void => {
    form.code = digits.value.join('');

    if (form.code.length !== OTP_LENGTH) {
        form.setError('code', 'Vui lòng nhập đủ 6 chữ số.');

        return;
    }

    form.post('/admin/login/otp', {
        onFinish: () => {
            digits.value = digits.value.map(() => '');
            form.reset('code');
            focusInput(0);
        },
    });
};

watch(digits, (value) => {
    if (value.every((digit) => digit !== '')) {
        submit();
    }
}, { deep: true });

onMounted(() => focusInput(0));

const resend = (): void => {
    router.post('/admin/login/otp/resend');
};
</script>

<template>
    <Head title="Xác thực đăng nhập" />

    <div class="auth-page">
        <form class="auth-card" @submit.prevent="submit">
            <div class="auth-brand">
                <span class="mark">HM</span>
                <div><b>HVAC Mart</b><small>ADMINISTRATION</small></div>
            </div>
            <h1>Nhập mã xác thực</h1>
            <p class="auth-hint">
                Mã gồm 6 chữ số đã được gửi tới <b>{{ props.email }}</b> và có hiệu lực trong {{ props.validForMinutes }} phút.
            </p>
            <p v-if="props.status" class="auth-success">{{ props.status }}</p>

            <div class="field">
                <label for="adminOtp0">Mã OTP</label>
                <div class="auth-otp-group" @paste.prevent="onPaste">
                    <input
                        v-for="(_, index) in digits"
                        :id="`adminOtp${index}`"
                        :key="index"
                        :ref="(el) => setInputRef(el, index)"
                        v-model="digits[index]"
                        class="auth-otp"
                        inputmode="numeric"
                        maxlength="1"
                        :autocomplete="index === 0 ? 'one-time-code' : 'off'"
                        :aria-label="`Chữ số ${index + 1}`"
                        :autofocus="index === 0"
                        @input="onInput(index)"
                        @keydown="onKeydown($event, index)"
                        @focus="($event.target as HTMLInputElement).select()"
                    >
                </div>
                <span v-if="form.errors.code" class="auth-error">{{ form.errors.code }}</span>
            </div>

            <button class="btn primary" type="submit" :disabled="form.processing">
                {{ form.processing ? 'Đang xác thực...' : 'Đăng nhập' }} <FontAwesomeIcon :icon="['fas', 'arrow-right']" class="icon-ne" />
            </button>
            <button class="btn" type="button" @click="resend">Gửi lại mã</button>
            <a class="auth-back" href="/admin/login"><FontAwesomeIcon :icon="['fas', 'arrow-left']" /> Quay lại</a>
        </form>
    </div>
</template>
