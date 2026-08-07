<template>
    <nav class="platform-fee-subnav" aria-label="Chức năng bậc phí nền tảng">
        <router-link
            v-for="item in items"
            :key="item.name"
            :to="{ name: item.name }"
            class="subnav-item"
            active-class="subnav-active"
        >
            <AppIcon :name="item.icon" size="15" />
            <span>{{ item.label }}</span>
        </router-link>
        <button class="subnav-action" type="button" @click="showSettings = true">
            <AppIcon name="bellRing" size="15" />
            <span>Cài đặt nhắc phí</span>
        </button>
    </nav>
    <PlatformFeeSettingsDialog :open="showSettings" @close="showSettings = false" @saved="handleSaved" />
</template>

<script>
import AppIcon from "./AppIcon.vue";
import PlatformFeeSettingsDialog from "./admin/PlatformFeeSettingsDialog.vue";

export default {
    name: "PlatformFeeSubnav",
    components: { AppIcon, PlatformFeeSettingsDialog },
    methods: {
        handleSaved() {
            this.showSettings = false;
        },
    },
    data() {
        return {
            showSettings: false,
            items: [
                {
                    name: "admin-platform-fee-tiers",
                    label: "Cấu hình bậc phí",
                    icon: "layers3",
                },
                {
                    name: "admin-platform-fee-ledgers",
                    label: "Phí duy trì",
                    icon: "receiptText",
                },
                {
                    name: "admin-platform-fee-policies",
                    label: "Chính sách áp dụng",
                    icon: "fileSearch",
                },
            ],
        };
    },
};
</script>

<style scoped>
.platform-fee-subnav {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}

.subnav-item {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-height: 36px;
    padding: 8px 12px;
    border: 0;
    border-radius: 0;
    background: transparent;
    color: var(--admin-muted, #334155);
    font-size: 13px;
    font-weight: 400;
    text-decoration: none;
}

.subnav-item.never-hover-class-placeholder {
    background: var(--admin-primary-soft, #f0fdf4);
    color: var(--admin-primary-dark, #166534);
}

.subnav-active,
.subnav-active.never-hover-class-placeholder {
    background: var(--admin-primary-soft, #e2f6e8);
    color: var(--admin-primary-dark, #15733a);
}

.subnav-action {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-height: 36px;
    border: 0;
    border-radius: 0;
    padding: 8px 12px;
    background: transparent;
    color: var(--admin-primary-dark, #15733a);
    font: inherit;
    cursor: pointer;
}

@media (max-width: 720px) {
    .platform-fee-subnav {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .subnav-item {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .platform-fee-subnav {
        grid-template-columns: 1fr;
    }
}
</style>
