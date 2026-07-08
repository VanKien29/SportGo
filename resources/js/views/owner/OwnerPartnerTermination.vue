<template>
  <div class="termination-page">
    <header class="page-head">
      <div>
        <p class="eyebrow">Partner termination</p>
        <h1>Cham dut hop dong doi tac</h1>
      </div>
      <button class="btn btn-outline" type="button" @click="$router.back()">Quay lai</button>
    </header>

    <div v-if="loading" class="panel muted">Dang tai ho so...</div>
    <div v-else-if="error" class="panel alert">
      <p>{{ error }}</p>
      <button class="btn btn-primary" type="button" @click="load">Thu lai</button>
    </div>

    <template v-else>
      <section class="panel summary-panel">
        <div>
          <p class="eyebrow">{{ cluster?.name || termination?.venue_cluster?.name || 'Cum san' }}</p>
          <h2>{{ statusLabel(termination?.status || 'eligible') }}</h2>
          <p class="hint">{{ eligibility?.reason || eligibility?.warning || 'Owner can gui yeu cau, ky OTP va theo doi quy trinh cham dut.' }}</p>
        </div>
        <div class="money-grid">
          <div>
            <span>So du owner</span>
            <strong>{{ money(summary.owner_balance_total) }}</strong>
          </div>
          <div>
            <span>No booking tuong lai</span>
            <strong>{{ money(summary.future_online_booking_liability) }}</strong>
          </div>
          <div>
            <span>Dang refund/withdraw</span>
            <strong>{{ money((Number(summary.pending_refund_liability) || 0) + (Number(summary.pending_withdrawal_amount) || 0)) }}</strong>
          </div>
          <div>
            <span>Co the rut</span>
            <strong>{{ money(summary.withdrawable_amount) }}</strong>
          </div>
        </div>
      </section>

      <section v-if="!termination || termination.status === 'draft_preview'" class="panel form-panel">
        <div class="section-title">
          <h2>1. Tao don yeu cau</h2>
          <span>{{ summary.future_booking_count || 0 }} booking tuong lai</span>
        </div>

        <label>
          Ly do cham dut
          <textarea v-model.trim="form.reason" rows="3" maxlength="2000" />
        </label>

        <label>
          Mo ta chi tiet
          <textarea v-model.trim="form.detail_reason" rows="4" maxlength="5000" />
        </label>

        <div class="form-grid">
          <label>
            Ngay mong muon
            <input v-model="form.requested_effective_date" type="date" />
          </label>
          <label>
            Phuong an booking tuong lai
            <select v-model="form.future_booking_policy">
              <option value="">Chon phuong an</option>
              <option v-for="policy in policies" :key="policy.value" :value="policy.value">
                {{ policy.label }}
              </option>
            </select>
          </label>
        </div>

        <label class="check-row">
          <input v-model="form.warning_accepted" type="checkbox" />
          <span>Toi da doc canh bao: sau khi gui, cum san se bi khoa thao tac quan ly thong thuong.</span>
        </label>

        <div class="actions">
          <button class="btn btn-primary" type="button" :disabled="working || !canPreview" @click="preview">
            Tao ban xem truoc
          </button>
          <button v-if="termination" class="btn btn-outline" type="button" :disabled="working" @click="sendOtp">
            Gui OTP ky don
          </button>
        </div>

        <div v-if="signing.requestId" class="otp-box">
          <p>OTP da gui. Ma doi soat file: <strong>{{ signing.hashShort }}</strong></p>
          <label>
            OTP
            <input v-model.trim="signing.otp" inputmode="numeric" maxlength="6" />
          </label>
          <button class="btn btn-primary" type="button" :disabled="working || signing.otp.length !== 6" @click="submit">
            Ky va gui yeu cau
          </button>
        </div>
      </section>

      <section v-if="termination && termination.status !== 'draft_preview'" class="panel">
        <div class="section-title">
          <h2>2. Xu ly booking tuong lai</h2>
          <button class="btn btn-outline" type="button" @click="loadFutureBookings">Lam moi</button>
        </div>

        <div v-if="futureBookings.length" class="booking-list">
          <label v-for="booking in futureBookings" :key="booking.id" class="booking-row">
            <input v-model="selectedBookingIds" type="checkbox" :value="booking.id" />
            <span>
              <strong>{{ booking.booking_code }}</strong>
              {{ booking.booking_date }} {{ booking.start_time }}-{{ booking.end_time }}
              <small>{{ booking.customer?.full_name || booking.customer?.username || '-' }}</small>
            </span>
            <em>{{ money(booking.paid_online_amount) }} · {{ booking.action_status || booking.status }}</em>
          </label>
        </div>
        <p v-else class="hint">Khong con booking tuong lai bat buoc xu ly.</p>

        <div class="actions">
          <button class="btn btn-primary" type="button" :disabled="working || !futureBookings.length" @click="bulkAction('cancel_all_refund_to_user_balance')">
            Huy va hoan tien
          </button>
          <button class="btn btn-outline" type="button" :disabled="working || !futureBookings.length" @click="bulkAction('serve_until_last_booking')">
            Phuc vu den booking cuoi
          </button>
          <button class="btn btn-outline" type="button" :disabled="working || !futureBookings.length" @click="bulkAction('manual_per_booking')">
            Xu ly thu cong
          </button>
        </div>
      </section>

      <section v-if="termination && termination.status !== 'draft_preview'" class="panel split-panel">
        <div>
          <div class="section-title">
            <h2>3. Rut tien quyet toan</h2>
          </div>
          <div class="form-grid">
            <label>
              Wallet ID
              <input v-model.trim="withdrawal.owner_wallet_id" inputmode="numeric" />
            </label>
            <label>
              Bank account ID
              <input v-model.trim="withdrawal.owner_bank_account_id" inputmode="numeric" />
            </label>
            <label>
              So tien
              <input v-model.number="withdrawal.amount" type="number" min="50000" step="1000" />
            </label>
          </div>
          <button class="btn btn-primary" type="button" :disabled="working" @click="storeWithdrawal">
            Gui yeu cau rut tien
          </button>
        </div>

        <div>
          <div class="section-title">
            <h2>4. Bien ban cuoi</h2>
          </div>
          <p class="hint">
            Khi admin sinh bien ban cuoi va ky SportGo, owner se nhan OTP de ky xac nhan.
          </p>
          <button class="btn btn-outline" type="button" :disabled="working" @click="sendFinalOtp">
            Gui OTP ky bien ban cuoi
          </button>
          <div v-if="finalSigning.requestId" class="otp-box">
            <p>OTP da gui. Ma doi soat file: <strong>{{ finalSigning.hashShort }}</strong></p>
            <label>
              OTP
              <input v-model.trim="finalSigning.otp" inputmode="numeric" maxlength="6" />
            </label>
            <button class="btn btn-primary" type="button" :disabled="working || finalSigning.otp.length !== 6" @click="signFinal">
              Ky bien ban cuoi
            </button>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { ownerPartnerTerminationService } from '../../services/ownerPartnerTermination.js';

