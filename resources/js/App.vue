<template>
  <router-view v-slot="{ Component }">
    <Suspense timeout="0">
      <template #default>
        <component :is="Component" />
      </template>
      <template #fallback>
        <main class="app-route-loading" aria-live="polite">
          <span class="app-route-loading__spinner" aria-hidden="true"></span>
          <p>Đang mở trang...</p>
        </main>
      </template>
    </Suspense>
  </router-view>
  <ClientFooter v-if="showClientFooter" />
  <SetPasswordModal
    v-if="showSetPasswordModal"
    @done="handlePasswordSetupDone"
  />
  <PolicyAcceptanceModal
    v-else-if="requiredPolicies.length"
    :policies="requiredPolicies"
    @accepted="handlePoliciesAccepted"
  />
  <FloatingActions />
</template>

<script>
import ClientFooter from "./components/ClientFooter.vue";
import FloatingActions from "./components/FloatingActions.vue";
import PolicyAcceptanceModal from "./components/PolicyAcceptanceModal.vue";
import SetPasswordModal from "./components/SetPasswordModal.vue";
import { policyService } from "./services/policies.js";
import { getAuth, needsPasswordSetup } from "./stores/auth.js";

export default {
  name: "App",
  components: {
    ClientFooter,
    FloatingActions,
    PolicyAcceptanceModal,
    SetPasswordModal,
  },
  data() {
    return {
      showSetPasswordModal: false,
      requiredPolicies: [],
      checkingPolicies: false,
    };
  },
  computed: {
    showClientFooter() {
      const path = this.$route.path;
      if (/^\/(?:admin|owner|staff)(?:\/|$)/.test(path)) return false;
      if (/^\/(?:login|register|forgot-password|chat)(?:\/|$)/.test(path)) {
        return false;
      }
      return true;
    },
  },
  mounted() {
    this.showSetPasswordModal = needsPasswordSetup();
    this.checkRequiredPolicies();
  },
  watch: {
    $route() {
      this.showSetPasswordModal = needsPasswordSetup();
      this.checkRequiredPolicies();
    },
  },
  methods: {
    shouldCheckPolicies() {
      const auth = getAuth();
      if (!auth?.token) return false;
      if (auth.role_group === "admin") return false;
      return !this.$route.path.startsWith("/admin");
    },
    async checkRequiredPolicies() {
      if (this.checkingPolicies) return;
      if (!this.shouldCheckPolicies()) {
        this.requiredPolicies = [];
        return;
      }

      this.checkingPolicies = true;
      try {
        const response = await policyService.required();
        this.requiredPolicies = response.data || response.policies || [];
      } catch {
        this.requiredPolicies = [];
      } finally {
        this.checkingPolicies = false;
      }
    },
    handlePasswordSetupDone() {
      this.showSetPasswordModal = false;
      this.checkRequiredPolicies();
    },
    handlePoliciesAccepted() {
      this.requiredPolicies = [];
    },
  },
};
</script>

<style>
:root {
  --sg-green: #22c55e;
  --sg-green-dark: #16a34a;
  --sg-green-light: #4ade80;
  --sg-green-pale: #dcfce7;
  --sg-dark: #111827;
  --sg-darker: #0a0f1a;
  --sg-surface: #f8fafc;
  --sg-white: #ffffff;
  --sg-text: #1e293b;
  --sg-text-muted: #64748b;
  --sg-border: #e2e8f0;
  --sg-danger: #ef4444;
  --sg-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  --sg-shadow-lg: 0 10px 24px rgba(0, 0, 0, 0.1);
  --sg-radius: 12px;
  --sg-radius-sm: 8px;
  --sg-radius-full: 9999px;
  --sg-transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

* {
  box-sizing: border-box;
}

html,
body,
#app {
  min-height: 100%;
  margin: 0;
}

body {
  color: var(--sg-text);
  background: var(--sg-surface);
  font-family: var(
    --sportgo-font-body,
    "Inter",
    -apple-system,
    BlinkMacSystemFont,
    "Segoe UI",
    sans-serif
  );
  line-height: 1.55;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

.app-route-loading {
  min-height: 100vh;
  display: grid;
  place-items: center;
  align-content: center;
  gap: 12px;
  color: var(--sg-text-muted);
  background: var(--sg-surface);
}

.app-route-loading p {
  margin: 0;
  font-size: 14px;
}

.app-route-loading__spinner {
  width: 28px;
  height: 28px;
  border: 3px solid #d9f5e4;
  border-top-color: var(--sg-green-dark);
  border-radius: 50%;
  animation: app-route-spin 0.75s linear infinite;
}

@keyframes app-route-spin {
  to { transform: rotate(360deg); }
}

a {
  color: inherit;
  text-decoration: none;
}

button,
input,
select,
textarea {
  font: inherit;
  letter-spacing: 0;
}

button {
  border: 0;
  background: none;
  cursor: pointer;
}
</style>
