<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    email: string | null;
    validForMinutes: number;
    status: string | null;
}>();

const form = useForm({
    code: '',
});

const submit = (): void => {
    form.post('/admin/login/otp', {
        onFinish: () => form.reset('code'),
    });
};

const resend = (): void => {
    router.post('/admin/login/otp/resend');
};
</script>

<template>
    <Head title="Xác thực đăng nhập" />

    <div class="auth-page">
        <form class="auth-card" @submit.prevent="submit">
            <div class="auth-brand">
                <span class="mark">N</span>
                <div><b>KB TECH</b><small>ADMINISTRATION</small></div>
            </div>
            <h1>Nhập mã xác thực</h1>
            <p class="auth-hint">
                Mã gồm 6 chữ số đã được gửi tới <b>{{ props.email }}</b> và có hiệu lực trong {{ props.validForMinutes }} phút.
            </p>
            <p v-if="props.status" class="auth-success">{{ props.status }}</p>

            <div class="field">
                <label for="adminOtp">Mã OTP</label>
                <input
                    id="adminOtp"
                    v-model="form.code"
                    class="auth-otp"
                    inputmode="numeric"
                    maxlength="6"
                    autocomplete="one-time-code"
                    required
                    autofocus
                >
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
