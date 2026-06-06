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
    </nav>
</template>

<script>
import AppIcon from "./AppIcon.vue";

export default {
    name: "PlatformFeeSubnav",
    components: { AppIcon },
    data() {
        return {
            items: [
                {
                    name: "admin-platform-fee-tiers",
                    label: "Cấu hình bậc phí",
                    icon: "layers",
                },
                {
                    name: "admin-platform-fee-ledgers",
                    label: "Phí duy trì",
                    icon: "receipt",
                },
                {
                    name: "admin-platform-fee-policies",
                    label: "Chính sách áp dụng",
                    icon: "fileText",
                },
                {
                    name: "admin-platform-fee-settings",
                    label: "Cài đặt & nhắc hạn",
                    icon: "settings",
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
}

.subnav-item {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-height: 36px;
    padding: 8px 12px;
    border: 1px solid #dbe3ea;
    border-radius: 8px;
    background: #fff;
    color: #475569;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
}

.subnav-item:hover {
    border-color: #86efac;
    background: #f0fdf4;
    color: #166534;
}

.subnav-active,
.subnav-active:hover {
    border-color: #16a34a;
    background: #16a34a;
    color: #fff;
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