const SIGNATURE_IMAGE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=';

const route = useRoute();
const loading = ref(true);
const working = ref(false);
const error = ref('');
const eligibility = ref(null);
const termination = ref(null);
const futureBookings = ref([]);
const selectedBookingIds = ref([]);

const form = reactive({
  reason: '',
  detail_reason: '',
  requested_effective_date: '',
  future_booking_policy: '',
  warning_accepted: false,
});

const signing = reactive({ requestId: null, hashShort: '', otp: '' });
const finalSigning = reactive({ requestId: null, hashShort: '', otp: '' });
const withdrawal = reactive({
  owner_wallet_id: '',
  owner_bank_account_id: '',
  amount: null,
  owner_note: 'Rut tien trong ho so cham dut hop dong.',
});

const cluster = computed(() => eligibility.value?.data?.cluster || termination.value?.venue_cluster || null);
const currentClusterId = computed(() => route.name === 'owner-partner-termination-request'
  ? (termination.value?.venue_cluster_id || termination.value?.venue_cluster?.id)
  : route.params.id);
const summary = computed(() => eligibility.value?.data?.summary || {
  owner_balance_total: termination.value?.owner_balance_total || 0,
  future_online_booking_liability: termination.value?.future_online_booking_liability || 0,
  pending_refund_liability: termination.value?.pending_refund_liability || 0,
  pending_withdrawal_amount: termination.value?.pending_withdrawal_amount || 0,
  withdrawable_amount: termination.value?.withdrawable_amount || 0,
  future_booking_count: termination.value?.future_booking_count || 0,
});
const policies = computed(() => eligibility.value?.data?.policies || []);
const canPreview = computed(() => Boolean(eligibility.value?.data?.eligible));

onMounted(load);

async function load() {
  loading.value = true;
  error.value = '';
  try {
    if (route.name === 'owner-partner-termination-request') {
      await loadRequest(route.params.id);
    } else {
      const response = await ownerPartnerTerminationService.eligibility(route.params.id);
      eligibility.value = response;
      termination.value = response.data?.active_request || null;
      if (termination.value) {
        hydrateForm(termination.value);
        await loadFutureBookings();
      }
    }
  } catch (err) {
    error.value = err.message || 'Khong the tai ho so cham dut.';
  } finally {
    loading.value = false;
  }
}

async function loadRequest(id) {
  const response = await ownerPartnerTerminationService.show(id);
  termination.value = response.data;
  hydrateForm(termination.value);
  await loadFutureBookings();
}

async function loadFutureBookings() {
  if (!termination.value?.id) return;
  const response = await ownerPartnerTerminationService.futureBookings(termination.value.id);
  futureBookings.value = response.data || [];
  selectedBookingIds.value = futureBookings.value.map((booking) => booking.id);
}

function hydrateForm(request) {
  form.reason = request.reason || form.reason;
  form.detail_reason = request.detail_reason || form.detail_reason;
  form.requested_effective_date = request.requested_effective_date || '';
  form.future_booking_policy = request.future_booking_policy || form.future_booking_policy;
  form.warning_accepted = Boolean(request.owner_warning_accepted_at || form.warning_accepted);
}

