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
    <div v-else-if="loadError" class="panel alert">
      <p>{{ loadError }}</p>
      <button class="btn btn-primary" type="button" @click="load">Thử lại</button>
    </div>

    <template v-else>
      <section class="panel summary-panel" :class="{ archived: hasArchivedTermination }">
        <div>
          <p class="eyebrow">{{ cluster?.name || termination?.venue_cluster?.name || 'Cụm sân' }}</p>
          <h2>{{ summaryStatusTitle }}</h2>
          <p class="hint">{{ summaryStatusDescription }}</p>
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

      <section v-if="!termination && latestClosedRequest" class="archive-band">
        <div>
          <p class="eyebrow">Hồ sơ gần nhất</p>
          <h2>{{ statusLabel(latestClosedRequest.status) }}</h2>
          <p class="hint">{{ latestClosedRequest.reason }}</p>
        </div>
        <div class="archive-documents">
          <span>{{ latestClosedDocuments.length }} văn bản được lưu</span>
          <button
            v-for="document in latestClosedDocuments"
            :key="document.id"
            class="btn btn-outline"
            type="button"
            @click="openDocumentPreview(document)"
          >
            Xem {{ documentTypeLabel(document.document_type).toLowerCase() }}
          </button>
        </div>
      </section>

      <nav v-if="termination && !isOwnerCancelledStatus(termination.status)" class="workflow-strip" aria-label="Tiến độ chấm dứt hợp tác">
        <div v-for="(step, index) in ownerSteps" :key="step.key" class="workflow-step" :class="step.state">
          <span class="workflow-marker">{{ step.state === 'done' ? '✓' : index + 1 }}</span>
          <strong>{{ step.label }}</strong>
        </div>
      </nav>

      <section v-if="termination" class="next-action-panel">
        <div>
          <p class="eyebrow">Việc cần làm tiếp theo</p>
          <h2>{{ ownerNextAction.title }}</h2>
          <p>{{ ownerNextAction.description }}</p>
        </div>
        <div v-if="isDraftRequest(termination.status) && !isUnilateralNotice" class="actions compact-actions">
          <button class="btn btn-outline" type="button" @click="editingDraft = true">Sửa nội dung</button>
          <button class="btn btn-primary" type="button" @click="openRequestPreview">Xem file và ký</button>
        </div>
        <div v-else-if="isUnilateralNotice && isSubmittedRequest(termination.status)" class="actions compact-actions">
          <button class="btn btn-primary" type="button" @click="openUnilateralNotice">Mở công văn</button>
        </div>
        <div v-else-if="canOwnerSignFinal" class="actions compact-actions">
          <button class="btn btn-primary" type="button" @click="openFinalPreview">Xem file và ký biên bản</button>
        </div>
      </section>

      <div v-if="actionError" class="inline-error" role="alert">
        <strong>Chưa thể thực hiện thao tác</strong>
        <p>{{ actionError }}</p>
      </div>

      <section v-if="isUnilateralNotice && isSubmittedRequest(termination.status)" class="acknowledgement-band">
        <div>
          <p class="eyebrow">Xác nhận tiếp nhận</p>
          <strong>Chủ sân cần đọc công văn trước khi xác nhận</strong>
          <p class="hint">Sau khi xác nhận, hồ sơ chuyển sang xử lý booking và nghĩa vụ tài chính. Việc xác nhận không đồng nghĩa từ bỏ quyền yêu cầu xem xét lại.</p>
        </div>
        <label class="check-row">
          <input v-model="noticeAcknowledgementAccepted" type="checkbox" />
          <span>Tôi xác nhận đã mở, đọc và nhận công văn chấm dứt do SportGo phát hành.</span>
        </label>
        <button class="btn btn-primary" type="button" :disabled="working || !noticeAcknowledgementAccepted" @click="acknowledgeNotice">
          Xác nhận đã nhận
        </button>
      </section>

      <section v-if="canRequestReconsideration" class="reconsideration-band">
        <button v-if="!showReconsiderationForm" class="btn btn-outline" type="button" @click="showReconsiderationForm = true">
          Yêu cầu SportGo xem xét lại
        </button>
        <form v-else @submit.prevent="requestReconsideration">
          <div>
            <strong>Yêu cầu xem xét lại công văn</strong>
            <p class="hint">Nêu rõ căn cứ hoặc dữ liệu cần SportGo kiểm tra. Công văn vẫn có hiệu lực cho đến khi admin thu hồi.</p>
          </div>
          <label>
            Nội dung đề nghị
            <textarea v-model.trim="reconsiderationReason" rows="4" minlength="20" maxlength="2000" required />
          </label>
          <div class="actions compact-actions">
            <button class="btn btn-outline" type="button" @click="showReconsiderationForm = false">Đóng</button>
            <button class="btn btn-primary" type="submit" :disabled="working || reconsiderationReason.length < 20">Gửi xem xét lại</button>
          </div>
        </form>
        <p v-if="termination.workflow_state?.reconsideration_pending" class="pending-note">SportGo đang xem xét phản hồi gần nhất của chủ sân.</p>
      </section>

      <section v-if="((!termination && !hasArchivedTermination) || editingDraft) && !isUnilateralNotice" class="panel form-panel">
        <div class="section-title">
          <div>
            <p class="eyebrow">Bước 1</p>
            <h2>Thông tin yêu cầu</h2>
          </div>
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
          <button v-if="termination" class="btn btn-outline" type="button" @click="editingDraft = false">Đóng chỉnh sửa</button>
          <button class="btn btn-primary" type="button" :disabled="working || !canPreview" @click="preview">
            Xem trước đơn
          </button>
        </div>
      </section>

      <section v-if="displayDocuments.length" class="panel document-panel">
        <div class="section-title">
          <h2>Văn bản đã tạo</h2>
          <span>{{ displayDocuments.length }} file hiện hành</span>
        </div>
        <div class="document-list">
          <div v-for="document in displayDocuments" :key="document.id" class="document-row">
            <div>
              <strong>{{ documentTypeLabel(document.document_type) }}</strong>
              <small>{{ documentMeta(document) }}</small>
            </div>
            <div class="document-actions">
              <span class="status-pill">{{ documentProgressLabel(document) }}</span>
              <button
                v-if="document.generated_document?.id && !(canOwnerSignFinal && isFinalDocument(document))"
                class="btn btn-outline"
                type="button"
                :disabled="working"
                @click="isPendingCancellationDocument(document) ? openCancellationPreview(document) : openDocumentPreview(document)"
              >
                {{ isPendingCancellationDocument(document) ? 'Xem file và ký' : 'Xem file' }}
              </button>
            </div>
          </div>
        </div>
      </section>

      <section v-if="showBookingWorkspace" class="panel">
        <div class="section-title">
          <h2>2. Xử lý booking tương lai</h2>
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
        <div v-if="futureBookings.length" class="booking-bulk-bar">
          <label>
            Phương án xử lý
            <select v-model="bookingActionChoice">
              <option value="">Chọn phương án</option>
              <option v-for="policy in policies" :key="policy.value" :value="policy.value">{{ policy.label }}</option>
            </select>
          </label>
          <div>
            <span>{{ selectedBookingIds.length }} booking đã chọn</span>
            <button class="btn btn-primary" type="button" :disabled="working || !selectedBookingIds.length || !bookingActionChoice" @click="bulkAction(bookingActionChoice)">
              Áp dụng phương án
            </button>
          </div>
        </div>
        <p v-else class="empty-copy">Không còn booking tương lai cần xử lý.</p>
      </section>

      <section v-if="showSettlementWorkspace" class="panel">
        <div>
          <div class="section-title">
            <h2>3. Rút tiền quyết toán</h2>
          </div>
          <div class="form-grid">
            <label>
              Nguồn số dư
              <select v-model="withdrawal.owner_wallet_id">
                <option value="">Chọn nguồn số dư</option>
                <option v-for="wallet in ownerWallets" :key="wallet.id" :value="wallet.id">
                  {{ wallet.venue_cluster?.name || cluster?.name || 'Số dư chủ sân' }} - {{ money(wallet.available_balance) }}
                </option>
              </select>
            </label>
            <label>
              Tài khoản nhận tiền
              <select v-model="withdrawal.owner_bank_account_id">
                <option value="">Chọn tài khoản ngân hàng</option>
                <option v-for="account in bankAccounts" :key="account.id" :value="account.id">
                  {{ account.bank_name }} - {{ maskAccountNumber(account.account_number) }} - {{ account.account_holder_name }}
                </option>
              </select>
            </label>
            <label>
              Số tiền
              <input v-model.number="withdrawal.amount" type="number" min="50000" :max="summary.withdrawable_amount" step="1000" />
              <small>Tối đa {{ money(summary.withdrawable_amount) }}</small>
            </label>
          </div>
          <p v-if="!ownerWallets.length || !bankAccounts.length" class="withdrawal-help">
            Chưa có nguồn số dư hoặc tài khoản ngân hàng hợp lệ.
            <RouterLink :to="{ name: 'owner-finance' }">Mở trang tài chính để kiểm tra</RouterLink>.
          </p>
          <button class="btn btn-primary" type="button" :disabled="working || !canSubmitWithdrawal" @click="storeWithdrawal">
            Gửi yêu cầu rút tiền
          </button>
        </div>

      </section>

      <details v-if="canOwnerCancelRequest" class="panel cancel-panel">
        <summary>Không tiếp tục chấm dứt hợp tác?</summary>
        <div class="cancel-panel-body">
          <div class="section-title">
            <h2>Hủy yêu cầu chấm dứt</h2>
            <span>Cần chữ ký và OTP của chủ sân</span>
          </div>
        <p class="hint">
          Chỉ hủy được khi hồ sơ chưa vào bước ký biên bản cuối và chưa có booking bị hủy/hoàn tiền không thể đảo ngược. Các xử lý thủ công đã phát sinh sẽ được giữ trong lịch sử.
        </p>
        <label>
          Lý do hủy yêu cầu
          <textarea v-model.trim="cancelForm.reason" rows="3" maxlength="1000" />
        </label>
        <div class="actions">
          <span class="hint">{{ cancelForm.reason.length }}/1000 ký tự</span>
          <button class="btn btn-danger" type="button" :disabled="working || cancelForm.reason.length < 10" @click="previewCancellation">
            Tạo văn bản hủy và ký
          </button>
        </div>
        </div>
      </details>
    </template>

    <DocumentViewerModal
      :show="showPreviewModal"
      :document="previewDocument"
      :action-mode="['request', 'cancel', 'final'].includes(previewPurpose)"
      @close="closePreviewModal"
    >
      <template #actions>
        <div v-if="actionError" class="inline-error modal-action-error" role="alert">
          <strong>Chưa thể thực hiện thao tác</strong>
          <p>{{ actionError }}</p>
        </div>
        <div v-if="previewPurpose === 'request'" class="preview-signing-panel">
          <div class="signing-context">
            <span>Đơn yêu cầu chấm dứt</span>
            <p>Chữ ký và OTP sẽ xác nhận đúng phiên bản file đang mở.</p>
          </div>
          <div class="signature-field">
            <div class="signature-field-head">
              <strong>Chữ ký chủ sân</strong>
              <button type="button" @click="clearSignaturePad('request')">Xóa</button>
            </div>
            <canvas
              ref="requestSignatureCanvas"
              class="signature-pad modal-signature"
              width="620"
              height="150"
              @pointerdown="startSignature($event, 'request')"
              @pointermove="drawSignature($event, 'request')"
              @pointerup="stopSignature"
              @pointerleave="stopSignature"
            ></canvas>
          </div>
          <label class="check-row">
            <input v-model="requestSignatureAccepted" type="checkbox" />
            <span>Tôi xác nhận nội dung file đúng và hiểu ảnh hưởng tới booking, số dư, quyền quản lý cụm sân.</span>
          </label>
          <div v-if="!signing.requestId" class="actions signing-submit-row">
            <button class="btn btn-primary" type="button" :disabled="working || requestSignatureEmpty || !requestSignatureAccepted" @click="confirmBeforeOtp">
              Gửi OTP xác nhận
            </button>
          </div>
          <div v-else class="otp-box">
            <p><strong>Nhập OTP email chủ sân</strong><small>Mã đối soát {{ signing.hashShort }}</small></p>
            <label>
              Mã OTP gồm 6 số
              <input v-model.trim="signing.otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code" />
            </label>
            <button class="btn btn-primary" type="button" :disabled="working || signing.otp.length !== 6" @click="submit">
              Ký và gửi yêu cầu
            </button>
          </div>
        </div>

        <div v-else-if="previewPurpose === 'cancel'" class="preview-signing-panel">
          <div class="signing-context warning">
            <span>Hủy yêu cầu chấm dứt</span>
            <p>Đơn cũ vẫn được lưu; chữ ký này chỉ áp dụng cho văn bản hủy đang mở.</p>
          </div>
          <div class="signature-field">
            <div class="signature-field-head">
              <strong>Chữ ký chủ sân</strong>
              <button type="button" @click="clearSignaturePad('cancel')">Xóa</button>
            </div>
            <canvas
              ref="cancelSignatureCanvas"
              class="signature-pad modal-signature"
              width="620"
              height="150"
              @pointerdown="startSignature($event, 'cancel')"
              @pointermove="drawSignature($event, 'cancel')"
              @pointerup="stopSignature"
              @pointerleave="stopSignature"
            ></canvas>
          </div>
          <label class="check-row">
            <input v-model="cancelSignatureAccepted" type="checkbox" />
            <span>Tôi xác nhận nội dung văn bản hủy đúng và hiểu dữ liệu đã xử lý không tự động rollback.</span>
          </label>
          <div v-if="!cancelSigning.requestId" class="actions signing-submit-row">
            <button class="btn btn-danger" type="button" :disabled="working || cancelSignatureEmpty || !cancelSignatureAccepted" @click="sendCancelOtp">
              Gửi OTP xác nhận hủy
            </button>
          </div>
          <div v-else class="otp-box">
            <p><strong>Nhập OTP email chủ sân</strong><small>Mã đối soát {{ cancelSigning.hashShort }}</small></p>
            <label>
              Mã OTP gồm 6 số
              <input v-model.trim="cancelSigning.otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code" />
            </label>
            <button class="btn btn-danger" type="button" :disabled="working || cancelSigning.otp.length !== 6" @click="cancelRequest">
              Ký và xác nhận hủy yêu cầu
            </button>
          </div>
        </div>

        <div v-else-if="previewPurpose === 'final'" class="preview-signing-panel">
          <div class="signing-context">
            <span>Biên bản chấm dứt cuối</span>
            <p>SportGo đã ký. Chủ sân kiểm tra booking và quyết toán trước khi xác nhận.</p>
          </div>
          <div class="signature-field">
            <div class="signature-field-head">
              <strong>Chữ ký chủ sân</strong>
              <button type="button" @click="clearSignaturePad('final')">Xóa</button>
            </div>
            <canvas
              ref="finalSignatureCanvas"
              class="signature-pad modal-signature"
              width="620"
              height="150"
              @pointerdown="startSignature($event, 'final')"
              @pointermove="drawSignature($event, 'final')"
              @pointerup="stopSignature"
              @pointerleave="stopSignature"
            ></canvas>
          </div>
          <label class="check-row">
            <input v-model="finalSignatureAccepted" type="checkbox" />
            <span>Tôi xác nhận đã đọc biên bản cuối và đồng ý hoàn tất chấm dứt hợp tác.</span>
          </label>
          <div v-if="!finalSigning.requestId" class="actions signing-submit-row">
            <button class="btn btn-primary" type="button" :disabled="working || finalSignatureEmpty || !finalSignatureAccepted" @click="sendFinalOtp">
              Gửi OTP xác nhận
            </button>
          </div>
          <div v-else class="otp-box">
            <p><strong>Nhập OTP email chủ sân</strong><small>Mã đối soát {{ finalSigning.hashShort }}</small></p>
            <label>
              Mã OTP gồm 6 số
              <input v-model.trim="finalSigning.otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code" />
            </label>
            <button class="btn btn-primary" type="button" :disabled="working || finalSigning.otp.length !== 6" @click="signFinal">
              Ký biên bản cuối
            </button>
          </div>
        </div>
      </template>
    </DocumentViewerModal>

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
import DocumentViewerModal from '../../components/DocumentViewerModal.vue';
import { ownerPartnerTerminationService } from '../../services/ownerPartnerTermination.js';
import { api } from '../../services/api.js';

