<template>
  <div class="owner-partner-termination-container">

    <!-- Loading State -->
    <div v-if="loading" class="state-loading-surface">
      <div class="spinner"></div>
      <p>Đang tải thông tin hồ sơ chấm dứt...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="loadError" class="state-error-surface">
      <AppIcon name="alert" size="24" class="error-icon" />
      <div class="state-copy">
        <h3>Không thể tải hồ sơ</h3>
        <p>{{ loadError }}</p>
      </div>
      <button class="btn btn-outline btn-sm" type="button" @click="load">Thử lại</button>
    </div>

    <!-- MAIN MASTER WORKSPACE (EXACTLY MATCHING CLUSTER GENERAL INFO & PARTNER PROFILE PAGE) -->
    <div v-else class="termination-master-workspace">
      
      <!-- Top Header Surface (Seamless Tabs Header - Only rendered if multiple tabs exist) -->
      <div v-if="tabs.length > 1" class="termination-header-tabs-surface">
        <div class="hero-integrated-tabs">
          <AppTabs :tabs="tabs" :model-value="activeTab" @update:model-value="activeTab = $event" />
        </div>
      </div>

      <!-- Content Surface (Seamless White Block connected below Tabs or full standalone card) -->
      <div class="termination-content-surface" :class="{ standalone: tabs.length <= 1 }">

        <!-- Status Summary & Financial Metrics Section -->
        <section class="termination-meta-summary">
          <div class="summary-title-block">
            <h2 class="summary-heading">{{ summaryStatusTitle }}</h2>
            <p class="summary-desc">{{ summaryStatusDescription }}</p>
          </div>

          <!-- 4-Column Flat Financial Metrics Grid -->
          <div class="summary-financial-grid">
            <div class="financial-metric-item">
              <span class="metric-label">Số dư chủ sân</span>
              <strong class="metric-amount">{{ money(summary.owner_balance_total) }}</strong>
            </div>
            <div class="financial-metric-item">
              <span class="metric-label">Nghĩa vụ booking tương lai</span>
              <strong class="metric-amount">{{ money(summary.future_online_booking_liability) }}</strong>
            </div>
            <div class="financial-metric-item">
              <span class="metric-label">Đang hoàn / rút tiền</span>
              <strong class="metric-amount">{{ money((Number(summary.pending_refund_liability) || 0) + (Number(summary.pending_withdrawal_amount) || 0)) }}</strong>
            </div>
            <div class="financial-metric-item highlight">
              <span class="metric-label">Có thể rút ngay</span>
              <strong class="metric-amount green">{{ money(summary.withdrawable_amount) }}</strong>
            </div>
          </div>
        </section>

        <!-- Workflow Stepper Bar -->
        <nav v-if="termination && !isOwnerCancelledStatus(termination.status)" class="workflow-stepper-strip" aria-label="Tiến độ chấm dứt hợp tác">
          <div
            v-for="(step, index) in ownerSteps"
            :key="step.key"
            class="stepper-step"
            :class="step.state"
          >
            <div class="step-circle">{{ step.state === 'done' ? '✓' : index + 1 }}</div>
            <span class="step-label">{{ step.label }}</span>
          </div>
        </nav>

        <!-- Urgent / Next Action Notice Banner -->
        <section v-if="termination" class="urgent-action-notice">
          <AppIcon name="alertCircle" size="20" class="notice-icon" />
          <div class="notice-body">
            <strong>{{ ownerNextAction.title }}</strong>
            <p>{{ ownerNextAction.description }}</p>
          </div>
          <div v-if="isDraftRequest(termination.status) && !isUnilateralNotice" class="urgent-actions-group">
            <button class="btn btn-outline btn-sm" type="button" @click="editingDraft = true">Sửa nội dung</button>
            <button class="btn btn-primary btn-sm" type="button" @click="openRequestPreview">Xem file & ký</button>
          </div>
          <div v-else-if="isUnilateralNotice && isSubmittedRequest(termination.status)" class="urgent-actions-group">
            <button class="btn btn-primary btn-sm" type="button" @click="openUnilateralNotice">Mở công văn</button>
          </div>
          <div v-else-if="canOwnerSignFinal" class="urgent-actions-group">
            <button class="btn btn-primary btn-sm" type="button" @click="openFinalPreview">Xem file & ký biên bản</button>
          </div>
        </section>

        <!-- Action Error Alert -->
        <div v-if="actionError" class="action-error-banner" role="alert">
          <AppIcon name="alert" size="18" />
          <div>
            <strong>Chưa thể thực hiện thao tác</strong>
            <p>{{ actionError }}</p>
          </div>
        </div>

        <!-- Unilateral Notice Acknowledgement -->
        <section v-if="isUnilateralNotice && isSubmittedRequest(termination.status)" class="acknowledgement-block">
          <div class="ack-copy">
            <h4>Chủ sân cần đọc công văn trước khi xác nhận</h4>
            <p>Sau khi xác nhận, hồ sơ chuyển sang xử lý booking và nghĩa vụ tài chính. Việc xác nhận không đồng nghĩa từ bỏ quyền yêu cầu xem xét lại.</p>
          </div>
          <label class="checkbox-label">
            <input v-model="noticeAcknowledgementAccepted" type="checkbox" />
            <span>Tôi xác nhận đã mở, đọc và nhận công văn chấm dứt do SportGo phát hành.</span>
          </label>
          <div class="form-actions-right">
            <button class="btn btn-primary btn-sm" type="button" :disabled="working || !noticeAcknowledgementAccepted" @click="acknowledgeNotice">
              Xác nhận đã nhận
            </button>
          </div>
        </section>

        <!-- Reconsideration Form -->
        <section v-if="canRequestReconsideration" class="reconsideration-block">
          <button v-if="!showReconsiderationForm" class="btn btn-outline btn-sm" type="button" @click="showReconsiderationForm = true">
            Yêu cầu SportGo xem xét lại
          </button>
          <form v-else @submit.prevent="requestReconsideration" class="reconsideration-form">
            <div class="form-head">
              <h4>Yêu cầu xem xét lại công văn</h4>
              <p>Nêu rõ căn cứ hoặc dữ liệu cần SportGo kiểm tra. Công văn vẫn có hiệu lực cho đến khi admin thu hồi.</p>
            </div>
            <div class="form-group">
              <label class="form-label">Nội dung đề nghị</label>
              <textarea v-model.trim="reconsiderationReason" class="form-control" rows="4" minlength="20" maxlength="2000" required></textarea>
            </div>
            <div class="form-actions-right">
              <button class="btn btn-outline btn-sm" type="button" @click="showReconsiderationForm = false">Đóng</button>
              <button class="btn btn-primary btn-sm" type="submit" :disabled="working || reconsiderationReason.length < 20">Gửi xem xét lại</button>
            </div>
          </form>
          <p v-if="termination.workflow_state?.reconsideration_pending" class="pending-note">SportGo đang xem xét phản hồi gần nhất của chủ sân.</p>
        </section>

        <!-- TAB 1: THÔNG TIN & YÊU CẦU -->
        <div v-if="activeTab === 'info'" class="tab-pane-flow">
          
          <!-- Request Form (Draft / Initial Request) -->
          <section v-if="((!termination && !hasArchivedTermination) || editingDraft) && !isUnilateralNotice" class="termination-section">
            <div class="tab-section-header">
              <div>
                <h2>Thông tin yêu cầu chấm dứt</h2>
                <p class="section-subtitle">Nhập lý do và chọn phương án xử lý để tạo bản xem trước đơn chấm dứt</p>
              </div>
              <span class="count-pill">{{ summary.future_booking_count || 0 }} booking tương lai</span>
            </div>

            <div class="form-group">
              <label class="form-label">Lý do chấm dứt</label>
              <textarea
                v-model.trim="form.reason"
                class="form-control"
                rows="3"
                maxlength="2000"
                placeholder="Nhập lý do chính dẫn đến yêu cầu chấm dứt hợp tác..."
              ></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">Mô tả chi tiết</label>
              <textarea
                v-model.trim="form.detail_reason"
                class="form-control"
                rows="4"
                maxlength="5000"
                placeholder="Cung cấp thêm chi tiết về tình hình vận hành, thời gian bàn giao hoặc các thỏa thuận đi kèm..."
              ></textarea>
            </div>

            <!-- Interactive Calendar Selection & Quick Presets -->
            <div class="form-group full-width">
              <label class="form-label">Ngày mong muốn chấm dứt hiệu lực</label>
              
              <div class="calendar-picker-container">
                <div class="calendar-picker-header">
                  <button
                    type="button"
                    class="calendar-trigger-btn"
                    @click="showCalendarPicker = !showCalendarPicker"
                  >
                    <AppIcon name="calendar" size="16" class="calendar-icon" />
                    <span class="chosen-date-text">{{ formattedSelectedDate }}</span>
                    <AppIcon name="chevronDown" size="14" class="chevron-icon" :class="{ rotated: showCalendarPicker }" />
                  </button>

                  <div class="quick-preset-chips">
                    <button
                      type="button"
                      class="preset-chip"
                      @click="quickSelectDays(7)"
                    >
                      Sau 7 ngày
                    </button>
                    <button
                      type="button"
                      class="preset-chip"
                      @click="quickSelectDays(14)"
                    >
                      Sau 14 ngày
                    </button>
                    <button
                      type="button"
                      class="preset-chip"
                      @click="quickSelectDays(30)"
                    >
                      Sau 30 ngày
                    </button>
                  </div>
                </div>

                <!-- Expanded Month Calendar Picker Dropdown -->
                <div v-if="showCalendarPicker" class="interactive-month-calendar">
                  <div class="month-nav-header">
                    <button type="button" class="nav-month-btn" @click="prevMonth">
                      <AppIcon name="chevronLeft" size="16" />
                    </button>
                    <strong class="current-month-label">{{ calendarTitle }}</strong>
                    <button type="button" class="nav-month-btn" @click="nextMonth">
                      <AppIcon name="chevronRight" size="16" />
                    </button>
                  </div>

                  <div class="calendar-weekdays-grid">
                    <span>T2</span><span>T3</span><span>T4</span><span>T5</span><span>T6</span><span>T7</span><span>CN</span>
                  </div>

                  <div class="calendar-days-grid">
                    <template v-for="(item, idx) in monthDaysGrid" :key="idx">
                      <span v-if="item.empty" class="day-cell empty"></span>
                      <button
                        v-else
                        type="button"
                        class="day-cell"
                        :class="{
                          past: item.isPast,
                          selected: item.isSelected,
                          today: item.isToday
                        }"
                        :disabled="item.isPast"
                        @click="selectCalendarDate(item.dateString)"
                      >
                        {{ item.day }}
                      </button>
                    </template>
                  </div>
                </div>
              </div>
            </div>

            <!-- Interactive Policy Choice Cards Grid -->
            <div class="form-group full-width">
              <label class="form-label">Phương án xử lý booking tương lai</label>
              <div class="policy-option-cards-grid">
                <div
                  v-for="policy in policies"
                  :key="policy.value"
                  class="policy-choice-card"
                  :class="{ selected: form.future_booking_policy === policy.value }"
                  @click="form.future_booking_policy = policy.value"
                >
                  <div class="card-content">
                    <strong class="card-title">{{ policy.label }}</strong>
                    <p class="card-desc">{{ policyDescription(policy.value) }}</p>
                  </div>
                </div>
              </div>
            </div>

            <label class="checkbox-label">
              <input v-model="form.warning_accepted" type="checkbox" />
              <span>Tôi đã đọc cảnh báo: Sau khi nộp đơn, cụm sân sẽ bị khóa các thao tác quản lý thông thường để chờ duyệt.</span>
            </label>

            <div class="form-actions-right">
              <button v-if="termination" class="btn btn-outline" type="button" @click="editingDraft = false">Đóng chỉnh sửa</button>
              <button class="btn btn-primary" type="button" :disabled="working || !canPreview" @click="preview">
                <AppIcon name="eye" size="14" />
                <span>Xem trước đơn</span>
              </button>
            </div>
          </section>

          <!-- Archived Termination History Info (If applicable) -->
          <section v-if="!termination && latestClosedRequest" class="termination-section">
            <div class="tab-section-header">
              <div>
                <h2>Hồ sơ chấm dứt gần nhất</h2>
                <p class="section-subtitle">Thông tin chi tiết về lần chấm dứt hợp đồng trước đây</p>
              </div>
              <span class="status-tag">{{ statusLabel(latestClosedRequest.status) }}</span>
            </div>

            <div class="meta-info-grid">
              <div class="meta-info-item">
                <span class="meta-info-label">Trạng thái hồ sơ</span>
                <span class="meta-info-value">{{ statusLabel(latestClosedRequest.status) }}</span>
              </div>
              <div class="meta-info-item">
                <span class="meta-info-label">Số văn bản lưu</span>
                <span class="meta-info-value">{{ latestClosedDocuments.length }} văn bản</span>
              </div>
              <div class="meta-info-item full-width">
                <span class="meta-info-label">Lý do chấm dứt</span>
                <span class="meta-info-value">{{ latestClosedRequest.reason || '-' }}</span>
              </div>
            </div>

            <div class="archive-docs-list">
              <button
                v-for="document in latestClosedDocuments"
                :key="document.id"
                class="btn btn-outline btn-sm"
                type="button"
                @click="openDocumentPreview(document)"
              >
                <AppIcon name="fileText" size="14" />
                <span>Xem {{ documentTypeLabel(document.document_type).toLowerCase() }}</span>
              </button>
            </div>
          </section>

          <!-- Cancellation Request Accordion -->
          <details v-if="canOwnerCancelRequest" class="cancel-accordion">
            <summary class="cancel-summary">Không tiếp tục chấm dứt hợp tác?</summary>
            <div class="cancel-body">
              <div class="tab-section-header">
                <div>
                  <h2>Hủy yêu cầu chấm dứt</h2>
                  <p class="section-subtitle">Yêu cầu chữ ký điện tử và xác nhận OTP của chủ sân</p>
                </div>
              </div>
              <p class="cancel-hint">
                Chỉ hủy được khi hồ sơ chưa vào bước ký biên bản cuối và chưa có booking bị hủy/hoàn tiền không thể đảo ngược.
              </p>
              <div class="form-group">
                <label class="form-label">Lý do hủy yêu cầu</label>
                <textarea v-model.trim="cancelForm.reason" class="form-control" rows="3" maxlength="1000" placeholder="Nhập lý do dừng thủ tục chấm dứt hợp tác..."></textarea>
              </div>
              <div class="form-actions-right">
                <span class="char-count">{{ cancelForm.reason.length }}/1000 ký tự</span>
                <button class="btn btn-danger-soft" type="button" :disabled="working || cancelForm.reason.length < 10" @click="previewCancellation">
                  Tải văn bản hủy & ký
                </button>
              </div>
            </div>
          </details>
        </div>

        <!-- TAB 2: BOOKING TƯƠNG LAI -->
        <div v-if="activeTab === 'bookings'" class="tab-pane-flow">
          <section class="termination-section">
            <div class="tab-section-header">
              <div>
                <h2>Xử lý lịch đặt sân tương lai</h2>
                <p class="section-subtitle">Quản lý và giải quyết các lượt đặt sân đã nhận trước thời điểm thu hồi</p>
              </div>
            </div>

            <div v-if="futureBookings.length" class="booking-list-grid">
              <label v-for="booking in futureBookings" :key="booking.id" class="booking-item-row">
                <input v-model="selectedBookingIds" type="checkbox" :value="booking.id" />
                <div class="booking-details">
                  <strong>{{ booking.booking_code }}</strong>
                  <span>{{ booking.booking_date }} {{ booking.start_time }}-{{ booking.end_time }}</span>
                  <small>{{ booking.customer?.full_name || booking.customer?.username || '-' }}</small>
                </div>
                <div class="booking-amount">
                  <span>{{ money(booking.paid_online_amount) }}</span>
                  <em>{{ booking.action_status || booking.status }}</em>
                </div>
              </label>
            </div>

            <div v-if="futureBookings.length" class="booking-bulk-actions">
              <div class="select-group">
                <label class="form-label">Phương án xử lý hàng loạt:</label>
                <select v-model="bookingActionChoice" class="form-control select-sm">
                  <option value="">-- Chọn phương án --</option>
                  <option v-for="policy in policies" :key="policy.value" :value="policy.value">{{ policy.label }}</option>
                </select>
              </div>

              <div class="submit-group">
                <span>Đã chọn <strong>{{ selectedBookingIds.length }}</strong> booking</span>
                <button class="btn btn-primary btn-sm" type="button" :disabled="working || !selectedBookingIds.length || !bookingActionChoice" @click="bulkAction(bookingActionChoice)">
                  Áp dụng
                </button>
              </div>
            </div>

            <div v-else class="empty-tab-state">
              <AppIcon name="checkCircle" size="28" class="faint-icon" />
              <p>Không còn lịch đặt sân tương lai nào cần xử lý.</p>
            </div>
          </section>
        </div>

        <!-- TAB 3: QUYẾT TOÁN & RÚT TIỀN -->
        <div v-if="activeTab === 'settlement'" class="tab-pane-flow">
          <section class="termination-section">
            <div class="tab-section-header">
              <div>
                <h2>Rút tiền quyết toán</h2>
                <p class="section-subtitle">Thực hiện rút số dư còn lại về tài khoản ngân hàng đối tác</p>
              </div>
            </div>

            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Nguồn số dư</label>
                <select v-model="withdrawal.owner_wallet_id" class="form-control">
                  <option value="">-- Chọn nguồn số dư --</option>
                  <option v-for="wallet in ownerWallets" :key="wallet.id" :value="wallet.id">
                    {{ wallet.venue_cluster?.name || cluster?.name || 'Số dư chủ sân' }} - {{ money(wallet.available_balance) }}
                  </option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Tài khoản nhận tiền</label>
                <select v-model="withdrawal.owner_bank_account_id" class="form-control">
                  <option value="">-- Chọn tài khoản ngân hàng --</option>
                  <option v-for="account in bankAccounts" :key="account.id" :value="account.id">
                    {{ account.bank_name }} - {{ maskAccountNumber(account.account_number) }} - {{ account.account_holder_name }}
                  </option>
                </select>
              </div>

              <div class="form-group full-width">
                <label class="form-label">Số tiền rút (VND)</label>
                <input v-model.number="withdrawal.amount" type="number" min="50000" :max="summary.withdrawable_amount" step="1000" class="form-control" />
                <small class="help-text">Số tiền tối đa có thể rút: {{ money(summary.withdrawable_amount) }}</small>
              </div>
            </div>

            <p v-if="!ownerWallets.length || !bankAccounts.length" class="withdrawal-help-alert">
              Chưa có nguồn số dư hoặc tài khoản ngân hàng hợp lệ.
              <RouterLink :to="{ name: 'owner-finance' }">Mở trang tài chính để thêm tài khoản</RouterLink>.
            </p>

            <div class="form-actions-right">
              <button class="btn btn-primary" type="button" :disabled="working || !canSubmitWithdrawal" @click="storeWithdrawal">
                Gửi yêu cầu rút tiền
              </button>
            </div>
          </section>
        </div>

        <!-- TAB 4: VĂN BẢN ĐIỆN TỬ -->
        <div v-if="activeTab === 'documents'" class="tab-pane-flow">
          <section class="termination-section">
            <div class="tab-section-header">
              <div>
                <h2>Danh sách văn bản điện tử đã sinh</h2>
                <p class="section-subtitle">Tất cả đơn từ và biên bản liên quan đến thủ tục chấm dứt ({{ displayDocuments.length }} văn bản)</p>
              </div>
            </div>

            <div class="flat-doc-list">
              <article v-for="document in displayDocuments" :key="document.id" class="flat-doc-row">
                <div class="doc-file-type-icon">
                  <AppIcon name="fileText" size="18" />
                </div>

                <div class="doc-main-details">
                  <h4 class="doc-title">{{ documentTypeLabel(document.document_type) }}</h4>
                  <span class="doc-meta-info">{{ documentMeta(document) }}</span>
                </div>

                <div class="doc-actions-group">
                  <span class="doc-status-pill">{{ documentProgressLabel(document) }}</span>
                  <button
                    v-if="document.generated_document?.id && !(canOwnerSignFinal && isFinalDocument(document))"
                    class="btn btn-outline btn-sm"
                    type="button"
                    :disabled="working"
                    @click="isPendingCancellationDocument(document) ? openCancellationPreview(document) : openDocumentPreview(document)"
                  >
                    <AppIcon name="eye" size="14" />
                    <span>{{ isPendingCancellationDocument(document) ? 'Xem file & ký' : 'Xem file' }}</span>
                  </button>
                </div>
              </article>
            </div>
          </section>
        </div>
      </div>
    </div>

    <!-- Document Viewer & Signature Modal -->
    <DocumentViewerModal
      :show="showPreviewModal"
      :document="previewDocument"
      :action-mode="['request', 'cancel', 'final'].includes(previewPurpose)"
      @close="closePreviewModal"
    >
      <template #actions>
        <div v-if="actionError" class="action-error-banner modal-error" role="alert">
          <strong>Chưa thể thực hiện thao tác</strong>
          <p>{{ actionError }}</p>
        </div>

        <div v-if="previewPurpose === 'request'" class="preview-signing-panel">
          <div class="signing-context">
            <strong>Ký điện tử: Đơn yêu cầu chấm dứt</strong>
            <p>Chữ ký và OTP sẽ xác nhận đúng phiên bản file đang xem.</p>
          </div>
          <div class="signature-field">
            <div class="signature-field-head">
              <strong>Vẽ chữ ký của chủ sân</strong>
              <button type="button" class="btn-text-sm" @click="clearSignaturePad('request')">Xóa vẽ lại</button>
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
          <label class="checkbox-label">
            <input v-model="requestSignatureAccepted" type="checkbox" />
            <span>Tôi xác nhận nội dung file đúng và đồng ý gửi đơn yêu cầu chấm dứt hợp đồng.</span>
          </label>
          <div v-if="!signing.requestId" class="form-actions-right">
            <button class="btn btn-primary" type="button" :disabled="working || requestSignatureEmpty || !requestSignatureAccepted" @click="confirmBeforeOtp">
              Gửi OTP xác nhận
            </button>
          </div>
          <div v-else class="otp-box">
            <p><strong>Nhập OTP email chủ sân</strong> <small>(Mã đối soát {{ signing.hashShort }})</small></p>
            <div class="form-group">
              <input v-model.trim="signing.otp" inputmode="numeric" maxlength="6" class="form-control otp-input" placeholder="Mã 6 chữ số" />
            </div>
            <button class="btn btn-primary" type="button" :disabled="working || signing.otp.length !== 6" @click="submit">
              Ký & Gửi đơn
            </button>
          </div>
        </div>

        <div v-else-if="previewPurpose === 'cancel'" class="preview-signing-panel">
          <div class="signing-context warning">
            <strong>Ký điện tử: Văn bản hủy yêu cầu chấm dứt</strong>
            <p>Đơn cũ vẫn được lưu trong lịch sử; chữ ký này xác nhận rút lại đơn.</p>
          </div>
          <div class="signature-field">
            <div class="signature-field-head">
              <strong>Vẽ chữ ký của chủ sân</strong>
              <button type="button" class="btn-text-sm" @click="clearSignaturePad('cancel')">Xóa vẽ lại</button>
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
          <label class="checkbox-label">
            <input v-model="cancelSignatureAccepted" type="checkbox" />
            <span>Tôi xác nhận nội dung văn bản hủy đúng và đồng ý tiếp tục vận hành.</span>
          </label>
          <div v-if="!cancelSigning.requestId" class="form-actions-right">
            <button class="btn btn-danger-soft" type="button" :disabled="working || cancelSignatureEmpty || !cancelSignatureAccepted" @click="sendCancelOtp">
              Gửi OTP xác nhận hủy
            </button>
          </div>
          <div v-else class="otp-box">
            <p><strong>Nhập OTP email chủ sân</strong> <small>(Mã đối soát {{ cancelSigning.hashShort }})</small></p>
            <div class="form-group">
              <input v-model.trim="cancelSigning.otp" inputmode="numeric" maxlength="6" class="form-control otp-input" placeholder="Mã 6 chữ số" />
            </div>
            <button class="btn btn-danger-soft" type="button" :disabled="working || cancelSigning.otp.length !== 6" @click="cancelRequest">
              Ký & Xác nhận hủy
            </button>
          </div>
        </div>

        <div v-else-if="previewPurpose === 'final'" class="preview-signing-panel">
          <div class="signing-context">
            <strong>Ký điện tử: Biên bản chấm dứt hợp đồng cuối</strong>
            <p>SportGo đã ký. Chủ sân kiểm tra kỹ công nợ trước khi ký hoàn tất.</p>
          </div>
          <div class="signature-field">
            <div class="signature-field-head">
              <strong>Vẽ chữ ký của chủ sân</strong>
              <button type="button" class="btn-text-sm" @click="clearSignaturePad('final')">Xóa vẽ lại</button>
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
          <label class="checkbox-label">
            <input v-model="finalSignatureAccepted" type="checkbox" />
            <span>Tôi xác nhận đã đọc biên bản cuối và đồng ý hoàn tất chấm dứt hợp tác.</span>
          </label>
          <div v-if="!finalSigning.requestId" class="form-actions-right">
            <button class="btn btn-primary" type="button" :disabled="working || finalSignatureEmpty || !finalSignatureAccepted" @click="sendFinalOtp">
              Gửi OTP xác nhận
            </button>
          </div>
          <div v-else class="otp-box">
            <p><strong>Nhập OTP email chủ sân</strong> <small>(Mã đối soát {{ finalSigning.hashShort }})</small></p>
            <div class="form-group">
              <input v-model.trim="finalSigning.otp" inputmode="numeric" maxlength="6" class="form-control otp-input" placeholder="Mã 6 chữ số" />
            </div>
            <button class="btn btn-primary" type="button" :disabled="working || finalSigning.otp.length !== 6" @click="signFinal">
              Ký biên bản cuối
            </button>
          </div>
        </div>
      </template>
    </DocumentViewerModal>

    <!-- Confirmation Modal -->
    <div v-if="showFutureBookingConfirm" class="modal-backdrop" @click.self="showFutureBookingConfirm = false">
      <div class="confirm-dialog-card">
        <div class="dialog-head">
          <h3>Xác nhận booking tương lai</h3>
          <button class="btn-icon-square" type="button" @click="showFutureBookingConfirm = false">✕</button>
        </div>
        <p class="dialog-copy">
          Cụm sân hiện đang có booking tương lai. Sau khi chủ sân ký gửi yêu cầu, cụm sân sẽ dừng nhận booking mới và khóa các thao tác quản lý thông thường.
        </p>
        <div class="dialog-actions-right">
          <button class="btn btn-outline btn-sm" type="button" @click="showFutureBookingConfirm = false">Hủy bỏ</button>
          <button class="btn btn-primary btn-sm" type="button" :disabled="working" @click="sendRequestOtp">
            Đồng ý & Tiếp tục
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '../../components/AppIcon.vue';
import AppTabs from '../../components/common/AppTabs.vue';
import DocumentViewerModal from '../../components/DocumentViewerModal.vue';
import { api } from '../../services/api.js';
import { ownerPartnerTerminationService } from '../../services/ownerPartnerTermination';
import { addCalendarDays, businessDateString } from '../../utils/businessTime.js';

