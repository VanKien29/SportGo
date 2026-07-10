<template>
  <div class="partner-detail-page">
    <header class="page-head">
      <button class="btn ghost" type="button" @click="router.push({ name: 'admin-partner-applications' })">
        <AppIcon name="arrowLeft" size="16" />
        Quay lại
      </button>

      <div class="title-block">
        <p>Hồ sơ đối tác</p>
        <h2>{{ application?.venue_name || 'Đang tải hồ sơ' }}</h2>
      </div>

      <span v-if="application" class="status" :class="`status-${application.status}`">
        {{ statusLabel(application.status) }}
      </span>
    </header>

    <div v-if="message" class="notice success">{{ message }}</div>
    <div v-if="error" class="notice error">{{ error }}</div>

    <section v-if="loading" class="state-card">Đang tải hồ sơ...</section>
    <section v-else-if="!application" class="state-card error">Không tìm thấy hồ sơ đối tác.</section>

    <template v-else>
      <section class="action-strip">
        <div>
          <strong>{{ application.business_name || application.applicant_full_name }}</strong>
          <span>{{ application.user?.email || application.applicant_email || '-' }}</span>
        </div>

        <div class="action-buttons">
          <button
            v-if="canReviewApplication"
            class="btn primary"
            type="button"
            @click="actionMode = 'approve'"
          >
            <AppIcon name="check" size="16" />
            Duyệt
          </button>
          <button
            v-if="canReviewApplication"
            class="btn danger"
            type="button"
            @click="actionMode = 'reject'"
          >
            <AppIcon name="x" size="16" />
            Từ chối
          </button>
          <button
            v-if="canReviewApplication"
            class="btn warning"
            type="button"
            @click="actionMode = 'supplement'"
          >
            <AppIcon name="fileText" size="16" />
            Bổ sung
          </button>
          <button
            v-if="pendingSportgoDocument"
            class="btn primary"
            type="button"
            @click="openDocument(pendingSportgoDocument)"
          >
            <AppIcon name="pencil" size="16" />
            Ký hợp đồng
          </button>
        </div>
      </section>

      <section v-if="!canReviewApplication && !pendingSportgoDocument" class="review-panel readonly-panel">
        <div class="panel-head">
          <div>
            <h3>{{ readonlyActionTitle }}</h3>
            <p>{{ readonlyActionText }}</p>
          </div>
        </div>
      </section>

      <section v-if="actionMode === 'approve' && canReviewApplication" class="review-panel">
        <div class="panel-head">
          <div>
            <h3>Duyệt hồ sơ và tạo hợp đồng</h3>
            <p>Sau khi duyệt, hệ thống tạo giấy/hợp đồng đối tác và chờ SportGo ký trước.</p>
          </div>
          <button class="icon-btn" type="button" title="Đóng" @click="clearAction">
            <AppIcon name="x" size="16" />
          </button>
        </div>

        <form class="review-form" @submit.prevent="submitApprove">
          <label v-if="requiresInitialCourt" class="field" :class="{ invalid: fieldErrors.initial_court_name }">
            <span>Tên sân con ban đầu</span>
            <input v-model.trim="approveForm.initial_court_name" type="text" placeholder="Ví dụ: Sân 1" />
            <small v-if="fieldErrors.initial_court_name">{{ fieldErrors.initial_court_name }}</small>
          </label>

          <label v-if="requiresInitialCourt" class="field" :class="{ invalid: fieldErrors.court_type_id }">
            <span>Loại sân</span>
            <select v-model="approveForm.court_type_id">
              <option value="">Chọn loại sân con</option>
              <option v-for="courtType in leafCourtTypes" :key="courtType.id" :value="courtType.id">
                {{ courtType.name }}
              </option>
            </select>
            <small v-if="fieldErrors.court_type_id">{{ fieldErrors.court_type_id }}</small>
          </label>

          <label class="field full">
            <span>Ghi chú duyệt</span>
            <textarea v-model.trim="approveForm.review_note" rows="4" placeholder="Ghi chú nội bộ nếu cần"></textarea>
          </label>

          <p v-if="actionError" class="inline-error full">{{ actionError }}</p>

          <div class="form-actions full">
            <button class="btn ghost" type="button" @click="clearAction">Hủy</button>
            <button class="btn primary" type="submit" :disabled="saving">
              <AppIcon name="check" size="16" />
              {{ saving ? 'Đang duyệt...' : 'Duyệt và tạo hợp đồng' }}
            </button>
          </div>
        </form>
      </section>

      <section v-if="(actionMode === 'reject' || actionMode === 'supplement') && canReviewApplication" class="review-panel" :class="{ 'danger-panel': actionMode === 'reject' }">
        <div class="panel-head">
          <div>
            <h3>{{ actionMode === 'supplement' ? 'Yêu cầu bổ sung hồ sơ' : 'Từ chối hồ sơ' }}</h3>
            <p>{{ actionMode === 'supplement' ? 'Nhập rõ giấy tờ/thông tin cần bổ sung. Nội dung này sẽ được gửi cho người đăng ký qua email.' : 'Bắt buộc nhập lý do. Lý do này sẽ được gửi cho người đăng ký qua email.' }}</p>
          </div>
          <button class="icon-btn" type="button" title="Đóng" @click="clearAction">
            <AppIcon name="x" size="16" />
          </button>
        </div>

        <form class="review-form" @submit.prevent="submitReject">
          <label class="field full" :class="{ invalid: fieldErrors.reason }">
            <span>{{ actionMode === 'supplement' ? 'Nội dung cần bổ sung' : 'Lý do từ chối' }}</span>
            <textarea v-model.trim="rejectForm.reason" rows="5" :placeholder="actionMode === 'supplement' ? 'Ví dụ: bổ sung ảnh mặt sau CCCD, hợp đồng thuê mặt bằng còn hiệu lực...' : 'Nêu rõ lý do hồ sơ chưa đạt'"></textarea>
            <small v-if="fieldErrors.reason">{{ fieldErrors.reason }}</small>
          </label>

          <p v-if="actionError" class="inline-error full">{{ actionError }}</p>

          <div class="form-actions full">
            <button class="btn ghost" type="button" @click="clearAction">Hủy</button>
            <button class="btn" :class="actionMode === 'supplement' ? 'primary' : 'danger'" type="submit" :disabled="saving">
              <AppIcon name="x" size="16" />
              {{ saving ? 'Đang xử lý...' : (actionMode === 'supplement' ? 'Gửi yêu cầu bổ sung' : 'Từ chối hồ sơ') }}
            </button>
          </div>
        </form>
      </section>

      <nav class="tabs">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          type="button"
          :class="{ active: activeTab === tab.value }"
          @click="activeTab = tab.value"
        >
          {{ tab.label }}
        </button>
      </nav>

      <section v-if="activeTab === 'overview'" class="summary-grid">
        <InfoPanel title="Người đăng ký" :items="[
          ['Họ tên', application.applicant_full_name || application.user?.full_name],
          ['Điện thoại', application.applicant_phone || application.user?.phone],
          ['Email', application.applicant_email || application.user?.email],
          ['Ngày sinh', dateOnly(application.applicant_birth_date)],
          ['Địa chỉ liên hệ', application.applicant_address],
        ]" />

        <InfoPanel title="Giấy tờ và pháp lý" :items="[
          ['Người đại diện', application.representative_name],
          ['Loại giấy tờ', identityLabel(application.representative_identity_type)],
          ['Số giấy tờ', application.representative_identity_number],
          ['Ngày cấp', dateOnly(application.representative_identity_issued_date)],
          ['Nơi cấp', application.representative_identity_issued_place],
          ['Địa chỉ pháp lý', application.business_address],
        ]" />

        <InfoPanel title="Đơn vị kinh doanh" :items="[
          ['Tên đơn vị/cá nhân', application.business_name],
          ['Mã số thuế', application.tax_code || '-'],
          ['Mã kinh doanh', application.business_code || '-'],
          ['Số giấy phép', application.business_license_number],
          ['Loại đăng ký', applicantTypeLabel(application.applicant_type)],
        ]" />

        <InfoPanel title="Ngân hàng" :items="[
          ['Ngân hàng', application.bank_name],
          ['Mã ngân hàng', application.bank_code],
          ['Số tài khoản', application.account_number],
          ['Chủ tài khoản', application.account_holder_name],
          ['Chi nhánh', application.bank_branch || '-'],
          ['Xác minh', bankVerificationLabel(application.bank_verification_status)],
        ]" />

        <InfoPanel class="wide" title="Cụm sân" :items="[
          ['Tên cụm sân', application.venue_name],
          ['Địa chỉ sân', application.venue_address],
          ['Tỉnh/Thành phố', application.venue_province],
          ['Phường/Xã', application.venue_ward],
          ['Tọa độ', coordinateText(application)],
          ['Google Maps', application.venue_map_url || '-'],
          ['Liên hệ sân', contactText(application)],
          ['Giờ mở cửa', application.expected_opening_hours || '-'],
          ['Tiện ích', compactList(application.amenities)],
          ['Số sân con', application.court_count_total || application.courts_count || 0],
          ['Giá cơ bản', money(application.base_price_per_hour)],
          ['Ghi chú trạng thái', application.status_reason || '-'],
        ]" />
      </section>

      <section v-if="activeTab === 'courts'" class="content-card">
        <div class="card-head">
          <h3>Danh sách cụm sân của đối tác</h3>
          <span>{{ application.partner_applications?.length || 0 }} cụm sân</span>
        </div>

        <div class="cluster-list">
          <article v-for="item in application.partner_applications || []" :key="item.id" class="cluster-row" :class="{ current: item.id === application.id }">
            <div>
              <strong>{{ item.venue_name }}</strong>
              <p>{{ item.venue_address || '-' }}</p>
              <small>{{ statusLabel(item.status) }} · {{ contractStatusLabel(item.contract_status) }} · {{ item.courts_count || 0 }} sân</small>
            </div>
            <button class="btn ghost small" type="button" :disabled="item.id === application.id" @click="openSiblingApplication(item)">
              Xem hồ sơ
            </button>
          </article>
        </div>

        <div class="card-head">
          <h3>Sân quản lý</h3>
          <span>{{ application.courts?.length || 0 }} sân</span>
        </div>

        <div class="court-grid">
          <article v-for="court in application.courts || []" :key="court.id || court.name" class="court-card">
            <strong>{{ court.name }}</strong>
            <span>{{ courtTypeName(court) }}</span>
            <p v-if="court.note">{{ court.note }}</p>
          </article>
        </div>

        <p v-if="!application.courts?.length" class="empty-text">Hồ sơ chưa có sân con.</p>
      </section>

      <section v-if="activeTab === 'documents'" class="documents-grid">
        <article class="content-card">
          <div class="card-head">
            <h3>Văn bản hệ thống</h3>
            <span>{{ generatedDocuments.length }} văn bản</span>
          </div>

          <div class="doc-list">
            <div v-for="group in generatedDocumentGroups" :key="group.key" class="doc-group">
              <div class="doc-group-head">
                <strong>{{ group.label }}</strong>
                <span>{{ group.documents.length }} văn bản</span>
              </div>
              <div v-for="document in group.documents" :key="document.id" class="doc-row">
                <div>
                  <strong>{{ document.title || documentTypeLabel(document.document_type) }}</strong>
                  <p>{{ document.document_code }} · {{ documentStatusLabel(document.status) }} · {{ signatureSummary(document.signatures) }}</p>
                </div>
                <div class="row-actions">
                  <button class="btn primary small icon-only" title="Xem" type="button" @click="openDocument(document)">
                    <AppIcon name="eye" size="15" />
                  </button>
                  <button class="btn ghost small icon-only" title="Tải xuống" type="button" @click="downloadGeneratedDocument(document)">
                    <AppIcon name="download" size="15" />
                  </button>
                </div>
              </div>
            </div>
            <p v-if="!generatedDocuments.length" class="empty-text">Chưa có văn bản hệ thống.</p>
          </div>
        </article>

        <article class="content-card">
          <div class="card-head">
            <h3>Tài liệu phụ lục</h3>
            <span>{{ uploadedDocuments.length }} file</span>
          </div>

          <div class="doc-list">
            <div v-for="group in uploadedDocumentGroups" :key="group.key" class="doc-group">
              <div class="doc-group-head">
                <strong>{{ group.label }}</strong>
                <span>{{ group.documents.length }} file</span>
              </div>
              <div v-for="document in group.documents" :key="document.id" class="doc-row">
                <div>
                  <strong>{{ document.title || uploadedTypeLabel(document.document_type) }}</strong>
                  <p>{{ document.file_name || uploadedTypeLabel(document.document_type) }} · {{ fileSize(document.file_size) }}</p>
                </div>
                <div class="row-actions">
                  <button class="btn primary small icon-only" title="Xem" type="button" @click="openDocument(document, 'uploaded')">
                    <AppIcon name="eye" size="15" />
                  </button>
                  <button class="btn ghost small icon-only" title="Tải xuống" type="button" @click="downloadUploadedDocument(document)">
                    <AppIcon name="download" size="15" />
                  </button>
                </div>
              </div>
            </div>
            <p v-if="!uploadedDocuments.length" class="empty-text">Chưa có tài liệu phụ lục.</p>
          </div>
        </article>
      </section>

      <section v-if="activeTab === 'signing'" class="content-card">
        <div class="card-head">
          <h3>Nhật ký ký số / OTP</h3>
          <span>{{ signingLogs.length }} giao dịch</span>
        </div>

        <div class="signing-log-list">
          <article v-for="log in signingLogs" :key="log.id" class="signing-log-row">
            <div>
              <strong>{{ log.document_title }}</strong>
              <p>{{ signingSideLabel(log.signer_side) }} · {{ log.otpStatusLabel }} · {{ log.otp_identifier || '-' }}</p>
              <dl>
                <dt>Mã tham chiếu OTP</dt>
                <dd>{{ log.otp_reference || '-' }}</dd>
                <dt>Gửi OTP</dt>
                <dd>{{ formatDate(log.otp_sent_at) }}</dd>
                <dt>Xác thực OTP</dt>
                <dd>{{ formatDate(log.otp_verified_at) }}</dd>
                <dt>Lần nhập</dt>
                <dd>{{ log.attempt_count ?? 0 }}/{{ log.max_attempts ?? '-' }}</dd>
                <dt>IP</dt>
                <dd>{{ log.ip_address || '-' }}</dd>
                <dt>Thiết bị</dt>
                <dd>{{ log.device || '-' }}</dd>
                <dt>Hash file</dt>
                <dd>{{ log.hash_short || '-' }}</dd>
                <dt>Vị trí chữ ký</dt>
                <dd>{{ log.signature_position || '-' }}</dd>
                <dt>User agent</dt>
                <dd>{{ log.user_agent || '-' }}</dd>
              </dl>
            </div>
          </article>
          <p v-if="!signingLogs.length" class="empty-text">Chưa có giao dịch OTP/ký số nào.</p>
        </div>
      </section>

      <section v-if="activeTab === 'history'" class="content-card">
        <div class="card-head">
          <h3>Lịch sử xử lý</h3>
          <span>{{ application.status_histories?.length || 0 }} mốc</span>
        </div>

        <div class="timeline">
          <article v-for="item in application.status_histories || []" :key="`${item.new_status}-${item.created_at}`" class="timeline-row">
            <span></span>
            <div>
              <strong>{{ statusLabel(item.new_status) }}</strong>
              <p>{{ formatDate(item.created_at) }} · {{ item.changed_by?.full_name || item.actor_type || '-' }}</p>
              <p v-if="item.reason">{{ item.reason }}</p>
            </div>
          </article>
          <p v-if="!application.status_histories?.length" class="empty-text">Chưa có lịch sử xử lý.</p>
        </div>
      </section>

      <section v-if="activeTab === 'settlement'" class="content-card">
        <div class="card-head">
          <h3>Quyết toán và chấm dứt</h3>
          <div class="head-actions">
            <span>{{ activeTerminationRequests.length }} đang xử lý</span>
            <button
              v-if="closedTerminationRequests.length"
              class="btn ghost small"
              type="button"
              @click="showClosedTerminations = !showClosedTerminations"
            >
              {{ showClosedTerminations ? 'Ẩn lịch sử' : `Lịch sử (${closedTerminationRequests.length})` }}
            </button>
            <button
              v-if="activeContract && !pendingTerminationRequest"
              class="btn danger small"
              type="button"
              @click="openTerminationDraftModal"
            >
              <AppIcon name="xCircle" size="14" /> Đơn phương chấm dứt
            </button>
          </div>
        </div>

        <div class="termination-list">
          <article v-for="request in displayedTerminationRequests" :key="request.id" class="termination-card">
            <div class="term-header">
              <div>
                <strong>{{ request.termination_code || 'Yêu cầu chấm dứt' }}</strong>
                <span class="badge" :class="`badge-${request.status}`">{{ terminationStatusLabel(request.status) }}</span>
              </div>
            </div>

            <div class="termination-next-action">
              <strong>{{ terminationNextAction(request).title }}</strong>
              <p>{{ terminationNextAction(request).description }}</p>
              <div class="termination-task-actions">
                <button
                  v-if="canConfirmTerminationRequest(request)"
                  class="btn primary small"
                  type="button"
                  :disabled="saving"
                  @click="handleConfirmTermination(request)"
                >
                  <AppIcon name="check" size="14" /> Xác nhận yêu cầu
                </button>
                <button
                  v-if="isUnilateralNotice(request) && isTerminationDraftStatus(request.status)"
                  class="btn primary small"
                  type="button"
                  :disabled="saving"
                  @click="openUnilateralNotice(request)"
                >
                  <AppIcon name="pencil" size="14" /> Xem và ký công văn
                </button>
                <button
                  v-if="canWithdrawUnilateralNotice(request)"
                  class="btn ghost small"
                  type="button"
                  :disabled="saving"
                  @click="openTerminationInlineAction(request, 'withdraw')"
                >
                  Thu hồi công văn
                </button>
                <button
                  v-if="hasPendingReconsideration(request)"
                  class="btn ghost small"
                  type="button"
                  :disabled="saving"
                  @click="openTerminationInlineAction(request, 'keep')"
                >
                  Giữ nguyên sau xem xét
                </button>
              </div>
              <div class="termination-progress">
                <span v-for="step in terminationAdminSteps(request)" :key="step.key" :class="step.state">
                  {{ step.label }}
                </span>
              </div>
            </div>

            <div class="term-info-grid">
              <div><span class="label">Loại</span><span>{{ terminationTypeLabel(request.termination_type) }}</span></div>
              <div><span class="label">Ngày yêu cầu</span><span>{{ formatDate(request.requested_at) }}</span></div>
              <div><span class="label">Ngày duyệt</span><span>{{ formatDate(request.approved_at) }}</span></div>
              <div><span class="label">Ngày thu hồi quyền</span><span>{{ formatDate(request.transition_end_at) }}</span></div>
              <div v-if="isUnilateralNotice(request)"><span class="label">Chủ sân xác nhận nhận</span><span>{{ formatDate(request.workflow_state?.owner_acknowledged_at) }}</span></div>
              <div class="full"><span class="label">Lý do</span><span>{{ request.reason || '-' }}</span></div>
            </div>

            <div v-if="hasPendingReconsideration(request)" class="reconsideration-alert">
              <strong>Chủ sân yêu cầu xem xét lại</strong>
              <p>{{ request.workflow_state?.latest_reconsideration_reason }}</p>
            </div>

            <form
              v-if="terminationInlineAction.requestId === request.id"
              class="termination-inline-form"
              @submit.prevent="submitTerminationInlineAction(request)"
            >
              <label class="field">
                <span>{{ terminationInlineAction.type === 'withdraw' ? 'Lý do thu hồi công văn' : 'Phản hồi giữ nguyên công văn' }}</span>
                <textarea v-model.trim="terminationInlineAction.note" rows="3" minlength="10" maxlength="1000" required></textarea>
              </label>
              <div class="actions compact">
                <button class="btn ghost small" type="button" @click="closeTerminationInlineAction">Đóng</button>
                <button class="btn primary small" type="submit" :disabled="saving || terminationInlineAction.note.length < 10">
                  {{ terminationInlineAction.type === 'withdraw' ? 'Xác nhận thu hồi' : 'Gửi phản hồi' }}
                </button>
              </div>
            </form>

            <div class="termination-finance-grid">
              <div><span class="label">Booking tương lai</span><strong>{{ terminationFinancial(request, 'future_booking_count') }}</strong></div>
              <div><span class="label">Có thể rút</span><strong class="amount positive">{{ money(terminationFinancial(request, 'withdrawable_amount')) }}</strong></div>
              <div><span class="label">Nợ booking online</span><strong class="amount negative">{{ money(terminationFinancial(request, 'future_online_booking_liability')) }}</strong></div>
              <div><span class="label">Hoàn/rút tiền treo</span><strong>{{ money(Number(terminationFinancial(request, 'pending_refund_liability')) + Number(terminationFinancial(request, 'pending_withdrawal_amount'))) }}</strong></div>
            </div>

            <div class="termination-actions">
              <button
                v-if="canPrepareFinalDocument(request)"
                class="btn primary small"
                type="button"
                :disabled="saving"
                @click="handleMarkTerminationReady(request)"
              >
                <AppIcon name="fileText" size="14" /> Xác nhận thủ công & sinh biên bản
              </button>
              <button
                v-if="isTerminationFinalSignatureStatus(request.status)"
                class="btn primary small"
                type="button"
                :disabled="saving"
                @click="handlePreviewFinalDocument(request)"
              >
                <AppIcon name="eye" size="14" />
                {{ terminationFinalAdminSigned(request) ? 'Xem biên bản cuối' : 'Mở file và ký biên bản' }}
              </button>
            </div>

            <div v-if="request.booking_actions?.length" class="settlement-box">
              <h4>Booking tương lai</h4>
              <div v-for="action in request.booking_actions" :key="action.id" class="withdrawal-row">
                <span>{{ action.booking?.booking_code || action.booking_id }}</span>
                <span>{{ bookingActionLabel(action.action) }}</span>
                <span class="badge" :class="`badge-${action.status}`">{{ action.status }}</span>
                <button
                  v-if="action.status !== 'resolved'"
                  class="btn ghost small"
                  type="button"
                  :disabled="saving"
                  @click="handleManualResolveBooking(request, action)"
                >
                  Xử lý xong
                </button>
              </div>
            </div>

            <!-- Settlement Details -->
            <div v-if="request.settlement" class="settlement-box">
              <h4>Quyết toán</h4>
              <div class="term-info-grid">
                <div><span class="label">Mã quyết toán</span><span>{{ request.settlement.settlement_code }}</span></div>
                <div><span class="label">Trạng thái</span><span>{{ settlementStatusLabel(request.settlement.status) }}</span></div>
                <div><span class="label">Tổng chi trả chủ sân</span><span class="amount positive">{{ money(request.settlement.final_payable_to_owner) }}</span></div>
                <div><span class="label">Phí chưa thanh toán</span><span class="amount negative">{{ money(request.settlement.unpaid_platform_fee_amount) }}</span></div>
              </div>

              <table v-if="request.settlement.items?.length" class="settlement-table">
                <thead>
                  <tr><th>Hạng mục</th><th>Hướng</th><th>Số tiền</th></tr>
                </thead>
                <tbody>
                  <tr v-for="item in request.settlement.items" :key="item.id">
                    <td>{{ item.description }}</td>
                    <td>{{ item.direction === 'payable_to_owner' ? 'Trả chủ sân' : 'Thu từ chủ sân' }}</td>
                    <td class="amount" :class="item.direction === 'payable_to_owner' ? 'positive' : 'negative'">{{ money(item.amount) }}</td>
                  </tr>
                </tbody>
              </table>

              <!-- Withdrawal Requests -->
              <div v-if="request.settlement.withdrawal_requests?.length" class="withdrawal-section">
                <h5>Lệnh rút tiền</h5>
                <div v-for="wd in request.settlement.withdrawal_requests" :key="wd.id" class="withdrawal-row">
                  <span>{{ money(wd.amount) }}</span>
                  <span class="badge" :class="`badge-${wd.status}`">{{ withdrawalStatusLabel(wd.status) }}</span>
                  <span class="muted">{{ formatDate(wd.created_at) }}</span>
                </div>
              </div>
            </div>

            <!-- Termination Documents -->
            <div v-if="request.documents?.length" class="term-documents">
              <h4>Tài liệu thanh lý</h4>
              <div v-for="doc in request.documents" :key="doc.id" class="doc-row">
                <div>
                  <strong>{{ documentTypeLabel(doc.document_type) }}</strong>
                  <p class="muted">{{ formatDate(doc.generated_at) }}</p>
                </div>
                <div class="row-actions">
                  <button v-if="doc.generated_document" class="btn ghost small" type="button" @click="openDocument(doc.generated_document)">
                    <AppIcon name="eye" size="14" /> Xem
                  </button>
                  <button v-if="doc.generated_document" class="btn ghost small" type="button" @click="downloadGeneratedDocument(doc.generated_document)">
                    <AppIcon name="download" size="14" /> Tải
                  </button>
                </div>
              </div>
            </div>
          </article>

          <p v-if="!displayedTerminationRequests.length" class="empty-text">
            {{ closedTerminationRequests.length ? 'Không có hồ sơ chấm dứt đang xử lý. Mở lịch sử để xem hồ sơ đã kết thúc.' : 'Chưa có yêu cầu chấm dứt hoặc quyết toán.' }}
          </p>
        </div>
      </section>

      <!-- Modal đơn phương chấm dứt -->
      <div v-if="terminationModal.open" class="modal-backdrop" @click.self="terminationModal.open = false">
        <form class="modal-panel" @submit.prevent="handleUnilateralTermination">
          <div class="modal-head">
            <h3>Đơn phương chấm dứt hợp đồng</h3>
            <button class="icon-btn" type="button" @click="terminationModal.open = false">
              <AppIcon name="x" size="18" />
            </button>
          </div>
          <div class="modal-body">
            <p class="muted">Bước này chỉ tạo bản xem trước. Công văn chưa gửi và cụm sân chưa bị khóa cho đến khi admin xem file, ký và xác thực OTP.</p>
            <label class="field">
              <span>Lý do chấm dứt</span>
              <textarea v-model.trim="terminationModal.reason" rows="4" required maxlength="2000" placeholder="Nhập lý do chấm dứt..."></textarea>
            </label>
            <label class="field">
              <span>Mô tả chi tiết</span>
              <textarea v-model.trim="terminationModal.detail_reason" rows="4" maxlength="5000" placeholder="Nêu căn cứ, sự kiện và dữ liệu cần đối tác biết"></textarea>
            </label>
            <div class="modal-form-grid">
              <label class="field">
                <span>Ngày dự kiến hoàn tất</span>
                <input v-model="terminationModal.requested_effective_date" type="date" required />
              </label>
              <label class="field">
                <span>Phương án booking đang tồn tại</span>
                <select v-model="terminationModal.future_booking_policy" required>
                  <option value="manual_per_booking">Xử lý thủ công từng booking</option>
                  <option value="serve_until_last_booking">Tiếp tục phục vụ đến booking cuối</option>
                  <option value="cancel_all_refund_to_user_balance">Hủy và hoàn tiền toàn bộ</option>
                </select>
              </label>
            </div>
          </div>
          <div class="modal-foot">
            <button class="btn ghost" type="button" @click="terminationModal.open = false">Hủy</button>
            <button class="btn primary" type="submit" :disabled="saving">{{ saving ? 'Đang tạo file...' : 'Tạo bản xem trước' }}</button>
          </div>
        </form>
      </div>

    </template>

    <DocumentViewerModal :show="unilateralNoticeModal.open" :document="unilateralNoticeModal.document" @close="closeUnilateralNotice">
      <template #actions>
        <div class="unilateral-sign-panel">
          <div>
            <strong>Ký và phát hành công văn</strong>
            <p class="muted">Kiểm tra toàn bộ file bên trái. Sau khi OTP hợp lệ, hệ thống mới gửi công văn và khóa cụm sân nhận booking mới.</p>
          </div>
          <canvas
            ref="unilateralNoticeCanvas"
            class="termination-signature-pad"
            width="520"
            height="150"
            @pointerdown="startUnilateralNoticeSignature"
            @pointermove="drawUnilateralNoticeSignature"
            @pointerup="stopUnilateralNoticeSignature"
            @pointerleave="stopUnilateralNoticeSignature"
          ></canvas>
          <label class="admin-sign-confirm">
            <input v-model="unilateralNoticeModal.accepted" type="checkbox" />
            <span>Tôi xác nhận đại diện SportGo đã kiểm tra đúng văn bản đang hiển thị và đồng ý phát hành.</span>
          </label>
          <div v-if="!unilateralNoticeModal.signingRequestId" class="actions compact">
            <button class="btn ghost small" type="button" @click="prepareUnilateralNoticeSignature">Ký lại</button>
            <button
              class="btn primary small"
              type="button"
              :disabled="saving || unilateralNoticeSignatureEmpty || !unilateralNoticeModal.accepted"
              @click="sendUnilateralNoticeOtp"
            >
              Gửi OTP ký công văn
            </button>
          </div>
          <div v-else class="unilateral-otp-box">
            <p>Mã đối soát file: <strong>{{ unilateralNoticeModal.hashShort }}</strong></p>
            <label class="field">
              <span>OTP email admin</span>
              <input v-model.trim="unilateralNoticeModal.otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code" />
            </label>
            <button class="btn primary small" type="button" :disabled="saving || unilateralNoticeModal.otp.length !== 6" @click="signAndIssueUnilateralNotice">
              Ký và gửi công văn
            </button>
          </div>
        </div>
      </template>
    </DocumentViewerModal>

    <DocumentViewerModal :show="terminationFinalModal.open" :document="terminationFinalModal.document" @close="closeTerminationFinalModal">
      <template #actions>
        <div v-if="terminationFinalModal.request && !terminationFinalAdminSigned(terminationFinalModal.request)" class="unilateral-sign-panel">
          <div>
            <strong>Ký biên bản chấm dứt cuối</strong>
            <p class="muted">Kiểm tra toàn bộ file bên trái. SportGo ký trước; chủ sân chỉ được ký sau khi chữ ký này được lưu thành công.</p>
          </div>
          <canvas
            ref="terminationFinalCanvas"
            class="termination-signature-pad"
            width="520"
            height="150"
            @pointerdown="startTerminationFinalSignature"
            @pointermove="drawTerminationFinalSignature"
            @pointerup="stopTerminationFinalSignature"
            @pointerleave="stopTerminationFinalSignature"
          ></canvas>
          <label class="admin-sign-confirm">
            <input v-model="terminationFinalSigning.accepted" type="checkbox" />
            <span>Tôi xác nhận đại diện SportGo đã kiểm tra đúng biên bản đang hiển thị và đồng ý ký.</span>
          </label>
          <div v-if="!terminationFinalSigning.requestId" class="actions compact">
            <button class="btn ghost small" type="button" @click="clearTerminationFinalSignature">Ký lại</button>
            <button
              class="btn primary small"
              type="button"
              :disabled="saving || terminationFinalSignatureEmpty || !terminationFinalSigning.accepted"
              @click="handleSendTerminationFinalOtp(terminationFinalModal.request)"
            >
              Gửi OTP ký biên bản
            </button>
          </div>
          <div v-else class="unilateral-otp-box">
            <p>Mã đối soát file: <strong>{{ terminationFinalSigning.hashShort }}</strong></p>
            <label class="field">
              <span>OTP email admin</span>
              <input v-model.trim="terminationFinalSigning.otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code" />
            </label>
            <button
              class="btn primary small"
              type="button"
              :disabled="saving || terminationFinalSigning.otp.length !== 6"
              @click="handleSignTerminationFinalDocument(terminationFinalModal.request)"
            >
              Ký biên bản
            </button>
          </div>
        </div>
      </template>
    </DocumentViewerModal>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, defineComponent, h, watch, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '../../components/AppIcon.vue';
