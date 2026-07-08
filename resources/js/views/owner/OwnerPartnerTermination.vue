<template>
  <div class="termination-page">
    <header class="page-head">
      <div>
        <p class="eyebrow">Chấm dứt hợp tác</p>
        <h1>Chấm dứt hợp đồng đối tác</h1>
      </div>
      <button class="btn btn-outline" type="button" @click="$router.back()">Quay lại</button>
    </header>

    <div v-if="loading" class="panel muted">Đang tải hồ sơ...</div>
    <div v-else-if="error" class="panel alert">
      <p>{{ error }}</p>
      <button class="btn btn-primary" type="button" @click="load">Thử lại</button>
    </div>

    <template v-else>
      <section class="panel summary-panel">
        <div>
          <p class="eyebrow">{{ cluster?.name || termination?.venue_cluster?.name || 'Cụm sân' }}</p>
          <h2>{{ statusLabel(termination?.status || 'eligible') }}</h2>
          <p class="hint">{{ eligibility?.reason || eligibility?.warning || 'Chủ sân có thể tạo đơn, ký OTP và theo dõi quy trình chấm dứt hợp tác.' }}</p>
        </div>
        <div class="money-grid">
          <div>
            <span>Số dư chủ sân</span>
            <strong>{{ money(summary.owner_balance_total) }}</strong>
          </div>
          <div>
            <span>Nghĩa vụ booking tương lai</span>
            <strong>{{ money(summary.future_online_booking_liability) }}</strong>
          </div>
          <div>
            <span>Đang hoàn/rút tiền</span>
            <strong>{{ money((Number(summary.pending_refund_liability) || 0) + (Number(summary.pending_withdrawal_amount) || 0)) }}</strong>
          </div>
          <div>
            <span>Có thể rút</span>
            <strong>{{ money(summary.withdrawable_amount) }}</strong>
          </div>
        </div>
      </section>

      <section v-if="!termination || termination.status === 'draft_preview'" class="panel form-panel">
        <div class="section-title">
          <h2>1. Tạo đơn yêu cầu</h2>
          <span>{{ summary.future_booking_count || 0 }} booking tương lai</span>
        </div>

        <label>
          Lý do chấm dứt
          <textarea v-model.trim="form.reason" rows="3" maxlength="2000" />
        </label>

        <label>
          Mô tả chi tiết
          <textarea v-model.trim="form.detail_reason" rows="4" maxlength="5000" />
        </label>

        <div class="form-grid">
          <label>
            Ngày mong muốn
            <input v-model="form.requested_effective_date" type="date" />
          </label>
          <label>
            Phương án booking tương lai
            <select v-model="form.future_booking_policy">
              <option value="">Chọn phương án</option>
              <option v-for="policy in policies" :key="policy.value" :value="policy.value">
                {{ policy.label }}
              </option>
            </select>
          </label>
        </div>

        <label class="check-row">
          <input v-model="form.warning_accepted" type="checkbox" />
          <span>Tôi đã đọc cảnh báo: sau khi gửi, cụm sân sẽ bị khóa các thao tác quản lý thông thường.</span>
        </label>

        <div class="actions">
          <button class="btn btn-primary" type="button" :disabled="working || !canPreview" @click="preview">
            Tạo bản xem trước
          </button>
        </div>

        <div v-if="termination" class="signature-panel">
          <div class="section-title">
            <h2>2. Ký và gửi yêu cầu</h2>
            <span>OTP gửi qua email chủ sân</span>
          </div>
          <p class="hint">Kiểm tra file đã sinh ở mục văn bản, ký vào khung dưới đây rồi gửi OTP để xác nhận đơn yêu cầu chấm dứt.</p>
          <canvas
            ref="requestSignatureCanvas"
            class="signature-pad"
            width="620"
            height="190"
            @pointerdown="startSignature($event, 'request')"
            @pointermove="drawSignature($event, 'request')"
            @pointerup="stopSignature"
            @pointerleave="stopSignature"
          ></canvas>
          <label class="check-row">
            <input v-model="requestSignatureAccepted" type="checkbox" />
            <span>Tôi xác nhận đã đọc đơn yêu cầu, hiểu ảnh hưởng tới booking, số dư và quyền quản lý cụm sân.</span>
          </label>
          <div class="actions">
            <button class="btn btn-outline" type="button" @click="clearSignaturePad('request')">Ký lại</button>
            <button class="btn btn-primary" type="button" :disabled="working || requestSignatureEmpty || !requestSignatureAccepted" @click="confirmBeforeOtp">
              Gửi OTP ký đơn
            </button>
          </div>
        </div>

        <div v-if="signing.requestId" class="otp-box">
          <p>OTP đã gửi. Mã đối soát file: <strong>{{ signing.hashShort }}</strong></p>
          <label>
            OTP
            <input v-model.trim="signing.otp" inputmode="numeric" maxlength="6" />
          </label>
          <button class="btn btn-primary" type="button" :disabled="working || signing.otp.length !== 6" @click="submit">
            Ký và gửi yêu cầu
          </button>
        </div>
      </section>

      <section v-if="documents.length" class="panel document-panel">
        <div class="section-title">
          <h2>Văn bản đã tạo</h2>
          <span>{{ documents.length }} file</span>
        </div>
        <div class="document-list">
          <div v-for="document in documents" :key="document.id" class="document-row">
            <div>
              <strong>{{ documentTypeLabel(document.document_type) }}</strong>
              <small>{{ documentMeta(document) }}</small>
            </div>
            <div class="document-actions">
              <span class="status-pill">{{ documentStatusLabel(document.status) }}</span>
              <button
                v-if="document.generated_document?.id"
                class="btn btn-outline"
                type="button"
                :disabled="working"
                @click="downloadDocument(document.generated_document)"
              >
                Tải file
              </button>
            </div>
          </div>
        </div>
      </section>

      <section v-if="termination && termination.status !== 'draft_preview'" class="panel">
        <div class="section-title">
          <h2>2. Xử lý booking tương lai</h2>
          <button class="btn btn-outline" type="button" @click="loadFutureBookings">Làm mới</button>
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
        <p v-else class="hint">Không còn booking tương lai bắt buộc xử lý.</p>

        <div class="actions">
          <button class="btn btn-primary" type="button" :disabled="working || !futureBookings.length" @click="bulkAction('cancel_all_refund_to_user_balance')">
            Hủy và hoàn tiền
          </button>
          <button class="btn btn-outline" type="button" :disabled="working || !futureBookings.length" @click="bulkAction('serve_until_last_booking')">
            Phục vụ đến booking cuối
          </button>
          <button class="btn btn-outline" type="button" :disabled="working || !futureBookings.length" @click="bulkAction('manual_per_booking')">
            Xử lý thủ công
          </button>
        </div>
      </section>

      <section v-if="termination && termination.status !== 'draft_preview'" class="panel split-panel">
        <div>
          <div class="section-title">
            <h2>3. Rút tiền quyết toán</h2>
          </div>
          <div class="form-grid">
            <label>
              Mã số dư chủ sân
              <input v-model.trim="withdrawal.owner_wallet_id" inputmode="numeric" />
            </label>
            <label>
              Mã tài khoản ngân hàng nhận tiền
              <input v-model.trim="withdrawal.owner_bank_account_id" inputmode="numeric" />
            </label>
            <label>
              Số tiền
              <input v-model.number="withdrawal.amount" type="number" min="50000" step="1000" />
            </label>
          </div>
          <button class="btn btn-primary" type="button" :disabled="working" @click="storeWithdrawal">
            Gửi yêu cầu rút tiền
          </button>
        </div>

        <div>
          <div class="section-title">
            <h2>4. Biên bản cuối</h2>
          </div>
          <p class="hint">
            Khi admin sinh biên bản cuối và ký SportGo, chủ sân sẽ nhận OTP để ký xác nhận.
          </p>
          <p v-if="termination.status !== 'waiting_final_document_signature'" class="hint">
            Biên bản cuối chỉ ký sau khi booking và công nợ đã xử lý xong.
          </p>
          <p v-else-if="!termination.final_document_admin_signed_at" class="hint">
            Đang chờ SportGo ký trước khi chủ sân ký xác nhận.
          </p>
          <div v-if="canOwnerSignFinal" class="signature-panel compact">
            <canvas
              ref="finalSignatureCanvas"
              class="signature-pad"
              width="620"
              height="190"
              @pointerdown="startSignature($event, 'final')"
              @pointermove="drawSignature($event, 'final')"
              @pointerup="stopSignature"
              @pointerleave="stopSignature"
            ></canvas>
            <label class="check-row">
              <input v-model="finalSignatureAccepted" type="checkbox" />
              <span>Tôi xác nhận đã đọc biên bản cuối và đồng ý hoàn tất chấm dứt hợp tác.</span>
            </label>
          </div>
          <button class="btn btn-outline" type="button" :disabled="working || !canOwnerSignFinal || finalSignatureEmpty || !finalSignatureAccepted" @click="sendFinalOtp">
            Gửi OTP ký biên bản cuối
          </button>
          <div v-if="finalSigning.requestId" class="otp-box">
            <p>OTP đã gửi. Mã đối soát file: <strong>{{ finalSigning.hashShort }}</strong></p>
            <label>
              OTP
              <input v-model.trim="finalSigning.otp" inputmode="numeric" maxlength="6" />
            </label>
            <button class="btn btn-primary" type="button" :disabled="working || finalSigning.otp.length !== 6" @click="signFinal">
              Ký biên bản cuối
            </button>
          </div>
        </div>
      </section>
    </template>

    <div v-if="showFutureBookingConfirm" class="modal-backdrop" @click.self="showFutureBookingConfirm = false">
      <div class="confirm-dialog">
        <div class="section-title">
          <h2>Xác nhận booking tương lai</h2>
          <button class="btn btn-outline" type="button" @click="showFutureBookingConfirm = false">Đóng</button>
        </div>
        <p class="hint">
          Cụm sân đang có booking tương lai. Sau khi chủ sân ký gửi yêu cầu, cụm sân sẽ dừng nhận booking mới và chỉ còn các thao tác trong hồ sơ chấm dứt.
        </p>
        <div class="confirm-grid">
          <div>
            <span>Booking tương lai</span>
            <strong>{{ effectiveFutureBookingCount }}</strong>
          </div>
          <div>
            <span>Phương án</span>
            <strong>{{ selectedPolicyLabel }}</strong>
          </div>
          <div>
            <span>Tiền online đang giữ</span>
            <strong>{{ money(summary.future_online_booking_liability) }}</strong>
          </div>
        </div>
        <div class="actions">
          <button class="btn btn-outline" type="button" @click="showFutureBookingConfirm = false">Kiểm tra lại</button>
          <button class="btn btn-primary" type="button" :disabled="working" @click="sendOtpFromConfirm">
            Xác nhận và gửi OTP
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { ownerPartnerTerminationService } from '../../services/ownerPartnerTermination.js';

