<template>
  <transition name="vip-toast">
    <aside v-if="visible" class="vip-toast" role="status">
      <button class="toast-close" type="button" aria-label="Đóng" @click="close">×</button>
      <span>SportGo VIP</span>
      <strong>Mua gói VIP đi</strong>
      <p>Nhận cashback, voucher riêng theo gói và ưu tiên xử lý khi cần hỗ trợ.</p>
      <router-link class="toast-action" to="/vip-membership" @click="close">Xem gói VIP</router-link>
    </aside>
  </transition>
</template>

<script>
export default {
  name: 'VipPromptToast',
  props: {
    duration: { type: Number, default: 9000 },
  },
  data() {
    return {
      visible: false,
      timer: null,
    };
  },
  mounted() {
    this.visible = true;
    this.timer = window.setTimeout(this.close, this.duration);
  },
  beforeUnmount() {
    if (this.timer) window.clearTimeout(this.timer);
  },
  methods: {
    close() {
      this.visible = false;
      if (this.timer) {
        window.clearTimeout(this.timer);
        this.timer = null;
      }
    },
  },
};
</script>

<style scoped>
.vip-toast{position:fixed;right:22px;bottom:22px;z-index:850;display:grid;gap:7px;width:min(320px,calc(100vw - 32px));padding:16px 16px 14px;border:1px solid #fbbf24;border-radius:12px;background:#fffbeb;color:#78350f;box-shadow:0 20px 50px rgba(15,23,42,.18)}.vip-toast span{font-size:11px;font-weight:900;text-transform:uppercase}.vip-toast strong{font-size:18px;font-weight:900}.vip-toast p{margin:0;color:#92400e;font-size:13px;font-weight:650;line-height:1.45}.toast-action{justify-self:start;margin-top:3px;padding:8px 11px;border-radius:8px;background:#16a34a;color:#fff;font-size:13px;font-weight:850;text-decoration:none}.toast-close{position:absolute;top:7px;right:8px;width:26px;height:26px;border:0;border-radius:999px;background:rgba(120,53,15,.1);color:#78350f;font-size:18px;line-height:1;cursor:pointer}.vip-toast-enter-active,.vip-toast-leave-active{transition:opacity .22s ease,transform .22s ease}.vip-toast-enter-from,.vip-toast-leave-to{opacity:0;transform:translateY(10px)}
</style>