import DocumentViewerModal from '../../components/DocumentViewerModal.vue';
import { adminPartnerApplicationService } from '../../services/adminPartnerApplications.js';

const route = useRoute();
const router = useRouter();

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const message = ref('');
const actionError = ref('');
const application = ref(null);
const courtTypes = ref([]);
const detailTabs = ['overview', 'courts', 'documents', 'signing', 'history', 'settlement'];
const activeTab = ref(detailTabs.includes(String(route.query.tab)) ? String(route.query.tab) : 'overview');
const actionMode = ref(route.query.action || '');
const fieldErrors = reactive({});
const terminationModal = reactive({
  open: false,
  reason: '',
  detail_reason: '',
  requested_effective_date: '',
  future_booking_policy: 'manual_per_booking',
});
const terminationInlineAction = reactive({ requestId: null, type: '', note: '' });
const showClosedTerminations = ref(false);
const unilateralNoticeModal = reactive({
  open: false,
  requestId: null,
  document: null,
  signingRequestId: null,
  hashShort: '',
  otp: '',
  accepted: false,
});
const unilateralNoticeCanvas = ref(null);
const unilateralNoticeDrawing = ref(false);
const unilateralNoticeSignatureEmpty = ref(true);
const terminationFinalModal = reactive({ open: false, request: null, document: null });
const terminationFinalSigning = reactive({ requestIdFor: null, requestId: null, hashShort: '', otp: '', accepted: false });
const terminationFinalCanvas = ref(null);
const terminationFinalDrawing = ref(false);
const terminationFinalSignatureEmpty = ref(true);
const TERMINATING_STATUSES = [
  'draft',
  'submitted',
  'reviewing',
  'settlement_processing',
  'settlement_completed',
  'pending_signature',
  'transition_period',
  'draft_preview',
  'cancellation_in_progress',
  'future_bookings_processing',
  'waiting_final_settlement',
  'waiting_final_document_signature',
  'terminating',
];
const approveForm = reactive({
  initial_court_name: '',
  court_type_id: '',
  review_note: '',
});