const route = useRoute();
const router = useRouter();

const loading = ref(true);
const loadError = ref('');
const actionError = ref('');
const working = ref(false);
const eligibility = ref(null);
const termination = ref(null);
const editingDraft = ref(false);
const futureBookings = ref([]);
const selectedBookingIds = ref([]);
const bookingActionChoice = ref('');
const ownerWallets = ref([]);
const bankAccounts = ref([]);
const activeTab = ref('info');

const showCalendarPicker = ref(false);
const calendarToday = businessDateString();
const calendarYear = ref(Number(calendarToday.slice(0, 4)));
const calendarMonth = ref(Number(calendarToday.slice(5, 7)) - 1);

const monthNames = ['Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6', 'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'];

const calendarTitle = computed(() => `${monthNames[calendarMonth.value]} năm ${calendarYear.value}`);

const formattedSelectedDate = computed(() => {
  if (!form.requested_effective_date) return 'Chọn ngày chấm dứt trên lịch';
  const [y, m, d] = form.requested_effective_date.split('-');
  return `Ngày ${d}/${m}/${y}`;
});

const monthDaysGrid = computed(() => {
  const year = calendarYear.value;
  const month = calendarMonth.value;
  const firstDay = new Date(year, month, 1);
  const lastDay = new Date(year, month + 1, 0);

  let startDayOfWeek = firstDay.getDay() - 1;
  if (startDayOfWeek < 0) startDayOfWeek = 6;

  const totalDays = lastDay.getDate();
  const todayStr = businessDateString();

  const days = [];
  for (let i = 0; i < startDayOfWeek; i++) {
    days.push({ empty: true });
  }

  for (let d = 1; d <= totalDays; d++) {
    const monthStr = String(month + 1).padStart(2, '0');
    const dayStr = String(d).padStart(2, '0');
    const dateStr = `${year}-${monthStr}-${dayStr}`;

    days.push({
      empty: false,
      day: d,
      dateString: dateStr,
      isPast: dateStr < todayStr,
      isSelected: form.requested_effective_date === dateStr,
      isToday: dateStr === todayStr,
    });
  }

  return days;
});