const route = useRoute();
const loading = ref(true);
const working = ref(false);
const loadError = ref('');
const actionError = ref('');
const eligibility = ref(null);
const termination = ref(null);
const futureBookings = ref([]);
const selectedBookingIds = ref([]);
const bookingActionChoice = ref('');
const ownerWallets = ref([]);
const bankAccounts = ref([]);
const showFutureBookingConfirm = ref(false);
const showPreviewModal = ref(false);
const previewPurpose = ref('document');
const editingDraft = ref(false);
const noticeAcknowledgementAccepted = ref(false);
const showReconsiderationForm = ref(false);
const reconsiderationReason = ref('');
const requestSignatureCanvas = ref(null);
const finalSignatureCanvas = ref(null);
const cancelSignatureCanvas = ref(null);
const activeSignaturePad = ref('');
const requestSignatureEmpty = ref(true);
const finalSignatureEmpty = ref(true);
const cancelSignatureEmpty = ref(true);
const requestSignatureAccepted = ref(false);
const finalSignatureAccepted = ref(false);
const cancelSignatureAccepted = ref(false);

const form = reactive({
  reason: '',
  detail_reason: '',
  requested_effective_date: '',
  future_booking_policy: '',
  warning_accepted: false,
});

const signing = reactive({ requestId: null, hashShort: '', otp: '' });
const finalSigning = reactive({ requestId: null, hashShort: '', otp: '' });
const cancelSigning = reactive({ requestId: null, hashShort: '', otp: '' });
const cancelForm = reactive({ reason: '' });
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
const summary = computed(() => eligibility.value?.data?.summary || termination.value?.financial_summary || {
  owner_balance_total: termination.value?.owner_balance_total || 0,
  future_online_booking_liability: termination.value?.future_online_booking_liability || 0,
  pending_refund_liability: termination.value?.pending_refund_liability || 0,
  pending_withdrawal_amount: termination.value?.pending_withdrawal_amount || 0,
  withdrawable_amount: termination.value?.withdrawable_amount || 0,
  future_booking_count: termination.value?.future_booking_count || 0,
});
const policies = computed(() => eligibility.value?.data?.policies || []);
const allFutureBookingsSelected = computed({
  get: () => futureBookings.value.length > 0 && selectedBookingIds.value.length === futureBookings.value.length,
  set: (checked) => {
    selectedBookingIds.value = checked ? futureBookings.value.map((booking) => booking.id) : [];
  },
});
const canSubmitWithdrawal = computed(() => {
  const amount = Number(withdrawal.amount || 0);
  return Boolean(
    withdrawal.owner_wallet_id
    && withdrawal.owner_bank_account_id
    && amount >= 50000
    && amount <= Number(summary.value.withdrawable_amount || 0)
  );
});
const canPreview = computed(() => Boolean(eligibility.value?.data?.eligible));
const latestClosedRequest = computed(() => eligibility.value?.data?.latest_closed_request || null);
const latestClosedDocuments = computed(() => requestDocuments(latestClosedRequest.value));
const hasArchivedTermination = computed(() => isTerminatedStatus(latestClosedRequest.value?.status));
const documents = computed(() => requestDocuments(termination.value));
const displayDocuments = computed(() => {
  const currentByType = new Map();
  [...documents.value]
    .sort((left, right) => Number(right.generated_document?.id || right.id) - Number(left.generated_document?.id || left.id))
    .forEach((document) => {
      const key = document.document_type || document.generated_document?.document_type || document.id;
      if (!currentByType.has(key)) currentByType.set(key, document);
    });

  return [...currentByType.values()];
});
const latestRequestDocument = computed(() => displayDocuments.value.find((document) => (
  ['owner_termination_request', 'termination_request', 'unilateral_notice', 'unilateral_termination_notice'].includes(document.document_type)
)) || null);
const latestFinalDocument = computed(() => displayDocuments.value.find((document) => (
  ['settlement_minutes', 'final_termination_file'].includes(document.document_type)
)) || null);
const finalAdminSigned = computed(() => Boolean(termination.value?.final_document_admin_signed_at)
  || (latestFinalDocument.value?.generated_document?.signatures || [])
    .some((signature) => signature.signer_side === 'sportgo' && signature.status === 'signed'));