const rejectForm = reactive({
  reason: '',
});

const tabs = [
  { value: 'overview', label: 'Tổng quan' },
  { value: 'courts', label: 'Sân quản lý' },
  { value: 'documents', label: 'Tài liệu & văn bản' },
  { value: 'signing', label: 'Nhật ký ký số / OTP' },
  { value: 'history', label: 'Lịch sử' },
  { value: 'settlement', label: 'Hủy/chấm dứt & quyết toán' },
];

const generatedDocuments = computed(() => (application.value?.documents || []).filter(d => d.source !== 'uploaded'));
const uploadedDocuments = computed(() => application.value?.uploaded_documents || []);
const generatedDocumentGroups = computed(() => groupDocuments(generatedDocuments.value, generatedDocumentGroupMeta));
const uploadedDocumentGroups = computed(() => groupDocuments(uploadedDocuments.value, uploadedDocumentGroupMeta));
const signingLogs = computed(() => generatedDocuments.value.flatMap((document) => (
  (document.signing_requests || []).map((log) => ({
    ...log,
    document_title: document.title || documentTypeLabel(document.document_type),
    otpStatusLabel: signingStatusLabel(log.status || log.otp_status),
  }))
)));
const requiresInitialCourt = computed(() => !(application.value?.courts || []).length);
const leafCourtTypes = computed(() => courtTypes.value.filter((type) => type.is_active !== false && Number(type.children_count || 0) === 0));
const pendingSportgoDocument = computed(() => generatedDocuments.value.find((document) => (
  ['partner_contract', 'venue_scale_appendix', 'venue_location_appendix'].includes(document.document_type)
    && document.status === 'pending_sportgo_signature'
)) || null);
const canReviewApplication = computed(() => isReviewable(application.value?.status));
const activeContract = computed(() => (application.value?.contracts || []).find(c => c.status === 'signed_active') || null);
const activeTerminationRequests = computed(() => (application.value?.termination_requests || []).filter((request) => TERMINATING_STATUSES.includes(request.status)));
const closedTerminationRequests = computed(() => (application.value?.termination_requests || []).filter((request) => !TERMINATING_STATUSES.includes(request.status)));
const displayedTerminationRequests = computed(() => (
  showClosedTerminations.value
    ? [...activeTerminationRequests.value, ...closedTerminationRequests.value]
    : activeTerminationRequests.value
));
const pendingTerminationRequest = computed(() => activeTerminationRequests.value[0] || null);
const readonlyActionTitle = computed(() => {
  if (application.value?.status === 'need_supplement') return 'Đang chờ người dùng bổ sung';
  if (application.value?.status === 'rejected') return 'Hồ sơ đã bị từ chối';
  if (application.value?.status === 'cancelled') return 'Hồ sơ đã hủy';
  if (application.value?.status === 'completed') return 'Hồ sơ đã hoàn tất';
  return 'Không có thao tác xét duyệt';
});
const readonlyActionText = computed(() => {
  if (application.value?.status === 'need_supplement') return 'Admin đã gửi yêu cầu bổ sung. Chỉ xử lý tiếp sau khi người dùng cập nhật hồ sơ và ký lại đơn đăng ký.';
  if (application.value?.status === 'rejected') return 'Hồ sơ đã dừng ở trạng thái từ chối. Người dùng có thể tạo bản sao hồ sơ để đăng ký lại.';
  if (application.value?.status === 'cancelled') return 'Hồ sơ đã bị hủy và không thể duyệt.';
  if (application.value?.status === 'completed') return 'Hồ sơ đã hoàn tất ký hợp đồng và kích hoạt đối tác.';
  return 'Trạng thái hiện tại không cho phép duyệt, từ chối hoặc yêu cầu bổ sung.';
});

