<template>
    <div class="system-profile-page">
        <section class="profile-hero">
            <div>
                <p class="eyebrow">Cấu hình hệ thống</p>
                <h1>Thông tin cơ bản hệ thống</h1>
                <p>
                    Quản lý tên hệ thống, thông tin pháp lý, người đại diện,
                    thông tin liên hệ và logo dùng trên các màn vận hành.
                </p>
            </div>
            <button type="button" class="ghost-button" :disabled="loading" @click="loadProfile">
                <span>↻</span>
                Tải lại
            </button>
        </section>

        <div v-if="message" class="alert success">{{ message }}</div>
        <div v-if="error" class="alert error">{{ error }}</div>

        <div v-if="loading" class="profile-grid">
            <div v-for="item in 3" :key="item" class="profile-card skeleton-card">
                <span></span>
                <strong></strong>
                <p></p>
                <p></p>
                <p></p>
            </div>
        </div>

        <form v-else class="profile-form" @submit.prevent="submit">
            <section class="profile-card identity-card">
                <div class="card-heading">
                    <div>
                        <p class="eyebrow">Nhận diện</p>
                        <h2>Tên và logo</h2>
                    </div>
                    <div class="logo-preview" :class="{ empty: !logoPreview }">
                        <img v-if="logoPreview" :src="logoPreview" alt="Logo hệ thống" />
                        <span v-else>SG</span>
                    </div>
                </div>

                <div class="form-grid two">
                    <label class="field">
                        <span>Tên hệ thống <b>*</b></span>
                        <input v-model.trim="form.system_name" type="text" placeholder="SportGo" />
                    </label>
                    <label class="field">
                        <span>Tên viết tắt</span>
                        <input v-model.trim="form.company_short_name" type="text" placeholder="SportGo" />
                    </label>
                    <label class="field file-field">
                        <span>Upload logo</span>
                        <input type="file" accept=".jpg,.jpeg,.png,.webp,.svg" @change="onFileChange($event, 'logo')" />
                    </label>
                    <label class="field">
                        <span>Logo URL</span>
                        <input v-model.trim="form.logo_url" type="text" placeholder="/storage/system/logo.png" />
                    </label>
                    <label class="field file-field">
                        <span>Upload favicon</span>
                        <input type="file" accept=".ico,.jpg,.jpeg,.png,.webp,.svg,image/*" @change="onFileChange($event, 'favicon')" />
                    </label>
                    <label class="field">
                        <span>Favicon URL</span>
                        <input v-model.trim="form.favicon_url" type="text" placeholder="/storage/system/favicon.ico" />
                    </label>
                </div>
            </section>

            <section class="profile-card">
                <div class="card-heading">
                    <div>
                        <p class="eyebrow">Pháp lý</p>
                        <h2>Thông tin công ty</h2>
                    </div>
                </div>

                <div class="form-grid two">
                    <label class="field wide">
                        <span>Tên công ty <b>*</b></span>
                        <input v-model.trim="form.company_name" type="text" placeholder="Công ty TNHH SportGo" />
                    </label>
                    <label class="field">
                        <span>Mã số thuế</span>
                        <input v-model.trim="form.tax_code" type="text" placeholder="0101234567" />
                    </label>
                    <label class="field">
                        <span>Mã số kinh doanh</span>
                        <input v-model.trim="form.business_code" type="text" placeholder="0101234567" />
                    </label>
                    <label class="field">
                        <span>Số giấy phép kinh doanh</span>
                        <input v-model.trim="form.business_license_number" type="text" placeholder="GP-..." />
                    </label>
                    <label class="field">
                        <span>Người đại diện <b>*</b></span>
                        <input v-model.trim="form.representative_name" type="text" placeholder="Nguyễn Văn A" />
                    </label>
                    <label class="field">
                        <span>Chức vụ</span>
                        <input v-model.trim="form.representative_title" type="text" placeholder="Giám đốc" />
                    </label>
                    <label class="field wide">
                        <span>Địa chỉ công ty <b>*</b></span>
                        <textarea v-model.trim="form.company_address" rows="3" placeholder="Số nhà, phường/xã, tỉnh/thành phố"></textarea>
                    </label>
                </div>
            </section>

            <section class="profile-card">
                <div class="card-heading">
                    <div>
                        <p class="eyebrow">Liên hệ</p>
                        <h2>Kênh hỗ trợ</h2>
                    </div>
                </div>

                <div class="form-grid three">
                    <label class="field">
                        <span>Email hỗ trợ</span>
                        <input v-model.trim="form.support_email" type="email" placeholder="support@sportgo.vn" />
                    </label>
                    <label class="field">
                        <span>Số điện thoại hỗ trợ</span>
                        <input v-model.trim="form.support_phone" type="text" placeholder="0900000000" />
                    </label>
                    <label class="field">
                        <span>Website</span>
                        <input v-model.trim="form.website_url" type="text" placeholder="https://sportgo.vn" />
                    </label>
                </div>
            </section>

            <section class="profile-card summary-card">
                <div>
                    <p class="eyebrow">Kiểm tra nhanh</p>
                    <h2>{{ form.system_name || "SportGo" }}</h2>
                    <p>{{ form.company_name || "Chưa nhập tên công ty" }}</p>
                    <p>{{ form.company_address || "Chưa nhập địa chỉ công ty" }}</p>
                </div>
                <button type="submit" class="primary-button" :disabled="saving">
                    {{ saving ? "Đang lưu..." : "Lưu thông tin hệ thống" }}
                </button>
            </section>
        </form>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from "vue";