const route = useRoute();
const loading = ref(true);
const working = ref(false);
const error = ref('');
const eligibility = ref(null);
const termination = ref(null);
const futureBookings = ref([]);
const selectedBookingIds = ref([]);
const showFutureBookingConfirm = ref(false);
const requestSignatureCanvas = ref(null);
const finalSignatureCanvas = ref(null);
const activeSignaturePad = ref('');
const requestSignatureEmpty = ref(true);
const finalSignatureEmpty = ref(true);
const requestSignatureAccepted = ref(false);
const finalSignatureAccepted = ref(false);

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
  owner_note: 'Rút tiền trong hồ sơ chấm dứt hợp đồng.',
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
const documents = computed(() => termination.value?.documents || []);
const effectiveFutureBookingCount = computed(() => Number(termination.value?.future_booking_count ?? summary.value.future_booking_count) || 0);
const selectedPolicyLabel = computed(() => policyLabel(termination.value?.future_booking_policy || form.future_booking_policy));
const canOwnerSignFinal = computed(() => termination.value?.status === 'waiting_final_document_signature' && Boolean(termination.value?.final_document_admin_signed_at));

onMounted(async () => {
  await load();
  prepareSignaturePads();
});

watch(() => [termination.value?.id, termination.value?.status, canOwnerSignFinal.value], () => {
  prepareSignaturePads();
});

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
    error.value = err.message || 'Không thể tải hồ sơ chấm dứt.';
  } finally {
    loading.value = false;
  }
  prepareSignaturePads();
}