onMounted(async () => {
  await Promise.all([loadApplication(), loadCourtTypes()]);
  prepareTerminationFinalSignature();
});

watch(() => route.params.id, () => {
  clearAction();
  loadApplication();
});

watch(() => route.query.action, (action) => {
  actionMode.value = canReviewApplication.value ? (action || '') : '';
});

watch(() => route.query.tab, (tab) => {
  if (detailTabs.includes(String(tab))) activeTab.value = String(tab);
});

async function loadApplication() {
  loading.value = true;
  error.value = '';

  try {
    const response = await adminPartnerApplicationService.show(route.params.id);
    application.value = response.data;
    if (!isReviewable(application.value?.status) && ['approve', 'reject', 'supplement'].includes(actionMode.value)) {
      actionMode.value = '';
    }
    await nextTick();
    prepareTerminationFinalSignature();
  } catch (err) {
    error.value = err.message || 'Không tải được hồ sơ đối tác.';
  } finally {
    loading.value = false;
  }
}

async function loadCourtTypes() {
  try {
    const response = await adminPartnerApplicationService.courtTypes();
    courtTypes.value = response.data || [];
  } catch {
    courtTypes.value = [];
  }
}

function openDocument(doc, type = 'generated') {
  router.push({
    name: 'admin-partner-application-document',
    params: { id: application.value.id, documentId: doc.id },
    query: type === 'uploaded' ? { type: 'uploaded' } : {},
  });
}

function openSiblingApplication(item) {
  if (!item?.id || item.id === application.value?.id) return;
  router.push({ name: 'admin-partner-application-detail', params: { id: item.id } });
}

async function downloadGeneratedDocument(document) {
  if (!document?.id) return;
  clearAlerts();
  try {
    await adminPartnerApplicationService.downloadDocument(document.id);
  } catch (err) {
    error.value = err.message || 'Không tải được văn bản.';
  }
}

async function downloadUploadedDocument(document) {
  if (!document?.id) return;
  clearAlerts();
  try {
    await adminPartnerApplicationService.downloadUploadedDocument(document.id);
  } catch (err) {
    error.value = err.message || 'Không tải được tài liệu phụ lục.';
  }
}

function clearAction() {
  actionMode.value = '';
  actionError.value = '';
  clearFieldErrors();
}

async function submitApprove() {
  clearAlerts();
  if (!validateApprove()) return;

  saving.value = true;
  try {
    const response = await adminPartnerApplicationService.approve(application.value.id, {
      initial_court_name: approveForm.initial_court_name,
      court_type_id: approveForm.court_type_id,
      review_note: approveForm.review_note,
    });
    message.value = response.message || 'Đã duyệt hồ sơ và tạo hợp đồng.';
    application.value = response.data;
    clearAction();
  } catch (err) {
    applyActionError(err, 'Không duyệt được hồ sơ.');
  } finally {
    saving.value = false;
  }
}

async function submitReject() {
  clearAlerts();
  clearFieldErrors();

  if (!rejectForm.reason) {
    fieldErrors.reason = actionMode.value === 'supplement' ? 'Vui lòng nhập nội dung cần bổ sung.' : 'Vui lòng nhập lý do từ chối.';
    return;
  }

  saving.value = true;
  try {
    const response = await adminPartnerApplicationService.reject(application.value.id, {
      reason: rejectForm.reason,
      action_type: actionMode.value === 'supplement' ? 'need_supplement' : 'reject',
    });
    message.value = response.message || (actionMode.value === 'supplement' ? 'Đã yêu cầu bổ sung hồ sơ.' : 'Đã từ chối hồ sơ.');
    application.value = response.data;
    clearAction();
  } catch (err) {
    applyActionError(err, 'Không từ chối được hồ sơ.');
  } finally {
    saving.value = false;
  }
}

function validateApprove() {
  clearFieldErrors();

  if (requiresInitialCourt.value && !approveForm.initial_court_name) {
    fieldErrors.initial_court_name = 'Vui lòng nhập tên sân con ban đầu.';
  }

  if (requiresInitialCourt.value && !approveForm.court_type_id) {
    fieldErrors.court_type_id = 'Vui lòng chọn loại sân con.';
  }

  return !Object.keys(fieldErrors).length;
}

function clearAlerts() {
  message.value = '';
  error.value = '';
  actionError.value = '';
  clearFieldErrors();
}

function clearFieldErrors() {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key]);
}

function applyActionError(err, fallback) {
  const errors = err.data?.errors || {};
  Object.entries(errors).forEach(([key, value]) => {
    fieldErrors[key] = Array.isArray(value) ? value[0] : value;
  });
  actionError.value = err.message || fallback;
}

function isReviewable(status) {
  return ['pending', 'reviewing', 'submitted'].includes(status);
}

function terminationStatusIn(status, values) {
  return values.includes(status);
}

function terminationFinancial(request, key) {
  return request?.financial_summary?.[key] ?? request?.[key] ?? 0;
}

function isTerminationDraftStatus(status) {
  return terminationStatusIn(status, ['draft', 'draft_preview']);
}

function isTerminationSubmittedStatus(status) {
  return terminationStatusIn(status, ['submitted', 'cancellation_in_progress']);
}

function isTerminationFutureBookingStatus(status) {
  return terminationStatusIn(status, ['reviewing', 'future_bookings_processing']);
}

function isTerminationSettlementStatus(status) {
  return terminationStatusIn(status, ['settlement_processing', 'settlement_completed', 'waiting_final_settlement']);
}

function isTerminationFinalSignatureStatus(status) {
  return terminationStatusIn(status, ['pending_signature', 'waiting_final_document_signature']);
}

function isTerminationTransitionStatus(status) {
  return terminationStatusIn(status, ['transition_period', 'terminating']);
}

function isTerminationCompletedStatus(status) {
  return terminationStatusIn(status, ['completed', 'terminated']);
}