import { fetchSystemProfile, saveSystemProfile } from "../../services/adminSystemProfile.service.js";
import { applySystemProfile } from "../../stores/systemProfile.js";

const emptyForm = {
    system_name: "",
    company_name: "",
    company_short_name: "",
    representative_name: "",
    representative_title: "",
    company_address: "",
    tax_code: "",
    business_code: "",
    business_license_number: "",
    support_email: "",
    support_phone: "",
    website_url: "",
    logo_url: "",
    favicon_url: "",
};

const form = reactive({ ...emptyForm });
const files = reactive({ logo: null, favicon: null });
const localLogoPreview = ref("");
const loading = ref(false);
const saving = ref(false);
const error = ref("");
const message = ref("");

const logoPreview = computed(() => localLogoPreview.value || form.logo_url);

function assignForm(data) {
    Object.keys(emptyForm).forEach((key) => {
        form[key] = data?.[key] ?? "";
    });
}

async function loadProfile() {
    loading.value = true;
    error.value = "";
    message.value = "";

    try {
        const data = await fetchSystemProfile();
        assignForm(data);
    } catch (err) {
        error.value = err.message || "Không thể tải thông tin hệ thống.";
    } finally {
        loading.value = false;
    }
}

function onFileChange(event, type) {
    const file = event.target.files?.[0] || null;
    files[type] = file;

    if (type === "logo") {
        if (localLogoPreview.value) URL.revokeObjectURL(localLogoPreview.value);
        localLogoPreview.value = file ? URL.createObjectURL(file) : "";
    }
}

function buildFormData() {
    const payload = new FormData();

    Object.keys(emptyForm).forEach((key) => {
        payload.append(key, form[key] ?? "");
    });

    if (files.logo) payload.append("logo_file", files.logo);
    if (files.favicon) payload.append("favicon_file", files.favicon);

    return payload;
}

async function submit() {
    saving.value = true;
    error.value = "";
    message.value = "";

    try {
        const response = await saveSystemProfile(buildFormData());
        assignForm(response.data);
        applySystemProfile(response.data);
        files.logo = null;
        files.favicon = null;
        if (localLogoPreview.value) {
            URL.revokeObjectURL(localLogoPreview.value);
            localLogoPreview.value = "";
        }
        message.value = response.message || "Đã lưu thông tin hệ thống.";
    } catch (err) {
        error.value = err.message || "Không thể lưu thông tin hệ thống.";
    } finally {
        saving.value = false;
    }
}

