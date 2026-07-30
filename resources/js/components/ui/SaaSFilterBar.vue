<template>
    <div class="avc-filters animate-fade-in">
        <div class="filter-row">
            <div class="filter-tabs" v-if="tabs && tabs.length">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    type="button"
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
            default: () => []
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
    margin-bottom: 12px;
    width: 100%;
    box-sizing: border-box;
}
.filter-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    width: 100%;
}
.filter-tabs {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.avc-filters .filter-tabs button.tab-btn {
    height: 36px !important;
    min-height: 36px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 0 14px !important;
    border-radius: 6px !important;
    border: 1px solid var(--admin-border-soft, #cbd5e1) !important;
    background: var(--admin-surface, #ffffff) !important;
    color: var(--admin-muted, #475569) !important;
    font-size: 13px !important;
    font-weight: 500 !important;
    cursor: pointer !important;
    transition: all 0.18s ease !important;
    box-sizing: border-box !important;
    white-space: nowrap !important;
}
.avc-filters .filter-tabs button.tab-btn.active {
    background: var(--admin-primary, #22a653) !important;
    border-color: var(--admin-primary, #22a653) !important;
    color: #ffffff !important;
}
.filter-search {
    flex: 1;
    min-width: 260px;
}
.filter-search .search-box {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    height: 38px !important;
    padding: 0 12px !important;
    border-radius: 8px !important;
    border: 1px solid var(--admin-border-soft, #cbd5e1) !important;
    background: var(--admin-surface, #ffffff) !important;
    box-sizing: border-box !important;
    width: 100% !important;
}
.filter-search .search-box .search-input {
    border: none !important;
    outline: none !important;
    background: transparent !important;
    flex: 1 !important;
    width: 100% !important;
    height: 100% !important;
    font-size: 13px !important;
    color: var(--admin-text, #0f172a) !important;
    padding: 0 !important;
    margin: 0 !important;
    box-shadow: none !important;
}
.filter-search .search-box input::placeholder {
    color: var(--admin-faint, #94a3b8) !important;
}
.filter-search .search-box svg {
    color: var(--admin-faint, #94a3b8) !important;
    flex-shrink: 0 !important;
}
.filter-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}
</style>
