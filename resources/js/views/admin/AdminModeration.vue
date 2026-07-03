<template>
  <div class="moderation-page">
    <AdminContentModeration 
      ref="moderationTab"
      @auto-approve-changed="onAutoApproveChanged"
    />
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import ActionIconButton from '../../components/ActionIconButton.vue';
import AdminContentModeration from './AdminContentModeration.vue';

export default {
  name: 'AdminModeration',
  components: {
    AppIcon,
    ActionIconButton,
    AdminContentModeration,
  },
  data() {
    return {
      autoApproveEnabled: false,
    };
  },
  methods: {
    onAutoApproveChanged(val) {
      this.autoApproveEnabled = val;
    },
    toggleModerationAutoApprove(event) {
      const checked = event.target.checked;
      this.autoApproveEnabled = checked;
      const child = this.$refs.moderationTab;
      if (child) {
        child.autoApproveEnabled = checked;
        child.toggleAutoApprove();
      }
    },
  },
};
</script>

<style scoped>
.moderation-page {
  display: flex !important;
  min-width: 0;
  flex-direction: column;
  width: 100%;
}


/* AUTO APPROVE TOGGLE */
.auto-approve-wrapper {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #f8fafc;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
}

.switch {
  position: relative;
  display: inline-block;
  width: 40px;
  height: 22px;
}

.switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #cbd5e1;
  transition: .4s;
  border-radius: 22px;
}

.slider:before {
  position: absolute;
  content: "";
  height: 16px;
  width: 16px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: .4s;
  border-radius: 50%;
}

input:checked + .slider {
  background-color: #10b981;
}

input:focus + .slider {
  box-shadow: 0 0 1px #10b981;
}

input:checked + .slider:before {
  transform: translateX(18px);
}

.switch-label {
  font-size: 13px;
  font-weight: 700;
  color: #334155;
}
</style>
