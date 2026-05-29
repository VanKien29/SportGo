<template>
  <div class="return-page">
    <PublicNavbar />

    <main class="return-main">
      <section class="return-panel">
        <div class="status-mark" :class="statusClass">
          <svg v-if="isSuccess" width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
          <svg v-else width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <circle cx="12" cy="12" r="10"/>
            <line x1="15" y1="9" x2="9" y2="15"/>
            <line x1="9" y1="9" x2="15" y2="15"/>
          </svg>
        </div>

        <h1>{{ title }}</h1>
        <p class="desc">{{ description }}</p>

        <div class="summary">
          <div class="summary-row">
            <span>Trạng thái</span>
            <strong>{{ statusText }}</strong>
          </div>
          <div class="summary-row" v-if="bookingId">
            <span>Mã booking</span>
            <strong>{{ bookingId }}</strong>
          </div>
        </div>

        <div class="actions">
          <router-link v-if="bookingId" :to="bookingRoute" class="primary-btn">
            Xem chi tiết đơn
          </router-link>
          <router-link to="/booking" class="secondary-btn">
            Đặt sân khác
          </router-link>
        </div>

        <p class="redirect-note" v-if="bookingId">
          Tự chuyển về chi tiết đơn sau {{ secondsLeft }} giây.
        </p>
      </section>
    </main>
  </div>
</template>

<script>
import PublicNavbar from '../../../components/PublicNavbar.vue';

export default {
  name: 'VnpayReturn',
  components: { PublicNavbar },
  data() {
    return {
      secondsLeft: 4,
      redirectTimer: null,
    };
  },
  computed: {
    bookingId() {
      return this.$route.query.booking_id || '';
    },
    paymentStatus() {
      return this.$route.query.payment_status || 'failed';
    },
    isSuccess() {
      return this.paymentStatus === 'success';
    },
    statusClass() {
      return this.isSuccess ? 'success' : 'failed';
    },
    statusText() {
      return this.isSuccess ? 'Thanh toán thành công' : 'Thanh toán thất bại';
    },
    title() {
      return this.isSuccess ? 'Thanh toán VNPAY thành công' : 'Thanh toán VNPAY chưa thành công';
    },
    description() {
      return this.isSuccess
        ? 'Đơn đặt sân của bạn đã được xác nhận. Bạn có thể xem lại thông tin booking bên dưới.'
        : 'Giao dịch chưa hoàn tất hoặc đã bị hủy. Nếu đơn còn thời gian giữ chỗ, bạn có thể quay lại chi tiết đơn để thanh toán lại.';
    },
    bookingRoute() {
      return {
        name: 'booking-detail',
        params: { id: this.bookingId },
        query: { payment_status: this.paymentStatus },
      };
    },
  },
  mounted() {
    if (this.bookingId) {
      this.redirectTimer = setInterval(() => {
        if (this.secondsLeft <= 1) {
          this.clearRedirectTimer();
          this.$router.push(this.bookingRoute);
          return;
        }

        this.secondsLeft -= 1;
      }, 1000);
    }
  },
  beforeUnmount() {
    this.clearRedirectTimer();
  },
  methods: {
    clearRedirectTimer() {
      if (this.redirectTimer) {
        clearInterval(this.redirectTimer);
        this.redirectTimer = null;
      }
    },
  },
};
</script>

<style scoped>
.return-page {
  min-height: 100vh;
  background: var(--sg-surface);
}

.return-main {
  max-width: 720px;
  margin: 0 auto;
  padding: 120px 24px 60px;
}

.return-panel {
  background: var(--sg-white);
  border: 1px solid var(--sg-border);
  border-radius: var(--sg-radius);
  box-shadow: var(--sg-shadow);
  padding: 36px;
  text-align: center;
}

.status-mark {
  width: 82px;
  height: 82px;
  margin: 0 auto 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.status-mark.success {
  background: var(--sg-green-pale);
  color: var(--sg-green-dark);
}

.status-mark.failed {
  background: #fef2f2;
  color: var(--sg-danger);
}

.return-panel h1 {
  color: var(--sg-dark);
  font-size: 26px;
  font-weight: 900;
}

.desc {
  max-width: 520px;
  margin: 12px auto 24px;
  color: var(--sg-text-muted);
  font-size: 15px;
  line-height: 1.6;
}

.summary {
  max-width: 520px;
  margin: 0 auto 24px;
  border: 1px solid var(--sg-border);
  border-radius: var(--sg-radius-sm);
  overflow: hidden;
  text-align: left;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 16px;
  color: var(--sg-text-muted);
  font-size: 14px;
}

.summary-row + .summary-row {
  border-top: 1px solid var(--sg-border);
}

.summary-row strong {
  color: var(--sg-dark);
  word-break: break-all;
  text-align: right;
}

.actions {
  display: flex;
  justify-content: center;
  gap: 12px;
  flex-wrap: wrap;
}

.primary-btn,
.secondary-btn {
  min-height: 42px;
  padding: 11px 20px;
  border-radius: var(--sg-radius-sm);
  font-size: 14px;
  font-weight: 800;
}

.primary-btn {
  background: var(--sg-green);
  color: #fff;
}

.secondary-btn {
  color: var(--sg-green-dark);
  background: var(--sg-green-pale);
}

.redirect-note {
  margin-top: 18px;
  color: var(--sg-text-muted);
  font-size: 13px;
}

@media (max-width: 640px) {
  .return-main {
    padding: 96px 16px 40px;
  }

  .return-panel {
    padding: 28px 18px;
  }

  .return-panel h1 {
    font-size: 22px;
  }
}
</style>