function isTerminationCancelledStatus(status) {
  return terminationStatusIn(status, ['cancelled', 'owner_cancelled_request']);
}

function isTerminationRejectedStatus(status) {
  return terminationStatusIn(status, ['rejected', 'admin_rejected']);
}

function canConfirmTerminationRequest(request) {
  return isTerminationSubmittedStatus(request.status) && request.termination_type !== 'unilateral_by_sportgo';
}

function isUnilateralNotice(request) {
  return request?.termination_type === 'unilateral_by_sportgo';
}

function hasPendingReconsideration(request) {
  return Boolean(isUnilateralNotice(request) && request.workflow_state?.reconsideration_pending);
}

function canWithdrawUnilateralNotice(request) {
  return Boolean(
    isUnilateralNotice(request)
    && ['draft', 'submitted', 'reviewing', 'settlement_processing'].includes(request.status)
    && !request.final_document_ready_at
    && !terminationFinalAdminSigned(request)
  );
}

function openTerminationDraftModal() {
  const defaultDate = new Date();
  defaultDate.setDate(defaultDate.getDate() + 30);
  terminationModal.open = true;
  terminationModal.reason = '';
  terminationModal.detail_reason = '';
  terminationModal.requested_effective_date = defaultDate.toISOString().slice(0, 10);
  terminationModal.future_booking_policy = 'manual_per_booking';
}

async function handleUnilateralTermination() {
  if (!terminationModal.reason) return;
  clearAlerts();
  saving.value = true;
  try {
    const response = await adminPartnerApplicationService.terminate(application.value.id, {
      reason: terminationModal.reason,
      detail_reason: terminationModal.detail_reason || null,
      requested_effective_date: terminationModal.requested_effective_date,
      future_booking_policy: terminationModal.future_booking_policy,
    });
    message.value = response.message || 'Đã tạo bản xem trước công văn.';
    terminationModal.open = false;
    await loadApplication();
    const request = (application.value?.termination_requests || []).find((item) => item.id === response.data?.id) || response.data;
    await openUnilateralNotice(request);
  } catch (err) {
    error.value = err.message || 'Không thể tạo bản xem trước công văn.';
  } finally {
    saving.value = false;
  }
}

function unilateralNoticeDocument(request) {
  const row = [...(request?.documents || [])]
    .reverse()
    .find((item) => ['unilateral_notice', 'unilateral_termination_notice'].includes(item.document_type));
  const document = row?.generated_document;
  if (!document?.id) return null;

  return {
    ...document,
    title: document.title || 'Công văn chấm dứt hợp tác',
    download_url: `/api/files/documents/${document.id}/download`,
  };
}

function terminationFinalDocument(request) {
  const row = [...(request?.documents || [])]
    .reverse()
    .find((item) => ['settlement_minutes', 'final_termination_file'].includes(item.document_type));
  const document = row?.generated_document;
  if (!document?.id) return null;

  return {
    ...document,
    title: document.title || 'Biên bản chấm dứt hợp tác cuối',
    download_url: `/api/files/documents/${document.id}/download`,
  };
}

function terminationFinalSignedBy(request, signerSide) {
  return (terminationFinalDocument(request)?.signatures || [])
    .some((signature) => signature.signer_side === signerSide && signature.status === 'signed');
}

function terminationFinalAdminSigned(request) {
  return Boolean(request?.final_document_admin_signed_at) || terminationFinalSignedBy(request, 'sportgo');
}

function terminationFinalOwnerSigned(request) {
  return Boolean(request?.final_document_owner_signed_at) || terminationFinalSignedBy(request, 'owner');
}

async function openUnilateralNotice(request) {
  const document = unilateralNoticeDocument(request);
  if (!document) {
    error.value = 'Không tìm thấy file công văn để xem và ký.';
    return;
  }

  unilateralNoticeModal.open = true;
  unilateralNoticeModal.requestId = request.id;
  unilateralNoticeModal.document = document;
  unilateralNoticeModal.signingRequestId = null;
  unilateralNoticeModal.hashShort = '';
  unilateralNoticeModal.otp = '';
  unilateralNoticeModal.accepted = false;
  await nextTick();
  prepareUnilateralNoticeSignature();
}

function closeUnilateralNotice() {
  unilateralNoticeModal.open = false;
  unilateralNoticeModal.requestId = null;
  unilateralNoticeModal.document = null;
  unilateralNoticeModal.signingRequestId = null;
  unilateralNoticeModal.hashShort = '';
  unilateralNoticeModal.otp = '';
  unilateralNoticeModal.accepted = false;
}

async function openTerminationFinalModal(request, generatedDocument = null) {
  const document = generatedDocument?.id
    ? {
      ...generatedDocument,
      title: generatedDocument.title || 'Biên bản chấm dứt hợp tác cuối',
      download_url: `/api/files/documents/${generatedDocument.id}/download`,
    }
    : terminationFinalDocument(request);

  if (!document) {
    error.value = 'Không tìm thấy file biên bản cuối để xem và ký.';
    return;
  }

  terminationFinalModal.open = true;
  terminationFinalModal.request = request;
  terminationFinalModal.document = document;
  terminationFinalSigning.requestIdFor = null;
  terminationFinalSigning.requestId = null;
  terminationFinalSigning.hashShort = '';
  terminationFinalSigning.otp = '';
  terminationFinalSigning.accepted = false;
  await nextTick();
  prepareTerminationFinalSignature();
}

function closeTerminationFinalModal() {
  terminationFinalModal.open = false;
  terminationFinalModal.request = null;
  terminationFinalModal.document = null;
  terminationFinalSigning.requestIdFor = null;
  terminationFinalSigning.requestId = null;
  terminationFinalSigning.hashShort = '';
  terminationFinalSigning.otp = '';
  terminationFinalSigning.accepted = false;
}

function prepareUnilateralNoticeSignature() {
  const canvas = unilateralNoticeCanvas.value;
  if (!canvas) return;
  const context = canvas.getContext('2d');
  context.fillStyle = '#fff';
  context.fillRect(0, 0, canvas.width, canvas.height);
  context.strokeStyle = '#0f172a';
  context.lineWidth = 2.4;
  context.lineCap = 'round';
  unilateralNoticeSignatureEmpty.value = true;
  unilateralNoticeModal.accepted = false;
  unilateralNoticeModal.signingRequestId = null;
  unilateralNoticeModal.hashShort = '';
  unilateralNoticeModal.otp = '';
}

function unilateralNoticePoint(event) {
  const canvas = unilateralNoticeCanvas.value;
  const rect = canvas.getBoundingClientRect();
  return {
    x: ((event.clientX - rect.left) / rect.width) * canvas.width,
    y: ((event.clientY - rect.top) / rect.height) * canvas.height,
  };
}

function startUnilateralNoticeSignature(event) {
  if (!unilateralNoticeCanvas.value) return;
  unilateralNoticeDrawing.value = true;
  unilateralNoticeSignatureEmpty.value = false;
  const context = unilateralNoticeCanvas.value.getContext('2d');
  const point = unilateralNoticePoint(event);
  context.beginPath();
  context.moveTo(point.x, point.y);
}

function drawUnilateralNoticeSignature(event) {
  if (!unilateralNoticeDrawing.value || !unilateralNoticeCanvas.value) return;
  const context = unilateralNoticeCanvas.value.getContext('2d');
  const point = unilateralNoticePoint(event);
  context.lineTo(point.x, point.y);
  context.stroke();
}

function stopUnilateralNoticeSignature() {
  unilateralNoticeDrawing.value = false;
}

async function sendUnilateralNoticeOtp() {
  clearAlerts();
  saving.value = true;
  try {
    const response = await adminPartnerApplicationService.sendUnilateralNoticeOtp(unilateralNoticeModal.requestId, {
      signature_image: unilateralNoticeCanvas.value?.toDataURL('image/png') || '',
    });
    unilateralNoticeModal.signingRequestId = response.data.signing_request_id;
    unilateralNoticeModal.hashShort = response.data.hash_short;
    unilateralNoticeModal.otp = '';
    message.value = response.message;
  } catch (err) {
    error.value = err.message || 'Không thể gửi OTP ký công văn.';
  } finally {
    saving.value = false;
  }
}

async function signAndIssueUnilateralNotice() {
  clearAlerts();
  saving.value = true;
  try {
    const response = await adminPartnerApplicationService.signUnilateralNotice(unilateralNoticeModal.requestId, {
      signing_request_id: unilateralNoticeModal.signingRequestId,
      otp: unilateralNoticeModal.otp,
    });
    message.value = response.message;
    closeUnilateralNotice();
    await loadApplication();
  } catch (err) {
    error.value = err.message || 'Không thể ký và gửi công văn.';
  } finally {
    saving.value = false;
  }
}

function openTerminationInlineAction(request, type) {
  terminationInlineAction.requestId = request.id;
  terminationInlineAction.type = type;
  terminationInlineAction.note = '';
}

function closeTerminationInlineAction() {
  terminationInlineAction.requestId = null;
  terminationInlineAction.type = '';
  terminationInlineAction.note = '';
}

async function submitTerminationInlineAction(request) {
  clearAlerts();
  saving.value = true;
  try {
    const response = terminationInlineAction.type === 'withdraw'
      ? await adminPartnerApplicationService.withdrawUnilateralNotice(request.id, { reason: terminationInlineAction.note })
      : await adminPartnerApplicationService.resolveUnilateralReconsideration(request.id, { note: terminationInlineAction.note });
    message.value = response.message;
    closeTerminationInlineAction();
    await loadApplication();
  } catch (err) {
    error.value = err.message || 'Không thể xử lý công văn.';
  } finally {
    saving.value = false;
  }
}

async function handleConfirmTermination(request) {
  if (!confirm('Xác nhận đồng ý thanh lý hợp đồng theo yêu cầu của đối tác?')) return;
  clearAlerts();
  saving.value = true;
  try {
    const response = await adminPartnerApplicationService.confirmTermination(application.value.id, { termination_request_id: request.id });
    message.value = response.message || 'Đã xác nhận thanh lý và tạo quyết toán.';
    await loadApplication();
  } catch (err) {
    error.value = err.message || 'Lỗi khi xác nhận thanh lý.';
  } finally {
    saving.value = false;
  }
}