async function preview() {
  await run(async () => {
    const response = await ownerPartnerTerminationService.preview(currentClusterId.value, {
      ...form,
      warning_accepted: form.warning_accepted,
    });
    termination.value = response.data;
  });
}

async function sendOtp() {
  await run(async () => {
    const response = await ownerPartnerTerminationService.sendOtp(currentClusterId.value, {
      termination_request_id: termination.value.id,
      signature_image: SIGNATURE_IMAGE,
    });
    signing.requestId = response.data.signing_request_id;
    signing.hashShort = response.data.hash_short;
    signing.otp = '';
  });
}

async function submit() {
  await run(async () => {
    const response = await ownerPartnerTerminationService.submit(currentClusterId.value, {
      termination_request_id: termination.value.id,
      signing_request_id: signing.requestId,
      otp: signing.otp,
    });
    termination.value = response.data;
    await loadFutureBookings();
  });
}

async function bulkAction(action) {
  await run(async () => {
    const ids = selectedBookingIds.value.length ? selectedBookingIds.value : futureBookings.value.map((booking) => booking.id);
    const response = await ownerPartnerTerminationService.bulkAction(termination.value.id, {
      booking_ids: ids,
      action,
      reason: 'Xu ly booking trong ho so cham dut hop dong.',
    });
    termination.value = response.data;
    await loadFutureBookings();
  });
}

async function storeWithdrawal() {
  await run(async () => {
    await ownerPartnerTerminationService.storeWithdrawal(termination.value.id, withdrawal);
    await loadRequest(termination.value.id);
  });
}

async function sendFinalOtp() {
  await run(async () => {
    const response = await ownerPartnerTerminationService.finalDocumentSignSendOtp(termination.value.id, {
      signature_image: SIGNATURE_IMAGE,
    });
    finalSigning.requestId = response.data.signing_request_id;
    finalSigning.hashShort = response.data.hash_short;
    finalSigning.otp = '';
  });
}

async function signFinal() {
  await run(async () => {
    const response = await ownerPartnerTerminationService.finalDocumentSign(termination.value.id, {
      signing_request_id: finalSigning.requestId,
      otp: finalSigning.otp,
    });
    termination.value = response.data;
  });
}

async function run(callback) {
  working.value = true;
  error.value = '';
  try {
    await callback();
  } catch (err) {
    error.value = err.message || 'Thao tac khong thanh cong.';
  } finally {
    working.value = false;
  }
}

function money(value) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(value) || 0);
}

function statusLabel(status) {
  return {
    eligible: 'Co the tao yeu cau',
    draft_preview: 'Cho owner ky gui',
    cancellation_in_progress: 'Da gui yeu cau',
    future_bookings_processing: 'Dang xu ly booking',
    waiting_final_settlement: 'Cho quyet toan cuoi',
    waiting_final_document_signature: 'Cho ky bien ban cuoi',
    terminating: 'Dang trong thoi gian xem ho so',
    terminated: 'Da cham dut',
    owner_cancelled_request: 'Owner da huy yeu cau',
    admin_rejected: 'Admin tu choi',
  }[status] || status;
}
</script>

<style scoped>
.termination-page {
  display: grid;
  gap: 18px;
}

.page-head,
.section-title,
.actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.page-head h1,
.section-title h2,
.summary-panel h2 {
  margin: 0;
}

.eyebrow {
  margin: 0 0 4px;
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0;
  text-transform: uppercase;
}

.panel {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 18px;
}

.muted {
  color: #64748b;
}

.alert {
  border-color: #fecaca;
  color: #b91c1c;
}

.summary-panel {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(280px, 1fr);
  gap: 18px;
}

.money-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.money-grid div {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px;
}

.money-grid span,
.booking-row small,
.hint {
  color: #64748b;
}

.money-grid strong {
  display: block;
  margin-top: 4px;
}

.form-panel,
.split-panel {
  display: grid;
  gap: 14px;
}

.form-grid,
.split-panel {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

label {
  display: grid;
  gap: 6px;
  font-weight: 700;
}

input,
select,
textarea {
  width: 100%;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 10px 12px;
  font: inherit;
}

.check-row,
.booking-row {
  grid-template-columns: auto minmax(0, 1fr);
  align-items: start;
  font-weight: 500;
}

.booking-list {
  display: grid;
  gap: 8px;
}

.booking-row {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px;
}

.booking-row em {
  grid-column: 2;
  color: #0f766e;
  font-style: normal;
}

.otp-box {
  display: grid;
  gap: 10px;
  border: 1px dashed #94a3b8;
  border-radius: 8px;
  padding: 14px;
}

.btn {
  border-radius: 8px;
}

@media (max-width: 860px) {
  .summary-panel,
  .form-grid,
  .split-panel,
  .money-grid {
    grid-template-columns: 1fr;
  }

  .page-head,
  .section-title,
  .actions {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
