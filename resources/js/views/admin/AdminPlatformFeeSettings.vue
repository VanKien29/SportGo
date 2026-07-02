<template>
    <section class="settings-page">
        <header class="page-head">
            <div>
                <p class="eyebrow">Phí nền tảng</p>
                <h2>Cài đặt nhắc phí</h2>
            </div>
        </header>

        <div v-if="toast" class="toast" :class="toastType">{{ toast }}</div>
        <div v-if="loading" class="state">Đang tải cài đặt...</div>

        <form v-else class="panel form" @submit.prevent="saveSettings">
            <label>
                Gửi nhắc trước hạn thanh toán (ngày) *
                <input
                    v-model.number="settings.default_due_days"
                    type="number"
                    min="1"
                    max="30"
                    step="1"
                    required
                />
                <small v-if="fieldError('default_due_days')" class="field-error">
                    {{ fieldError("default_due_days") }}
                </small>
            </label>

            <label>
                Lý do khóa cụm sân mặc định *
                <textarea
                    v-model.trim="settings.lock_reason"
                    rows="4"
                    minlength="3"
                    maxlength="500"
                    required
                ></textarea>
                <span class="field-meta">{{ settings.lock_reason.length }}/500</span>
                <small v-if="fieldError('lock_reason')" class="field-error">
                    {{ fieldError("lock_reason") }}
                </small>
            </label>

            <div class="actions">
                <button class="btn primary icon-text" type="submit" :disabled="saving || !hasUnsavedChanges">
                    <AppIcon name="check" size="17" />
                    <span>{{ saving ? "Đang lưu..." : "Lưu cài đặt" }}</span>
                </button>
                <button class="btn secondary" type="button" :disabled="saving || !hasUnsavedChanges" @click="requestCancelChanges">
                    Hủy thay đổi
                </button>
                <button class="btn secondary" type="button" :disabled="saving" @click="requestReset">
                    Khôi phục mặc định
                </button>
            </div>
        </form>

        <div v-if="confirmDialog" class="modal-backdrop" @click.self="closeConfirmDialog">
            <div class="modal" role="alertdialog" aria-modal="true" aria-labelledby="settings-confirm-title">
                <header class="modal-head">
                    <h3 id="settings-confirm-title">{{ confirmDialog.title }}</h3>
                    <button class="icon-close" type="button" title="Đóng" aria-label="Đóng" @click="closeConfirmDialog">
                        <AppIcon name="x" size="18" />
                    </button>
                </header>
                <p>{{ confirmDialog.message }}</p>
                <footer class="modal-actions">
                    <button class="btn secondary" type="button" @click="closeConfirmDialog">Quay lại</button>
                    <button class="btn danger icon-text" type="button" @click="confirmDialogAction">
                        <AppIcon name="check" size="17" />
                        <span>{{ confirmDialog.confirmLabel }}</span>
                    </button>
                </footer>
            </div>
        </div>
    </section>
</template>

<script>
import AppIcon from "../../components/AppIcon.vue";
import { api } from "../../services/api.js";

const defaultSettings = () => ({
    default_due_days: 7,
    lock_reason: "Quá hạn phí duy trì hệ thống",
});

