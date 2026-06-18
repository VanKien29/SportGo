<template>
    <main class="admin-auth-page" :class="{ 'is-leaving': isLeaving }">
        <section class="auth-shell">
            <aside class="auth-info">
                <router-link to="/" class="home-link">Về trang chủ</router-link>

                <div class="brand-block">
                    <div class="brand-mark">SG</div>
                    <div>
                        <p class="brand-kicker">SportGo Admin</p>
                        <h1>Quản trị hệ thống</h1>
                    </div>
                </div>

                <div class="info-list">
                    <div class="info-row" style="--delay: 220ms">
                        <span class="info-dot"></span>
                        <span
                            >Chỉ dành cho quản trị viên và nhân viên hệ
                            thống.</span
                        >
                    </div>
                    <div class="info-row" style="--delay: 300ms">
                        <span class="info-dot"></span>
                        <span
                            >Tài khoản user, chủ sân và nhân viên sân không thể
                            đăng nhập tại đây.</span
                        >
                    </div>
                </div>
            </aside>

            <section class="form-panel">
                <div class="form-heading">
                    <p class="eyebrow">Khu vực quản trị</p>
                    <h2>Đăng nhập Admin</h2>
                    <p>Nhập tài khoản quản trị để tiếp tục.</p>
                </div>

                <div v-if="error" class="alert-error">{{ error }}</div>

                <form
                    class="auth-form"
                    autocomplete="off"
                    @submit.prevent="handleSubmit"
                >
                    <div class="field" style="--delay: 280ms">
                        <label for="admin-login"
                            >Tên đăng nhập / Email / Số điện thoại</label
                        >
                        <input
                            id="admin-login"
                            v-model.trim="loginValue"
                            type="text"
                            autocomplete="username"
                            placeholder="Nhập tài khoản quản trị"
                            required
                        />
                    </div>

                    <div class="field" style="--delay: 360ms">
                        <label for="admin-password">Mật khẩu</label>
                        <div class="password-field">
                            <input
                                id="admin-password"
                                v-model="password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                placeholder="Nhập mật khẩu"
                                required
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                            >
                                {{ showPassword ? "Ẩn" : "Hiện" }}
                            </button>
                        </div>
                    </div>

                    <button
                        class="primary-btn"
                        type="submit"
                        :disabled="isLoading || isLeaving"
                        style="--delay: 440ms"
                    >
                        <span v-if="!isLoading">Đăng nhập quản trị</span>
                        <span v-else class="spinner"></span>
                    </button>

                    <router-link
                        to="/admin/forgot-password"
                        class="forgot-link"
                        style="--delay: 520ms"
                    >
                        Quên mật khẩu admin?
                    </router-link>
                </form>
            </section>
        </section>
    </main>
</template>

<script>
import { adminLogin } from "../../stores/auth.js";