function selectCalendarDate(dateStr) {
  form.requested_effective_date = dateStr;
  showCalendarPicker.value = false;
}

function quickSelectDays(daysToAdd) {
  form.requested_effective_date = addCalendarDays(businessDateString(), daysToAdd);
}

function prevMonth() {
  if (calendarMonth.value === 0) {
    calendarMonth.value = 11;
    calendarYear.value--;
  } else {
    calendarMonth.value--;
  }
}

function nextMonth() {
  if (calendarMonth.value === 11) {
    calendarMonth.value = 0;
    calendarYear.value++;
  } else {
    calendarMonth.value++;
  }
}

function policyDescription(val) {
  return {
    fulfill_all: 'Giữ nguyên các lịch hẹn hiện có và tiếp tục phục vụ khách hàng cho đến khi hết booking cuối.',
    cancel_refund_all: 'Hệ thống tự động hủy tất cả các lịch đặt tương lai và hoàn tiền 100% cho khách hàng.',
    cancel_partial: 'Hủy các lịch đặt tương lai và tính toán mức tiền hoàn cho khách hàng theo đúng quy định cụm sân.',
  }[val] || 'Nhấp chọn phương án xử lý phù hợp cho lượt đặt sân tương lai.';
}

const showPreviewModal = ref(false);
const previewPurpose = ref('view');
const showFutureBookingConfirm = ref(false);
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
  && ['submitted', 'reviewing', 'settlement_processing'].includes(termination.value.status)
));
const showBookingWorkspace = computed(() => Boolean(
  termination.value?.id
  && !isDraftRequest(termination.value.status)
  && (isFutureBookingStatus(termination.value.status) || futureBookings.value.length > 0)
));
const showSettlementWorkspace = computed(() => Boolean(termination.value?.id && isSettlementStatus(termination.value.status)));

