<template>
    <div class="sg-shell-admin" :class="{ 'nav-open': sidebarOpen }">
        <AdminSidebar
            :sections="sections"
            :active-route-name="activeRouteName"
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
                @toggle-sidebar="toggleSidebar"
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
        };
    },
    methods: {
        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
        },
        closeSidebar() {
            this.sidebarOpen = false;
        },
    },
    watch: {
        $route() {
            this.closeSidebar();
        },
    },
};
</script>
