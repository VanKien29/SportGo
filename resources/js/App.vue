<template>
  <router-view />
  <SetPasswordModal v-if="showSetPasswordModal" @done="handlePasswordSetupDone" />
  <PolicyAcceptanceModal
    v-else-if="requiredPolicies.length"
    :policies="requiredPolicies"
    @accepted="handlePoliciesAccepted"
  />
</template>

<script>
import PolicyAcceptanceModal from './components/PolicyAcceptanceModal.vue';
import SetPasswordModal from './components/SetPasswordModal.vue';
import { getAuth, needsPasswordSetup } from './stores/auth.js';
import { policyService } from './services/policies.js';

export default {
  name: 'App',
  components: { PolicyAcceptanceModal, SetPasswordModal },
  data() {
    return {
      showSetPasswordModal: false,
      requiredPolicies: [],
      checkingPolicies: false,
    };
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
      if (auth.role_group === 'admin') return false;
      if (this.$route.path.startsWith('/admin')) return false;
      return true;
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
  --sg-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -2px rgba(0,0,0,.1);
  --sg-shadow-lg: 0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -4px rgba(0,0,0,.1);
  --sg-shadow-xl: 0 20px 25px -5px rgba(0,0,0,.1), 0 8px 10px -6px rgba(0,0,0,.1);
  --sg-radius: 12px;
  --sg-radius-sm: 8px;
  --sg-radius-full: 9999px;
  --sg-transition: all .2s cubic-bezier(.4,0,.2,1);
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  color: var(--sg-text);
  background: var(--sg-surface);
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

a {
  text-decoration: none;
  color: inherit;
}

button {
  cursor: pointer;
  border: none;
  background: none;
  font-family: inherit;
}

input {
  font-family: inherit;
}
</style>