const tabs = computed(() => {
  const list = [
    { key: 'info', label: 'Thông tin & Yêu cầu', icon: 'fileText' },
  ];
  if (showBookingWorkspace.value) {
    list.push({ key: 'bookings', label: 'Booking tương lai', icon: 'calendar', badge: futureBookings.value.length });
  }
  if (showSettlementWorkspace.value) {
    list.push({ key: 'settlement', label: 'Rút tiền & Quyết toán', icon: 'creditCard' });
  }
  if (displayDocuments.value.length) {
    list.push({ key: 'documents', label: 'Văn bản điện tử', icon: 'paperclip', badge: displayDocuments.value.length });
  }
  return list;
});

const ownerSteps = computed(() => buildOwnerSteps());
const ownerNextAction = computed(() => {
  if (!termination.value) {
    return { title: 'Tạo đơn yêu cầu', description: 'Nhập lý do, chọn phương án booking tương lai rồi tạo bản xem trước.' };
  }

  const status = termination.value.status;
  if (isUnilateralNotice.value) {
    if (isDraftRequest(status)) return { title: 'Chờ SportGo ký công văn', description: 'Bản xem trước đang chờ admin ký.' };
    if (isSubmittedRequest(status)) return { title: 'Đọc và xác nhận đã nhận công văn', description: 'Mở file công văn, kiểm tra lý do rồi xác nhận đã nhận.' };
    if (isFutureBookingStatus(status)) return { title: 'Xử lý booking theo công văn', description: 'Cụm sân dừng nhận booking mới. Chủ sân xử lý các booking hiện có.' };
    if (isSettlementStatus(status)) return { title: 'Hoàn tất công nợ và đối soát', description: 'Booking đã được xử lý; tiếp tục xử lý rút tiền và số dư.' };
    if (isOwnerCancelledStatus(status)) return { title: 'SportGo đã thu hồi công văn', description: 'Quy trình đã dừng.' };
  }
  if (isDraftRequest(status)) return { title: 'Chủ sân ký đơn', description: 'Kiểm tra file đơn yêu cầu, ký và nhập OTP để gửi chính thức.' };
  if (isSubmittedRequest(status)) return { title: 'Chờ admin xác nhận', description: 'Đơn đã được gửi. Admin cần kiểm tra và xác nhận.' };
  if (isFutureBookingStatus(status)) return { title: 'Xử lý booking tương lai', description: 'Chọn booking cần xử lý, hủy/hoàn tiền hoặc phục vụ đến lượt cuối.' };
  if (isSettlementStatus(status)) return { title: 'Chờ quyết toán cuối', description: 'Booking đã xử lý. Chủ sân xử lý rút tiền/công nợ.' };
  if (isFinalSignatureStatus(status) && !finalAdminSigned.value) return { title: 'Chờ SportGo ký biên bản', description: 'Admin cần ký biên bản chấm dứt cuối trước.' };
  if (canOwnerSignFinal.value) return { title: 'Chủ sân ký biên bản cuối', description: 'SportGo đã ký. Chủ sân ký OTP để hoàn tất hồ sơ.' };
  if (isOwnerCancelledStatus(status)) return { title: 'Đã hủy yêu cầu', description: 'Cụm sân được mở lại.' };
  if (isTerminatingStatus(status)) return { title: 'Trong thời gian xem hồ sơ', description: 'Biên bản cuối đã ký đủ hai bên.' };
  if (isTerminatedStatus(status)) return { title: 'Đã chấm dứt', description: 'Hợp đồng đã chấm dứt hoàn tất.' };

  return { title: 'Theo dõi hồ sơ', description: 'Theo dõi văn bản, booking và rút tiền.' };
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
  window.dispatchEvent(new CustomEvent('owner-cluster-changed', { detail: selected }));
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
    const [walletsRes, accountsRes] = await Promise.all([
      api('/api/owner/wallets'),
      api('/api/owner/bank-accounts'),
    ]);
    ownerWallets.value = walletsRes.data || [];
    bankAccounts.value = accountsRes.data || [];
    if (ownerWallets.value.length && !withdrawal.owner_wallet_id) {
      withdrawal.owner_wallet_id = ownerWallets.value[0].id;
    }
    if (bankAccounts.value.length && !withdrawal.owner_bank_account_id) {
      withdrawal.owner_bank_account_id = bankAccounts.value[0].id;
    }
    if (!withdrawal.amount && summary.value.withdrawable_amount) {
      withdrawal.amount = Number(summary.value.withdrawable_amount);
    }
  } catch (e) {
    // Ignore wallet load errors
  }
}