function terminationNextAction(request) {
  if (isUnilateralNotice(request) && isTerminationDraftStatus(request.status)) {
    return {
      title: 'Cần admin xem và ký công văn',
      description: 'File mới là bản xem trước. Mở văn bản, ký và nhập OTP để phát hành; trước đó cụm sân chưa bị khóa bởi công văn này.',
    };
  }
  if (isUnilateralNotice(request) && isTerminationSubmittedStatus(request.status)) {
    return {
      title: 'Chờ chủ sân xác nhận đã nhận',
      description: 'Công văn đã ký và gửi, cụm sân đã dừng nhận booking mới. Chủ sân cần đọc file và xác nhận tiếp nhận.',
    };
  }
  if (isTerminationDraftStatus(request.status)) {
    return {
      title: 'Chờ chủ sân ký gửi yêu cầu',
      description: 'Chủ sân đã tạo bản xem trước nhưng chưa ký OTP gửi chính thức. Admin chưa cần xử lý cho đến khi đơn được gửi.',
    };
  }
  if (canConfirmTerminationRequest(request)) {
    return {
      title: 'Cần admin xác nhận yêu cầu',
      description: 'Kiểm tra đơn chủ sân đã ký, lý do chấm dứt và nghĩa vụ liên quan rồi xác nhận để bắt đầu xử lý thanh lý.',
    };
  }
  if (isTerminationFutureBookingStatus(request.status)) {
    return {
      title: 'Cần xử lý booking tương lai',
      description: 'Các booking chưa hoàn tất phải được hủy/hoàn tiền, phục vụ xong hoặc admin đánh dấu đã xử lý thủ công.',
    };
  }
  if (isTerminationSettlementStatus(request.status)) {
    return {
      title: 'Cần xác nhận quyết toán thủ công',
      description: 'Nếu booking và công nợ đã xử lý ngoài hệ thống, admin xác nhận thủ công để sinh biên bản chấm dứt cuối.',
    };
  }
  if (isTerminationFinalSignatureStatus(request.status) && !terminationFinalAdminSigned(request)) {
    return {
      title: 'Cần SportGo ký biên bản cuối',
      description: 'Ký OTP bằng tài khoản admin/SportGo trước, sau đó chủ sân mới ký xác nhận hoàn tất.',
    };
  }
  if (isTerminationFinalSignatureStatus(request.status) && terminationFinalAdminSigned(request) && !terminationFinalOwnerSigned(request)) {
    return {
      title: 'Chờ chủ sân ký biên bản cuối',
      description: 'SportGo đã ký. Chủ sân cần vào màn hồ sơ chấm dứt để ký OTP phần bên B.',
    };
  }
  if (isTerminationTransitionStatus(request.status)) {
    return {
      title: 'Đang trong thời gian xem hồ sơ',
      description: 'Hai bên đã ký biên bản cuối. Hệ thống sẽ thu hồi quyền chủ sân sau thời gian cấu hình.',
    };
  }
  if (isTerminationCompletedStatus(request.status)) {
    return {
      title: 'Đã chấm dứt hoàn tất',
      description: 'Hợp đồng đã kết thúc, cụm sân chuyển sang trạng thái chấm dứt hợp tác.',
    };
  }
  if (isTerminationCancelledStatus(request.status)) {
    if (isUnilateralNotice(request)) {
      return {
        title: 'SportGo đã thu hồi công văn',
        description: 'Cụm sân đã được mở lại nếu không còn khóa khác. File đã ký và lịch sử thu hồi vẫn được giữ để đối soát.',
      };
    }
    return {
      title: 'Chủ sân đã hủy yêu cầu',
      description: 'Yêu cầu dừng xử lý. Kiểm tra lịch sử nếu đã có thao tác thủ công phát sinh.',
    };
  }
  if (isTerminationRejectedStatus(request.status)) {
    return {
      title: 'Yêu cầu đã bị từ chối',
      description: 'Admin đã từ chối yêu cầu chấm dứt. Kiểm tra lý do và lịch sử xử lý trước khi tạo yêu cầu mới.',
    };
  }
  return {
    title: 'Theo dõi hồ sơ',
    description: 'Kiểm tra booking, công nợ, văn bản và chữ ký để chuyển hồ sơ sang bước tiếp theo.',
  };
}

function terminationAdminSteps(request) {
  const status = request.status;
  const adminSigned = terminationFinalAdminSigned(request);
  const ownerSigned = terminationFinalOwnerSigned(request);
  const bookingResolved = !Number(request.future_booking_count || 0) || isTerminationSettlementStatus(status) || isTerminationFinalSignatureStatus(status) || isTerminationTransitionStatus(status) || isTerminationCompletedStatus(status);
  const requestDone = !isTerminationDraftStatus(status) && !isTerminationSubmittedStatus(status);

  if (isUnilateralNotice(request)) {
    const acknowledged = Boolean(request.workflow_state?.owner_acknowledged_at);
    return [
      { key: 'notice', label: 'Ký công văn', state: isTerminationDraftStatus(status) ? 'current' : 'done' },
      { key: 'ack', label: 'Chủ sân xác nhận', state: isTerminationSubmittedStatus(status) ? 'current' : (acknowledged ? 'done' : 'pending') },
      { key: 'booking', label: 'Booking', state: isTerminationFutureBookingStatus(status) ? 'current' : (bookingResolved ? 'done' : 'pending') },
      { key: 'settlement', label: 'Đối soát', state: isTerminationSettlementStatus(status) ? 'current' : ((isTerminationFinalSignatureStatus(status) || isTerminationTransitionStatus(status) || isTerminationCompletedStatus(status)) ? 'done' : 'pending') },
      { key: 'final', label: 'Biên bản cuối', state: (adminSigned || ownerSigned) ? 'current' : ((isTerminationTransitionStatus(status) || isTerminationCompletedStatus(status)) ? 'done' : 'pending') },
    ];
  }

  return [
    { key: 'request', label: 'Duyệt yêu cầu', state: isTerminationDraftStatus(status) || isTerminationSubmittedStatus(status) ? 'current' : (requestDone ? 'done' : 'pending') },
    { key: 'booking', label: 'Booking', state: isTerminationFutureBookingStatus(status) ? 'current' : (bookingResolved ? 'done' : 'pending') },
    { key: 'settlement', label: 'Quyết toán', state: isTerminationSettlementStatus(status) ? 'current' : ((isTerminationFinalSignatureStatus(status) || isTerminationTransitionStatus(status) || isTerminationCompletedStatus(status)) ? 'done' : 'pending') },
    { key: 'admin-sign', label: 'SportGo ký', state: adminSigned ? 'done' : (isTerminationFinalSignatureStatus(status) ? 'current' : 'pending') },
    { key: 'owner-sign', label: 'Chủ sân ký', state: ownerSigned ? 'done' : (adminSigned && isTerminationFinalSignatureStatus(status) ? 'current' : 'pending') },
  ];
}

function canPrepareFinalDocument(request) {
  return isTerminationSettlementStatus(request.status);
}

async function handleMarkTerminationReady(request) {
  if (!confirm('Xác nhận booking/công nợ đã xử lý thủ công và sinh biên bản chấm dứt cuối?')) return;
  clearAlerts();
  saving.value = true;
  try {
    const response = await adminPartnerApplicationService.markTerminationReady(request.id, {
      note: 'Admin xác nhận thủ công booking/công nợ đã xử lý và sinh biên bản chấm dứt cuối.',
    });
    message.value = response.message || 'Đã xác nhận thủ công và sinh biên bản chấm dứt cuối.';
    await loadApplication();
    const refreshedRequest = (application.value?.termination_requests || [])
      .find((item) => item.id === request.id) || response.data;
    await openTerminationFinalModal(refreshedRequest);
  } catch (err) {
    error.value = err.message || 'Không thể sinh biên bản chấm dứt cuối.';
  } finally {
    saving.value = false;
  }
}

async function handlePreviewFinalDocument(request) {
  clearAlerts();
  saving.value = true;
  try {
    const response = await adminPartnerApplicationService.previewTerminationFinalDocument(request.id);
    message.value = terminationFinalAdminSigned(request)
      ? 'Đã mở biên bản chấm dứt cuối.'
      : (response.message || 'Đã mở biên bản chấm dứt cuối để SportGo ký.');
    await loadApplication();
    const refreshedRequest = (application.value?.termination_requests || [])
      .find((item) => item.id === request.id) || request;
    await openTerminationFinalModal(refreshedRequest, response.data);
  } catch (err) {
    error.value = err.message || 'Không thể làm mới biên bản chấm dứt cuối.';
  } finally {
    saving.value = false;
  }
}

function prepareTerminationFinalSignature() {
  const canvas = terminationFinalCanvas.value;
  if (!canvas) return;

  const context = canvas.getContext('2d');
  context.fillStyle = '#fff';
  context.fillRect(0, 0, canvas.width, canvas.height);
  context.strokeStyle = '#0f172a';
  context.lineWidth = 2.4;
  context.lineCap = 'round';
  terminationFinalSignatureEmpty.value = true;
}

function terminationSignaturePoint(event) {
  const canvas = terminationFinalCanvas.value;
  const rect = canvas.getBoundingClientRect();
  return {
    x: ((event.clientX - rect.left) / rect.width) * canvas.width,
    y: ((event.clientY - rect.top) / rect.height) * canvas.height,
  };
}

function startTerminationFinalSignature(event) {
  const canvas = terminationFinalCanvas.value;
  if (!canvas) return;

  terminationFinalDrawing.value = true;
  terminationFinalSignatureEmpty.value = false;
  terminationFinalSigning.requestIdFor = null;
  terminationFinalSigning.requestId = null;
  terminationFinalSigning.hashShort = '';
  terminationFinalSigning.otp = '';

  const context = canvas.getContext('2d');
  const current = terminationSignaturePoint(event);
  context.beginPath();
  context.moveTo(current.x, current.y);
}

function drawTerminationFinalSignature(event) {
  const canvas = terminationFinalCanvas.value;
  if (!terminationFinalDrawing.value || !canvas) return;

  const context = canvas.getContext('2d');
  const current = terminationSignaturePoint(event);
  context.lineTo(current.x, current.y);
  context.stroke();
}

function stopTerminationFinalSignature() {
  terminationFinalDrawing.value = false;
}

function clearTerminationFinalSignature() {
  prepareTerminationFinalSignature();
  terminationFinalSigning.accepted = false;
  terminationFinalSigning.requestIdFor = null;
  terminationFinalSigning.requestId = null;
  terminationFinalSigning.hashShort = '';
  terminationFinalSigning.otp = '';
}

function terminationFinalSignatureImage() {
  return terminationFinalCanvas.value?.toDataURL('image/png') || '';
}

async function handleSendTerminationFinalOtp(request) {
  clearAlerts();
  saving.value = true;
  try {
    const response = await adminPartnerApplicationService.sendTerminationFinalOtp(request.id, {
      signature_image: terminationFinalSignatureImage(),
    });
    terminationFinalSigning.requestIdFor = request.id;
    terminationFinalSigning.requestId = response.data.signing_request_id;
    terminationFinalSigning.hashShort = response.data.hash_short;
    terminationFinalSigning.otp = '';
    message.value = `Đã gửi OTP SportGo. Mã đối soát: ${terminationFinalSigning.hashShort}`;
  } catch (err) {
    error.value = err.message || 'Không thể gửi OTP ký biên bản.';
  } finally {
    saving.value = false;
  }
}