const finalOwnerSigned = computed(() => Boolean(termination.value?.final_document_owner_signed_at)
  || (latestFinalDocument.value?.generated_document?.signatures || [])
    .some((signature) => signature.signer_side === 'owner' && signature.status === 'signed'));
const selectedPreviewRow = ref(null);
const previewDocument = computed(() => {
  const row = selectedPreviewRow.value
    || (previewPurpose.value === 'final' ? latestFinalDocument.value : latestRequestDocument.value);
  const generated = row?.generated_document;
  if (!generated?.id) return null;

  return {
    ...generated,
    title: generated.title || documentTypeLabel(row.document_type),
    download_url: `/api/files/documents/${generated.id}/download`,
  };
});
const effectiveFutureBookingCount = computed(() => Number(termination.value?.future_booking_count ?? summary.value.future_booking_count) || 0);
const selectedPolicyLabel = computed(() => policyLabel(termination.value?.future_booking_policy || form.future_booking_policy));
const isUnilateralNotice = computed(() => termination.value?.termination_type === 'unilateral_by_sportgo');
const summaryStatusTitle = computed(() => {
  if (!termination.value) {
    return hasArchivedTermination.value
      ? statusLabel(latestClosedRequest.value?.status)
      : statusLabel('eligible');
  }
  if (isUnilateralNotice.value && isSubmittedRequest(termination.value.status)) return 'Công văn chờ chủ sân xác nhận';
  if (isUnilateralNotice.value && isOwnerCancelledStatus(termination.value.status)) return 'SportGo đã thu hồi công văn';
  return statusLabel(termination.value.status);
});
const summaryStatusDescription = computed(() => {
  if (!termination.value && hasArchivedTermination.value) {
    return 'Hợp đồng đã chấm dứt. Cụm sân và toàn bộ booking, thanh toán, văn bản được giữ lại để tra cứu; mọi thao tác vận hành đã khóa.';
  }
  if (isUnilateralNotice.value && isSubmittedRequest(termination.value?.status)) {
    return 'SportGo đã ký và gửi công văn. Chủ sân cần đọc file, xác nhận đã nhận rồi xử lý booking/công nợ.';
  }
  if (isUnilateralNotice.value && isOwnerCancelledStatus(termination.value?.status)) {
    return 'Cụm sân được mở lại nếu không có khóa khác. File đã ký và lịch sử thu hồi vẫn được lưu để đối soát.';
  }
  return eligibility.value?.reason || eligibility.value?.warning || 'Theo dõi văn bản, chữ ký, booking và nghĩa vụ tài chính trong một luồng.';
});
function statusIn(status, values) {
  return values.includes(status);
}

