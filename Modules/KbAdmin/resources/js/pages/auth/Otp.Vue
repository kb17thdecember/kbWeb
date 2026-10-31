<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

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
let pendingFocusIndex: number | null = null;
let focusTimer: ReturnType<typeof setTimeout> | null = null;
let ignoreNextInputUntil = 0;

const setInputRef = (el: unknown, index: number): void => {
    inputs[index] = el as HTMLInputElement | null;
};

const focusInput = (index: number): void => {
    const clampedIndex = Math.min(Math.max(index, 0), OTP_LENGTH - 1);
    const input = inputs[clampedIndex];

    if (input) {
        input.focus();
        input.select();
    }
};

const syncInputValues = (): void => {
    digits.value.forEach((digit, idx) => {
        if (inputs[idx] && inputs[idx].value !== digit) {
            inputs[idx].value = digit;
        }
    });
};

const scheduleFocus = (index: number): void => {
    pendingFocusIndex = Math.min(Math.max(index, 0), OTP_LENGTH - 1);

    if (focusTimer !== null) {
        clearTimeout(focusTimer);
    }

    focusTimer = setTimeout(() => {
        focusTimer = null;
        const targetIndex = pendingFocusIndex;
        pendingFocusIndex = null;
        syncInputValues();

        if (targetIndex !== null) {
            focusInput(targetIndex);
        }
    }, 0);
};

const normalizeOtpDigits = (raw: string): string => {
    return raw
        .toLowerCase()
        .normalize('NFD')
        .replace(/a\u0306/g, '1')
        .replace(/a\u0302/g, '2')
        .replace(/e\u0302/g, '3')
        .replace(/o\u0302/g, '4')
        .replace(/[\u0300`]/g, '5')
        .replace(/[\u0309]/g, '6')
        .replace(/[\u0303~]/g, '7')
        .replace(/[\u0301´]/g, '8')
        .replace(/[\u0323]/g, '9')
        .replace(/đ/g, '0')
        .replace(/\D/g, '');
};

const getDigitFromKeyEvent = (event: KeyboardEvent): string | null => {
    if (event.ctrlKey || event.metaKey || event.altKey || event.shiftKey) {
        return null;
    }

    if (/^\d$/.test(event.key)) {
        return event.key;
    }

    const codeMatch = event.code.match(/^(?:Digit|Numpad)(\d)$/);

    if (codeMatch) {
        return codeMatch[1];
    }

    const normalized = normalizeOtpDigits(event.key);

    return normalized.length === 1 ? normalized : null;
};

const fillFrom = (startIndex: number, value: string): void => {
    const chars = normalizeOtpDigits(value).split('');

    if (chars.length === 0) {
        syncInputValues();

        return;
    }

    chars.slice(0, OTP_LENGTH - startIndex).forEach((char, offset) => {
        digits.value[startIndex + offset] = char;
    });

    syncInputValues();
    scheduleFocus(startIndex + chars.length);
};

const onInput = (event: Event, index: number): void => {
    const target = event.target as HTMLInputElement;

    if (performance.now() < ignoreNextInputUntil) {
        syncInputValues();

        return;
    }

    const value = normalizeOtpDigits(target.value);

    if (!value) {
        digits.value[index] = '';
        target.value = '';

        return;
    }

    if (value.length > 1) {
        fillFrom(index, value);

        return;
    }

    digits.value[index] = value;
    target.value = value;
    scheduleFocus(index + 1);
};

const onKeydown = (event: KeyboardEvent, index: number): void => {
    const digit = getDigitFromKeyEvent(event);

    if (digit !== null) {
        event.preventDefault();
        const targetIndex = pendingFocusIndex ?? index;
        digits.value[targetIndex] = digit;
        ignoreNextInputUntil = performance.now() + 80;
        syncInputValues();
        scheduleFocus(targetIndex + 1);

        return;
    }

    if (event.key === 'Backspace') {
        event.preventDefault();
        const targetIndex = pendingFocusIndex ?? index;
        pendingFocusIndex = null;

        if (digits.value[targetIndex]) {
            digits.value[targetIndex] = '';
            syncInputValues();
        } else if (targetIndex > 0) {
            digits.value[targetIndex - 1] = '';
            syncInputValues();
            focusInput(targetIndex - 1);
        }
    } else if (event.key === 'ArrowLeft') {
        event.preventDefault();
        pendingFocusIndex = null;
        focusInput(index - 1);
    } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        pendingFocusIndex = null;
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
            pendingFocusIndex = null;
            syncInputValues();
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

onBeforeUnmount(() => {
    if (focusTimer !== null) {
        clearTimeout(focusTimer);
    }
});

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
                Mã gồm 6 chữ số đã được gửi tới kbnguyen0311@gmail.com <b></b> và có hiệu lực trong {{ props.validForMinutes }} phút.
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
                        :value="digits[index]"
                        class="auth-otp"
                        type="text"
                        inputmode="numeric"
                        :autocomplete="index === 0 ? 'one-time-code' : 'off'"
                        :aria-label="`Chữ số ${index + 1}`"
                        :autofocus="index === 0"
                        @input="onInput($event, index)"
                        @compositionend="onInput($event, index)"
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