async function handleSignTerminationFinalDocument(request) {
  clearAlerts();
  saving.value = true;
  try {
    const response = await adminPartnerApplicationService.signTerminationFinalDocument(request.id, {
      signing_request_id: terminationFinalSigning.requestId,
      otp: terminationFinalSigning.otp,
    });
    message.value = response.message || 'SportGo đã ký biên bản chấm dứt cuối.';
    terminationFinalSigning.requestIdFor = null;
    terminationFinalSigning.requestId = null;
    terminationFinalSigning.hashShort = '';
    terminationFinalSigning.otp = '';
    terminationFinalSigning.accepted = false;
    await loadApplication();
    closeTerminationFinalModal();
  } catch (err) {
    error.value = err.message || 'Không thể ký biên bản chấm dứt cuối.';
  } finally {
    saving.value = false;
  }
}

async function handleManualResolveBooking(request, action) {
  if (!confirm('Xác nhận booking này đã được xử lý thủ công?')) return;
  clearAlerts();
  saving.value = true;
  try {
    const response = await adminPartnerApplicationService.manualResolveTerminationBooking(request.id, {
      booking_id: action.booking_id,
      note: 'Admin xác nhận booking đã được xử lý thủ công.',
    });
    message.value = response.message || 'Đã ghi nhận booking được xử lý.';
    await loadApplication();
  } catch (err) {
    error.value = err.message || 'Không thể cập nhật booking.';
  } finally {
    saving.value = false;
  }
}

const InfoPanel = defineComponent({
  props: { title: String, items: Array },
  setup(props) {
    return () => h('article', { class: 'info-panel' }, [
      h('h3', props.title),
      h('dl', (props.items || []).flatMap(([label, value]) => [
        h('dt', label),
        h('dd', value || '-'),
      ])),
    ]);
  },
});

function statusLabel(status) {
  return {
    draft: 'Chờ ký đơn',
    pending: 'Chờ duyệt',
    submitted: 'Chờ duyệt',
    reviewing: 'Đang xem xét',
    need_supplement: 'Cần bổ sung',
    approved_pending_contract: 'Đã duyệt, chờ hợp đồng',
    contract_pending_sportgo_signature: 'Chờ SportGo ký',
    contract_pending_owner_signature: 'Chờ chủ sân ký',
    completed: 'Hoàn tất',
    rejected: 'Từ chối',
    cancelled: 'Đã hủy',
  }[status] || status || '-';
}

function identityLabel(value) {
  return { cccd: 'CCCD', cmnd: 'CMND', passport: 'Hộ chiếu' }[value] || value || '-';
}

function applicantTypeLabel(value) {
  return { individual: 'Cá nhân', business: 'Hộ kinh doanh', company: 'Doanh nghiệp' }[value] || value || '-';
}

function bankVerificationLabel(status) {
  return {
    verified: 'Đã xác minh',
    pending: 'Chưa xác minh',
    lookup_not_configured: 'Chưa cấu hình tra cứu',
    name_mismatch: 'Tên chủ tài khoản không khớp',
    not_found: 'Không tìm thấy tài khoản',
    provider_unavailable: 'Dịch vụ xác minh lỗi',
  }[status] || status || '-';
}

function documentTypeLabel(type) {
  if (type === 'owner_termination_request') return 'Đơn chủ sân yêu cầu chấm dứt';
  if (type === 'venue_scale_request') return 'Đơn yêu cầu thay đổi quy mô sân';
  if (type === 'venue_location_change_request') return 'Đơn yêu cầu thay đổi vị trí cụm sân';
  if (type === 'venue_scale_appendix') return 'Phụ lục thay đổi quy mô sân';
  if (type === 'venue_location_appendix') return 'Phụ lục thay đổi vị trí cụm sân';

  return {
    partner_application_form: 'Đơn đăng ký đối tác',
    partner_contract: 'Giấy/hợp đồng đối tác kinh doanh',
    termination_request: 'Đơn yêu cầu chấm dứt',
    mutual_liquidation_minutes: 'Biên bản thanh lý',
    unilateral_termination_notice: 'Công văn chấm dứt',
    settlement_minutes: 'Biên bản quyết toán',
  }[type] || type || 'Văn bản';
}

function uploadedTypeLabel(type) {
  return {
    identity: 'CCCD/CMND/Hộ chiếu',
    business_license: 'Giấy đăng ký kinh doanh',
    facility: 'Ảnh cơ sở/sân',
    bank: 'Chứng từ ngân hàng',
    lease: 'Hợp đồng thuê mặt bằng',
    scale_request_documents: 'Hồ sơ yêu cầu thay đổi quy mô',
    scale_request_supplement: 'Hồ sơ yêu cầu thay đổi quy mô',
    location_change_documents: 'Hồ sơ yêu cầu thay đổi vị trí',
    location_change_supplement: 'Hồ sơ yêu cầu thay đổi vị trí',
    additional: 'Tài liệu bổ sung',
  }[type] || type || 'Tài liệu';
}

function groupDocuments(documents, metaResolver) {
  const map = new Map();
  for (const document of documents || []) {
    const meta = metaResolver(document);
    if (!map.has(meta.key)) {
      map.set(meta.key, { ...meta, documents: [] });
    }
    map.get(meta.key).documents.push(document);
  }

  return Array.from(map.values());
}

function generatedDocumentGroupMeta(document) {
  const type = document?.document_type;
  if (['partner_contract', 'venue_scale_appendix', 'venue_location_appendix'].includes(type)) {
    return { key: 'contracts', label: 'Hợp đồng và phụ lục' };
  }
  if (type === 'partner_application_form') {
    return { key: 'partner_application', label: 'Đơn đăng ký đối tác' };
  }
  if (type === 'venue_scale_request') {
    return { key: 'scale_request', label: 'Đơn yêu cầu thay đổi quy mô' };
  }
  if (type === 'venue_location_change_request') {
    return { key: 'location_request', label: 'Đơn yêu cầu thay đổi vị trí' };
  }
  if (['termination_request', 'owner_termination_request', 'mutual_liquidation_minutes', 'unilateral_termination_notice', 'settlement_minutes'].includes(type)) {
    return { key: 'termination', label: 'Văn bản chấm dứt hợp tác' };
  }
  return { key: 'other', label: 'Văn bản khác' };
}

function uploadedDocumentGroupMeta(document) {
  const type = document?.document_type || document?.category;
  if (['identity', 'business_license', 'facility', 'bank', 'lease'].includes(type)) {
    return { key: 'registration', label: 'Hồ sơ đăng ký đối tác' };
  }
  if (['scale_request_documents', 'scale_request_supplement'].includes(type)) {
    return { key: 'scale_request_uploads', label: 'Hồ sơ yêu cầu thay đổi quy mô' };
  }
  if (['location_change_documents', 'location_change_supplement'].includes(type)) {
    return { key: 'location_request_uploads', label: 'Hồ sơ yêu cầu thay đổi vị trí' };
  }
  return { key: 'other_uploads', label: 'Tài liệu bổ sung khác' };
}

function documentStatusLabel(status) {
  return {
    generated: 'Đã sinh',
    pending_owner_signature: 'Chờ chủ sân ký',
    pending_sportgo_signature: 'Chờ SportGo ký',
    completed: 'Hoàn tất',
  }[status] || status || '-';
}

function contractStatusLabel(status) {
  return {
    pending_sportgo_signature: 'Chờ SportGo ký',
    pending_owner_signature: 'Chờ chủ sân ký',
    signed_active: 'Đang hiệu lực',
    terminated: 'Đã chấm dứt',
    cancelled: 'Đã hủy',
  }[status] || status || 'Chưa có hợp đồng';
}

function terminationStatusLabel(status) {
  return {
    draft: 'Chờ chủ sân ký gửi',
    draft_preview: 'Chờ chủ sân ký gửi',
    cancellation_in_progress: 'Chủ sân đã gửi yêu cầu',
    future_bookings_processing: 'Đang xử lý booking',
    waiting_final_settlement: 'Chờ quyết toán cuối',
    waiting_final_document_signature: 'Chờ ký biên bản cuối',
    terminating: 'Đang trong thời gian xem hồ sơ',
    terminated: 'Đã chấm dứt',
    owner_cancelled_request: 'Chủ sân đã hủy',
    admin_rejected: 'Admin từ chối',
    submitted: 'Chờ xác nhận',
    reviewing: 'Đang xem xét',
    settlement_processing: 'Chờ quyết toán cuối',
    settlement_completed: 'Chờ ký biên bản cuối',
    pending_signature: 'Chờ ký biên bản cuối',
    transition_period: 'Giai đoạn chuyển tiếp',
    completed: 'Đã hoàn tất',
    rejected: 'Đã từ chối',
    cancelled: 'Đã hủy',
  }[status] || status || '-';
}

function terminationTypeLabel(type) {
  return {
    mutual_agreement: 'Thỏa thuận hai bên',
    unilateral_by_owner: 'Đơn phương từ chủ sân',
    unilateral_by_sportgo: 'Đơn phương từ SportGo',
  }[type] || type || '-';
}

function bookingActionLabel(action) {
  return {
    cancel_all_refund_to_user_balance: 'Hủy và hoàn tiền',
    serve_until_last_booking: 'Phục vụ đến booking cuối',
    manual_per_booking: 'Xử lý thủ công',
  }[action] || action || '-';
}

function settlementStatusLabel(status) {
  return {
    pending: 'Chờ duyệt',
    approved: 'Đã duyệt',
    completed: 'Hoàn tất',
    cancelled: 'Đã hủy',
  }[status] || status || '-';
}

function withdrawalStatusLabel(status) {
  return {
    pending: 'Chờ xử lý',
    approved: 'Đã duyệt',
    processing: 'Đang xử lý',
    completed: 'Đã hoàn tất',
    rejected: 'Từ chối',
  }[status] || status || '-';
}

function signingStatusLabel(status) {
  return {
    otp_sent: 'OTP đã gửi',
    verified: 'OTP đã xác thực',
    signed: 'Đã ký',
    failed: 'OTP lỗi',
    expired: 'OTP hết hạn',
    cancelled: 'Đã hủy',
  }[status] || status || '-';
}

function signingSideLabel(side) {
  return {
    owner: 'Đối tác/chủ sân',
    sportgo: 'SportGo/Admin',
  }[side] || side || '-';
}

function signatureSummary(signatures = []) {
  if (!signatures?.length) return 'Chưa có chữ ký';
  return signatures
    .filter((signature) => signature.status === 'signed')
    .map((signature) => `${signature.signer_side === 'sportgo' ? 'SportGo' : 'Chủ sân'}: ${formatDate(signature.signed_at)}`)
    .join(' · ') || 'Chưa có chữ ký';
}

function courtTypeName(court) {
  return court?.court_type?.name || court?.courtType?.name || court?.court_type_name_snapshot || 'Loại sân';
}

function coordinateText(item) {
  return item?.venue_latitude && item?.venue_longitude ? `${item.venue_latitude}, ${item.venue_longitude}` : '-';
}

function contactText(item) {
  return [item?.venue_phone, item?.venue_email].filter(Boolean).join(' · ') || '-';
}

function compactList(value) {
  return Array.isArray(value) ? value.filter(Boolean).join(', ') || '-' : value || '-';
}