function isDraftRequest(status) {
  return statusIn(status, ['draft_preview', 'draft']);
}

function isSubmittedRequest(status) {
  return statusIn(status, ['cancellation_in_progress', 'submitted']);
}

function isFutureBookingStatus(status) {
  return statusIn(status, ['future_bookings_processing', 'reviewing']);
}

function isSettlementStatus(status) {
  return statusIn(status, ['waiting_final_settlement', 'settlement_processing', 'settlement_completed']);
}

function isFinalSignatureStatus(status) {
  return statusIn(status, ['waiting_final_document_signature', 'pending_signature']);
}

function isTerminatingStatus(status) {
  return statusIn(status, ['terminating', 'transition_period']);
}

function isTerminatedStatus(status) {
  return statusIn(status, ['terminated', 'completed']);
}

function isOwnerCancelledStatus(status) {
  return statusIn(status, ['owner_cancelled_request', 'cancelled']);
}

const canOwnerSignFinal = computed(() => isFinalSignatureStatus(termination.value?.status) && finalAdminSigned.value && !finalOwnerSigned.value);
const canOwnerCancelRequest = computed(() => {
  if (!termination.value?.id) return false;
  if (isUnilateralNotice.value) return false;
  if (latestFinalDocument.value || termination.value.final_document_ready_at || finalAdminSigned.value || finalOwnerSigned.value) return false;
  return isSubmittedRequest(termination.value.status) || isFutureBookingStatus(termination.value.status) || isSettlementStatus(termination.value.status);
});
const canRequestReconsideration = computed(() => Boolean(
  isUnilateralNotice.value
  && termination.value?.id
  && [
    'submitted',
    'reviewing',
    'settlement_processing',
  ].includes(termination.value.status)
));
const showBookingWorkspace = computed(() => Boolean(
  termination.value?.id
  && !isDraftRequest(termination.value.status)
  && (isFutureBookingStatus(termination.value.status) || futureBookings.value.length > 0)
));
const showSettlementWorkspace = computed(() => Boolean(termination.value?.id && isSettlementStatus(termination.value.status)));
const ownerSteps = computed(() => buildOwnerSteps());
const ownerNextAction = computed(() => {
  if (!termination.value) {
    return { title: 'Tạo đơn yêu cầu', description: 'Nhập lý do, chọn phương án booking tương lai rồi tạo bản xem trước.' };
  }

  const status = termination.value.status;
  if (isUnilateralNotice.value) {
    if (isDraftRequest(status)) {
      return { title: 'Chờ SportGo ký công văn', description: 'Bản xem trước đang chờ admin ký. Công văn chưa được gửi và cụm sân chưa bị khóa bởi hồ sơ này.' };
    }
    if (isSubmittedRequest(status)) {
      return { title: 'Đọc và xác nhận đã nhận công văn', description: 'Mở file công văn, kiểm tra lý do, thời hạn và nghĩa vụ cần xử lý rồi xác nhận đã nhận.' };
    }
    if (isFutureBookingStatus(status)) {
      return { title: 'Xử lý booking theo công văn', description: 'Cụm sân dừng nhận booking mới. Chủ sân xử lý các booking hiện có và vẫn có thể yêu cầu SportGo xem xét lại.' };
    }
    if (isSettlementStatus(status)) {
      return { title: 'Hoàn tất công nợ và đối soát', description: 'Booking đã được xử lý; tiếp tục xử lý refund, withdrawal và số dư trước khi lập biên bản cuối.' };
    }
    if (isOwnerCancelledStatus(status)) {
      return { title: 'SportGo đã thu hồi công văn', description: 'Quy trình đã dừng. Văn bản, chữ ký và lịch sử xử lý được giữ nguyên để đối soát.' };
    }
  }
  if (isDraftRequest(status)) {
    return { title: 'Chủ sân ký đơn', description: 'Kiểm tra file đơn yêu cầu, ký vào khung chữ ký và nhập OTP để gửi chính thức.' };
  }
  if (isSubmittedRequest(status)) {
    return { title: 'Chờ admin xác nhận', description: 'Đơn đã được gửi. Admin cần kiểm tra và xác nhận để chuyển sang bước xử lý booking, công nợ và biên bản.' };
  }
  if (isFutureBookingStatus(status)) {
    return { title: 'Xử lý booking tương lai', description: 'Chọn booking cần xử lý, hủy/hoàn tiền, phục vụ đến booking cuối hoặc chuyển sang xử lý thủ công.' };
  }
  if (isSettlementStatus(status)) {
    return { title: 'Chờ quyết toán cuối', description: 'Booking đã xử lý. Chủ sân xử lý rút tiền/công nợ, hoặc chờ admin xác nhận xử lý thủ công.' };
  }
  if (isFinalSignatureStatus(status) && !finalAdminSigned.value) {
    return { title: 'Chờ SportGo ký biên bản', description: 'Admin cần ký biên bản chấm dứt cuối trước, sau đó chủ sân mới ký xác nhận.' };
  }
  if (canOwnerSignFinal.value) {
    return { title: 'Chủ sân ký biên bản cuối', description: 'SportGo đã ký. Chủ sân ký OTP để hoàn tất hồ sơ chấm dứt.' };
  }
  if (isOwnerCancelledStatus(status)) {
    return { title: 'Đã hủy yêu cầu', description: 'Cụm sân được mở lại nếu chưa có xử lý không thể đảo ngược.' };
  }
  if (isTerminatingStatus(status)) {
    return { title: 'Trong thời gian xem hồ sơ', description: 'Biên bản cuối đã ký đủ hai bên. Chủ sân chỉ còn quyền xem hồ sơ đến ngày hệ thống thu hồi quyền.' };
  }
  if (isTerminatedStatus(status)) {
    return { title: 'Đã chấm dứt', description: 'Hợp đồng đã chấm dứt hoàn tất, quyền chủ sân với cụm sân đã được thu hồi.' };
  }

  return { title: 'Theo dõi hồ sơ', description: 'Theo dõi văn bản, booking, rút tiền và thông báo từ SportGo trong hồ sơ chấm dứt.' };
});

