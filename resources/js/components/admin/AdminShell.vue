<template>
    <div
        class="sg-shell-admin"
        :class="{
            'nav-open': sidebarOpen,
            'sidebar-collapsed': sidebarCollapsed,
            'sidebar-style-two-level': sidebarStyle === 'two-level',
        }"
    >
        <AdminSidebar
            :sections="sections"
            :active-route-name="activeRouteName"
            :collapsed="sidebarCollapsed"
            @navigate="closeSidebar"
        />
        <button
            v-if="sidebarOpen"
            class="admin-shell-backdrop"
            type="button"
            aria-label="Đóng menu"
            @click="closeSidebar"
        ></button>

        <main class="main-content">
            <AdminTopbar
                :title="title"
                :section-label="sectionLabel"
                :sidebar-collapsed="sidebarCollapsed"
                @toggle-sidebar="toggleSidebar"
                @toggle-collapse="toggleCollapse"
            />
            <div class="content-area">
                <slot />
            </div>
            <footer class="admin-footer">
                <span>SportGo Admin</span>
            </footer>
        </main>
    </div>
</template>

<script>
import AdminSidebar from "./AdminSidebar.vue";
import AdminTopbar from "./AdminTopbar.vue";

export default {
    name: "AdminShell",
    components: { AdminSidebar, AdminTopbar },
    props: {
        sections: { type: Array, required: true },
        title: { type: String, required: true },
        sectionLabel: { type: String, default: "" },
        activeRouteName: { type: String, default: "" },
    },
    data() {
        return {
            sidebarOpen: false,
            sidebarCollapsed: localStorage.getItem('admin-sidebar-collapsed') === 'true',
            sidebarStyle: localStorage.getItem('admin-sidebar-style') || 'one-level',
        };
    },
    created() {
        window.addEventListener('sidebar-style-changed', this.loadSidebarStyle);
    },
    beforeUnmount() {
        window.removeEventListener('sidebar-style-changed', this.loadSidebarStyle);
    },
    methods: {
        loadSidebarStyle() {
            this.sidebarStyle = localStorage.getItem('admin-sidebar-style') || 'one-level';
        },
        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
        },
        closeSidebar() {
            this.sidebarOpen = false;
        },
        toggleCollapse() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem('admin-sidebar-collapsed', this.sidebarCollapsed);
        },
    },
    watch: {
        $route() {
            this.closeSidebar();
        },
    },
};
</script>