export default {
    name: "AdminPlatformFeeSettings",
    components: { AppIcon },
    data() {
        return {
            settings: defaultSettings(),
            savedSettings: null,
            fieldErrors: {},
            loading: true,
            saving: false,
            confirmDialog: null,
            toast: "",
            toastType: "success",
        };
    },
    computed: {
        hasUnsavedChanges() {
            return (
                this.savedSettings !== null &&
                JSON.stringify(this.settings) !== JSON.stringify(this.savedSettings)
            );
        },
    },
    mounted() {
        this.loadSettings();
    },
    methods: {
        async loadSettings() {
            this.loading = true;
            try {
                this.settings = await api("/api/admin/platform-fee-settings");
                this.savedSettings = { ...this.settings };
            } catch {
                this.show("Không thể tải cài đặt nhắc phí.", "error");
            } finally {
                this.loading = false;
            }
        },
        validateSettings() {
            const errors = {};
            const days = Number(this.settings.default_due_days);
            const reason = String(this.settings.lock_reason || "").trim();

            if (!Number.isInteger(days) || days < 1 || days > 30) {
                errors.default_due_days = ["Số ngày nhắc trước hạn phải là số nguyên từ 1 đến 30."];
            }
            if (reason.length < 3 || reason.length > 500) {
                errors.lock_reason = ["Lý do khóa cụm sân phải có từ 3 đến 500 ký tự."];
            }

            this.fieldErrors = errors;
            return Object.keys(errors).length === 0;
        },
        async saveSettings() {
            if (!this.validateSettings() || this.saving) return;
            this.saving = true;
            try {
                const response = await api("/api/admin/platform-fee-settings", {
                    method: "PUT",
                    body: JSON.stringify({
                        ...this.settings,
                        lock_reason: this.settings.lock_reason.trim(),
                    }),
                });
                this.settings = response.data;
                this.savedSettings = { ...response.data };
                this.fieldErrors = {};
                this.show(response.message || "Đã lưu cài đặt nhắc phí.");
            } catch (error) {
                this.fieldErrors = error.validation?.errors || error.data?.errors || {};
                this.show("Không thể lưu cài đặt. Vui lòng kiểm tra dữ liệu.", "error");
            } finally {
                this.saving = false;
            }
        },
        requestCancelChanges() {
            if (!this.hasUnsavedChanges) return;
            this.confirmDialog = {
                type: "cancel",
                title: "Hủy thay đổi?",
                message: "Các thay đổi cấu hình chưa lưu sẽ bị bỏ.",
                confirmLabel: "Hủy thay đổi",
            };
        },
        requestReset() {
            this.confirmDialog = {
                type: "reset",
                title: "Khôi phục mặc định?",
                message: "Cấu hình nhắc phí sẽ được đặt về mặc định và lưu ngay.",
                confirmLabel: "Khôi phục",
            };
        },
        closeConfirmDialog() {
            this.confirmDialog = null;
        },
        async confirmDialogAction() {
            const type = this.confirmDialog?.type;
            this.confirmDialog = null;
            if (type === "cancel") {
                this.settings = { ...this.savedSettings };
                this.fieldErrors = {};
                this.show("Đã hủy các thay đổi chưa lưu.");
            }
            if (type === "reset") {
                this.settings = defaultSettings();
                await this.saveSettings();
            }
        },
        fieldError(field) {
            return this.fieldErrors[field]?.[0] || "";
        },
        show(message, type = "success") {
            this.toast = message;
            this.toastType = type;
            setTimeout(() => {
                this.toast = "";
            }, 3500);
        },
    },
};
</script>

<style scoped>
.settings-page { display:grid; gap:16px; max-width:900px; }
.page-head { display:flex; align-items:center; justify-content:space-between; }
.page-head h2, .modal-head h3, p { margin:0; }
.eyebrow { margin:0 0 4px; color:#16a34a; font-size:12px; font-weight:900; text-transform:uppercase; }
.panel, .modal { border:1px solid #e2e8f0; border-radius:8px; background:#fff; }
.form { display:grid; gap:16px; padding:20px; }
label { display:flex; flex-direction:column; gap:6px; color:#334155; font-weight:800; }
input, textarea { width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:10px 12px; font:inherit; }
.field-meta { justify-self:end; color:#64748b; font-size:12px; font-weight:700; }
.field-error { color:#dc2626; font-size:12px; font-weight:850; }
.actions, .modal-head, .modal-actions, .icon-text { display:flex; align-items:center; gap:10px; }
.actions { flex-wrap:wrap; }
.btn { border:0; border-radius:8px; padding:10px 14px; font-weight:900; cursor:pointer; }
.btn.primary { background:#16a34a; color:#fff; }
.btn.secondary { background:#e2e8f0; color:#334155; }
.btn.danger { background:#dc2626; color:#fff; }
.btn:disabled { cursor:not-allowed; opacity:.5; }
.toast, .state { border-radius:8px; padding:12px 14px; font-weight:800; }
.toast.success { background:#ecfdf5; color:#047857; }
.toast.error { background:#fef2f2; color:#b91c1c; }
.state { border:1px solid #e2e8f0; background:#fff; color:#64748b; }
.modal-backdrop { position:fixed; inset:0; z-index:950; display:grid; place-items:center; padding:20px; background:rgba(15,23,42,.55); }
.modal { width:min(500px,calc(100vw - 32px)); }
.modal-head { justify-content:space-between; padding:18px 20px; border-bottom:1px solid #e2e8f0; }
.icon-close { display:inline-grid; width:32px; height:32px; place-items:center; border:1px solid #dbe3ea; border-radius:8px; background:#fff; cursor:pointer; }
.modal > p { padding:20px; color:#475569; line-height:1.55; }
.modal-actions { justify-content:flex-end; padding:14px 20px; border-top:1px solid #e2e8f0; background:#f8fafc; }
@media (max-width:640px) { .actions { display:grid; } .actions .btn { width:100%; } }
</style>