onMounted(async () => {
  await load();
  prepareSignaturePads();
});

watch(() => [termination.value?.id, termination.value?.status, canOwnerSignFinal.value], () => {
  prepareSignaturePads();
});

async function load() {
  loading.value = true;
  loadError.value = '';
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
    syncSelectedCluster();
    await loadFinancialAccounts();
  } catch (err) {
    loadError.value = err.message || 'Không thể tải hồ sơ chấm dứt.';
  } finally {
    loading.value = false;
  }
  prepareSignaturePads();
}

function syncSelectedCluster() {
  const selected = cluster.value;
  if (!selected?.id) return;

  localStorage.setItem('selected_cluster', String(selected.id));
  window.dispatchEvent(new CustomEvent('owner-cluster-changed', {
    detail: selected,
  }));
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

async function loadFinancialAccounts() {
  try {
    const response = await api('/api/owner/finance/wallets');
    ownerWallets.value = (response.data || []).filter((wallet) => (
      !currentClusterId.value || String(wallet.venue_cluster_id) === String(currentClusterId.value)
    ));
    if (!ownerWallets.value.length) ownerWallets.value = response.data || [];
    bankAccounts.value = response.bank_accounts || [];

    const defaultWallet = ownerWallets.value.find((wallet) => String(wallet.venue_cluster_id) === String(currentClusterId.value)) || ownerWallets.value[0];
    const defaultAccount = bankAccounts.value.find((account) => account.is_default) || bankAccounts.value[0];
    withdrawal.owner_wallet_id ||= defaultWallet?.id || '';
    withdrawal.owner_bank_account_id ||= defaultAccount?.id || '';
    if (!withdrawal.amount && Number(summary.value.withdrawable_amount) >= 50000) {
      withdrawal.amount = Number(summary.value.withdrawable_amount);
    }
  } catch {
    ownerWallets.value = [];
    bankAccounts.value = [];
  }
}

function hydrateForm(request) {
  form.reason = request.reason || form.reason;
  form.detail_reason = request.detail_reason || form.detail_reason;
  form.requested_effective_date = request.requested_effective_date || '';
  form.future_booking_policy = request.future_booking_policy || form.future_booking_policy;
  form.warning_accepted = Boolean(request.owner_warning_accepted_at || form.warning_accepted);
  bookingActionChoice.value = request.future_booking_policy || bookingActionChoice.value;
}

function buildOwnerSteps() {
  const status = termination.value?.status;
  const adminSigned = finalAdminSigned.value;
  const ownerSigned = finalOwnerSigned.value;

  if (isUnilateralNotice.value) {
    return [
      {
        key: 'notice',
        label: 'SportGo ký công văn',
        state: isDraftRequest(status) ? 'current' : 'done',
      },
      {
        key: 'acknowledge',
        label: 'Chủ sân xác nhận',
        state: isSubmittedRequest(status) ? 'current' : (isDraftRequest(status) ? 'pending' : 'done'),
      },
      {
        key: 'bookings',
        label: 'Xử lý booking',
        state: isFutureBookingStatus(status) ? 'current' : ((isSettlementStatus(status) || isFinalSignatureStatus(status) || isTerminatingStatus(status) || isTerminatedStatus(status)) ? 'done' : 'pending'),
      },
      {
        key: 'settlement',
        label: 'Đối soát công nợ',
        state: isSettlementStatus(status) ? 'current' : ((isFinalSignatureStatus(status) || isTerminatingStatus(status) || isTerminatedStatus(status)) ? 'done' : 'pending'),
      },
      {
        key: 'final',
        label: 'Ký biên bản cuối',
        state: isFinalSignatureStatus(status) ? 'current' : ((isTerminatingStatus(status) || isTerminatedStatus(status)) ? 'done' : 'pending'),
      },
    ];
  }

  return [
    {
      key: 'request',
      label: 'Tạo và ký đơn',
      hint: isDraftRequest(status) ? 'Đang chờ chủ sân ký OTP' : 'Đơn yêu cầu đã tạo',
      state: isDraftRequest(status) ? 'current' : 'done',
    },
    {
      key: 'bookings',
      label: 'Xử lý booking',
      hint: effectiveFutureBookingCount.value ? `${effectiveFutureBookingCount.value} booking cần theo dõi` : 'Không còn booking tương lai',
      state: isFutureBookingStatus(status) ? 'current' : ((isSettlementStatus(status) || isFinalSignatureStatus(status) || isTerminatingStatus(status) || isTerminatedStatus(status)) ? 'done' : 'pending'),
    },
    {
      key: 'settlement',
      label: 'Quyết toán',
      hint: isSettlementStatus(status) ? 'Chờ rút tiền/công nợ hoặc admin xác nhận thủ công' : 'Theo số dư và nghĩa vụ còn lại',
      state: isSettlementStatus(status) ? 'current' : ((isFinalSignatureStatus(status) || isTerminatingStatus(status) || isTerminatedStatus(status)) ? 'done' : 'pending'),
    },
    {
      key: 'sportgo-sign',
      label: 'SportGo ký biên bản',
      hint: adminSigned ? 'SportGo đã ký' : 'Chờ admin ký',
      state: adminSigned ? 'done' : (isFinalSignatureStatus(status) ? 'current' : 'pending'),
    },
    {
      key: 'owner-sign',
      label: 'Chủ sân ký biên bản',
      hint: ownerSigned ? 'Chủ sân đã ký' : 'Ký sau SportGo',
      state: ownerSigned ? 'done' : (canOwnerSignFinal.value ? 'current' : 'pending'),
    },
  ];
}

async function preview() {
  await run(async () => {
    const response = await ownerPartnerTerminationService.preview(currentClusterId.value, {
      ...form,
      warning_accepted: form.warning_accepted,
    });
    termination.value = response.data;
    resetRequestSigning();
    editingDraft.value = false;
    await openRequestPreview();
  });
}

async function openRequestPreview() {
  selectedPreviewRow.value = latestRequestDocument.value;
  previewPurpose.value = 'request';
  showPreviewModal.value = true;
  await nextTick();
  prepareSignaturePad('request');
}

async function openUnilateralNotice() {
  selectedPreviewRow.value = latestRequestDocument.value;
  previewPurpose.value = 'document';
  showPreviewModal.value = true;
}

async function acknowledgeNotice() {
  await run(async () => {
    const response = await ownerPartnerTerminationService.acknowledgeUnilateralNotice(termination.value.id);
    termination.value = response.data;
    noticeAcknowledgementAccepted.value = false;
    await loadFutureBookings();
  });
}

async function requestReconsideration() {
  await run(async () => {
    const response = await ownerPartnerTerminationService.requestUnilateralReconsideration(termination.value.id, {
      reason: reconsiderationReason.value,
    });
    termination.value = response.data;
    reconsiderationReason.value = '';
    showReconsiderationForm.value = false;
  });
}

async function openFinalPreview() {
  selectedPreviewRow.value = latestFinalDocument.value;
  previewPurpose.value = 'final';
  showPreviewModal.value = true;
  await nextTick();
  prepareSignaturePad('final');
}

async function openCancellationPreview(document) {
  selectedPreviewRow.value = document;
  cancelForm.reason = cancelForm.reason || document?.generated_document?.render_data?.cancellation_reason || '';
  previewPurpose.value = 'cancel';
  showPreviewModal.value = true;
  resetCancelSigning();
  await nextTick();
  prepareSignaturePad('cancel');
}

function openDocumentPreview(document) {
  selectedPreviewRow.value = document;
  previewPurpose.value = 'document';
  showPreviewModal.value = true;
}

function closePreviewModal() {
  showPreviewModal.value = false;
  selectedPreviewRow.value = null;
  if (previewPurpose.value === 'request') resetRequestSigning();
  if (previewPurpose.value === 'final') {
    finalSigning.requestId = null;
    finalSigning.hashShort = '';
    finalSigning.otp = '';
    finalSignatureAccepted.value = false;
  }
  if (previewPurpose.value === 'cancel') resetCancelSigning();
  previewPurpose.value = 'document';
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
    showPreviewModal.value = false;
    previewPurpose.value = 'document';
  });
}