function hydrateForm(target) {
  if (!target) return;
  form.reason = target.reason || '';
  form.detail_reason = target.detail_reason || '';
  form.requested_effective_date = target.requested_effective_date ? target.requested_effective_date.slice(0, 10) : '';
  form.future_booking_policy = target.future_booking_policy || '';
  form.warning_accepted = Boolean(target.warning_accepted);
}

function buildOwnerSteps() {
  if (isUnilateralNotice.value) {
    const current = termination.value?.status;
    const isSubmitted = isSubmittedRequest(current);
    const isBooking = isFutureBookingStatus(current);
    const isSettlement = isSettlementStatus(current);
    const isFinalSig = isFinalSignatureStatus(current);
    const isDone = isTerminatedStatus(current);

    return [
      { key: 'notice', label: '1. Tiếp nhận công văn', state: isSubmitted ? 'current' : 'done' },
      { key: 'booking', label: '2. Xử lý booking', state: isBooking ? 'current' : (isSubmitted ? 'pending' : 'done') },
      { key: 'settlement', label: '3. Quyết toán', state: isSettlement ? 'current' : (isSubmitted || isBooking ? 'pending' : 'done') },
      { key: 'done', label: '4. Thu hồi quyền', state: isDone ? 'done' : (isFinalSig ? 'current' : 'pending') },
    ];
  }

  const current = termination.value?.status || 'draft';
  const isDraft = isDraftRequest(current);
  const isSubmitted = isSubmittedRequest(current);
  const isBooking = isFutureBookingStatus(current);
  const isSettlement = isSettlementStatus(current);
  const isFinalSig = isFinalSignatureStatus(current);
  const isDone = isTerminatedStatus(current);

  return [
    { key: 'draft', label: '1. Đơn & chữ ký', state: isDraft ? 'current' : 'done' },
    { key: 'submitted', label: '2. Admin xác nhận', state: isSubmitted ? 'current' : (isDraft ? 'pending' : 'done') },
    { key: 'booking', label: '3. Booking & Công nợ', state: isBooking || isSettlement ? 'current' : (isDraft || isSubmitted ? 'pending' : 'done') },
    { key: 'final', label: '4. Ký biên bản cuối', state: isFinalSig ? 'current' : (isDone ? 'done' : 'pending') },
  ];
}

function requestDocuments(target) {
  if (!target) return [];
  const list = target.documents || [];

  return list.map((doc) => ({
    ...doc,
    generated_document: doc.generated_document || doc.generatedDocument || null,
  }));
}

function isPendingCancellationDocument(doc) {
  const type = doc?.document_type;
  return ['owner_cancellation_request', 'cancellation_request'].includes(type)
    && doc?.status === 'pending_owner_signature';
}