async function loadRequest(id) {
  const response = await ownerPartnerTerminationService.show(id);
  termination.value = response.data;
  hydrateForm(termination.value);
  await loadFutureBookings();
  prepareSignaturePads();
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
    resetRequestSigning();
    prepareSignaturePads();
  });
}

async function confirmBeforeOtp() {
  if (effectiveFutureBookingCount.value > 0) {
    showFutureBookingConfirm.value = true;
    return;
  }

  await sendOtp();
}

async function sendOtpFromConfirm() {
  showFutureBookingConfirm.value = false;
  await sendOtp();
}

async function sendOtp() {
  await run(async () => {
    const response = await ownerPartnerTerminationService.sendOtp(currentClusterId.value, {
      termination_request_id: termination.value.id,
      signature_image: signatureImage('request'),
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
      reason: 'Xử lý booking trong hồ sơ chấm dứt hợp đồng.',
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

async function downloadDocument(document) {
  if (!document?.id) return;
  await run(async () => {
    await ownerPartnerTerminationService.downloadDocument(document.id);
  });
}

async function sendFinalOtp() {
  await run(async () => {
    const response = await ownerPartnerTerminationService.finalDocumentSignSendOtp(termination.value.id, {
      signature_image: signatureImage('final'),
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
    error.value = err.message || 'Thao tác không thành công.';
  } finally {
    working.value = false;
  }
}

async function prepareSignaturePads() {
  await nextTick();
  prepareSignaturePad('request');
  prepareSignaturePad('final');
}

function prepareSignaturePad(pad) {
  const canvas = pad === 'final' ? finalSignatureCanvas.value : requestSignatureCanvas.value;
  if (!canvas) return;

  const context = canvas.getContext('2d');
  context.fillStyle = '#fff';
  context.fillRect(0, 0, canvas.width, canvas.height);
  context.strokeStyle = '#0f172a';
  context.lineWidth = 2.4;
  context.lineCap = 'round';

  if (pad === 'final') {
    finalSignatureEmpty.value = true;
  } else {
    requestSignatureEmpty.value = true;
  }
}

function point(event, canvas) {
  const rect = canvas.getBoundingClientRect();
  return {
    x: ((event.clientX - rect.left) / rect.width) * canvas.width,
    y: ((event.clientY - rect.top) / rect.height) * canvas.height,
  };
}

function startSignature(event, pad) {
  const canvas = pad === 'final' ? finalSignatureCanvas.value : requestSignatureCanvas.value;
  if (!canvas) return;

  activeSignaturePad.value = pad;
  if (pad === 'final') {
    finalSignatureEmpty.value = false;
    finalSigning.requestId = null;
    finalSigning.hashShort = '';
    finalSigning.otp = '';
  } else {
    requestSignatureEmpty.value = false;
    signing.requestId = null;
    signing.hashShort = '';
    signing.otp = '';
  }

  const context = canvas.getContext('2d');
  const current = point(event, canvas);
  context.beginPath();
  context.moveTo(current.x, current.y);
}

function drawSignature(event, pad) {
  if (activeSignaturePad.value !== pad) return;
  const canvas = pad === 'final' ? finalSignatureCanvas.value : requestSignatureCanvas.value;
  if (!canvas) return;

  const context = canvas.getContext('2d');
  const current = point(event, canvas);
  context.lineTo(current.x, current.y);
  context.stroke();
}

function stopSignature() {
  activeSignaturePad.value = '';
}

function clearSignaturePad(pad) {
  prepareSignaturePad(pad);
  if (pad === 'final') {
    finalSigning.requestId = null;
    finalSigning.hashShort = '';
    finalSigning.otp = '';
    finalSignatureAccepted.value = false;
  } else {
    resetRequestSigning();
  }
}

function resetRequestSigning() {
  signing.requestId = null;
  signing.hashShort = '';
  signing.otp = '';
  requestSignatureAccepted.value = false;
}

function signatureImage(pad) {
  const canvas = pad === 'final' ? finalSignatureCanvas.value : requestSignatureCanvas.value;
  return canvas?.toDataURL('image/png') || '';
}

function money(value) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(value) || 0);
}

function statusLabel(status) {
  return {
    eligible: 'Có thể tạo yêu cầu',
    draft_preview: 'Chờ chủ sân ký gửi',
    cancellation_in_progress: 'Đã gửi yêu cầu',
    future_bookings_processing: 'Đang xử lý booking',
    waiting_final_settlement: 'Chờ quyết toán cuối',
    waiting_final_document_signature: 'Chờ ký biên bản cuối',
    terminating: 'Đang trong thời gian xem hồ sơ',
    terminated: 'Đã chấm dứt',
    owner_cancelled_request: 'Chủ sân đã hủy yêu cầu',
    admin_rejected: 'Admin từ chối',
  }[status] || status;
}

function policyLabel(value) {
  return policies.value.find((policy) => policy.value === value)?.label || value || '-';
}

function documentTypeLabel(type) {
  return {
    owner_termination_request: 'Đơn yêu cầu chấm dứt của chủ sân',
    settlement_minutes: 'Biên bản chấm dứt cuối',
    final_termination_file: 'Biên bản chấm dứt cuối',
  }[type] || type || 'Văn bản';
}

function documentStatusLabel(status) {
  return {
    pending_signature: 'Chờ ký',
    generated: 'Đã tạo',
    signed: 'Đã ký',
    completed: 'Hoàn tất',
  }[status] || status || 'Đã tạo';
}

function documentMeta(document) {
  const generated = document.generated_document || {};
  return [
    generated.document_code,
    documentStatusLabel(generated.status),
    generated.signatures?.length ? `${generated.signatures.length} chữ ký` : null,
  ].filter(Boolean).join(' - ');
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

.signature-panel {
  display: grid;
  gap: 12px;
  border: 1px dashed #94a3b8;
  border-radius: 8px;
  background: #f8fafc;
  padding: 14px;
}

.signature-panel.compact {
  margin: 12px 0;
}

.signature-pad {
  width: 100%;
  max-width: 100%;
  height: 190px;
  touch-action: none;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #fff;
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

.document-list {
  display: grid;
  gap: 10px;
}

.document-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px;
}

.document-row small {
  display: block;
  margin-top: 4px;
  color: #64748b;
}

.document-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.status-pill {
  border: 1px solid #bbf7d0;
  border-radius: 999px;
  color: #166534;
  font-size: 12px;
  font-weight: 700;
  padding: 5px 9px;
  white-space: nowrap;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: grid;
  place-items: center;
  background: rgb(15 23 42 / 45%);
  padding: 18px;
}

.confirm-dialog {
  display: grid;
  gap: 14px;
  width: min(620px, 100%);
  background: #fff;
  border-radius: 8px;
  padding: 18px;
}

.confirm-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.confirm-grid div {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px;
}

.confirm-grid span {
  display: block;
  color: #64748b;
  font-size: 12px;
}

.confirm-grid strong {
  display: block;
  margin-top: 4px;
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
  .money-grid,
  .confirm-grid {
    grid-template-columns: 1fr;
  }

  .page-head,
  .section-title,
  .actions,
  .document-row,
  .document-actions {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