async function bulkAction(action) {
  if (!selectedBookingIds.value.length) {
    actionError.value = 'Vui lòng chọn ít nhất một booking cần xử lý.';
    return;
  }
  await run(async () => {
    const response = await ownerPartnerTerminationService.bulkAction(termination.value.id, {
      booking_ids: selectedBookingIds.value,
      action,
      reason: 'Xử lý booking trong hồ sơ chấm dứt hợp đồng.',
    });
    termination.value = response.data;
    showPreviewModal.value = false;
    previewPurpose.value = 'document';
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
    closePreviewModal();
  });
}

async function sendCancelOtp() {
  await run(async () => {
    const response = await ownerPartnerTerminationService.cancelSendOtp(termination.value.id, {
      generated_document_id: previewDocument.value?.id,
      signature_image: signatureImage('cancel'),
    });
    cancelSigning.requestId = response.data.signing_request_id;
    cancelSigning.hashShort = response.data.hash_short;
    cancelSigning.otp = '';
  });
}

async function cancelRequest() {
  await run(async () => {
    const response = await ownerPartnerTerminationService.cancel(termination.value.id, {
      signing_request_id: cancelSigning.requestId,
      otp: cancelSigning.otp,
      reason: cancelForm.reason || previewDocument.value?.render_data?.cancellation_reason || '',
    });
    termination.value = response.data;
    resetCancelSigning();
    showPreviewModal.value = false;
    previewPurpose.value = 'document';
    await loadFutureBookings();
  });
}

async function previewCancellation() {
  await run(async () => {
    const response = await ownerPartnerTerminationService.cancelPreview(termination.value.id, {
      reason: cancelForm.reason,
    });
    selectedPreviewRow.value = {
      document_type: 'termination_cancellation_request',
      generated_document: response.data.document,
    };
    previewPurpose.value = 'cancel';
    showPreviewModal.value = true;
    resetCancelSigning();
    await nextTick();
    prepareSignaturePad('cancel');
  });
}

async function run(callback) {
  working.value = true;
  actionError.value = '';
  try {
    await callback();
  } catch (err) {
    actionError.value = err.message || 'Thao tác không thành công.';
  } finally {
    working.value = false;
  }
}

async function prepareSignaturePads() {
  await nextTick();
  prepareSignaturePad('request');
  prepareSignaturePad('final');
  prepareSignaturePad('cancel');
}

function prepareSignaturePad(pad) {
  const canvas = signatureCanvas(pad);
  if (!canvas) return;

  const context = canvas.getContext('2d');
  context.fillStyle = '#fff';
  context.fillRect(0, 0, canvas.width, canvas.height);
  context.strokeStyle = '#0f172a';
  context.lineWidth = 2.4;
  context.lineCap = 'round';

  if (pad === 'final') {
    finalSignatureEmpty.value = true;
  } else if (pad === 'cancel') {
    cancelSignatureEmpty.value = true;
  } else {
    requestSignatureEmpty.value = true;
  }
}

function signatureCanvas(pad) {
  if (pad === 'final') return finalSignatureCanvas.value;
  if (pad === 'cancel') return cancelSignatureCanvas.value;
  return requestSignatureCanvas.value;
}