function isFinalDocument(doc) {
  const type = doc?.document_type;
  return ['settlement_minutes', 'final_termination_file'].includes(type);
}

function documentTypeLabel(type) {
  return {
    owner_termination_request: 'Đơn yêu cầu chấm dứt hợp đồng',
    partner_termination_request: 'Đơn yêu cầu chấm dứt hợp đồng',
    termination_request: 'Đơn yêu cầu chấm dứt hợp đồng',
    unilateral_notice: 'Công văn chấm dứt hợp đồng',
    unilateral_termination_notice: 'Công văn chấm dứt hợp đồng',
    settlement_minutes: 'Biên bản quyết toán & thanh lý',
    final_termination_file: 'Biên bản quyết toán & thanh lý',
    owner_cancellation_request: 'Văn bản hủy yêu cầu chấm dứt',
  }[type] || type || 'Văn bản điện tử';
}

function documentProgressLabel(doc) {
  const status = doc?.status || doc?.generated_document?.status;
  return {
    pending_owner_signature: 'Chờ chủ sân ký',
    pending_sportgo_signature: 'Chờ SportGo ký',
    completed: 'Đã hoàn tất',
    generated: 'Bản thảo',
  }[status] || status || 'Đã tạo';
}

function documentMeta(doc) {
  const code = doc.document_code || doc.generated_document?.document_code || 'Mã tự động';
  const created = formatDate(doc.created_at || doc.generated_document?.created_at);
  return `${code} • Tạo lúc ${created}`;
}

function openRequestPreview() {
  selectedPreviewRow.value = latestRequestDocument.value;
  previewPurpose.value = 'request';
  actionError.value = '';
  showPreviewModal.value = true;
}

function openUnilateralNotice() {
  selectedPreviewRow.value = latestRequestDocument.value;
  previewPurpose.value = 'notice';
  actionError.value = '';
  showPreviewModal.value = true;
}

function openFinalPreview() {
  selectedPreviewRow.value = latestFinalDocument.value;
  previewPurpose.value = 'final';
  actionError.value = '';
  showPreviewModal.value = true;
}

function openCancellationPreview(doc) {
  selectedPreviewRow.value = doc;
  previewPurpose.value = 'cancel';
  actionError.value = '';
  showPreviewModal.value = true;
}

function openDocumentPreview(doc) {
  selectedPreviewRow.value = doc;
  previewPurpose.value = 'view';
  actionError.value = '';
  showPreviewModal.value = true;
}

function closePreviewModal() {
  showPreviewModal.value = false;
  selectedPreviewRow.value = null;
  resetSigningState();
}

function resetSigningState() {
  signing.requestId = null;
  signing.hashShort = '';
  signing.otp = '';
  finalSigning.requestId = null;
  finalSigning.hashShort = '';
  finalSigning.otp = '';
  cancelSigning.requestId = null;
  cancelSigning.hashShort = '';
  cancelSigning.otp = '';
  actionError.value = '';
}

function prepareSignaturePads() {}

function startSignature(event, target) {
  activeSignaturePad.value = target;
  const canvas = getCanvasRef(target);
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const rect = canvas.getBoundingClientRect();
  ctx.strokeStyle = '#0f172a';
  ctx.lineWidth = 2;
  ctx.lineCap = 'round';
  ctx.beginPath();
  ctx.moveTo(
    ((event.clientX - rect.left) / rect.width) * canvas.width,
    ((event.clientY - rect.top) / rect.height) * canvas.height
  );
}

function drawSignature(event, target) {
  if (activeSignaturePad.value !== target) return;
  const canvas = getCanvasRef(target);
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const rect = canvas.getBoundingClientRect();
  ctx.lineTo(
    ((event.clientX - rect.left) / rect.width) * canvas.width,
    ((event.clientY - rect.top) / rect.height) * canvas.height
  );
  ctx.stroke();
  setSignatureEmpty(target, false);
}

function stopSignature() {
  activeSignaturePad.value = '';
}

function clearSignaturePad(target) {
  const canvas = getCanvasRef(target);
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  setSignatureEmpty(target, true);
}

function getCanvasRef(target) {
  if (target === 'request') return requestSignatureCanvas.value;
  if (target === 'final') return finalSignatureCanvas.value;
  if (target === 'cancel') return cancelSignatureCanvas.value;
  return null;
}

function setSignatureEmpty(target, isEmpty) {
  if (target === 'request') requestSignatureEmpty.value = isEmpty;
  if (target === 'final') finalSignatureEmpty.value = isEmpty;
  if (target === 'cancel') cancelSignatureEmpty.value = isEmpty;
}

function getSignatureData(target) {
  const canvas = getCanvasRef(target);
  return canvas ? canvas.toDataURL('image/png') : null;
}

async function confirmBeforeOtp() {
  actionError.value = '';
  if (summary.value.future_booking_count > 0 && !termination.value) {
    showFutureBookingConfirm.value = true;
    return;
  }
  await sendRequestOtp();
}

async function sendRequestOtp() {
  showFutureBookingConfirm.value = false;
  working.value = true;
  actionError.value = '';
  try {
    const payload = {
      venue_cluster_id: currentClusterId.value,
      reason: form.reason,
      detail_reason: form.detail_reason,
      requested_effective_date: form.requested_effective_date,
      future_booking_policy: form.future_booking_policy,
      warning_accepted: form.warning_accepted,
      owner_signature_base64: getSignatureData('request'),
    };
    const response = await ownerPartnerTerminationService.requestOtp(payload);
    signing.requestId = response.data.signature_request_id;
    signing.hashShort = response.data.document_hash?.slice(0, 8) || 'OTP';
  } catch (err) {
    actionError.value = err.message || 'Không thể gửi OTP.';
  } finally {
    working.value = false;
  }
}

async function submit() {
  working.value = true;
  actionError.value = '';
  try {
    const payload = {
      signature_request_id: signing.requestId,
      otp: signing.otp,
    };
    const response = await ownerPartnerTerminationService.submit(payload);
    termination.value = response.data;
    editingDraft.value = false;
    closePreviewModal();
    await load();
  } catch (err) {
    actionError.value = err.message || 'Xác nhận OTP thất bại.';
  } finally {
    working.value = false;
  }
}

async function sendFinalOtp() {
  working.value = true;
  actionError.value = '';
  try {
    const response = await ownerPartnerTerminationService.finalSignOtp(termination.value.id, {
      owner_signature_base64: getSignatureData('final'),
    });
    finalSigning.requestId = response.data.signature_request_id;
    finalSigning.hashShort = response.data.document_hash?.slice(0, 8) || 'OTP';
  } catch (err) {
    actionError.value = err.message || 'Không thể gửi OTP biên bản.';
  } finally {
    working.value = false;
  }
}

async function signFinal() {
  working.value = true;
  actionError.value = '';
  try {
    const response = await ownerPartnerTerminationService.signFinal(termination.value.id, {
      signature_request_id: finalSigning.requestId,
      otp: finalSigning.otp,
    });
    termination.value = response.data;
    closePreviewModal();
    await load();
  } catch (err) {
    actionError.value = err.message || 'Ký biên bản thất bại.';
  } finally {
    working.value = false;
  }
}

function previewCancellation() {
  if (cancelForm.reason.length < 10) return;
  actionError.value = '';
  openCancellationPreview(null);
}

async function sendCancelOtp() {
  working.value = true;
  actionError.value = '';
  try {
    const response = await ownerPartnerTerminationService.cancelOtp(termination.value.id, {
      reason: cancelForm.reason,
      owner_signature_base64: getSignatureData('cancel'),
    });
    cancelSigning.requestId = response.data.signature_request_id;
    cancelSigning.hashShort = response.data.document_hash?.slice(0, 8) || 'OTP';
  } catch (err) {
    actionError.value = err.message || 'Không thể gửi OTP hủy.';
  } finally {
    working.value = false;
  }
}

