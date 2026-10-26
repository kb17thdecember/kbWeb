<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
});

const submit = (): void => {
    form.post('/admin/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Đăng nhập quản trị" />

    <div class="auth-page">
        <form class="auth-card" @submit.prevent="submit">
            <div class="auth-brand">
                <span class="mark">N</span>
                <div><b>KB TECH</b><small>ADMINISTRATION</small></div>
            </div>
            <h1>Đăng nhập quản trị</h1>
            <p class="auth-hint">Nhập email và mật khẩu, sau đó chúng tôi sẽ gửi mã xác thực qua email.</p>

            <div class="field">
                <label for="adminEmail">Email</label>
                <input id="adminEmail" v-model="form.email" type="email" autocomplete="username" required autofocus>
                <span v-if="form.errors.email" class="auth-error">{{ form.errors.email }}</span>
            </div>
            <div class="field">
                <label for="adminPassword">Mật khẩu</label>
                <input id="adminPassword" v-model="form.password" type="password" autocomplete="current-password" required>
                <span v-if="form.errors.password" class="auth-error">{{ form.errors.password }}</span>
            </div>

            <button class="btn primary" type="submit" :disabled="form.processing">
                {{ form.processing ? 'Đang kiểm tra...' : 'Tiếp tục ↗' }}
            </button>
        </form>
    </div>
</template>