function money(value) {
  const number = Number(value || 0);
  return number > 0
    ? new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(number)
    : '-';
}

function fileSize(value) {
  const bytes = Number(value || 0);
  if (!bytes) return '-';
  const units = ['B', 'KB', 'MB', 'GB'];
  const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
  return `${(bytes / 1024 ** index).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
}

function formatDate(value) {
  if (!value) return '-';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString('vi-VN');
}

function dateOnly(value) {
  if (!value) return '-';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('vi-VN');
}
</script>

<style scoped>
.partner-detail-page {
  display: grid;
  gap: 16px;
  max-width: 1280px;
}

.page-head,
.action-strip,
.panel-head,
.card-head,
.form-actions,
.row-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.title-block {
  flex: 1;
  min-width: 0;
}

.title-block p {
  margin: 0 0 4px;
  color: #047857;
  font-size: 11px;
  font-weight: 900;
  letter-spacing: .1em;
  text-transform: uppercase;
}

.title-block h2 {
  margin: 0;
  color: var(--admin-text, #0f172a);
  font-size: 24px;
}

.notice,
.state-card,
.action-strip,
.review-panel,
.info-panel,
.content-card {
  border: 1px solid var(--admin-border, #e5e7eb);
  border-radius: 8px;
  background: var(--admin-surface, #fff);
}

.notice,
.state-card {
  padding: 14px 16px;
  font-weight: 750;
}

.notice.success {
  color: #166534;
  background: #f0fdf4;
  border-color: #bbf7d0;
}

.notice.error,
.state-card.error {
  color: #991b1b;
  background: #fef2f2;
  border-color: #fecaca;
}

.state-card {
  color: var(--admin-faint, #64748b);
  text-align: center;
  padding: 36px;
}

.action-strip,
.review-panel,
.content-card,
.info-panel {
  padding: 16px;
}

.action-strip strong,
.action-strip span {
  display: block;
}

.action-strip span {
  margin-top: 4px;
  color: var(--admin-faint, #64748b);
  font-size: 13px;
}

.action-buttons,
.row-actions {
  flex-wrap: wrap;
}

.tabs {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 2px;
}

.tabs button {
  min-height: 38px;
  border: 1px solid var(--admin-border, #e5e7eb);
  border-radius: 8px;
  padding: 0 13px;
  background: var(--admin-surface, #fff);
  color: var(--admin-faint, #64748b);
  font-weight: 850;
  cursor: pointer;
  white-space: nowrap;
}

.tabs button.active {
  border-color: #0f172a;
  background: #0f172a;
  color: #fff;
}

.panel-head {
  align-items: flex-start;
  margin-bottom: 14px;
}

.panel-head h3,
.info-panel h3,
.content-card h3 {
  margin: 0;
  color: var(--admin-text, #0f172a);
  font-size: 16px;
}

.panel-head p {
  margin: 5px 0 0;
  color: var(--admin-faint, #64748b);
  font-size: 13px;
}

.review-form {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.field {
  display: grid;
  gap: 6px;
}

.field span {
  color: var(--admin-text, #0f172a);
  font-size: 13px;
  font-weight: 850;
}

.field input,
.field select,
.field textarea {
  width: 100%;
  border: 1px solid var(--admin-border, #dbe3ef);
  border-radius: 8px;
  padding: 10px 11px;
  background: var(--admin-surface, #fff);
  color: var(--admin-text, #0f172a);
  font: inherit;
}

.field.invalid input,
.field.invalid select,
.field.invalid textarea {
  border-color: #dc2626;
}

.field small,
.inline-error {
  color: #b91c1c;
  font-size: 12px;
  font-weight: 750;
}

.full,
.wide {
  grid-column: 1 / -1;
}

.form-actions {
  justify-content: flex-end;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

dl {
  display: grid;
  grid-template-columns: 160px minmax(0, 1fr);
  gap: 9px 14px;
  margin: 14px 0 0;
}

dt {
  color: var(--admin-faint, #64748b);
  font-size: 13px;
}

dd {
  margin: 0;
  color: var(--admin-text, #111827);
  font-weight: 750;
  overflow-wrap: anywhere;
}

.card-head {
  margin-bottom: 12px;
}

.card-head span {
  color: var(--admin-faint, #64748b);
  font-size: 12px;
  font-weight: 850;
}

.court-grid,
.documents-grid {
  display: grid;
  gap: 14px;
}

.documents-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  align-items: start;
}

.court-grid {
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
}

.court-card,
.doc-row {
  border: 1px solid var(--admin-border, #e5e7eb);
  border-radius: 8px;
  padding: 13px;
  background: var(--admin-surface-muted, #f8fafc);
}

.court-card {
  display: grid;
  gap: 5px;
}

.court-card span,
.court-card p,
.doc-row p,
.timeline-row p,
.empty-text {
  margin: 4px 0 0;
  color: var(--admin-faint, #64748b);
  font-size: 13px;
  line-height: 1.45;
}

.doc-list {
  display: grid;
  gap: 10px;
}

.doc-group {
  display: grid;
  gap: 8px;
}

.doc-group-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 8px;
  background: #f8fafc;
  color: #0f172a;
}

.doc-group-head span {
  color: #64748b;
  font-size: 13px;
}

.signing-log-list {
  display: grid;
  gap: 12px;
}

.signing-log-row {
  border: 1px solid var(--admin-border, #e5e7eb);
  border-radius: 8px;
  padding: 14px;
  background: var(--admin-surface-muted, #f8fafc);
}

.signing-log-row strong {
  color: var(--admin-text, #0f172a);
}

.signing-log-row p {
  margin: 5px 0 0;
  color: var(--admin-faint, #64748b);
  font-size: 13px;
}

.signing-log-row dl {
  margin-top: 12px;
  grid-template-columns: 150px minmax(0, 1fr);
}

.doc-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.cluster-list {
  display: grid;
  gap: 10px;
  margin: 12px 0 18px;
}

.cluster-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  border: 1px solid var(--admin-border, #e5e7eb);
  border-radius: 8px;
  padding: 12px;
  background: var(--admin-surface-muted, #f8fafc);
}

.cluster-row.current {
  border-color: #86efac;
  background: #f0fdf4;
}

.cluster-row strong {
  color: var(--admin-text, #0f172a);
}

.cluster-row p,
.cluster-row small {
  display: block;
  margin: 4px 0 0;
  color: var(--admin-faint, #64748b);
}

.timeline {
  display: grid;
  gap: 12px;
}

.timeline-row {
  display: grid;
  grid-template-columns: 13px minmax(0, 1fr);
  gap: 10px;
}

.timeline-row > span {
  width: 10px;
  height: 10px;
  margin-top: 5px;
  border-radius: 999px;
  background: #0f172a;
}

.btn,
.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: 1px solid transparent;
  border-radius: 8px;
  font-weight: 850;
  cursor: pointer;
  text-decoration: none;
}

.btn {
  min-height: 38px;
  padding: 0 13px;
}

.btn.small {
  min-height: 34px;
  padding: 0 11px;
  font-size: 13px;
}

.icon-btn {
  width: 34px;
  height: 34px;
  background: transparent;
  color: var(--admin-faint, #64748b);
}

.btn.primary {
  background: #0f172a;
  color: #fff;
}

.btn.ghost {
  background: var(--admin-surface, #fff);
  border-color: var(--admin-border, #e5e7eb);
  color: var(--admin-text, #334155);
}

.btn.danger {
  background: #fee2e2;
  color: #b91c1c;
  border-color: #fecaca;
}

.btn.warning {
  background: #fef3c7;
  color: #92400e;
  border-color: #fcd34d;
}

.btn:disabled {
  opacity: .58;
  cursor: not-allowed;
}

.status {
  display: inline-flex;
  align-items: center;
  min-height: 28px;
  border-radius: 999px;
  padding: 0 11px;
  background: #fef3c7;
  color: #92400e;
  font-size: 12px;
  font-weight: 900;
}

.status-completed {
  background: #dcfce7;
  color: #166534;
}

.status-rejected,
.status-cancelled {
  background: #fee2e2;
  color: #991b1b;
}

@media (max-width: 980px) {
  .summary-grid,
  .documents-grid,
  .review-form {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 700px) {
  .page-head,
  .action-strip,
  .doc-row {
    align-items: flex-start;
    flex-direction: column;
  }

  .action-buttons,
  .row-actions,
  .btn {
    width: 100%;
  }

  dl {
    grid-template-columns: 1fr;
  }
}
.termination-finance-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  margin: 14px 0;
}

.termination-next-action {
  display: grid;
  gap: 8px;
  margin: 12px 0;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  background: #f0fdf4;
  padding: 12px;
}

.termination-next-action strong {
  color: #14532d;
}

.termination-next-action p {
  margin: 0;
  color: #475569;
}

.termination-task-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.termination-inline-form,
.reconsideration-alert {
  display: grid;
  gap: 10px;
  margin: 12px 0;
  border-left: 3px solid #f59e0b;
  background: #fffbeb;
  padding: 12px 14px;
}

.reconsideration-alert p {
  margin: 0;
  color: #78350f;
}

.modal-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.unilateral-sign-panel {
  display: grid;
  gap: 12px;
}

.unilateral-sign-panel p {
  margin: 4px 0 0;
}

.unilateral-otp-box {
  display: grid;
  gap: 10px;
  border: 1px solid var(--admin-border, #dbe7df);
  background: #f8fafc;
  padding: 12px;
}

.termination-progress {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.termination-progress span {
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  background: #fff;
  color: #64748b;
  font-size: 12px;
  font-weight: 800;
  padding: 5px 9px;
}

.termination-progress span.current {
  border-color: #22c55e;
  color: #166534;
}

.termination-progress span.done {
  border-color: #bbf7d0;
  background: #dcfce7;
  color: #166534;
}

.termination-finance-grid > div {
  border: 1px solid var(--admin-border, #e2e8f0);
  border-radius: 8px;
  padding: 10px;
}

.termination-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 12px 0;
}

.termination-actions .actions.compact {
  margin: 0;
}

.admin-termination-sign-box {
  display: grid;
  gap: 10px;
  width: min(560px, 100%);
  border: 1px dashed var(--admin-border, #cbd5e1);
  border-radius: 8px;
  background: #f8fafc;
  padding: 12px;
}

.termination-signature-pad {
  width: 100%;
  max-width: 100%;
  height: 160px;
  touch-action: none;
  border: 1px solid var(--admin-border, #cbd5e1);
  border-radius: 8px;
  background: #fff;
}

.admin-sign-confirm {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 8px;
  align-items: flex-start;
  color: var(--admin-text, #334155);
  font-size: 13px;
  font-weight: 700;
}

.otp-input {
  max-width: 140px;
  border: 1px solid var(--admin-border, #cbd5e1);
  border-radius: 8px;
  padding: 8px 10px;
}

@media (max-width: 980px) {
  .termination-finance-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .termination-finance-grid {
    grid-template-columns: 1fr;
  }

  .modal-form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