async function cancelRequest() {
  working.value = true;
  actionError.value = '';
  try {
    const response = await ownerPartnerTerminationService.cancel(termination.value.id, {
      signature_request_id: cancelSigning.requestId,
      otp: cancelSigning.otp,
    });
    termination.value = response.data;
    closePreviewModal();
    await load();
  } catch (err) {
    actionError.value = err.message || 'Hủy yêu cầu thất bại.';
  } finally {
    working.value = false;
  }
}

async function acknowledgeNotice() {
  working.value = true;
  actionError.value = '';
  try {
    const response = await ownerPartnerTerminationService.acknowledgeNotice(termination.value.id);
    termination.value = response.data;
    await load();
  } catch (err) {
    actionError.value = err.message || 'Xác nhận công văn thất bại.';
  } finally {
    working.value = false;
  }
}

async function requestReconsideration() {
  if (reconsiderationReason.value.length < 20) return;
  working.value = true;
  actionError.value = '';
  try {
    const response = await ownerPartnerTerminationService.requestReconsideration(termination.value.id, {
      reason: reconsiderationReason.value,
    });
    termination.value = response.data;
    showReconsiderationForm.value = false;
    reconsiderationReason.value = '';
    await load();
  } catch (err) {
    actionError.value = err.message || 'Gửi yêu cầu xem xét lại thất bại.';
  } finally {
    working.value = false;
  }
}

async function bulkAction(policy) {
  if (!selectedBookingIds.value.length || !policy) return;
  working.value = true;
  actionError.value = '';
  try {
    const response = await ownerPartnerTerminationService.bulkBookingAction(termination.value.id, {
      booking_ids: selectedBookingIds.value,
      action_type: policy,
    });
    futureBookings.value = response.data || [];
    await load();
  } catch (err) {
    actionError.value = err.message || 'Xử lý booking thất bại.';
  } finally {
    working.value = false;
  }
}

async function storeWithdrawal() {
  if (!canSubmitWithdrawal.value) return;
  working.value = true;
  actionError.value = '';
  try {
    await api('/api/owner/withdrawals', {
      method: 'POST',
      body: JSON.stringify({
        owner_wallet_id: withdrawal.owner_wallet_id,
        owner_bank_account_id: withdrawal.owner_bank_account_id,
        amount: Number(withdrawal.amount),
        owner_note: withdrawal.owner_note,
        partner_termination_request_id: termination.value?.id,
      }),
    });
    await load();
  } catch (err) {
    actionError.value = err.message || 'Gửi yêu cầu rút tiền thất bại.';
  } finally {
    working.value = false;
  }
}

function policyLabel(val) {
  return {
    fulfill_all: 'Phục vụ đến booking cuối',
    cancel_refund_all: 'Hủy & Hoàn tiền toàn bộ',
    cancel_partial: 'Hủy & Hoàn tiền theo chính sách',
  }[val] || val || 'Chưa chọn';
}

function statusLabel(status) {
  return {
    eligible: 'Có thể tạo yêu cầu',
    draft: 'Bản nháp',
    draft_preview: 'Bản xem trước',
    submitted: 'Đã gửi, chờ duyệt',
    reviewing: 'Đang xem xét',
    approved: 'Đã duyệt',
    pending_signature: 'Chờ chủ sân ký',
    cancellation_in_progress: 'Đang hủy hợp tác',
    future_bookings_processing: 'Đang xử lý booking',
    waiting_final_settlement: 'Chờ quyết toán',
    waiting_final_document_signature: 'Chờ ký biên bản cuối',
    terminating: 'Đang thu hồi quyền',
    transition_period: 'Giai đoạn chuyển tiếp',
    settlement_processing: 'Đang quyết toán',
    settlement_completed: 'Đã quyết toán',
    completed: 'Đã hoàn tất',
    rejected: 'Từ chối',
    cancelled: 'Đã hủy',
    terminated: 'Đã thu hồi quyền',
    owner_cancelled_request: 'Chủ sân đã hủy',
    admin_rejected: 'Admin từ chối',
  }[status] || status || '-';
}

function money(value) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(value || 0));
}

