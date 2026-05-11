<script setup>
import { ref } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

function submit() {
    form.post('/admin/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Đăng nhập Admin" />

    <div class="login-wrapper">
        <!-- Animated background -->
        <div class="bg-animation">
            <div class="bg-orb bg-orb-1"></div>
            <div class="bg-orb bg-orb-2"></div>
            <div class="bg-orb bg-orb-3"></div>
        </div>

        <div class="login-container">
            <!-- Logo -->
            <div class="login-header">
                <div class="logo-icon">
                    <svg class="logo-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <h1 class="login-title">PC Shop</h1>
                <p class="login-subtitle">Đăng nhập vào hệ thống quản trị</p>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="login-form">
                <!-- Error Alert -->
                <div v-if="form.errors.email" class="error-alert">
                    <svg class="error-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ form.errors.email }}</span>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label for="login-email" class="form-label">Email</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                        </svg>
                        <input
                            id="login-email"
                            v-model="form.email"
                            type="email"
                            class="form-input"
                            placeholder="admin@pcshop.vn"
                            autocomplete="email"
                            autofocus
                            required
                        />
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="login-password" class="form-label">Mật khẩu</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <input
                            id="login-password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            class="form-input form-input-password"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required
                        />
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="password-toggle"
                        >
                            <svg v-if="!showPassword" class="toggle-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg v-else class="toggle-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Remember -->
                <div class="form-options">
                    <label class="remember-label">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            class="remember-checkbox"
                        />
                        <span>Ghi nhớ đăng nhập</span>
                    </label>
                </div>

                <!-- Submit -->
                <button
                    type="submit"
                    class="submit-btn"
                    :disabled="form.processing"
                >
                    <svg v-if="form.processing" class="spinner" viewBox="0 0 24 24">
                        <circle class="spinner-track" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                        <path class="spinner-head" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    <span v-if="!form.processing">Đăng nhập</span>
                    <span v-else>Đang xử lý...</span>
                </button>
            </form>

            <!-- Footer -->
            <p class="login-footer">
                © {{ new Date().getFullYear() }} PC Shop — Hệ thống quản trị
            </p>
        </div>
    </div>
</template>

<style scoped>
.login-wrapper {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #020617;
    position: relative;
    overflow: hidden;
    padding: 1rem;
}

/* Animated background orbs */
.bg-animation {
    position: absolute;
    inset: 0;
    overflow: hidden;
    pointer-events: none;
}
.bg-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.15;
}
.bg-orb-1 {
    width: 500px;
    height: 500px;
    background: #06b6d4;
    top: -100px;
    right: -100px;
    animation: float1 15s ease-in-out infinite;
}
.bg-orb-2 {
    width: 400px;
    height: 400px;
    background: #8b5cf6;
    bottom: -80px;
    left: -80px;
    animation: float2 20s ease-in-out infinite;
}
.bg-orb-3 {
    width: 300px;
    height: 300px;
    background: #3b82f6;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    animation: float3 18s ease-in-out infinite;
}
@keyframes float1 { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-60px, 40px); } }
@keyframes float2 { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(50px, -30px); } }
@keyframes float3 { 0%, 100% { transform: translate(-50%, -50%) scale(1); } 50% { transform: translate(-50%, -50%) scale(1.2); } }

.login-container {
    position: relative;
    z-index: 10;
    width: 100%;
    max-width: 420px;
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.85));
    backdrop-filter: blur(40px);
    border: 1px solid rgba(100, 116, 139, 0.15);
    border-radius: 1.5rem;
    padding: 2.5rem;
    box-shadow:
        0 0 0 1px rgba(6, 182, 212, 0.05),
        0 25px 50px -12px rgba(0, 0, 0, 0.6),
        0 0 80px -20px rgba(6, 182, 212, 0.1);
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px) scale(0.96); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.login-header {
    text-align: center;
    margin-bottom: 2rem;
}
.logo-icon {
    width: 56px;
    height: 56px;
    margin: 0 auto 1rem;
    border-radius: 1rem;
    background: linear-gradient(135deg, #06b6d4, #3b82f6);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 30px rgba(6, 182, 212, 0.3);
}
.logo-svg {
    width: 28px;
    height: 28px;
    color: white;
}
.login-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #f1f5f9;
    margin-bottom: 0.375rem;
    letter-spacing: -0.025em;
}
.login-subtitle {
    font-size: 0.8125rem;
    color: #64748b;
}

.login-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.error-alert {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: 0.75rem;
    color: #fca5a5;
    font-size: 0.8125rem;
    animation: shake 0.5s cubic-bezier(0.36, 0.07, 0.19, 0.97);
}
@keyframes shake {
    10%, 90% { transform: translateX(-1px); }
    20%, 80% { transform: translateX(2px); }
    30%, 50%, 70% { transform: translateX(-4px); }
    40%, 60% { transform: translateX(4px); }
}
.error-icon {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    color: #ef4444;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.form-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.input-wrapper {
    position: relative;
}
.input-icon {
    position: absolute;
    left: 0.875rem;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    color: #475569;
    transition: color 0.2s;
    pointer-events: none;
}
.form-input {
    width: 100%;
    padding: 0.75rem 0.875rem 0.75rem 2.75rem;
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(51, 65, 85, 0.5);
    border-radius: 0.75rem;
    color: #e2e8f0;
    font-size: 0.875rem;
    transition: all 0.2s;
    outline: none;
}
.form-input-password {
    padding-right: 2.75rem;
}
.form-input::placeholder {
    color: #475569;
}
.form-input:focus {
    border-color: rgba(6, 182, 212, 0.5);
    box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1);
}
.form-input:focus + .input-icon,
.input-wrapper:focus-within .input-icon {
    color: #06b6d4;
}

.password-toggle {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    padding: 0.25rem;
    background: none;
    border: none;
    cursor: pointer;
    color: #475569;
    transition: color 0.2s;
}
.password-toggle:hover {
    color: #94a3b8;
}
.toggle-icon {
    width: 18px;
    height: 18px;
}

.form-options {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.remember-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    font-size: 0.8125rem;
    color: #94a3b8;
}
.remember-checkbox {
    width: 16px;
    height: 16px;
    border-radius: 4px;
    border: 1px solid rgba(71, 85, 105, 0.6);
    background: rgba(15, 23, 42, 0.8);
    accent-color: #06b6d4;
    cursor: pointer;
}

.submit-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.875rem;
    background: linear-gradient(135deg, #06b6d4, #3b82f6);
    color: white;
    font-size: 0.875rem;
    font-weight: 600;
    border: none;
    border-radius: 0.75rem;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(6, 182, 212, 0.3);
    margin-top: 0.25rem;
}
.submit-btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 8px 25px rgba(6, 182, 212, 0.4);
}
.submit-btn:active:not(:disabled) {
    transform: translateY(0);
}
.submit-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.spinner {
    width: 20px;
    height: 20px;
    animation: spin 1s linear infinite;
}
.spinner-track {
    opacity: 0.25;
}
.spinner-head {
    opacity: 0.75;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}

.login-footer {
    text-align: center;
    margin-top: 1.5rem;
    font-size: 0.6875rem;
    color: #475569;
}
</style>
