<template>
    <div
        class="sg-shell-admin sg-shell-owner"
        :class="{
            'nav-open': sidebarOpen,
            'sidebar-collapsed': sidebarCollapsed,
            'sidebar-style-two-level': sidebarStyle === 'two-level',
        }"
    >
        <OwnerSidebar
            :sections="sections"
            :active-route-name="activeRouteName"
            :collapsed="sidebarCollapsed"
            :user-role-label="roleLabel"
            :workspace-label="workspaceLabel"
            :home-url="homeUrl"
            :show-utility-navigation="showUtilityNavigation"
            :clusters="clusters"
            :selected-cluster-id="selectedClusterId"
            :selected-cluster="selectedCluster"
            :cluster-loading="clusterLoading"
            @navigate="closeSidebar"
            @cluster-change="$emit('cluster-change', $event)"
        />
        <button
            v-if="sidebarOpen"
            class="admin-shell-backdrop"
            type="button"
            aria-label="Đóng menu"
            @click="closeSidebar"
        ></button>

        <main class="main-content">
            <OwnerTopbar
                :title="title"
                :section-label="sectionLabel"
                :sidebar-collapsed="sidebarCollapsed"
                :clusters="clusters"
                :selected-cluster-id="selectedClusterId"
                :selected-cluster="selectedCluster"
                :cluster-loading="clusterLoading"
                :workspace-label="workspaceLabel"
                :profile-url="profileUrl"
                :billing-url="billingUrl"
                :settings-url="settingsUrl"
                :show-account-links="showAccountLinks"
                @toggle-sidebar="toggleSidebar"
                @toggle-collapse="toggleCollapse"
                @cluster-change="$emit('cluster-change', $event)"
            />
            <div class="content-area">
                <slot />
            </div>
        </main>
    </div>
</template>

<script>
import OwnerSidebar from "./OwnerSidebar.vue";
import OwnerTopbar from "./OwnerTopbar.vue";

export default {
    name: "OwnerShell",
    components: { OwnerSidebar, OwnerTopbar },
    props: {
        sections: { type: Array, required: true },
        title: { type: String, required: true },
        sectionLabel: { type: String, default: "" },
        activeRouteName: { type: String, default: "" },
        clusters: { type: Array, default: () => [] },
        selectedClusterId: { type: [String, Number], default: "" },
        selectedCluster: { type: Object, default: null },
        clusterLoading: { type: Boolean, default: false },
        workspaceLabel: { type: String, default: 'Owner' },
        roleLabel: { type: String, default: 'Chủ sân' },
        homeUrl: { type: String, default: '/owner/dashboard' },
        profileUrl: { type: String, default: '/owner/profile' },
        billingUrl: { type: String, default: '/owner/billing' },
        settingsUrl: { type: String, default: '/owner/settings' },
        showUtilityNavigation: { type: Boolean, default: true },
        showAccountLinks: { type: Boolean, default: true },
    },
    emits: ["cluster-change"],
    data() {
        return {
            sidebarOpen: false,
            sidebarCollapsed: localStorage.getItem('owner-sidebar-collapsed') === 'true',
            sidebarStyle: localStorage.getItem('owner-sidebar-style') || 'one-level',
        };
    },
    created() {
        window.addEventListener('owner-sidebar-style-changed', this.loadSidebarStyle);
    },
    beforeUnmount() {
        window.removeEventListener('owner-sidebar-style-changed', this.loadSidebarStyle);
    },
    methods: {
        loadSidebarStyle() {
            this.sidebarStyle = localStorage.getItem('owner-sidebar-style') || 'one-level';
        },
        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
        },
        closeSidebar() {
            this.sidebarOpen = false;
        },
        toggleCollapse() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem('owner-sidebar-collapsed', this.sidebarCollapsed);
        },
    },
    watch: {
        $route() {
            this.closeSidebar();
        },
    },
};
</script>