function point(event, canvas) {
  const rect = canvas.getBoundingClientRect();
  return {
    x: ((event.clientX - rect.left) / rect.width) * canvas.width,
    y: ((event.clientY - rect.top) / rect.height) * canvas.height,
  };
}

function startSignature(event, pad) {
  const canvas = signatureCanvas(pad);
  if (!canvas) return;

  activeSignaturePad.value = pad;
  if (pad === 'final') {
    finalSignatureEmpty.value = false;
    finalSigning.requestId = null;
    finalSigning.hashShort = '';
    finalSigning.otp = '';
  } else if (pad === 'cancel') {
    cancelSignatureEmpty.value = false;
    cancelSigning.requestId = null;
    cancelSigning.hashShort = '';
    cancelSigning.otp = '';
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
  const canvas = signatureCanvas(pad);
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
  } else if (pad === 'cancel') {
    resetCancelSigning();
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

function resetCancelSigning() {
  cancelSigning.requestId = null;
  cancelSigning.hashShort = '';
  cancelSigning.otp = '';
  cancelSignatureAccepted.value = false;
}

function signatureImage(pad) {
  const canvas = signatureCanvas(pad);
  return canvas?.toDataURL('image/png') || '';
}

function money(value) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(value) || 0);
}

function maskAccountNumber(value) {
  const digits = String(value || '');
  if (digits.length <= 4) return digits || '-';
  return `•••• ${digits.slice(-4)}`;
}

function statusLabel(status) {
  return {
    eligible: 'Có thể tạo yêu cầu',
    draft: 'Chờ chủ sân ký gửi',
    draft_preview: 'Chờ chủ sân ký gửi',
    submitted: 'Đã gửi yêu cầu',
    cancellation_in_progress: 'Đã gửi yêu cầu',
    reviewing: 'Đang xử lý booking',
    future_bookings_processing: 'Đang xử lý booking',
    settlement_processing: 'Chờ quyết toán cuối',
    settlement_completed: 'Chờ ký biên bản cuối',
    waiting_final_settlement: 'Chờ quyết toán cuối',
    pending_signature: 'Chờ ký biên bản cuối',
    waiting_final_document_signature: 'Chờ ký biên bản cuối',
    transition_period: 'Đang trong thời gian xem hồ sơ',
    terminating: 'Đang trong thời gian xem hồ sơ',
    completed: 'Đã chấm dứt',
    terminated: 'Đã chấm dứt',
    cancelled: 'Chủ sân đã hủy yêu cầu',
    owner_cancelled_request: 'Chủ sân đã hủy yêu cầu',
    rejected: 'Admin từ chối',
    admin_rejected: 'Admin từ chối',
  }[status] || status;
}

function policyLabel(value) {
  return policies.value.find((policy) => policy.value === value)?.label || value || '-';
}

function requestDocuments(request) {
  const linked = request?.documents || [];
  const cancellationDocuments = (request?.generated_documents || [])
    .filter((document) => document.document_type === 'termination_cancellation_request')
    .map((document) => ({
      id: `generated-${document.id}`,
      document_type: document.document_type,
      status: document.status,
      generated_document: document,
    }));

  return [...linked, ...cancellationDocuments];
}

function documentTypeLabel(type) {
  return {
    owner_termination_request: 'Đơn yêu cầu chấm dứt của chủ sân',
    termination_cancellation_request: 'Đơn xác nhận hủy yêu cầu chấm dứt',
    unilateral_notice: 'Công văn chấm dứt từ SportGo',
    unilateral_termination_notice: 'Công văn chấm dứt từ SportGo',
    settlement_minutes: 'Biên bản chấm dứt cuối',
    final_termination_file: 'Biên bản chấm dứt cuối',
  }[type] || type || 'Văn bản';
}

function documentStatusLabel(status) {
  return {
    pending_signature: 'Chờ ký',
    pending_owner_signature: 'Chờ chủ sân ký',
    pending_sportgo_signature: 'Chờ SportGo ký',
    generated: 'Đã tạo',
    signed: 'Đã ký',
    completed: 'Hoàn tất',
  }[status] || status || 'Đã tạo';
}

function isFinalDocument(document) {
  return ['settlement_minutes', 'final_termination_file'].includes(
    document?.document_type || document?.generated_document?.document_type,
  );
}

function documentProgressLabel(document) {
  const generated = document?.generated_document || {};
  const signatures = generated.signatures || [];
  const ownerSigned = signatures.some((signature) => signature.signer_side === 'owner' && signature.status === 'signed');
  const sportgoSigned = signatures.some((signature) => signature.signer_side === 'sportgo' && signature.status === 'signed');
  const type = document?.document_type || generated.document_type;

  if (isFinalDocument(document)) {
    if (ownerSigned && sportgoSigned) return 'Hoàn tất';
    if (sportgoSigned) return 'Chờ chủ sân ký';
    return 'Chờ SportGo ký';
  }
  if (['unilateral_notice', 'unilateral_termination_notice'].includes(type)) {
    return sportgoSigned ? 'Đã ký' : 'Chờ SportGo ký';
  }
  if (['owner_termination_request', 'termination_request', 'termination_cancellation_request'].includes(type)) {
    return ownerSigned ? 'Đã ký' : 'Chờ chủ sân ký';
  }

  return documentStatusLabel(generated.status || document?.status);
}

function isPendingCancellationDocument(document) {
  const generated = document?.generated_document;
  return document?.document_type === 'termination_cancellation_request'
    && generated?.status === 'pending_owner_signature'
    && !(generated?.signatures || []).some((signature) => signature.signer_side === 'owner' && signature.status === 'signed');
}

function documentMeta(document) {
  const generated = document.generated_document || {};
  return [
    generated.document_code,
    documentProgressLabel(document),
    generated.signatures?.length ? `${generated.signatures.length} chữ ký` : null,
  ].filter(Boolean).join(' - ');
}
</script>

<style scoped>
.termination-page {
  --primary-color: #168447;
  --primary-hover: #116b3a;
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
  border: 0;
  border-bottom: 1px solid #dfe7e2;
  border-radius: 0;
  background: transparent;
  padding: 0 0 18px;
}