function formatDate(value) {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('vi-VN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function maskAccountNumber(number) {
  if (!number) return '';
  return `****${String(number).slice(-4)}`;
}
</script>

<style scoped>
.owner-partner-termination-container {
  display: flex;
  flex-direction: column;
  gap: 0;
  width: 100%;
}

.termination-master-workspace {
  display: flex;
  flex-direction: column;
}

/* Header Tabs Surface */
.termination-header-tabs-surface {
  background: transparent;
  border-radius: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
}

/* Content Surface (Exact match to ClusterGeneralInfoTab & OwnerPartnerProfile) */
.termination-content-surface {
  display: flex;
  flex-direction: column;
  background: var(--admin-surface, #ffffff);
  border-radius: 0 0 12px 12px;
  overflow: hidden;
  padding: 10px;
  gap: 28px;
}

.termination-content-surface.standalone {
  border-radius: 12px;
}

/* Top Meta Summary Block */
.termination-meta-summary {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.summary-heading {
  margin: 0;
  font-size: 18px;
  font-weight: 500;
  color: var(--admin-text, #0f172a);
}

.summary-desc {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--admin-muted, #64748b);
}

.summary-financial-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.financial-metric-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px 14px;
  background: var(--admin-hover, rgba(0, 0, 0, 0.02));
  border-radius: 8px;
}

.financial-metric-item.highlight {
  background: rgba(34, 197, 94, 0.06);
}

.metric-label {
  font-size: 12px;
  color: var(--admin-muted, #64748b);
}

.metric-amount {
  font-size: 15px;
  font-weight: 500;
  color: var(--admin-text, #0f172a);
}

.metric-amount.green {
  color: #16a34a;
}

/* Workflow Stepper Strip */
.workflow-stepper-strip {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  background: var(--admin-hover, #f8fafc);
  border-radius: 8px;
  gap: 12px;
}

.stepper-step {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  opacity: 0.55;
}

.stepper-step.current {
  opacity: 1;

  .step-circle {
    background: var(--admin-primary, #3b82f6);
    color: #ffffff;
  }

  .step-label {
    color: var(--admin-primary, #3b82f6);
    font-weight: 500;
  }
}

.stepper-step.done {
  opacity: 1;

  .step-circle {
    background: #16a34a;
    color: #ffffff;
  }

  .step-label {
    color: var(--admin-text, #0f172a);
  }
}

.step-circle {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: var(--admin-border-soft, #cbd5e1);
  color: var(--admin-text, #0f172a);
  font-size: 11.5px;
  font-weight: 500;
}

.step-label {
  font-size: 12.5px;
  color: var(--admin-muted, #64748b);
}

/* Flow & Section Header */
.tab-pane-flow {
  display: flex;
  flex-direction: column;
  gap: 28px;
}

.termination-section {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.tab-section-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.tab-section-header h2 {
  margin: 0;
  font-size: 17px;
  font-weight: 500;
  color: var(--admin-text, #0f172a);
}

.section-subtitle {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--admin-muted, #64748b);
}

.count-pill {
  font-size: 12px;
  font-weight: 500;
  padding: 4px 10px;
  border-radius: 6px;
  background: var(--admin-hover, #f1f5f9);
  color: var(--admin-muted, #64748b);
}

/* Standard Form Controls */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group.full-width {
  grid-column: span 2;
}

.form-label {
  font-size: 13px;
  font-weight: 500;
  color: var(--admin-text, #0f172a);
}

.form-control {
  width: 100%;
  height: 38px;
  padding: 0 12px;
  border-radius: 8px;
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  background: var(--admin-surface, #ffffff);
  color: var(--admin-text, #0f172a);
  font-size: 13.5px;
  outline: none;
  transition: border-color 0.15s ease;
}

textarea.form-control {
  height: auto;
  padding: 10px 12px;
}

.form-control:focus {
  border-color: var(--admin-primary, #3b82f6);
}

.checkbox-label {
  display: flex !important;
  flex-direction: row !important;
  align-items: center !important;
  justify-content: flex-start !important;
  gap: 10px !important;
  width: 100% !important;
  font-size: 13px;
  color: var(--admin-text, #0f172a);
  cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
  width: 16px !important;
  height: 16px !important;
  flex-shrink: 0 !important;
  margin: 0 !important;
  cursor: pointer;
}

.checkbox-label span {
  flex: 1;
  font-size: 13px;
  line-height: 1.4;
  color: var(--admin-text, #0f172a);
}

.form-actions-right {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
}

/* Urgent Action Notice Banner */
.urgent-action-notice {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 18px;
  border-radius: 8px;
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.2);
}

.notice-icon {
  color: #d97706;
  flex-shrink: 0;
}

.notice-body {
  flex: 1;
}

.notice-body strong {
  font-size: 13.5px;
  font-weight: 500;
  color: var(--admin-text, #0f172a);
}

.notice-body p {
  margin: 2px 0 0;
  font-size: 13px;
  color: var(--admin-muted, #64748b);
}

.urgent-actions-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

/* Flat Document List */
.flat-doc-list {
  display: flex;
  flex-direction: column;
}

.flat-doc-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px 0;
  border-bottom: 1px solid var(--admin-border-soft, #f1f5f9);
}

.flat-doc-row:last-child {
  border-bottom: none;
}

.doc-file-type-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: var(--admin-hover, #f1f5f9);
  color: var(--admin-primary, #3b82f6);
  flex-shrink: 0;
}

.doc-main-details {
  flex: 1;
}

.doc-title {
  margin: 0;
  font-size: 14px;
  font-weight: 500;
  color: var(--admin-text, #0f172a);
}

.doc-meta-info {
  font-size: 12px;
  color: var(--admin-muted, #64748b);
}

.doc-actions-group {
  display: flex;
  align-items: center;
  gap: 10px;
}

.doc-status-pill {
  font-size: 11.5px;
  padding: 2px 8px;
  border-radius: 4px;
  background: var(--admin-hover, #f1f5f9);
  color: var(--admin-muted, #64748b);
}

/* Flat Meta Info Grid (Read-only view without text input boxes) */
.meta-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px 32px;
}

.meta-info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.meta-info-item.full-width {
  grid-column: span 2;
}

.meta-info-label {
  font-size: 12.5px;
  font-weight: 500;
  color: var(--admin-muted, #64748b);
}

.meta-info-value {
  font-size: 14px;
  font-weight: 400;
  color: var(--admin-text, #0f172a);
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 7px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
  border: 1px solid transparent;
  white-space: nowrap;
}

.btn-sm {
  height: 32px;
  padding: 0 12px;
  font-size: 12.5px;
}

.btn-primary {
  background: var(--admin-primary, #3b82f6);
  color: #ffffff;
}

.btn-primary:hover:not(:disabled) {
  background: var(--admin-primary, #3b82f6);
  box-shadow: none;
  transform: none;
}

.btn-outline {
  background: transparent;
  border-color: var(--admin-border-soft, #cbd5e1);
  color: var(--admin-text, #334155);
}

.btn-outline:hover {
  background: var(--admin-hover, #f1f5f9);
}

.btn-danger-soft {
  background: rgba(239, 68, 68, 0.08);
  color: #dc2626;
  border-color: rgba(239, 68, 68, 0.2);
}

.btn-danger-soft:hover {
  background: rgba(239, 68, 68, 0.15);
}

/* State Surfaces */
.state-loading-surface, .state-error-surface {
  background: var(--admin-surface, #ffffff);
  border-radius: 12px;
  padding: 48px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  text-align: center;
  color: var(--admin-muted, #64748b);
}

.spinner {
  width: 28px;
  height: 28px;
  border: 3px solid rgba(59, 130, 246, 0.2);
  border-top-color: var(--admin-primary, #3b82f6);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Custom Interactive Calendar Picker */
.calendar-picker-container {
  display: flex;
  flex-direction: column;
  gap: 8px;
  position: relative;
}

.calendar-picker-header {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.calendar-trigger-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  height: 38px;
  padding: 0 14px;
  border-radius: 8px;
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  background: var(--admin-surface, #ffffff);
  color: var(--admin-text, #0f172a);
  font-size: 13.5px;
  cursor: pointer;
  min-width: 290px;
  justify-content: space-between;
  white-space: nowrap;
}

.calendar-trigger-btn:hover {
  border-color: var(--admin-primary, #3b82f6);
}

.calendar-icon {
  color: var(--admin-primary, #3b82f6);
}

.chevron-icon {
  color: var(--admin-muted, #64748b);
  transition: transform 0.2s ease;
}

.chevron-icon.rotated {
  transform: rotate(180deg);
}

.quick-preset-chips {
  display: flex;
  align-items: center;
  gap: 8px;
}

.preset-chip {
  height: 32px;
  padding: 0 12px;
  border-radius: 6px;
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  background: var(--admin-hover, #f8fafc);
  color: var(--admin-text, #334155);
  font-size: 12.5px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.preset-chip:hover {
  background: rgba(59, 130, 246, 0.08);
  border-color: var(--admin-primary, #3b82f6);
  color: var(--admin-primary, #3b82f6);
}

.interactive-month-calendar {
  width: 320px;
  padding: 16px;
  border-radius: 12px;
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  background: var(--admin-surface, #ffffff);
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 4px;
}

.month-nav-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.nav-month-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  border: none;
  background: var(--admin-hover, #f1f5f9);
  color: var(--admin-text, #0f172a);
  cursor: pointer;
}

.current-month-label {
  font-size: 13.5px;
  font-weight: 500;
  color: var(--admin-text, #0f172a);
}

.calendar-weekdays-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  text-align: center;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--admin-muted, #94a3b8);
}

.calendar-days-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}

.day-cell {
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  border: none;
  background: transparent;
  font-size: 13px;
  color: var(--admin-text, #0f172a);
  cursor: pointer;
}

.day-cell:hover:not(:disabled):not(.empty) {
  background: var(--admin-hover, #f1f5f9);
}

.day-cell.selected {
  background: var(--admin-primary, #3b82f6) !important;
  color: #ffffff !important;
  font-weight: 500;
}

.day-cell.today {
  border: 1px solid var(--admin-primary, #3b82f6);
}

.day-cell.past {
  color: var(--admin-muted, #cbd5e1);
  cursor: not-allowed;
}

/* Policy Option Choice Cards Grid */
.policy-option-cards-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  align-items: stretch;
}

.policy-choice-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 10px;
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  background: var(--admin-surface, #ffffff);
  cursor: pointer;
  transition: all 0.15s ease;
  height: 100%;
}

.policy-choice-card:hover {
  border-color: var(--admin-primary, #3b82f6);
}

.policy-choice-card.selected {
  border-color: var(--admin-primary, #3b82f6);
  background: rgba(59, 130, 246, 0.04);
}

.card-radio-circle {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 2px solid var(--admin-border-soft, #cbd5e1);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-top: 3px;
}

.policy-choice-card.selected .card-radio-circle {
  border-color: var(--admin-primary, #3b82f6);
}

.radio-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--admin-primary, #3b82f6);
}

.card-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.card-title {
  font-size: 13.5px;
  font-weight: 500;
  color: var(--admin-text, #0f172a);
  line-height: 1.4;
}

.card-desc {
  margin: 0;
  font-size: 12.5px;
  color: var(--admin-muted, #64748b);
  line-height: 1.4;
}

@media (max-width: 900px) {
  .policy-option-cards-grid {
    grid-template-columns: 1fr;
  }
}
</style>