onMounted(loadProfile);
onBeforeUnmount(() => {
    if (localLogoPreview.value) URL.revokeObjectURL(localLogoPreview.value);
});
</script>

<style scoped>
.system-profile-page {
    display: grid;
    gap: 18px;
}

.profile-hero,
.profile-card {
    border: 1px solid #d9e8dc;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 12px 32px rgba(22, 101, 52, 0.06);
}

.profile-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 22px 24px;
}

.profile-hero h1,
.profile-card h2 {
    margin: 0;
    color: #15251b;
}

.profile-hero p,
.summary-card p {
    margin: 6px 0 0;
    color: #657568;
}

.eyebrow {
    margin: 0 0 6px;
    color: #0f8f4d;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.profile-form,
.profile-grid {
    display: grid;
    gap: 16px;
}

.profile-card {
    padding: 20px;
}

.card-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
}

.logo-preview {
    display: grid;
    width: 92px;
    height: 92px;
    place-items: center;
    overflow: hidden;
    border: 1px solid #bfe5cb;
    border-radius: 18px;
    background: #f0fdf4;
    color: #15803d;
    font-size: 26px;
    font-weight: 900;
}

.logo-preview img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 10px;
}

.form-grid {
    display: grid;
    gap: 16px;
}

.form-grid.two {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.form-grid.three {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.field {
    display: grid;
    gap: 8px;
    color: #405448;
    font-weight: 700;
}

.field.wide {
    grid-column: 1 / -1;
}

.field span {
    font-size: 13px;
}

.field b {
    color: #dc2626;
}

.field input,
.field textarea {
    width: 100%;
    border: 1px solid #cfe1d3;
    border-radius: 10px;
    background: #fff;
    color: #14251b;
    font: inherit;
    font-weight: 700;
    outline: none;
    padding: 12px 13px;
    transition: border-color 0.16s ease, box-shadow 0.16s ease;
}

.field textarea {
    resize: vertical;
}

.field input:focus,
.field textarea:focus {
    border-color: #22c55e;
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.12);
}

.file-field input {
    padding: 10px;
}

.summary-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
}

.primary-button,
.ghost-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 42px;
    border-radius: 10px;
    border: 1px solid #cfe1d3;
    cursor: pointer;
    font-weight: 800;
    padding: 0 18px;
}

.primary-button {
    border-color: #16a34a;
    background: #16a34a;
    color: #fff;
    box-shadow: 0 12px 24px rgba(22, 163, 74, 0.18);
}

.ghost-button {
    background: #fff;
    color: #1f3428;
}

.primary-button:disabled,
.ghost-button:disabled {
    cursor: not-allowed;
    opacity: 0.65;
}

.alert {
    border-radius: 12px;
    font-weight: 800;
    padding: 12px 14px;
}

.alert.success {
    border: 1px solid #bbf7d0;
    background: #f0fdf4;
    color: #15803d;
}

.alert.error {
    border: 1px solid #fecaca;
    background: #fef2f2;
    color: #b91c1c;
}

.skeleton-card {
    display: grid;
    gap: 14px;
}

.skeleton-card span,
.skeleton-card strong,
.skeleton-card p {
    display: block;
    height: 16px;
    margin: 0;
    border-radius: 999px;
    background: linear-gradient(90deg, #edf5ef, #f8fffa, #edf5ef);
    background-size: 200% 100%;
    animation: shimmer 1.2s infinite;
}

.skeleton-card strong {
    width: 46%;
    height: 24px;
}

.skeleton-card p {
    width: 80%;
}

@keyframes shimmer {
    to {
        background-position: -200% 0;
    }
}

@media (max-width: 900px) {
    .profile-hero,
    .summary-card {
        align-items: stretch;
        flex-direction: column;
    }

    .form-grid.two,
    .form-grid.three {
        grid-template-columns: 1fr;
    }
}
</style>
