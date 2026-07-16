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
            <div class="filter-actions" v-if="$slots.actions">
                <slot name="actions"></slot>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "SaaSFilterBar",
    props: {
        modelValue: {
            type: [String, Number],
            default: ""
        },
        tabs: {
            type: Array,
            required: true
        }
    },
    emits: ["update:modelValue"]
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
    border-radius: var(--admin-radius) !important;
    border: 1px solid var(--admin-border) !important;
    background: var(--admin-surface) !important;
    color: var(--admin-muted) !important;
    font-size: var(--admin-font-size-md) !important;
    font-weight: 600 !important;
    line-height: normal !important;
    cursor: pointer !important;
    transition: all 0.18s !important;
    box-sizing: border-box !important;
}
.avc-filters .filter-tabs button.tab-btn.active {
    background: var(--admin-primary) !important;
    border-color: var(--admin-primary) !important;
    color: var(--admin-primary-text, #fff) !important;
}
.avc-filters .filter-tabs button.tab-btn:not(.active).never-hover-class-placeholder {
    background: var(--admin-hover, #f8fafc) !important;
    border-color: var(--admin-border-focus, #cbd5e1) !important;
    color: var(--admin-text, #1e293b) !important;
}
.filter-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}
</style>