.money-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.money-grid div {
  border-left: 1px solid #dfe7e2;
  padding: 4px 12px;
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

.archive-band {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 16px;
  border-bottom: 1px solid #dfe7e2;
  padding: 0 0 18px;
}

.archive-band h2,
.archive-band p {
  margin-top: 0;
}

.archive-documents {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  flex-wrap: wrap;
}

.workflow-strip {
  display: grid;
  grid-template-columns: repeat(5, minmax(132px, 1fr));
  overflow-x: auto;
  border: 1px solid #dfe7e2;
  border-radius: 8px;
  background: #fff;
  padding: 14px 16px;
}

.workflow-step {
  position: relative;
  display: grid;
  grid-template-columns: 28px minmax(0, 1fr);
  align-items: center;
  gap: 8px;
  min-width: 132px;
  padding-right: 16px;
}

.workflow-step strong {
  color: #64748b;
  font-size: 12px;
  line-height: 1.3;
}

.workflow-marker {
  position: relative;
  z-index: 1;
  display: grid;
  width: 28px;
  height: 28px;
  place-items: center;
  border: 1px solid #cbd5e1;
  border-radius: 50%;
  background: #fff;
  color: #64748b;
  font-size: 12px;
  font-weight: 800;
}

.workflow-step:not(:last-child)::after {
  position: absolute;
  top: 13px;
  right: 0;
  left: 28px;
  height: 1px;
  background: #dfe7e2;
  content: '';
}

.workflow-step.current strong,
.workflow-step.done strong {
  color: #14532d;
}

.workflow-step.current .workflow-marker {
  border-color: #16a34a;
  box-shadow: 0 0 0 3px #dcfce7;
  color: #166534;
}

.workflow-step.done .workflow-marker {
  border-color: #16a34a;
  background: #16a34a;
  color: #fff;
}

.next-action-panel {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  border-left: 4px solid #16a34a;
  background: #f0fdf4;
  padding: 16px 18px;
}

.next-action-panel h2,
.next-action-panel p {
  margin-top: 0;
}

.next-action-panel p:last-child {
  margin-bottom: 0;
  color: #475569;
}

.compact-actions {
  flex-wrap: wrap;
  justify-content: flex-end;
}

.inline-error {
  border-left: 4px solid #dc2626;
  background: #fef2f2;
  color: #991b1b;
  padding: 14px 16px;
}

.inline-error p {
  margin: 4px 0 0;
}

.acknowledgement-band,
.reconsideration-band {
  display: grid;
  gap: 12px;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  background: #fff;
  padding: 16px 18px;
}

.acknowledgement-band {
  grid-template-columns: minmax(0, 1fr) minmax(280px, .8fr) auto;
  align-items: center;
}

.acknowledgement-band p,
.reconsideration-band p {
  margin-top: 0;
}

.reconsideration-band form {
  display: grid;
  gap: 12px;
}

.pending-note {
  margin: 0;
  border-left: 3px solid #f59e0b;
  background: #fffbeb;
  color: #92400e;
  padding: 10px 12px;
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

.preview-signing-panel {
  display: grid;
  gap: 14px;
}

.preview-signing-panel p {
  margin: 0;
}

.signing-context {
  border-left: 3px solid #16a34a;
  background: #f0fdf4;
  padding: 10px 12px;
}

.signing-context.warning {
  border-left-color: #dc2626;
  background: #fef2f2;
}

.signing-context span {
  display: block;
  color: #166534;
  font-size: 13px;
  font-weight: 800;
}

.signing-context.warning span {
  color: #991b1b;
}

.signing-context p {
  margin-top: 4px;
  color: #475569;
  font-size: 12px;
  line-height: 1.45;
}

.signature-field {
  display: grid;
  gap: 7px;
}

.signature-field-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  color: #334155;
  font-size: 12px;
}

.signature-field-head button {
  border: 0;
  background: transparent;
  color: #15803d;
  font: inherit;
  font-weight: 750;
  cursor: pointer;
}

.signing-submit-row .btn {
  width: 100%;
}

.modal-action-error {
  margin-bottom: 12px;
}

.modal-signature {
  height: 150px;
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

.preview-signing-panel .modal-signature {
  height: 140px;
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

.select-all-bookings {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #475569;
  font-size: 13px;
  white-space: nowrap;
}

.select-all-bookings input,
.booking-row input,
.check-row input {
  width: auto;
}

.booking-bulk-bar {
  display: grid;
  grid-template-columns: minmax(240px, 1fr) auto;
  align-items: end;
  gap: 14px;
  margin-top: 14px;
  border-top: 1px solid #e2e8f0;
  padding-top: 14px;
}

.booking-bulk-bar > div {
  display: flex;
  align-items: center;
  gap: 12px;
}

.booking-bulk-bar > div > span {
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
}

.form-grid label small {
  color: #64748b;
  font-size: 11px;
  font-weight: 500;
}

.empty-copy {
  margin: 16px 0 0;
  color: #64748b;
  text-align: center;
}

.withdrawal-help {
  margin: 12px 0;
  border-left: 3px solid #f59e0b;
  background: #fffbeb;
  color: #92400e;
  padding: 10px 12px;
  font-size: 13px;
}

.withdrawal-help a {
  color: #166534;
  font-weight: 800;
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

.preview-signing-panel .otp-box {
  border-style: solid;
  background: #f8fafc;
}

.preview-signing-panel .otp-box p {
  display: grid;
  gap: 2px;
}

.preview-signing-panel .otp-box small {
  color: #64748b;
  font-size: 11px;
}

.single-column {
  grid-template-columns: 1fr;
}

.cancel-panel {
  padding: 0;
}

.cancel-panel summary {
  cursor: pointer;
  font-weight: 700;
  padding: 16px 18px;
}

.cancel-panel-body {
  display: grid;
  gap: 12px;
  border-top: 1px solid #e2e8f0;
  padding: 18px;
}

.btn {
  border-radius: 8px;
}

.btn-primary {
  border: 1px solid var(--primary-color);
  background: var(--primary-color);
  color: #fff;
}

.btn-primary:hover:not(:disabled) {
  border-color: var(--primary-hover);
  background: var(--primary-hover);
}

.btn-primary:disabled {
  border-color: #cbd5e1;
  background: #e2e8f0;
  color: #475569;
  opacity: 1;
  cursor: not-allowed;
}

.btn-danger {
  border: 1px solid #dc2626;
  background: #dc2626;
  color: #fff;
}

@media (max-width: 860px) {
  .summary-panel,
  .archive-band,
  .form-grid,
  .split-panel,
  .money-grid,
  .confirm-grid {
    grid-template-columns: 1fr;
  }

  .workflow-strip {
    grid-template-columns: 1fr;
    gap: 8px;
    overflow: visible;
    padding: 12px;
  }

  .workflow-step {
    min-width: 0;
    padding-right: 0;
  }

  .workflow-step:not(:last-child)::after {
    top: 28px;
    right: auto;
    bottom: -8px;
    left: 13px;
    width: 1px;
    height: auto;
  }

  .next-action-panel {
    align-items: stretch;
    flex-direction: column;
  }

  .acknowledgement-band {
    grid-template-columns: 1fr;
  }

  .booking-bulk-bar {
    grid-template-columns: 1fr;
  }

  .booking-bulk-bar > div {
    align-items: stretch;
    flex-direction: column;
  }

  .archive-documents {
    align-items: stretch;
    flex-direction: column;
  }

  .money-grid div {
    border-top: 1px solid #dfe7e2;
    border-left: 0;
    padding: 10px 0 0;
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
