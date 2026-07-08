<template>
    <div class="avc-filters animate-fade-in">
        <div class="filter-row">
            <div class="filter-tabs">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    class="tab-btn"
                    :class="{ active: modelValue === tab.value }"
                    @click="$emit('update:modelValue', tab.value)"
                >
                    {{ tab.label }}
                </button>
            </div>
            <div class="filter-search">
                <div class="search-box">
                    <AppIcon name="search" size="16" />
                    <input
                        :id="searchId"
                        :value="search"
                        @input="$emit('update:search', $event.target.value)"
                        type="text"
                        :placeholder="searchPlaceholder"
                        class="search-input"
                    />
                </div>
            </div>
            <div class="filter-actions" v-if="$slots.actions">
                <slot name="actions"></slot>
            </div>
        </div>
    </div>
</template>

<script>
import AppIcon from "../AppIcon.vue";

export default {
    name: "SaaSFilterBar",
    components: { AppIcon },
    props: {
        modelValue: {
            type: [String, Number],
            default: ""
        },
        tabs: {
            type: Array,
            required: true
        },
        search: {
            type: String,
            default: ""
        },
        searchPlaceholder: {
            type: String,
            default: "Tìm kiếm nhanh..."
        },
        searchId: {
            type: String,
            default: "search-input"
        }
    },
    emits: ["update:modelValue", "update:search"]
};
</script>

<style scoped>
.avc-filters {
    padding: 12px 0;
}
.filter-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}
.filter-tabs {
    display: flex;
    gap: 6px;
}
.avc-filters .filter-tabs button.tab-btn {
    height: 38px !important;
    min-height: 38px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 0 16px !important;
    border-radius: 8px !important;
    border: 1px solid var(--admin-border) !important;
    background: var(--admin-surface) !important;
    color: var(--admin-muted) !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    transition: all 0.18s !important;
    box-sizing: border-box !important;
}
.avc-filters .filter-tabs button.tab-btn.active {
    background: var(--admin-primary) !important;
    border-color: var(--admin-primary) !important;
    color: var(--admin-primary-text, #fff) !important;
}
.avc-filters .filter-tabs button.tab-btn:not(.active):hover {
    background: var(--admin-hover) !important;
    color: var(--admin-primary-dark) !important;
}
.filter-search {
    flex: 1;
    min-width: 250px;
}
/* Search box border styling to increase contrast on light theme */
.filter-search .search-box {
    border-color: var(--admin-border) !important;
}
.filter-search .search-box input::placeholder {
    color: var(--admin-faint) !important;
}
.filter-search .search-box svg {
    color: var(--admin-faint) !important;
}
.filter-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}
</style>