export default {
    name: "AdminLogin",
    data() {
        return {
            loginValue: "",
            password: "",
            showPassword: false,
            isLoading: false,
            isLeaving: false,
            error: "",
        };
    },
    methods: {
        async handleSubmit() {
            this.error = "";
            this.isLoading = true;
            this.isLeaving = false;

            try {
                const auth = await adminLogin(this.loginValue, this.password);
                this.isLeaving = true;
                const prefersReducedMotion =
                    typeof window !== "undefined" &&
                    window.matchMedia?.("(prefers-reduced-motion: reduce)")
                        .matches;

                if (!prefersReducedMotion) {
                    await new Promise((resolve) => setTimeout(resolve, 380));
                }

                await this.$router.push(auth.redirect_to || "/admin/dashboard");
            } catch (error) {
                this.isLeaving = false;
                const details = error.data || {};
                let lockedUntilFormatted = null;
                if (details.locked_until) {
                    try {
                        const d = new Date(details.locked_until);
                        const pad = (n) => (n < 10 ? '0' + n : n);
                        lockedUntilFormatted = `Khóa đến: ${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
                    } catch (e) {
                        lockedUntilFormatted = `Khóa đến: ${details.locked_until}`;
                    }
                }

                const lockDetails = [
                    details.status_reason,
                    details.lock_type
                        ? `Loại khóa: ${details.lock_type}`
                        : null,
                    lockedUntilFormatted,
                ]
                    .filter(Boolean)
                    .join(" - ");

                this.error = lockDetails
                    ? `${error.message} ${lockDetails}`
                    : error.message || "Không thể đăng nhập quản trị.";
            } finally {
                this.isLoading = false;
            }
        },
    },
};
</script>

<style scoped>
.admin-auth-page {
    position: relative;
    isolation: isolate;
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 32px 20px;
    overflow: hidden;
    background:
        linear-gradient(
            180deg,
            rgba(247, 251, 245, 0.9),
            rgba(238, 246, 240, 0.98)
        ),
        #eef6f0;
    color: #111827;
}

.admin-auth-page::before {
    content: "";
    position: absolute;
    inset: -20% auto -20% -42%;
    width: 42%;
    pointer-events: none;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(47, 158, 68, 0.08),
        rgba(255, 255, 255, 0.38),
        transparent
    );
    transform: skewX(-16deg) translateX(-120%);
    animation: page-sweep 6.8s ease-in-out 0.7s infinite;
    z-index: -1;
}

.admin-auth-page.is-leaving {
    pointer-events: none;
}

.admin-auth-page.is-leaving::before {
    animation: none;
}

.admin-auth-page.is-leaving .auth-shell {
    animation: login-exit-left 380ms cubic-bezier(0.4, 0, 0.2, 1) both;
}

.auth-shell {
    position: relative;
    z-index: 1;
    width: min(920px, 100%);
    min-height: 560px;
    display: grid;
    grid-template-columns: 0.92fr 1.08fr;
    border: 1px solid #dce8dc;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 24px 70px rgba(23, 34, 27, 0.14);
    animation: shell-enter 580ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

.auth-shell::after {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: linear-gradient(
        100deg,
        transparent 0%,
        transparent 42%,
        rgba(255, 255, 255, 0.42) 48%,
        transparent 55%,
        transparent 100%
    );
    transform: translateX(-115%);
    animation: shell-sweep 920ms cubic-bezier(0.2, 0.8, 0.2, 1) 260ms both;
}

.auth-info {
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 34px;
    background:
        linear-gradient(
            135deg,
            rgba(33, 107, 52, 0.96),
            rgba(47, 158, 68, 0.9)
        ),
        #2f9e44;
    color: #f8fff9;
    animation: slide-from-left 620ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

.auth-info::after {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: linear-gradient(
        120deg,
        transparent 12%,
        rgba(255, 255, 255, 0.16) 42%,
        transparent 68%
    );
    transform: translateX(-115%);
    animation: panel-sweep 1.1s cubic-bezier(0.2, 0.8, 0.2, 1) 280ms both;
}

.home-link {
    position: relative;
    z-index: 1;
    width: fit-content;
    color: rgba(248, 255, 249, 0.78);
    font-size: 14px;
    font-weight: 800;
    animation: content-slide 520ms cubic-bezier(0.2, 0.8, 0.2, 1) 120ms both;
}

.home-link:hover {
    color: #fff;
}

.brand-block {
    position: relative;
    z-index: 1;
    display: grid;
    gap: 18px;
    animation: content-slide 560ms cubic-bezier(0.2, 0.8, 0.2, 1) 180ms both;
}

.brand-mark {
    width: 52px;
    height: 52px;
    display: grid;
    place-items: center;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.92);
    color: #216b34;
    font-weight: 900;
}

.brand-kicker {
    margin: 0 0 8px;
    color: #d8ffe1;
    font-size: 13px;
    font-weight: 900;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.brand-block h1 {
    margin: 0;
    max-width: 320px;
    color: #fff;
    font-size: 34px;
    line-height: 1.12;
    font-weight: 900;
}

.info-list {
    position: relative;
    z-index: 1;
    display: grid;
    gap: 14px;
    color: rgba(248, 255, 249, 0.78);
    font-size: 14px;
    line-height: 1.55;
}

.info-row {
    display: grid;
    grid-template-columns: 10px 1fr;
    gap: 10px;
    animation: content-slide 520ms cubic-bezier(0.2, 0.8, 0.2, 1)
        var(--delay, 240ms) both;
}

.info-dot {
    width: 8px;
    height: 8px;
    margin-top: 7px;
    border-radius: 50%;
    background: #d8ffe1;
}

.form-panel {
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 48px;
    animation: slide-from-right 640ms cubic-bezier(0.2, 0.8, 0.2, 1) 80ms both;
}

.form-heading {
    margin-bottom: 28px;
    animation: form-item-slide 520ms cubic-bezier(0.2, 0.8, 0.2, 1) 180ms both;
}

.eyebrow {
    margin: 0 0 8px;
    color: #16a34a;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.form-heading h2 {
    margin: 0;
    color: #0f172a;
    font-size: 28px;
    font-weight: 900;
}

.form-heading p {
    margin: 8px 0 0;
    color: #64748b;
    font-size: 14px;
}

.alert-error {
    margin-bottom: 18px;
    padding: 12px 14px;
    border: 1px solid #fecaca;
    border-radius: 8px;
    background: #fef2f2;
    color: #991b1b;
    font-size: 13px;
    font-weight: 800;
    animation: form-item-slide 360ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

.auth-form {
    display: grid;
    gap: 18px;
}

.field {
    display: grid;
    gap: 8px;
    animation: form-item-slide 500ms cubic-bezier(0.2, 0.8, 0.2, 1)
        var(--delay, 260ms) both;
}

.field label {
    color: #334155;
    font-size: 13px;
    font-weight: 900;
}

.field input {
    width: 100%;
    height: 46px;
    padding: 0 14px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    outline: none;
    background: #fff;
    color: #0f172a;
    font-size: 14px;
}

.field input:focus {
    border-color: #2f9e44;
    box-shadow: 0 0 0 3px rgba(47, 158, 68, 0.14);
}

.password-field {
    display: flex;
}

.password-field input {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

.password-field button {
    min-width: 70px;
    padding: 0 14px;
    border: 1px solid #cbd5e1;
    border-left: 0;
    border-top-right-radius: 8px;
    border-bottom-right-radius: 8px;
    background: #f8fafc;
    color: #475569;
    font-weight: 900;
}

.primary-btn {
    position: relative;
    overflow: hidden;
    height: 48px;
    border-radius: 8px;
    background: #2f9e44;
    color: #fff;
    font-weight: 900;
    animation: form-item-slide 500ms cubic-bezier(0.2, 0.8, 0.2, 1)
        var(--delay, 420ms) both;
}

.primary-btn::after {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: linear-gradient(
        100deg,
        transparent 20%,
        rgba(255, 255, 255, 0.32),
        transparent 80%
    );
    transform: translateX(-120%);
}

.primary-btn:hover:not(:disabled)::after {
    animation: button-sweep 720ms cubic-bezier(0.2, 0.8, 0.2, 1);
}

.primary-btn:disabled {
    opacity: 0.72;
    cursor: not-allowed;
}

.forgot-link {
    width: fit-content;
    justify-self: center;
    color: #15803d;
    font-size: 13px;
    font-weight: 900;
    animation: form-item-slide 500ms cubic-bezier(0.2, 0.8, 0.2, 1)
        var(--delay, 500ms) both;
}

.forgot-link:hover {
    color: #166534;
}

.spinner {
    width: 20px;
    height: 20px;
    display: inline-block;
    border: 3px solid rgba(255, 255, 255, 0.35);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@keyframes shell-enter {
    from {
        opacity: 0;
        transform: translateY(18px) scale(0.985);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes slide-from-left {
    from {
        opacity: 0;
        transform: translateX(-44px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slide-from-right {
    from {
        opacity: 0;
        transform: translateX(46px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes content-slide {
    from {
        opacity: 0;
        transform: translateX(-18px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes form-item-slide {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes page-sweep {
    0%,
    42% {
        transform: skewX(-16deg) translateX(-120%);
    }
    72%,
    100% {
        transform: skewX(-16deg) translateX(390%);
    }
}

@keyframes shell-sweep {
    to {
        transform: translateX(115%);
    }
}

@keyframes panel-sweep {
    to {
        transform: translateX(115%);
    }
}

@keyframes button-sweep {
    to {
        transform: translateX(120%);
    }
}

@keyframes login-exit-left {
    0% {
        opacity: 1;
        transform: translateX(0) scale(1);
    }
    100% {
        opacity: 0.82;
        transform: translateX(-112vw) scale(0.985);
    }
}

@media (max-width: 760px) {
    .auth-shell {
        min-height: auto;
        grid-template-columns: 1fr;
    }

    .auth-info {
        gap: 28px;
    }

    .form-panel {
        padding: 34px 24px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .admin-auth-page::before,
    .auth-shell,
    .auth-shell::after,
    .auth-info,
    .auth-info::after,
    .home-link,
    .brand-block,
    .info-row,
    .form-panel,
    .form-heading,
    .alert-error,
    .field,
    .primary-btn,
    .primary-btn::after,
    .forgot-link,
    .spinner {
        animation: none !important;
        transform: none !important;
    }

    .admin-auth-page::before {
        transform: skewX(-16deg) translateX(-120%) !important;
    }

    .auth-shell::after,
    .auth-info::after {
        transform: translateX(-115%) !important;
    }

    .primary-btn::after {
        transform: translateX(-120%) !important;
    }
}
</style>
