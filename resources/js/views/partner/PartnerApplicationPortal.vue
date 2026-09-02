<template>
  <div class="partner-portal-page partner-client-page sg-client-page" :class="{ 'partner-portal-page--landing': !formOpen }">
    <PublicNavbar />
    <main :class="['portal-main', { 'portal-main--landing': !formOpen, 'portal-main--form': formOpen }]">
      <!-- ───── LANDING PAGE VIEW ───── -->
      <template v-if="!formOpen">
        <!-- User Existing Applications / Draft Notification Bar -->
        <div v-if="draft || applications.length > 0" class="portal-user-sticky-banner">
          <div class="portal-banner-inner">
            <div class="portal-banner-info">
              <AppIcon name="fileText" size="18" />
              <span v-if="draft">Bạn đang có 1 bản nháp hồ sơ chưa gửi (Lưu lúc {{ formatDate(draft.saved_at) }})</span>
              <span v-else>Bạn đang có <strong>{{ applications.length }}</strong> hồ sơ đối tác trên hệ thống.</span>
            </div>
            <div class="portal-banner-actions">
              <button v-if="draft" type="button" class="btn btn-sm btn-primary" @click="continueDraft">Tiếp tục điền nháp</button>
              <button v-if="applications.length > 0" type="button" class="btn btn-sm btn-outline" @click="showApplicationsModal = true">
                Xem hồ sơ đối tác ({{ applications.length }})
              </button>
            </div>
          </div>
        </div>

        <!-- High-converting Partner Landing Page -->
        <PartnerLanding @start-registration="startNewApplication" />

        <section v-if="onboardingTerms" class="partner-terms-band" aria-labelledby="partner-terms-title">
          <div class="partner-terms-inner">
            <div class="partner-terms-heading">
              <div class="partner-terms-heading-left">
                <span class="partner-terms-kicker">THÔNG TIN CẦN BIẾT TRƯỚC KHI ĐĂNG KÝ</span>
                <h2 id="partner-terms-title" class="partner-terms-title">Phí nền tảng và chính sách áp dụng</h2>
                <p class="partner-terms-notice">{{ onboardingTerms.notice }}</p>
              </div>
              <div class="partner-terms-version-badge">
                <span>Cập nhật theo phiên bản đang hiệu lực</span>
              </div>
            </div>

            <div class="partner-terms-grid">
              <!-- FEE MATRIX CARD -->
              <div class="partner-fee-panel">
                <div class="partner-panel-title-row">
                  <div>
                    <h3 class="partner-panel-main-title">{{ onboardingTerms.platform_fee.title }}</h3>
                    <p class="partner-panel-summary">{{ onboardingTerms.platform_fee.summary }}</p>
                  </div>
                  <span class="partner-billing-pill">{{ onboardingTerms.platform_fee.billing_cycle_label }}</span>
                </div>

                <div class="partner-fee-rows-list">
                  <div
                    v-for="tier in onboardingTerms.platform_fee.tiers"
                    :key="tier.id"
                    class="partner-fee-tier-row"
                  >
                    <div class="partner-fee-tier-name">
                      <span class="tier-dot"></span>
                      <span>{{ tier.name }}</span>
                    </div>
                    <div class="partner-fee-tier-price">
                      <strong>{{ money(tier.price_per_court_month) }}</strong>
                      <small>/sân/tháng</small>
                    </div>
                    <div class="partner-fee-tier-discount" v-if="tier.annual_discount_percent > 0">
                      <span class="discount-tag">Giảm {{ tier.annual_discount_percent }}% năm</span>
                    </div>
                  </div>
                </div>

                <div class="partner-terms-footer-note">
                  <p>{{ onboardingTerms.platform_fee.settings.default_due_days }} ngày nhắc trước hạn; quá hạn có thể bị giới hạn quyền vận hành theo chính sách.</p>
                </div>
              </div>

              <!-- POLICIES LIST CARD -->
              <div class="partner-policy-panel">
                <div class="partner-policy-head">
                  <h3 class="partner-panel-main-title">Chính sách liên quan</h3>
                </div>
                
                <div class="partner-policy-items-list">
                  <details v-for="policy in onboardingTerms.policies" :key="policy.key" class="partner-policy-item">
                    <summary class="partner-policy-summary">
                      <span class="partner-policy-item-title">{{ policy.title }}</span>
                      <span class="partner-policy-ver">v{{ policy.version }}</span>
                    </summary>
                    <div class="partner-policy-content">
                      <p>{{ policy.content }}</p>
                    </div>
                  </details>
                </div>
              </div>
            </div>
          </div>
        </section>
      </template>

      <!-- ───── FORM VIEW WIZARD ───── -->
      <template v-else>
        <div class="portal-form-wrapper">
          <div class="portal-form-grid">
            <!-- LEFT SIDEBAR: STEP PERSISTENT NAV & TERMS SUMMARY -->
            <aside class="portal-form-sidebar">
              <div class="portal-form-sidebar-sticky">
                <div class="portal-sidebar-card">
                  <h3 class="portal-sidebar-title">Các bước hồ sơ</h3>
                  <nav class="wizard-steps-nav" aria-label="Các phần của hồ sơ">
                    <button type="button" class="wizard-step-link" :class="{ 'is-active': activeStep === 1 }" @click="scrollToStep(1, 'partner-step-personal')">
                      <span>1</span>
                      <div class="step-text">
                        <strong>Người đăng ký</strong>
                        <small>Chủ thể & Đại diện</small>
                      </div>
                    </button>
                    <button type="button" class="wizard-step-link" :class="{ 'is-active': activeStep === 2 }" @click="scrollToStep(2, 'partner-step-business')">
                      <span>2</span>
                      <div class="step-text">
                        <strong>Kinh doanh</strong>
                        <small>Đơn vị & Mã số thuế</small>
                      </div>
                    </button>
                    <button type="button" class="wizard-step-link" :class="{ 'is-active': activeStep === 3 }" @click="scrollToStep(3, 'partner-step-venue')">
                      <span>3</span>
                      <div class="step-text">
                        <strong>Cụm sân</strong>
                        <small>Thông tin & Quy mô</small>
                      </div>
                    </button>
                    <button type="button" class="wizard-step-link" :class="{ 'is-active': activeStep === 4 }" @click="scrollToStep(4, 'partner-step-documents')">
                      <span>4</span>
                      <div class="step-text">
                        <strong>Ngân hàng & Giấy tờ</strong>
                        <small>Tài khoản & Hồ sơ</small>
                      </div>
                    </button>
                  </nav>
                </div>

                <!-- TERMS & POLICY MODAL TRIGGER BUTTON -->
                <button v-if="onboardingTerms" type="button" class="portal-sidebar-terms-btn" @click="showTermsModal = true">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                  <span>Biểu phí & Chính sách</span>
                </button>

                <button type="button" class="portal-sidebar-exit-btn" @click="closeForm">
                  <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                  <span>Quay lại trang giới thiệu</span>
                </button>
              </div>
            </aside>

            <!-- RIGHT MAIN FORM CONTENT -->
            <div class="portal-form-content">
              <form class="wizard-form" novalidate @submit.prevent="submit">
                <div class="wizard-body">
                  <div v-if="formBanner" class="notice error mb-4 form-banner">
                    {{ formBanner }}
                  </div>

                  <!-- STEP 1: Cá nhân -->
                  <div id="partner-step-personal" class="step-content section-anchor">
                    <FormSection title="Thông tin người đăng ký / đại diện">
                      <div class="form-grid">
                        <FormField label="Họ tên người đăng ký" required :error="fieldErrors.applicant_full_name">
                          <input v-model.trim="form.applicant_full_name" :class="inputClass(fieldErrors.applicant_full_name)" />
                        </FormField>
                        <FormField label="Số điện thoại" required :error="fieldErrors.applicant_phone">
                          <input v-model.trim="form.applicant_phone" :class="inputClass(fieldErrors.applicant_phone)" inputmode="tel" @input="sanitizePhoneCharacters('applicant_phone')" />
                        </FormField>
                        <FormField label="Email" required :error="fieldErrors.applicant_email">
                          <input v-model.trim="form.applicant_email" :class="inputClass(fieldErrors.applicant_email)" type="email" />
                        </FormField>
                        <FormField label="Ngày sinh" required :error="fieldErrors.applicant_birth_date">
                          <input v-model="form.applicant_birth_date" :class="inputClass(fieldErrors.applicant_birth_date)" type="date" />
                        </FormField>
                        <FormField label="Loại chủ thể" required :error="fieldErrors.applicant_type">
                          <BaseCombobox v-model="form.applicant_type" :options="applicantTypeOptions" placeholder="Chọn loại chủ thể" :invalid="Boolean(fieldErrors.applicant_type)" />
                        </FormField>
                        <FormField label="Người đại diện pháp luật" required :error="fieldErrors.representative_name">
                          <input v-model.trim="form.representative_name" :class="inputClass(fieldErrors.representative_name)" />
                        </FormField>
                        <FormField label="Loại giấy tờ đại diện" required :error="fieldErrors.representative_identity_type">
                          <BaseCombobox v-model="form.representative_identity_type" :options="identityTypeOptions" placeholder="Chọn loại giấy tờ" :invalid="Boolean(fieldErrors.representative_identity_type)" @update:model-value="normalizeIdentityNumber" />
                        </FormField>
                        <FormField label="Số CCCD/CMND/Hộ chiếu" required :error="fieldErrors.representative_identity_number">
                          <input v-model.trim="form.representative_identity_number" :class="inputClass(fieldErrors.representative_identity_number)" @input="normalizeIdentityNumber" />
                        </FormField>
                        <FormField label="Ngày cấp" :error="fieldErrors.representative_identity_issued_date">
                          <input v-model="form.representative_identity_issued_date" :class="inputClass(fieldErrors.representative_identity_issued_date)" type="date" />
                        </FormField>
                        <FormField label="Nơi cấp" :error="fieldErrors.representative_identity_issued_place">
                          <input v-model.trim="form.representative_identity_issued_place" :class="inputClass(fieldErrors.representative_identity_issued_place)" />
                        </FormField>
                      </div>
                    </FormSection>
                  </div>

                  <!-- STEP 2: Kinh doanh -->
                  <div id="partner-step-business" class="step-content step-content--spaced section-anchor">
                    <FormSection title="Thông tin kinh doanh">
                      <div class="form-grid">
                        <FormField label="Tên đơn vị / Cá nhân kinh doanh" required :error="fieldErrors.business_name">
                          <input v-model.trim="form.business_name" :class="inputClass(fieldErrors.business_name)" />
                        </FormField>
                        <FormField label="Mã số thuế" :error="fieldErrors.tax_code">
                          <input v-model.trim="form.tax_code" :class="inputClass(fieldErrors.tax_code)" inputmode="numeric" @input="normalizeTaxCode" />
                        </FormField>
                        <FormField label="Số giấy đăng ký kinh doanh/pháp lý" required :error="fieldErrors.business_license_number">
                          <input v-model.trim="form.business_license_number" :class="inputClass(fieldErrors.business_license_number)" />
                        </FormField>
                        <FormField label="Mã doanh nghiệp/hộ kinh doanh (nếu có)" :error="fieldErrors.business_code">
                          <input v-model.trim="form.business_code" :class="inputClass(fieldErrors.business_code)" />
                        </FormField>
                        <FormField class="full-width" label="Địa chỉ liên hệ" required :error="fieldErrors.applicant_address">
                          <textarea v-model.trim="form.applicant_address" :class="textareaClass(fieldErrors.applicant_address)" rows="2"></textarea>
                        </FormField>
                        <FormField class="full-width" label="Địa chỉ pháp lý (trên giấy tờ)" required :error="fieldErrors.business_address">
                          <textarea v-model.trim="form.business_address" :class="textareaClass(fieldErrors.business_address)" rows="2"></textarea>
                        </FormField>
                      </div>
                    </FormSection>
                  </div>

                  <!-- STEP 3: Cụm sân -->
                  <div id="partner-step-venue" class="step-content step-content--spaced section-anchor">
                    <FormSection title="Địa chỉ và thông tin Cụm sân">
                      <div class="form-grid">
                        <!-- GOOGLE MAPS LINK INPUT (ĐẶT Ở ĐẦU FORM RÕ RÀNG) -->
                        <FormField class="full-width" label="Liên kết Google Maps (Google Maps Link)" :error="fieldErrors.venue_map_url">
                          <div class="input-with-action">
                            <input
                              v-model.trim="form.venue_map_url"
                              :class="inputClass(fieldErrors.venue_map_url)"
                              placeholder="Dán liên kết Google Maps (VD: https://www.google.com/maps/@21.028,105.854... hoặc link chia sẻ)"
                              @input="onGoogleMapUrlInput"
                              @paste="onGoogleMapUrlPaste"
                              @change="parseGoogleMapUrl(false)"
                            />
                            <button type="button" class="btn-parse-map" @click="parseGoogleMapUrl(false)">Trích xuất vị trí</button>
                          </div>
                          <p class="form-hint">Dán liên kết Google Maps và bấm Trích xuất vị trí để hệ thống tự động chọn Tỉnh/Thành phố & Phường/Xã.</p>
                        </FormField>

                        <!-- 2 Ô CHỌN TỈNH/THÀNH PHỐ VÀ PHƯỜNG/XÃ -->
                        <FormField label="Tỉnh/Thành phố" required :error="fieldErrors.venue_province_code">
                          <BaseCombobox v-model="form.venue_province_code" :options="provinceOptions" placeholder="Tìm Tỉnh/Thành phố" :invalid="Boolean(fieldErrors.venue_province_code)" @select="onProvinceSelect" />
                        </FormField>
                        <FormField label="Phường/Xã" required :error="fieldErrors.venue_ward_code">
                          <BaseCombobox v-model="form.venue_ward_code" :options="wardOptions" placeholder="Tìm Phường/Xã" :disabled="!form.venue_province_code" :invalid="Boolean(fieldErrors.venue_ward_code)" @select="syncVenueAddress" />
                        </FormField>
                        <FormField class="full-width" label="Số nhà, tên đường" required :error="fieldErrors.street_address">
                          <input v-model.trim="form.street_address" :class="inputClass(fieldErrors.street_address)" placeholder="Ví dụ: 123 Đường Nguyễn Văn Cừ" @input="syncVenueAddress" />
                        </FormField>
                        <!-- MAP DISPLAY (Đặt ngay dưới Địa chỉ để không phải cuộn trang) -->
                        <div class="form-group full-width map-picker-group">
                          <div class="map-picker-header">
                            <label class="form-label"><span class="form-label-text">Vị trí chính xác trên bản đồ</span></label>
                            <button type="button" class="current-location-btn" @click="getCurrentLocation">
                              <AppIcon name="mapPin" size="14" /> Lấy vị trí hiện tại của tôi
                            </button>
                          </div>

                          <p v-if="mapStatus" style="font-size: 13px; color: #0284c7; margin: 6px 0 0 0; font-weight: 500;">{{ mapStatus }}</p>
                          <p v-if="mapError" style="font-size: 13px; color: #ef4444; margin: 6px 0 0 0; font-weight: 500;">{{ mapError }}</p>

                          <div id="partner-application-map" ref="mapContainer" class="portal-map-picker"></div>
                          <p class="form-hint">Kéo thả ghim đỏ hoặc nhấp trực tiếp vào bản đồ để chọn vị trí cụm sân của bạn.</p>
                        </div>

                        <FormField class="full-width" label="Tên Cụm sân hiển thị" required :error="fieldErrors.venue_name">
                          <input v-model.trim="form.venue_name" :class="inputClass(fieldErrors.venue_name)" placeholder="Ví dụ: Cụm sân Cầu lông SportGo Tân Bình" />
                        </FormField>
                        <FormField label="Môn thể thao chính" required :error="fieldErrors.court_type_id">
                          <BaseCombobox v-model="form.court_type_id" :options="courtTypeOptions" placeholder="Chọn bộ môn" :invalid="Boolean(fieldErrors.court_type_id)" />
                        </FormField>
                        <FormField label="Số sân vận hành" required :error="fieldErrors.court_count">
                          <input v-model.number="form.court_count" :class="inputClass(fieldErrors.court_count)" type="number" min="1" max="200" />
                        </FormField>
                        <FormField label="Quy mô diện tích (m²)" :error="fieldErrors.venue_area_sqm">
                          <input v-model.number="form.venue_area_sqm" :class="inputClass(fieldErrors.venue_area_sqm)" type="number" min="10" />
                        </FormField>
                      </div>
                    </FormSection>
                  </div>

                  <!-- STEP 4: Giấy tờ & Ngân hàng -->
                  <div id="partner-step-documents" class="step-content step-content--spaced section-anchor">
                    <FormSection title="Thông tin ngân hàng">
                      <div class="form-grid">
                        <FormField label="Ngân hàng" required :error="fieldErrors.bank_code">
                          <BaseCombobox v-model="form.bank_code" :options="bankOptions" placeholder="Tìm ngân hàng" :invalid="Boolean(fieldErrors.bank_code)" @select="selectBank" />
                        </FormField>
                        <FormField label="Số tài khoản" required :error="fieldErrors.account_number">
                          <input v-model.trim="form.account_number" :class="inputClass(fieldErrors.account_number)" inputmode="numeric" @input="onAccountNumberInput" />
                        </FormField>
                        <FormField label="Tên chủ tài khoản" required :error="fieldErrors.account_holder_name">
                          <input
                            v-model.trim="form.account_holder_name"
                            :class="inputClass(fieldErrors.account_holder_name)"
                            placeholder="Viết IN HOA không dấu"
                            @input="onManualBankHolderInput()"
                          />
                        </FormField>
                        <FormField label="Chi nhánh" :error="fieldErrors.bank_branch">
                          <input v-model.trim="form.bank_branch" :class="inputClass(fieldErrors.bank_branch)" />
                        </FormField>
                      </div>
                    </FormSection>

                    <FormSection class="section-spaced" title="Tài liệu đính kèm">
                      <div class="form-grid">
                        <UploadBox :key="`identity-${uploadResetKey}`" title="CCCD/CMND người đại diện" required :max-files="5" :files="files.identity" :existing-files="existingDocuments.identity" :error="fieldErrors.identity_documents" @change="setFiles('identity', $event)" @remove="removeFile('identity', $event)" />
                        <UploadBox :key="`business-${uploadResetKey}`" title="Giấy ĐKKD/Pháp lý" required :max-files="5" :files="files.business_license" :existing-files="existingDocuments.business_license" :error="fieldErrors.business_license_documents" @change="setFiles('business_license', $event)" @remove="removeFile('business_license', $event)" />
                        <UploadBox :key="`facility-${uploadResetKey}`" title="Hình ảnh cơ sở/sân" required :max-files="12" :files="files.facility" :existing-files="existingDocuments.facility" :error="fieldErrors.facility_images" @change="setFiles('facility', $event)" @remove="removeFile('facility', $event)" />
                        <UploadBox :key="`bank-${uploadResetKey}`" title="Chứng từ ngân hàng" required :max-files="5" :files="files.bank" :existing-files="existingDocuments.bank" :error="fieldErrors.bank_documents" @change="setFiles('bank', $event)" @remove="removeFile('bank', $event)" />
                        <UploadBox :key="`lease-${uploadResetKey}`" title="Hợp đồng thuê mặt bằng" required :max-files="5" :files="files.lease" :existing-files="existingDocuments.lease" :error="fieldErrors.lease_documents" @change="setFiles('lease', $event)" @remove="removeFile('lease', $event)" />
                        <UploadBox :key="`additional-${uploadResetKey}`" title="Giấy tờ khác" :max-files="10" :files="files.additional" :existing-files="existingDocuments.additional" :error="fieldErrors.additional_documents" @change="setFiles('additional', $event)" @remove="removeFile('additional', $event)" />
                      </div>
                    </FormSection>

                    <div class="portal-card confirmation-card" :class="fieldErrors.confirmed ? 'has-error' : ''">
                      <label class="confirmation-label">
                        <input v-model="confirmed" type="checkbox" />
                        <span>
                          Tôi xác nhận thông tin trong hồ sơ là chính xác, đã đọc phí nền tảng và các chính sách liên quan, đồng ý để SportGo kiểm tra tài liệu trước khi duyệt đối tác.
                        </span>
                      </label>
                      <p v-if="fieldErrors.confirmed" class="confirmation-error">{{ fieldErrors.confirmed }}</p>
                    </div>
                  </div>
                </div>

                <!-- Form Actions -->
                <div class="wizard-footer">
                  <div class="wizard-footer-note">
                    <AppIcon name="save" size="16" />
                    <span>Hồ sơ có thể lưu nháp bất kỳ lúc nào. Bạn sẽ được chuyển sang bước ký điện tử sau khi gửi.</span>
                  </div>
                  <div class="wizard-actions">
                    <button type="button" class="btn btn-outline" @click="saveDraft">Lưu nháp</button>
                    <button type="submit" class="btn btn-primary" :disabled="submitDisabled">
                      <span v-if="submitting" class="submit-spinner"></span>
                      {{ submitting ? 'Đang xử lý...' : 'Gửi hồ sơ đăng ký' }}
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
      </template>

    </main>

    <FloatingActions />
    <Teleport to="body">
      <div v-if="showApplicationsModal" class="portal-modal-backdrop" @click.self="showApplicationsModal = false">
        <div class="portal-modal-container">
          <header class="portal-modal-header">
            <div>
              <h3>Quản lý hồ sơ đối tác của bạn</h3>
              <p>Theo dõi tiến trình xét duyệt, bổ sung giấy tờ hoặc ký hợp đồng</p>
            </div>
            <button type="button" class="portal-modal-close" @click="showApplicationsModal = false">✕</button>
          </header>

          <div class="portal-modal-body">
            <!-- Draft Item -->
            <article v-if="draft" class="app-list-item draft-item" style="margin-bottom: 16px;">
              <div class="application-summary">
                <div class="application-title-row">
                  <h3>{{ draft.venue_name || 'Chưa đặt tên cụm sân' }}</h3>
                  <span class="badge badge-amber">Bản nháp trên máy</span>
                </div>
                <p class="application-meta">
                  Lưu nháp • {{ formatDate(draft.saved_at) }}
                </p>
              </div>

              <div class="app-list-actions">
                <button type="button" class="btn btn-primary" @click="continueDraft(); showApplicationsModal = false;">
                  <AppIcon name="edit" size="16" /> Tiếp tục điền
                </button>
                <details class="application-more">
                  <summary class="icon-action" title="Thao tác khác" aria-label="Thao tác khác">
                    <AppIcon name="moreHorizontal" size="18" />
                  </summary>
                  <div class="application-more-menu">
                    <button type="button" class="danger-menu-action" @click="clearDraft">
                      <AppIcon name="trash" size="15" /> Xóa nháp
                    </button>
                  </div>
                </details>
              </div>
            </article>

            <div v-if="applications.length > 0" class="applications-stack">
              <article v-for="application in applications" :key="application.id" class="app-list-item" style="margin-bottom: 16px;">
                <div class="application-summary">
                  <div class="application-title-row">
                    <h3>{{ application.venue_name }}</h3>
                    <span class="badge" :class="statusClass(application.status)">
                      {{ statusLabel(application.status) }}
                    </span>
                  </div>
                  <p class="application-meta">
                    {{ application.venue_address }} • Gửi {{ formatDate(application.submitted_at) }}
                  </p>

                  <div v-if="application.status === 'rejected'" class="application-notice application-notice--danger">
                    <strong>Lý do từ chối:</strong> <span>{{ application.status_reason || 'SportGo chưa cung cấp lý do chi tiết.' }}</span>
                  </div>
                  <div v-if="application.status === 'need_supplement'" class="application-notice application-notice--warning">
                    <strong>Cần bổ sung hồ sơ:</strong> <span>{{ application.status_reason || 'Vui lòng liên hệ SportGo để biết thêm chi tiết.' }}</span>
                  </div>
                  <div v-if="application.status === 'contract_pending_owner_signature'" class="application-notice application-notice--success">
                    <strong>Hồ sơ đã được duyệt.</strong> <span>Hợp đồng hợp tác đã sẵn sàng. Vui lòng xem và ký hợp đồng để hoàn tất đăng ký.</span>
                  </div>
                </div>

                <div class="app-list-actions">
                  <button
                    v-if="applicationPrimaryAction(application)"
                    type="button"
                    class="btn btn-primary"
                    :disabled="actioningApplicationId === application.id"
                    @click="runApplicationPrimaryAction(application); showApplicationsModal = false;"
                  >
                    <AppIcon :name="applicationPrimaryAction(application).icon" size="16" />
                    {{ actioningApplicationId === application.id ? 'Đang xử lý...' : applicationPrimaryAction(application).label }}
                  </button>
                  <button type="button" class="btn btn-secondary action-detail" @click="openApplicationDetail(application); showApplicationsModal = false;">
                    <AppIcon name="eye" size="16" /> Chi tiết
                  </button>
                  <details v-if="canCancel(application)" class="application-more">
                    <summary class="icon-action" title="Thao tác khác" aria-label="Thao tác khác">
                      <AppIcon name="moreHorizontal" size="18" />
                    </summary>
                    <div class="application-more-menu">
                      <button type="button" class="danger-menu-action" :disabled="actioningApplicationId === application.id" @click="cancelApplication(application)">
                        <AppIcon name="trash" size="15" /> Hủy hồ sơ
                      </button>
                    </div>
                  </details>
                </div>
              </article>
            </div>
            <div v-else-if="!draft" class="portal-empty-state" style="text-align: center; padding: 32px 0;">
              <p>Chưa có hồ sơ nào.</p>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- TERMS & POLICY MODAL POPUP -->
    <Teleport to="body">
      <div v-if="showTermsModal && onboardingTerms" class="portal-modal-backdrop" @click.self="showTermsModal = false">
        <div class="portal-modal-container portal-terms-modal-container">
          <header class="portal-modal-header">
            <div>
              <h3>{{ onboardingTerms.platform_fee.title || 'Biểu phí nền tảng & Chính sách' }}</h3>
              <p>{{ onboardingTerms.platform_fee.summary }}</p>
            </div>
            <button type="button" class="portal-modal-close" @click="showTermsModal = false">✕</button>
          </header>

          <div class="portal-modal-body">
            <!-- FEE MATRIX TIERS -->
            <div class="modal-terms-section">
              <h4 class="modal-terms-subtitle">Bảng phí vận hành cụm sân</h4>
              <div class="modal-fee-grid">
                <div v-for="tier in onboardingTerms.platform_fee.tiers" :key="tier.id" class="modal-fee-card">
                  <div class="modal-fee-head">
                    <span class="tier-name">{{ tier.name }}</span>
                  </div>
                  <div class="modal-fee-amount">
                    <span class="tier-price-val">{{ money(tier.price_per_court_month) }}</span>
                    <small class="tier-price-unit">/sân/tháng</small>
                  </div>
                  <div v-if="tier.annual_discount_percent > 0" class="modal-fee-discount">
                    <span class="discount-tag">Giảm {{ tier.annual_discount_percent }}% năm</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- POLICIES -->
            <div v-if="onboardingTerms.policies && onboardingTerms.policies.length" class="modal-terms-section" style="margin-top: 24px;">
              <h4 class="modal-terms-subtitle">Các chính sách liên quan</h4>
              <div class="modal-policies-list">
                <details v-for="policy in onboardingTerms.policies" :key="policy.key" class="modal-policy-item">
                  <summary class="modal-policy-summary">
                    <span class="policy-title-text">{{ policy.title }}</span>
                    <span class="modal-policy-ver">v{{ policy.version }}</span>
                  </summary>
                  <div class="modal-policy-body">
                    <p>{{ policy.content }}</p>
                  </div>
                </details>
              </div>
            </div>
          </div>

          <footer class="portal-modal-footer">
            <button type="button" class="btn btn-secondary" @click="showTermsModal = false">Đóng cửa sổ</button>
          </footer>
        </div>
      </div>
    </Teleport>

    <!-- LOCATION VERIFICATION POPUP MODAL -->
    <Teleport to="body">
      <div v-if="locationModal.show" class="location-modal-backdrop" @click.self="rejectLocationModal">
        <div class="location-modal-card" role="dialog" aria-modal="true">
          <!-- State 1: Loading -->
          <div v-if="locationModal.loading" class="location-modal-loading">
            <span class="location-spinner"></span>
            <p class="location-loading-text">Đang trích xuất và xác minh vị trí từ tọa độ bản đồ...</p>
          </div>

          <!-- State 2: Resolved Result with 2 Options -->
          <div v-else class="location-modal-result">
            <h3 class="location-modal-title">Xác nhận vị trí hành chính</h3>
            <p class="location-modal-desc">Hệ thống đã nhận diện được vị trí của bạn thuộc khu vực:</p>
            <p class="location-detected-address">
              <strong>{{ locationModal.resolvedData?.full_address }}</strong>
            </p>
            <p class="location-modal-question">Bạn có muốn tự động chọn Tỉnh/Thành phố & Phường/Xã này vào thông tin đăng ký không?</p>

            <div class="location-modal-actions">
              <button type="button" class="location-btn location-btn-accept" @click="acceptLocationModal">
                Đồng ý và áp dụng
              </button>
              <button type="button" class="location-btn location-btn-reject" @click="rejectLocationModal">
                Từ chối, chọn thủ công
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, defineComponent, h, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import PublicNavbar from '../../components/PublicNavbar.vue';
import PartnerLanding from './PartnerLanding.vue';
import ConfirmActionModal from '../../components/ConfirmActionModal.vue';
import FloatingActions from '../../components/FloatingActions.vue';
import BackButton from '../../components/BackButton.vue';
import AppIcon from '../../components/AppIcon.vue';
import BaseCombobox from '../../components/BaseCombobox.vue';
import UploadBox from '../../components/UploadBox.vue';
import { getAuth } from '../../stores/auth.js';
import { api, apiFormData } from '../../services/api.js';

// ─── Constants ───────────────────────────────────────────────────────────────

const DRAFT_KEY = 'sportgo_partner_application_draft_v3';
const BANK_CACHE_KEY = 'sportgo_partner_banks_v2';
const BANK_CACHE_TTL = 24 * 60 * 60 * 1000;

// ─── Inline components ───────────────────────────────────────────────────────

const FormSection = defineComponent({
  name: 'FormSection',
  props: {
    title: { type: String, required: true },
  },
  setup(props, { slots, attrs }) {
    return () => h('div', { ...attrs, class: ['portal-card', 'portal-form-section', attrs.class] }, [
      h('div', { class: 'portal-section-header', style: { marginBottom: '16px', paddingBottom: '0px', border: 'none', borderBottom: 'none' } }, [
        h('h2', { class: 'portal-section-title', style: { fontSize: '18px', fontWeight: '600', color: '#0f172a', margin: '0', padding: '0', border: 'none', borderBottom: 'none', outline: 'none' } }, props.title),
      ]),
      h('div', { class: 'portal-section-body', style: { border: 'none', borderTop: 'none', borderBottom: 'none', paddingTop: '0' } }, slots.default?.()),
    ]);
  },
});

const FormField = defineComponent({
  name: 'FormField',
  props: {
    label: { type: String, required: true },
    required: { type: Boolean, default: false },
    error: { type: String, default: '' },
  },
  setup(props, { slots, attrs }) {
    return () => h('div', { class: ['form-group', props.error ? 'has-error' : '', attrs.class] }, [
      h('label', { class: 'form-label' }, [
        h('span', { class: 'form-label-text' }, [
          props.label,
          props.required
            ? h('span', { class: 'required', 'aria-hidden': 'true' }, ' *')
            : null,
        ]),
      ]),
      slots.default?.(),
      props.error ? h('p', { class: 'error-text' }, props.error) : null,
    ]);
  },
});

// ─── State ───────────────────────────────────────────────────────────────────
const route = useRoute();
const router = useRouter();
const toast = useToast();
const user = getAuth();

const loading = ref(false);
const applications = ref([]);
const onboardingTerms = ref(null);
const canRegister = ref(false);
const pageError = ref('');
const draft = ref(null);
const formOpen = ref(false);
const activeStep = ref(1);

function scrollToStep(stepIndex, elementId) {
  activeStep.value = stepIndex;
  const el = document.getElementById(elementId);
  if (el) {
    const yOffset = -90;
    const y = el.getBoundingClientRect().top + window.pageYOffset + yOffset;
    window.scrollTo({ top: y, behavior: 'smooth' });
  }
}

const showApplicationsModal = ref(false);
const showTermsModal = ref(false);
const fieldErrors = reactive({});
const formBanner = ref('');
const provinces = ref([]);
const wards = ref([]);
const banks = ref([]);
const courtTypes = ref([]);
const amenities = ref([]);
const files = reactive(blankFiles());
const existingDocuments = reactive(blankExistingDocuments());
const uploadResetKey = ref(0);
const confirmed = ref(false);
const submitting = ref(false);
const actioningApplicationId = ref('');
const cancelTarget = ref(null);
const cancelError = ref('');
const mapError = ref('');
const mapStatus = ref('');
const mapSuggestion = ref(null);
const locationModal = reactive({
  show: false,
  loading: false,
  resolvedData: null,
});
const bankTimer = ref(null);
const mapTimer = ref(null);
const mapContainer = ref(null);
const mapInstance = ref(null);
const mapMarker = ref(null);
const mapReverseBusy = ref(false);
const editingApplicationId = ref('');
const editingApplicationStatus = ref('');
let scrollObserver = null;

function initScrollSpy() {
  if (scrollObserver) scrollObserver.disconnect();
  const stepIds = [
    { id: 'partner-step-personal', step: 1 },
    { id: 'partner-step-business', step: 2 },
    { id: 'partner-step-venue', step: 3 },
    { id: 'partner-step-documents', step: 4 },
  ];
  if (typeof IntersectionObserver === 'undefined') return;
  scrollObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const matched = stepIds.find(s => s.id === entry.target.id);
        if (matched) activeStep.value = matched.step;
      }
    });
  }, { threshold: 0.2 });

  stepIds.forEach(s => {
    const el = document.getElementById(s.id);
    if (el) scrollObserver.observe(el);
  });
}

// ─── Static options ───────────────────────────────────────────────────────────
const applicantTypeOptions = [
  { value: 'individual', label: 'Cá nhân/hộ kinh doanh' },
  { value: 'business', label: 'Hộ kinh doanh có giấy phép' },
  { value: 'company', label: 'Doanh nghiệp' },
];
const identityTypeOptions = [
  { value: 'cccd', label: 'CCCD' },
  { value: 'cmnd', label: 'CMND' },
  { value: 'passport', label: 'Hộ chiếu' },
];

const form = reactive(defaultForm(user));

// ─── Computed ─────────────────────────────────────────────────────────────────
const bankOptions = computed(() => banks.value.map((b) => ({ ...b, value: b.code, label: `${b.short_name || b.code} - ${b.name || b.code}` })));
const provinceOptions = computed(() => provinces.value.map((p) => ({ ...p, value: p.code, label: p.name })));
const wardOptions = computed(() => wards.value.map((w) => ({ ...w, value: w.code, label: w.name })));
const courtTypeOptions = computed(() => courtTypes.value.filter((t) => t.is_active !== false && Number(t.children_count || 0) === 0).map((t) => ({ ...t, value: t.id, label: t.name })));
const submitDisabled = computed(() => submitting.value);
const reviewingCount = computed(() => applications.value.filter((a) => ['pending', 'submitted', 'reviewing'].includes(a.status)).length);
const selectedFeeTier = computed(() => {
  const count = Number(form.court_count_total || 0);
  return onboardingTerms.value?.platform_fee?.tiers?.find((tier) => count >= Number(tier.min_courts) && (tier.max_courts === null || count <= Number(tier.max_courts))) || null;
});

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
  if (route.name === 'partner-application' && !user) {
    router.replace({ name: 'login', query: { redirect: route.fullPath } });
    return;
  }
  loadDraft();
  if (user) {
    const loaders = [
      ['hồ sơ đã gửi', loadApplications],
      ['phí và chính sách đối tác', loadOnboardingTerms],
      ['danh sách ngân hàng', loadBanks],
      ['Tỉnh/Thành phố', loadProvinces],
      ['loại sân', loadCourtTypes],
      ['tiện ích', loadAmenities],
    ];
    const results = await Promise.allSettled(loaders.map(([, loader]) => loader()));
    const failedLabels = results
      .map((result, index) => (result.status === 'rejected' ? loaders[index][0] : null))
      .filter(Boolean);
    if (failedLabels.length) {
      formBanner.value = `Không thể tải ${failedLabels.join(', ')}. Vui lòng làm mới trang hoặc thử lại sau.`;
      toast.error(formBanner.value);
    }
    await openDraftFromRoute();
  }
  if (formOpen.value) {
    await nextTick();
    ensureMapInitialized();
  }
});

onBeforeUnmount(() => {
  try {
    if (bankTimer.value) clearTimeout(bankTimer.value);
    if (mapTimer.value) clearTimeout(mapTimer.value);
    destroyMapPicker();
  } catch (e) {
    // Prevent unmount exceptions from blocking navigation
  }
});

watch(() => route.name, (name) => {
  if (name === 'partner-application') {
    formOpen.value = true;
  } else if (name === 'partner-registration') {
    formOpen.value = false;
  }
}, { immediate: true });

watch(() => form.venue_province_code, async (code, old) => {
  if (code !== old) { form.venue_ward_code = ''; wards.value = []; await loadWards(code); syncVenueAddress(); }
});
watch(() => form.venue_ward_code, syncVenueAddress);
watch(formOpen, async (open) => {
  if (open) {
    await nextTick();
    ensureMapInitialized();
    initScrollSpy();
    return;
  }
  destroyMapPicker();
  if (scrollObserver) scrollObserver.disconnect();
});
watch(() => [form.venue_latitude, form.venue_longitude], updateMapPickerMarker);
watch(() => route.query.editDraft, async () => {
  if (route.name === 'partner-application') {
    await openDraftFromRoute();
  }
});

// ─── Helpers ──────────────────────────────────────────────────────────────────
function defaultForm(authUser) {
  return {
    applicant_full_name: authUser?.fullName || '', applicant_phone: authUser?.phone || '',
    applicant_email: authUser?.email || '', applicant_birth_date: '', applicant_address: '',
    applicant_type: 'individual', representative_name: authUser?.fullName || '',
    representative_identity_type: 'cccd', representative_identity_number: '',
    representative_identity_issued_date: '', representative_identity_issued_place: '',
    representative_position: 'Chủ cơ sở', business_name: '', tax_code: '', business_code: '',
    business_license_number: '', business_address: '', venue_name: '', street_address: '',
    venue_address: '', venue_province_code: '', venue_ward_code: '', venue_map_url: '',
    venue_latitude: '', venue_longitude: '', venue_phone: authUser?.phone || '',
    venue_email: authUser?.email || '', venue_description: '', expected_opening_hours: '05:00 - 23:00',
    parking_info: '', amenities: [], court_count_total: 1, base_price_per_hour: '',
    courts: [{ local_id: localId(), name: 'Sân 1', court_type_id: '', note: '' }],
    bank_name: '', bank_code: '', bank_bin: '', account_number: '', account_holder_name: '', bank_branch: '',
  };
}

function blankFiles() { return { identity: [], business_license: [], facility: [], bank: [], lease: [], additional: [] }; }
function blankExistingDocuments() { return { identity: [], business_license: [], facility: [], bank: [], lease: [], additional: [] }; }
function localId() { return `local-${Math.random().toString(36).slice(2)}-${Date.now()}`; }

function normalizeList(data) {
  if (Array.isArray(data)) return data;
  if (Array.isArray(data?.data)) return data.data;
  return [];
}

function readCache(key) {
  try { const p = JSON.parse(localStorage.getItem(key) || 'null'); if (!p || Date.now() > p.expires_at) return null; return p.value; } catch { return null; }
}
function writeCache(key, value, ttl) { localStorage.setItem(key, JSON.stringify({ value, expires_at: Date.now() + ttl })); }

function inputClass(error, extra = '') {
  return ['form-control', error ? 'has-error is-invalid' : '', extra].filter(Boolean).join(' ');
}
function textareaClass(error) { return ['form-textarea', error ? 'has-error' : '']; }
// ─── Data loaders ─────────────────────────────────────────────────────────────
async function loadApplications() {
  loading.value = true;
  try {
    const r = await api('/api/user/partner-application');
    applications.value = r.data?.history || [];
    onboardingTerms.value = r.data?.onboarding_terms || onboardingTerms.value;
    canRegister.value = Boolean(r.data?.can_register);
    pageError.value = '';
  } catch (error) {
    canRegister.value = false;
    pageError.value = error.message || 'Không thể tải danh sách hồ sơ. Quyền tạo hồ sơ mới tạm khóa để tránh gửi trùng.';
    throw error;
  } finally {
    loading.value = false;
  }
}

async function refreshApplications() {
  try {
    await loadApplications();
  } catch {
    toast.error(pageError.value);
  }
}
async function loadOnboardingTerms() {
  const r = await api('/api/user/partner-application/terms');
  onboardingTerms.value = r.data || null;
}
async function loadBanks() {
  const cached = readCache(BANK_CACHE_KEY);
  if (cached?.length) { banks.value = cached; return; }
  const r = await api('/api/user/partner-application/banks');
  banks.value = normalizeList(r.data);
  if (banks.value.length) writeCache(BANK_CACHE_KEY, banks.value, BANK_CACHE_TTL);
}
async function loadProvinces() { const r = await api('/api/user/partner-application/provinces'); provinces.value = normalizeList(r.data); }
async function loadWards(code) {
  if (!code) return;
  try {
    const r = await api(`/api/user/partner-application/provinces/${code}/wards`);
    wards.value = normalizeList(r.data);
    delete fieldErrors.venue_ward_code;
  } catch (error) {
    wards.value = [];
    fieldErrors.venue_ward_code = error.message || 'Không thể tải danh sách Phường/Xã. Vui lòng thử lại.';
    toast.error(fieldErrors.venue_ward_code);
  }
}
async function loadCourtTypes() { const r = await api('/api/court-types'); courtTypes.value = normalizeList(r.data); }
async function loadAmenities() { const r = await api('/api/amenities?active_only=1'); amenities.value = normalizeList(r.data); }

// ─── Form lifecycle ───────────────────────────────────────────────────────────
function startNewApplication() {
  if (!user) {
    router.push({ name: 'login', query: { redirect: '/partner-application' } });
    return;
  }
  editingApplicationId.value = '';
  editingApplicationStatus.value = '';
  resetForm(defaultForm(user));
  if (route.name !== 'partner-application') {
    router.push({ name: 'partner-application' });
  } else {
    formOpen.value = true;
  }
}

function resetForm(next) {
  Object.assign(form, next);
  Object.assign(files, blankFiles());
  Object.assign(existingDocuments, blankExistingDocuments());
  uploadResetKey.value += 1;
  clearErrors();
  formBanner.value = '';
  confirmed.value = false;
  mapError.value = '';
  mapStatus.value = '';
  mapSuggestion.value = null;
  mapReverseBusy.value = false;
}

function persistDraft(showMessage = false) {
  const payload = { ...form, editing_application_id: editingApplicationId.value || '', saved_at: new Date().toISOString() };
  localStorage.setItem(DRAFT_KEY, JSON.stringify(payload));
  draft.value = payload;
  if (showMessage) formBanner.value = 'Đã lưu nháp hồ sơ trên trình duyệt.';
}

function saveDraft() {
  persistDraft(true);
}

function closeForm(event) {
  event?.preventDefault();
  persistDraft(false);
  formOpen.value = false;

  if (route.name !== 'partner-registration') {
    router.push({ name: 'partner-registration' });
  } else if (route.query.editDraft) {
    const query = { ...route.query };
    delete query.editDraft;
    router.replace({ query });
  }
}

function loadDraft() {
  try { draft.value = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch { draft.value = null; }
}

async function continueDraft() {
  if (!draft.value) return;
  const localDraft = { ...draft.value };
  const applicationId = localDraft.editing_application_id || editingApplicationId.value || '';
  editingApplicationId.value = applicationId;

  if (applicationId) {
    try {
      const response = await api(`/api/user/partner-application/${applicationId}`);
      const application = response.data;
      if (!application || !['draft', 'need_supplement'].includes(application.status)) {
        toast.error('Hồ sơ này không còn ở trạng thái cho phép chỉnh sửa.');
        await refreshApplications();
        return;
      }
      loadApplicationIntoForm(application);
      Object.keys(defaultForm(user)).forEach((key) => {
        if (Object.prototype.hasOwnProperty.call(localDraft, key)) form[key] = localDraft[key];
      });
    } catch (error) {
      toast.error(error.message || 'Không thể tải hồ sơ gốc để tiếp tục bản nháp. Vui lòng thử lại.');
      return;
    }
  } else {
    editingApplicationStatus.value = '';
    resetForm({ ...defaultForm(user), ...localDraft });
  }

  formOpen.value = true;
  if (form.venue_province_code) await loadWards(form.venue_province_code);
  syncVenueAddress();
}

function clearDraft() {
  localStorage.removeItem(DRAFT_KEY);
  draft.value = null;
  editingApplicationId.value = '';
  editingApplicationStatus.value = '';
}

async function openDraftFromRoute() {
  const id = route.query.editDraft ? String(route.query.editDraft) : '';
  if (!id) return;

  const application = applications.value.find((item) => String(item.id) === id);
  if (application && ['draft', 'need_supplement'].includes(application.status)) {
    editingApplicationId.value = id;
    loadApplicationIntoForm(application);
    formOpen.value = true;
    if (form.venue_province_code) await loadWards(form.venue_province_code);
    syncVenueAddress();
    formBanner.value = application.status === 'need_supplement'
      ? 'Bạn đang bổ sung hồ sơ theo yêu cầu của SportGo. Bấm gửi để hệ thống tạo lại đơn đăng ký mới.'
      : 'Bạn đang sửa bản nháp. Bấm gửi để hệ thống tạo lại đơn đăng ký mới.';
    return;
  }

  if (draft.value) {
    editingApplicationId.value = id;
    await continueDraft();
  }
}

async function editApplication(application) {
  if (!application) return;
  editingApplicationId.value = application.id;
  loadApplicationIntoForm(application);
  formOpen.value = true;
  if (form.venue_province_code) await loadWards(form.venue_province_code);
  syncVenueAddress();
  formBanner.value = 'Bạn đang bổ sung/chỉnh sửa hồ sơ. Sau khi gửi, hệ thống sẽ tạo lại đơn đăng ký để bạn xem và ký lại.';
}

async function duplicateApplication(application) {
  if (!application) return;
  editingApplicationId.value = '';
  editingApplicationStatus.value = '';
  loadApplicationIntoForm(application);
  editingApplicationStatus.value = '';
  Object.assign(existingDocuments, blankExistingDocuments());
  uploadResetKey.value += 1;
  confirmed.value = false;
  formOpen.value = true;
  if (form.venue_province_code) await loadWards(form.venue_province_code);
  syncVenueAddress();
  formBanner.value = 'Đã tạo bản sao thông tin từ hồ sơ bị từ chối. Vui lòng kiểm tra, tải lại giấy tờ bắt buộc và gửi hồ sơ mới.';
}

function loadApplicationIntoForm(application) {
  const savedDocuments = application.documents || application.uploaded_documents || [];
  const activeSavedDocuments = savedDocuments.filter((doc) => doc.status !== 'rejected' && doc.file_available !== false);
  editingApplicationStatus.value = application.status || '';
  resetForm({
    ...defaultForm(user),
    applicant_full_name: application.applicant_full_name || '',
    applicant_phone: application.applicant_phone || '',
    applicant_email: application.applicant_email || '',
    applicant_birth_date: dateInputValue(application.applicant_birth_date),
    applicant_address: application.applicant_address || '',
    applicant_type: application.applicant_type || 'individual',
    representative_name: application.representative_name || '',
    representative_identity_type: application.representative_identity_type || 'cccd',
    representative_identity_number: application.representative_identity_number || '',
    representative_identity_issued_date: dateInputValue(application.representative_identity_issued_date),
    representative_identity_issued_place: application.representative_identity_issued_place || '',
    representative_position: application.representative_position || 'Chủ cơ sở',
    business_name: application.business_name || '',
    tax_code: application.tax_code || '',
    business_code: application.business_code || '',
    business_license_number: application.business_license_number || '',
    business_address: application.business_address || '',
    venue_name: application.venue_name || '',
    street_address: streetFromVenueAddress(application),
    venue_address: application.venue_address || '',
    venue_province_code: application.venue_province_code || '',
    venue_ward_code: application.venue_ward_code || '',
    venue_map_url: application.venue_map_url || '',
    venue_latitude: application.venue_latitude || '',
    venue_longitude: application.venue_longitude || '',
    venue_phone: application.venue_phone || '',
    venue_email: application.venue_email || '',
    venue_description: application.venue_description || '',
    expected_opening_hours: application.expected_opening_hours || '05:00 - 23:00',
    parking_info: application.parking_info || '',
    amenities: Array.isArray(application.amenities) ? application.amenities : [],
    court_count_total: application.court_count_total || Math.max(1, (application.courts || []).length),
    base_price_per_hour: application.base_price_per_hour || '',
    courts: applicationCourtsForForm(application),
    bank_name: application.bank_name || '',
    bank_code: application.bank_code || '',
    bank_bin: '',
    account_number: application.account_number || '',
    account_holder_name: application.account_holder_name || '',
    bank_branch: application.bank_branch || '',
  });
  Object.assign(existingDocuments, groupExistingDocuments(activeSavedDocuments));
  confirmed.value = true;
}

function groupExistingDocuments(documents = []) {
  const groups = blankExistingDocuments();
  documents.forEach((document) => {
    if (document.status === 'rejected' || document.file_available === false) return;
    const group = existingDocumentGroup(document);
    if (group && groups[group]) groups[group].push(document);
  });
  return groups;
}

function existingDocumentGroup(document) {
  const type = document?.document_type;
  const typeGroups = {
    identity: 'identity', identity_front: 'identity', identity_back: 'identity',
    business_license: 'business_license', business_registration: 'business_license',
    facility: 'facility', venue_front_image: 'facility', court_area_image: 'facility', parking_area_image: 'facility',
    bank: 'bank', bank_account_proof: 'bank',
    lease: 'lease', lease_contract: 'lease',
    additional: 'additional',
  };
  if (typeGroups[type]) return typeGroups[type];
  return {
    legal_identity: 'identity',
    identity_documents: 'identity',
    business_license: 'business_license',
    business_documents: 'business_license',
    facility_images: 'facility',
    venue_images: 'facility',
    bank_documents: 'bank',
    lease_contract: 'lease',
    land_documents: 'lease',
    additional_documents: 'additional',
  }[document?.document_group] || null;
}

function dateInputValue(value) {
  if (!value) return '';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? String(value).slice(0, 10) : date.toISOString().slice(0, 10);
}

function streetFromVenueAddress(application) {
  const address = application.venue_address || '';
  const ward = application.venue_ward || '';
  const province = application.venue_province || '';
  return address
    .replace(ward ? `, ${ward}` : '', '')
    .replace(province ? `, ${province}` : '', '')
    .trim()
    .replace(/,\s*$/, '');
}

function applicationCourtsForForm(application) {
  const rows = (application.courts || []).map((court, index) => ({
    local_id: localId(),
    name: court.name || `Sân ${index + 1}`,
    court_type_id: court.court_type_id || '',
    note: court.note || '',
  }));

  return rows.length ? rows : [{ local_id: localId(), name: 'Sân 1', court_type_id: '', note: '' }];
}

function sanitizePhoneCharacters(field) {
  let value = String(form[field] || '').replace(/[^\d+]/g, '');
  if (value.includes('+')) value = `+${value.replace(/\+/g, '')}`;
  form[field] = value;
}

function normalizeIdentityNumber() {
  const v = String(form.representative_identity_number || '');
  form.representative_identity_number = form.representative_identity_type === 'passport' ? v.replace(/[^a-zA-Z0-9]/g, '').toUpperCase() : v.replace(/\D/g, '');
}

function normalizeTaxCode() { form.tax_code = String(form.tax_code || '').replace(/[^\d-]/g, ''); }

// ─── Bank verification ────────────────────────────────────────────────────────
function selectBank(bank) { form.bank_name = bank?.short_name || bank?.name || ''; form.bank_bin = bank?.bin || ''; }
function onAccountNumberInput() { sanitizeDigitsField('account_number'); }
function sanitizeDigitsField(field) { form[field] = String(form[field] || '').replace(/\D/g, ''); }
function onCourtCountInput() {
  sanitizeDigitsField('court_count_total');
  const total = Number(form.court_count_total);
  if (Number.isInteger(total) && total >= 1 && total <= 100) syncCourtRows();
}
function sanitizeCoordinate(field) {
  let value = String(form[field] || '').replace(/[^0-9.-]/g, '');
  value = value.replace(/(?!^)-/g, '');
  const parts = value.split('.');
  if (parts.length > 2) value = `${parts.shift()}.${parts.join('')}`;
  form[field] = value;
}
function onManualBankHolderInput() {
  form.account_holder_name = String(form.account_holder_name || '').toUpperCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/đ/g, "d").replace(/Đ/g, "D");
}

// ─── Address / Map ────────────────────────────────────────────────────────────
function onProvinceSelect() { form.venue_ward_code = ''; wards.value = []; syncVenueAddress(); }

function syncVenueAddress() {
  const province = provinces.value.find((p) => String(p.code) === String(form.venue_province_code))?.name;
  const ward = wards.value.find((w) => String(w.code) === String(form.venue_ward_code))?.name;
  form.venue_address = [form.street_address, ward, province].filter(Boolean).join(', ');
  if (
    mapSuggestion.value
    && (!mapSuggestion.value.province_code || String(mapSuggestion.value.province_code) === String(form.venue_province_code))
    && (!mapSuggestion.value.ward_code || String(mapSuggestion.value.ward_code) === String(form.venue_ward_code))
  ) {
    mapSuggestion.value = null;
    mapStatus.value = 'Đã cập nhật địa chỉ theo tọa độ bản đồ.';
  }
}

function onGoogleMapUrlInput() {
  parseGoogleMapUrl(true);
}

function onGoogleMapUrlPaste() {
  setTimeout(() => parseGoogleMapUrl(false), 120);
}

async function parseGoogleMapUrl(silent = false) {
  const rawUrl = String(form.venue_map_url || '').trim();
  if (!rawUrl) {
    if (!silent) toast.info('Vui lòng dán liên kết Google Maps trước khi bấm trích xuất.');
    return false;
  }

  locationModal.show = true;
  locationModal.loading = true;
  locationModal.resolvedData = null;

  let lat = null;
  let lng = null;

  // Pattern 1: @lat,lng e.g. @10.801234,106.691234
  const atMatch = rawUrl.match(/@(-?\d+\.\d+),(-?\d+\.\d+)/);

  // Pattern 2: !3d10.801234...!4d106.691234
  const d3d4Match = rawUrl.match(/!3d(-?\d+\.\d+)(?:.*?)!4d(-?\d+\.\d+)/);

  // Pattern 3: ?q=10.801234,106.691234 or ?query=10.801234,106.691234 or &ll=10.801234,106.691234
  const qMatch = rawUrl.match(/[?&](?:q|ll|query)=(-?\d+\.\d+)[,%2C\s]+(-?\d+\.\d+)/i);

  // Pattern 4: /search/10.801234,+106.691234 or /dir//10.801234,106.691234
  const searchMatch = rawUrl.match(/\/(?:search|dir)\/(?:[^\/]*\/)*(-?\d+\.\d+)[,%2C\s]+(-?\d+\.\d+)/i);

  // Pattern 5: Direct raw coordinates e.g. 10.801234, 106.691234 or 10.801234 106.691234
  const rawCoordMatch = rawUrl.match(/^@?(-?\d+\.\d{4,})[\s,]+(-?\d+\.\d{4,})$/);

  if (d3d4Match) {
    lat = parseFloat(d3d4Match[1]);
    lng = parseFloat(d3d4Match[2]);
  } else if (qMatch) {
    lat = parseFloat(qMatch[1]);
    lng = parseFloat(qMatch[2]);
  } else if (searchMatch) {
    lat = parseFloat(searchMatch[1]);
    lng = parseFloat(searchMatch[2]);
  } else if (rawCoordMatch) {
    lat = parseFloat(rawCoordMatch[1]);
    lng = parseFloat(rawCoordMatch[2]);
  } else if (atMatch) {
    lat = parseFloat(atMatch[1]);
    lng = parseFloat(atMatch[2]);
  }

  if (lat !== null && lng !== null && !isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
    const formattedLat = Number(lat).toFixed(7);
    const formattedLng = Number(lng).toFixed(7);
    form.venue_latitude = formattedLat;
    form.venue_longitude = formattedLng;
    form.latitude = formattedLat;
    form.longitude = formattedLng;

    if (mapInstance.value) {
      mapInstance.value.setView([lat, lng], 16);
      if (mapMarker.value) {
        mapMarker.value.setLatLng([lat, lng]);
      } else {
        mapMarker.value = L.marker([lat, lng], { draggable: true }).addTo(mapInstance.value);
        mapMarker.value.on('dragend', (event) => applyPickedCoordinates(event.target.getLatLng()));
      }
    } else {
      initMapPicker();
    }

    await reverseCoordinates(formattedLat, formattedLng, { overwriteStreet: true, applyLocation: true, silent });
    return true;
  }

  // 2. Call backend resolve-map API to expand shortlink redirects (e.g. maps.app.goo.gl)
  try {
    const response = await api('/api/user/partner-application/resolve-map', {
      method: 'POST',
      body: JSON.stringify({ url: rawUrl }),
    });

    const resData = response?.data;
    if (resData && resData.latitude && resData.longitude) {
      const bLat = Number(resData.latitude).toFixed(7);
      const bLng = Number(resData.longitude).toFixed(7);
      applyPickedCoordinates({ lat: bLat, lng: bLng });
      if (mapInstance.value) {
        mapInstance.value.setView([resData.latitude, resData.longitude], 16);
        if (mapMarker.value) {
          mapMarker.value.setLatLng([resData.latitude, resData.longitude]);
        } else {
          mapMarker.value = L.marker([resData.latitude, resData.longitude], { draggable: true }).addTo(mapInstance.value);
        }
      } else {
        initMapPicker();
      }

      await compareResolvedAddress(resData, { overwriteStreet: true, applyLocation: true });

      if (!silent) toast.success(`Đã giải mã link Google Maps & tự động chọn Tỉnh/Thành phố, Phường/Xã thành công!`);
      return true;
    }
  } catch (err) {
    if (!silent && err?.message) {
      toast.error(err.message);
      return false;
    }
  }

  // 3. Fallback Geocoding Search
  const queryAddress = [form.venue_name, form.street_address, form.venue_address].filter(Boolean).join(', ');
  if (queryAddress) {
    try {
      const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(queryAddress)}&limit=1`);
      const data = await res.json();
      if (data && data.length > 0) {
        const gLat = parseFloat(data[0].lat);
        const gLng = parseFloat(data[0].lon);
        const fLat = Number(gLat).toFixed(7);
        const fLng = Number(gLng).toFixed(7);
        applyPickedCoordinates({ lat: fLat, lng: fLng });

        if (mapInstance.value) {
          mapInstance.value.setView([gLat, gLng], 16);
          if (mapMarker.value) mapMarker.value.setLatLng([gLat, gLng]);
        } else {
          initMapPicker();
        }
        if (!silent) toast.success(`Đã xác định vị trí theo địa chỉ: ${data[0].display_name.slice(0, 55)}...`);
        return true;
      }
    } catch (err) {
      // Ignore geocode network errors
    }
  }

  if (!silent) {
    toast.warning('Không thể trích xuất tọa độ từ đường link này. Vui lòng mở link trên Google Maps và chọn "Chia sẻ" -> sao chép link dạng đầy đủ, hoặc nhấp chọn trực tiếp vị trí trên bản đồ.');
  }
  return false;
}

function ensureMapInitialized(retries = 5) {
  if (mapInstance.value) {
    mapInstance.value.invalidateSize();
    return true;
  }
  const container = mapContainer.value || document.getElementById('partner-application-map');
  if (container) {
    initMapPicker();
    return true;
  }
  if (retries > 0) {
    setTimeout(() => ensureMapInitialized(retries - 1), 100);
  }
  return false;
}

function initMapPicker() {
  if (mapInstance.value) {
    mapInstance.value.invalidateSize();
    return;
  }
  const container = mapContainer.value || document.getElementById('partner-application-map');
  if (!container) return;
  const lat = validLatitude(form.venue_latitude) ? Number(form.venue_latitude) : 21.0285;
  const lng = validLongitude(form.venue_longitude) ? Number(form.venue_longitude) : 105.8542;
  const DefaultIcon = L.icon({
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41],
  });
  L.Marker.prototype.options.icon = DefaultIcon;
  mapInstance.value = L.map(container, { scrollWheelZoom: false }).setView([lat, lng], 15);
  L.tileLayer('https://mt1.google.com/vt/lyrs=m&hl=vi&x={x}&y={y}&z={z}', {
    attribution: '&copy; Google Maps',
    maxZoom: 20,
  }).addTo(mapInstance.value);
  mapMarker.value = L.marker([lat, lng], { draggable: true }).addTo(mapInstance.value);
  mapMarker.value.on('dragend', (event) => applyPickedCoordinates(event.target.getLatLng()));
  mapInstance.value.on('click', (event) => applyPickedCoordinates(event.latlng));
  const invalidate = () => mapInstance.value?.invalidateSize();
  setTimeout(invalidate, 100);
  setTimeout(invalidate, 300);
  setTimeout(invalidate, 600);
}

function destroyMapPicker() {
  if (!mapInstance.value) return;
  mapInstance.value.remove();
  mapInstance.value = null;
  mapMarker.value = null;
}

function getCurrentLocation() {
  if (navigator.geolocation) {
    mapError.value = '';
    mapStatus.value = 'Đang lấy vị trí hiện tại của bạn...';
    navigator.geolocation.getCurrentPosition(
      (position) => {
        mapStatus.value = '';
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        applyPickedCoordinates({ lat, lng });
        ensureMapInitialized();
        if (mapInstance.value) {
          mapInstance.value.setView([lat, lng], 16);
          if (mapMarker.value) {
            mapMarker.value.setLatLng([lat, lng]);
          } else {
            mapMarker.value = L.marker([lat, lng], { draggable: true }).addTo(mapInstance.value);
            mapMarker.value.on('dragend', (event) => applyPickedCoordinates(event.target.getLatLng()));
          }
          mapInstance.value.invalidateSize();
        }
        toast.success('Đã lấy vị trí hiện tại thành công!');
      },
      (err) => {
        mapStatus.value = '';
        let errorMsg = 'Không thể lấy vị trí. Hãy kiểm tra quyền truy cập vị trí của trình duyệt.';
        if (err.code === 1) {
          errorMsg = 'Bạn đã từ chối quyền truy cập vị trí trên trình duyệt.';
        } else if (err.code === 2) {
          errorMsg = 'Không thể xác định vị trí thiết bị hiện tại.';
        } else if (err.code === 3) {
          errorMsg = 'Quá thời gian chờ lấy vị trí.';
        }
        mapError.value = errorMsg;
        toast.error(errorMsg);
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
    );
  } else {
    mapError.value = 'Trình duyệt này không hỗ trợ định vị. Bạn vẫn có thể chọn vị trí trực tiếp trên bản đồ.';
    toast.error(mapError.value);
  }
}

function applyPickedCoordinates(point) {
  const lat = Number(point.lat).toFixed(7);
  const lng = Number(point.lng).toFixed(7);
  form.venue_latitude = lat;
  form.venue_longitude = lng;
  form.venue_map_url = googleMapsPointUrl(lat, lng);
  mapError.value = '';
  mapStatus.value = '';
  mapSuggestion.value = null;
  delete fieldErrors.venue_coordinates;
  delete fieldErrors.venue_latitude;
  delete fieldErrors.venue_longitude;
  delete fieldErrors.venue_map_url;

  locationModal.show = true;
  locationModal.loading = true;
  locationModal.resolvedData = null;

  reverseCoordinates(lat, lng, { overwriteStreet: true });
}

function googleMapsPointUrl(lat, lng) {
  return `https://www.google.com/maps?q=${lat},${lng}`;
}

function updateMapPickerMarker() {
  if (!mapInstance.value) {
    if (validLatitude(form.venue_latitude) && validLongitude(form.venue_longitude)) {
      ensureMapInitialized();
    }
    return;
  }
  if (!validLatitude(form.venue_latitude) || !validLongitude(form.venue_longitude)) return;
  const lat = Number(form.venue_latitude);
  const lng = Number(form.venue_longitude);
  if (!mapMarker.value) {
    mapMarker.value = L.marker([lat, lng], { draggable: true }).addTo(mapInstance.value);
    mapMarker.value.on('dragend', (event) => applyPickedCoordinates(event.target.getLatLng()));
  } else {
    const current = mapMarker.value.getLatLng();
    if (Math.abs(current.lat - lat) >= 0.000001 || Math.abs(current.lng - lng) >= 0.000001) {
      mapMarker.value.setLatLng([lat, lng]);
    }
  }
  mapInstance.value.setView([lat, lng], mapInstance.value.getZoom() || 15);
  mapInstance.value.invalidateSize();
}

function validLatitude(value) {
  const number = Number(value);
  return Number.isFinite(number) && number >= -90 && number <= 90;
}

function validLongitude(value) {
  const number = Number(value);
  return Number.isFinite(number) && number >= -180 && number <= 180;
}

function onMapUrlInput() {
  clearTimeout(mapTimer.value); mapError.value = ''; mapStatus.value = ''; mapSuggestion.value = null;
  form.venue_latitude = ''; form.venue_longitude = '';
  if (!form.venue_map_url) return;
  mapTimer.value = window.setTimeout(resolveMapUrl, 500);
}

async function resolveMapUrl() {
  mapError.value = '';
  locationModal.show = true;
  locationModal.loading = true;
  locationModal.resolvedData = null;

  let urlToResolve = (form.venue_map_url || '').trim();
  if (urlToResolve && !/^https?:\/\//i.test(urlToResolve)) {
    urlToResolve = 'https://' + urlToResolve;
  }
  try {
    const r = await api('/api/user/partner-application/resolve-map', { method: 'POST', body: JSON.stringify({ url: urlToResolve }) });
    const resolved = r.data || {};
    if (resolved.latitude && resolved.longitude) {
      form.venue_map_url = resolved.final_url || urlToResolve;
      form.venue_latitude = Number(resolved.latitude).toFixed(7);
      form.venue_longitude = Number(resolved.longitude).toFixed(7);
      await compareResolvedAddress(resolved, { overwriteStreet: false });
      return;
    }
  } catch (e) { console.error('Lỗi phân giải map:', e); }
  const coords = extractCoordinates(urlToResolve);
  if (!coords && !form.venue_latitude) {
    locationModal.show = false;
    locationModal.loading = false;
    mapError.value = 'Không lấy được tọa độ từ link Google Maps này. Vui lòng dùng link đầy đủ có tọa độ.';
    return;
  }
  if (coords) {
    form.venue_latitude = Number(coords.latitude).toFixed(7);
    form.venue_longitude = Number(coords.longitude).toFixed(7);
    await reverseCoordinates(form.venue_latitude, form.venue_longitude, { overwriteStreet: false });
  }
}

function extractCoordinates(url) {
  const d = decodeURIComponent(url || '');
  for (const p of [/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/, /!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/, /[?&](?:q|ll|query)=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/, /[?&]center=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/]) {
    const m = d.match(p); if (m) return { latitude: Number(m[1]), longitude: Number(m[2]) };
  }
  return null;
}

function streetFromAddress(address) {
  const parts = String(address || '').split(',').map((p) => p.trim()).filter(Boolean);
  if (!parts.length) return '';
  if (parts.length >= 2 && !/^(đường|phố|số|ngõ|ngách|hẻm|đại lộ|kđt|khu)/i.test(parts[0])) {
    return `${parts[0]}, ${parts[1]}`;
  }
  return parts[0];
}

async function reverseCoordinates(latitude, longitude, options = {}) {
  if (!validLatitude(latitude) || !validLongitude(longitude)) {
    locationModal.show = false;
    locationModal.loading = false;
    return;
  }
  mapReverseBusy.value = true;
  try {
    const r = await api('/api/user/partner-application/reverse-map', {
      method: 'POST',
      body: JSON.stringify({ latitude, longitude }),
    });
    await compareResolvedAddress(r.data || {}, options);
  } catch (e) {
    locationModal.show = false;
    locationModal.loading = false;
    toast.info('Không xác minh được Tỉnh/Thành phố từ tọa độ này. Bạn vui lòng tự chọn thủ công.');
  } finally {
    mapReverseBusy.value = false;
  }
}

async function compareResolvedAddress(resolved, options = {}) {
  const rp = resolved.province_code ? String(resolved.province_code) : '';
  const rw = resolved.ward_code ? String(resolved.ward_code) : '';

  let provName = resolved.province || '';
  let wardName = resolved.ward || '';

  if (rp && !provName) {
    provName = provinces.value.find((p) => String(p.code) === String(rp))?.name || '';
  }
  if (rp && rw && !wardName) {
    let wList = wards.value;
    if (!wList.length || String(wList[0]?.province_code) !== rp) {
      try {
        const res = await api(`/api/user/partner-application/provinces/${rp}/wards`);
        wList = normalizeList(res.data);
      } catch (e) {
        wList = [];
      }
    }
    wardName = (wList || []).find((w) => String(w.code) === String(rw))?.name || '';
  }

  const streetName = streetFromAddress(resolved.address) || '';
  const fullAddr = [streetName, wardName, provName].filter(Boolean).join(', ') || resolved.address || '';

  if (rp) {
    locationModal.loading = false;
    locationModal.resolvedData = {
      province_code: rp,
      ward_code: rw,
      street_address: streetName,
      province_name: provName,
      ward_name: wardName,
      full_address: fullAddr,
    };
  } else {
    locationModal.show = false;
    locationModal.loading = false;
    toast.info('Không nhận diện được Tỉnh/Thành phố từ vị trí này. Bạn có thể tự chọn thủ công.');
  }
}

async function acceptLocationModal() {
  if (!locationModal.resolvedData) return;
  const data = locationModal.resolvedData;
  if (data.province_code) {
    form.venue_province_code = String(data.province_code);
    await loadWards(form.venue_province_code);
  }
  if (data.ward_code) {
    form.venue_ward_code = String(data.ward_code);
  }
  if (data.street_address) {
    form.street_address = data.street_address;
  }
  syncVenueAddress();
  locationModal.show = false;
  locationModal.resolvedData = null;
  toast.success('Đã chọn Tỉnh/Thành phố & Phường/Xã theo vị trí nhận diện!');
}

function rejectLocationModal() {
  locationModal.show = false;
  locationModal.loading = false;
  locationModal.resolvedData = null;
  toast.info('Đã từ chối áp dụng tự động. Bạn có thể chọn Tỉnh/Thành phố & Phường/Xã thủ công.');
}

// ─── Courts ───────────────────────────────────────────────────────────────────
function syncCourtRows() {
  const total = Math.max(1, Number(form.court_count_total || 1));
  while (form.courts.length < total) form.courts.push({ local_id: localId(), name: `Sân ${form.courts.length + 1}`, court_type_id: form.courts[0]?.court_type_id || '', note: '' });
  if (form.courts.length > total) form.courts.splice(total);
}
function removeCourt(index) { if (form.courts.length <= 1) return; form.courts.splice(index, 1); form.court_count_total = form.courts.length; }

// ─── Files ────────────────────────────────────────────────────────────────────
function setFiles(group, event) { files[group] = Array.from(event.target.files || []); }
function removeFile(group, index) { files[group].splice(index, 1); }
function hasDocumentForGroup(group) {
  return files[group]?.length > 0 || (existingDocuments[group] || [])
    .some((document) => document.status !== 'rejected' && document.file_available !== false);
}

// ─── Validation ───────────────────────────────────────────────────────────────
function validateForm() {
  clearErrors();
  const required = {
    applicant_full_name: 'Vui lòng nhập họ tên người đăng ký.',
    applicant_phone: 'Vui lòng nhập số điện thoại.',
    applicant_email: 'Vui lòng nhập email.',
    applicant_birth_date: 'Vui lòng nhập ngày sinh.',
    applicant_address: 'Vui lòng nhập địa chỉ liên hệ.',
    representative_name: 'Vui lòng nhập người đại diện.',
    representative_identity_number: 'Vui lòng nhập số giấy tờ.',
    business_name: 'Vui lòng nhập tên đơn vị kinh doanh.',
    business_license_number: 'Vui lòng nhập số giấy đăng ký.',
    business_address: 'Vui lòng nhập địa chỉ pháp lý.',
    bank_code: 'Vui lòng chọn ngân hàng.',
    account_number: 'Vui lòng nhập số tài khoản.',
    street_address: 'Vui lòng nhập số nhà, tên đường.',
    venue_map_url: 'Vui lòng nhập link Google Maps.',
    venue_province_code: 'Vui lòng chọn Tỉnh/Thành phố.',
    venue_ward_code: 'Vui lòng chọn Phường/Xã.',
    venue_name: 'Vui lòng nhập tên cụm sân.',
    venue_phone: 'Vui lòng nhập số điện thoại tại sân.',
    court_count_total: 'Vui lòng nhập số lượng sân con.',
    base_price_per_hour: 'Vui lòng nhập giá cơ bản.',
  };
  Object.entries(required).forEach(([f, m]) => { if (!form[f]) fieldErrors[f] = m; });
  if (form.applicant_birth_date && new Date(form.applicant_birth_date) > new Date(new Date().setFullYear(new Date().getFullYear() - 18))) fieldErrors.applicant_birth_date = 'Người đăng ký phải đủ 18 tuổi.';
  if (form.applicant_phone && !/^(0\d{9}|\+84\d{9})$/.test(form.applicant_phone)) fieldErrors.applicant_phone = 'Số điện thoại phải có 10 số và bắt đầu bằng 0 hoặc +84.';
  if (form.venue_phone && !/^(0\d{9}|\+84\d{9})$/.test(form.venue_phone)) fieldErrors.venue_phone = 'Số điện thoại sân phải có 10 số và bắt đầu bằng 0 hoặc +84.';
  if (form.applicant_email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.applicant_email)) fieldErrors.applicant_email = 'Email không đúng định dạng.';
  if (form.venue_email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.venue_email)) fieldErrors.venue_email = 'Email sân không đúng định dạng.';
  if (form.tax_code && !/^\d{10}(-?\d{3})?$/.test(form.tax_code)) fieldErrors.tax_code = 'Mã số thuế phải gồm 10 số hoặc 13 số, có thể có dấu gạch ngang sau 10 số.';
  if (form.account_number && !/^\d{6,19}$/.test(form.account_number)) fieldErrors.account_number = 'Số tài khoản phải gồm từ 6 đến 19 chữ số.';
  if (!isValidIdentity()) fieldErrors.representative_identity_number = 'Số giấy tờ không đúng định dạng đã chọn.';
  if (!validLatitude(form.venue_latitude) || !validLongitude(form.venue_longitude)) {
    fieldErrors.venue_map_url = 'Vui lòng dùng link Google Maps có tọa độ hợp lệ hoặc chọn vị trí trên bản đồ.';
    fieldErrors.venue_coordinates = 'Vui lòng chọn vị trí hợp lệ trên bản đồ.';
    if (!validLatitude(form.venue_latitude)) fieldErrors.venue_latitude = 'Vĩ độ phải từ -90 đến 90.';
    if (!validLongitude(form.venue_longitude)) fieldErrors.venue_longitude = 'Kinh độ phải từ -180 đến 180.';
  }
  if (mapSuggestion.value) {
    fieldErrors.venue_ward_code = 'Phường/Xã đang chọn chưa khớp với tọa độ bản đồ. Vui lòng bấm “Cập nhật theo Google Maps” hoặc chọn lại vị trí.';
    fieldErrors.venue_coordinates = 'Tọa độ bản đồ chưa khớp với địa chỉ đã chọn.';
  }
  if (mapReverseBusy.value) {
    fieldErrors.venue_coordinates = 'Hệ thống đang cập nhật địa chỉ theo tọa độ. Vui lòng chờ hoàn tất rồi gửi lại.';
  }
  const courtCount = Number(form.court_count_total);
  if (!Number.isInteger(courtCount) || courtCount < 1 || courtCount > 100) fieldErrors.court_count_total = 'Số lượng sân con phải từ 1 đến 100.';
  const basePrice = Number(form.base_price_per_hour);
  if (!Number.isFinite(basePrice) || basePrice < 1000 || basePrice > 100000000) fieldErrors.base_price_per_hour = 'Giá cơ bản phải từ 1.000 đến 100.000.000 VNĐ.';
  // if (!bankVerified.value && !bankManualMode.value) fieldErrors.account_number = bankError.value || 'Vui lòng chờ xác minh tài khoản ngân hàng thành công.';
  if (!form.account_holder_name) fieldErrors.account_holder_name = 'Vui lòng nhập tên chủ tài khoản.';
  if (!hasDocumentForGroup('identity')) fieldErrors.identity_documents = 'Vui lòng tải lên CCCD/CMND.';
  if (!hasDocumentForGroup('business_license')) fieldErrors.business_license_documents = 'Vui lòng tải lên giấy tờ pháp lý.';
  if (!hasDocumentForGroup('facility')) fieldErrors.facility_images = 'Vui lòng tải lên hình ảnh cơ sở.';
  if (!hasDocumentForGroup('bank')) fieldErrors.bank_documents = 'Vui lòng tải lên chứng từ ngân hàng.';
  if (!hasDocumentForGroup('lease')) fieldErrors.lease_documents = 'Vui lòng tải lên hợp đồng hoặc giấy tờ thuê mặt bằng.';
  if (!confirmed.value) fieldErrors.confirmed = 'Vui lòng xác nhận thông tin trước khi gửi.';
  form.courts.forEach((c, i) => {
    if (!c.name) fieldErrors[`courts.${i}.name`] = 'Vui lòng nhập tên sân.';
    if (!c.court_type_id) fieldErrors[`courts.${i}.court_type_id`] = 'Vui lòng chọn loại sân.';
  });
  return Object.keys(fieldErrors).length === 0;
}

async function focusFirstError() {
  await nextTick();
  const first = document.querySelector('.has-error, .upload-box--error, .form-error');
  const section = first?.closest('details.form-section');
  if (section) {
    section.open = true;
    await nextTick();
  }
  if (first && typeof first.focus === 'function') first.focus({ preventScroll: false });
  first?.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
}

function isValidIdentity() {
  const v = form.representative_identity_number || '';
  if (form.representative_identity_type === 'cccd') return /^\d{12}$/.test(v);
  if (form.representative_identity_type === 'cmnd') return /^\d{9}(\d{3})?$/.test(v);
  return /^[A-Z0-9]{6,20}$/i.test(v);
}

function clearErrors() { Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k]); }

async function navigateToApplicationRoute(target) {
  const href = router.resolve(target).href;
  const absoluteHref = new URL(href, window.location.origin).toString();
  if (target?.name && target.name !== 'partner-application') {
    window.location.href = absoluteHref;
    return;
  }

  try {
    formOpen.value = false;
    await router.push(target);
    await nextTick();
    window.setTimeout(() => {
      const targetUrl = new URL(href, window.location.origin);
      const stillPortalMounted = Boolean(document.querySelector('.partner-portal-page'));
      if (stillPortalMounted && window.location.pathname === targetUrl.pathname) {
        window.location.href = absoluteHref;
      }
    }, 80);
  } catch (error) {
    window.location.href = absoluteHref;
  }
}

// ─── Submit ───────────────────────────────────────────────────────────────────
async function submit() {
  formBanner.value = '';
  if (!validateForm()) { await focusFirstError(); return; }
  submitting.value = true;
  try {
    syncVenueAddress();
    const payload = { ...form, court_count_total: Number(form.court_count_total), base_price_per_hour: Number(form.base_price_per_hour), courts: form.courts.map((c) => ({ name: c.name, court_type_id: c.court_type_id, note: c.note || '' })) };
    const formData = new FormData();
    Object.entries(payload).forEach(([k, v]) => {
      if (['courts', 'amenities'].includes(k)) formData.append(k, JSON.stringify(v || []));
      else if (v !== null && v !== undefined) formData.append(k, v);
    });
    formData.append('confirmed', '1');
    files.identity.forEach((f) => formData.append('identity_documents[]', f));
    files.business_license.forEach((f) => formData.append('business_license_documents[]', f));
    files.facility.forEach((f) => formData.append('facility_images[]', f));
    files.bank.forEach((f) => formData.append('bank_documents[]', f));
    files.lease.forEach((f) => formData.append('lease_documents[]', f));
    files.additional.forEach((f) => formData.append('additional_documents[]', f));
    persistDraft(false);
    const endpoint = editingApplicationId.value
      ? `/api/user/partner-application/${editingApplicationId.value}/draft`
      : '/api/user/partner-application';
    const response = await apiFormData(endpoint, formData);
    const application = response.data;
    editingApplicationId.value = application.id;
    const doc = applicationWord(application);
    if (doc) {
      await navigateToApplicationRoute({ name: 'partner-application-document', params: { id: application.id, documentId: doc.id }, query: { from: 'registration' } });
      return;
    }
    formOpen.value = false; await loadApplications();
  } catch (e) {
    clearErrors();
    const errors = e.data?.errors || {};
    Object.entries(errors).forEach(([f, m]) => {
      const key = f.replace(/\.\d+$/, '');
      fieldErrors[key] = Array.isArray(m) ? m[0] : m;
    });
    if (Object.keys(errors).length) {
      await focusFirstError();
    } else {
      formBanner.value = e.message || 'Vui lòng kiểm tra lại thông tin hồ sơ.';
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  } finally { submitting.value = false; }
}

// ─── Application actions ──────────────────────────────────────────────────────
function cancelApplication(application) {
  if (!application?.id || actioningApplicationId.value) return;
  cancelTarget.value = application;
  cancelError.value = '';
}

function closeCancelConfirmation() {
  if (actioningApplicationId.value) return;
  cancelTarget.value = null;
  cancelError.value = '';
}

async function confirmCancelApplication() {
  const application = cancelTarget.value;
  if (!application?.id || actioningApplicationId.value) return;

  actioningApplicationId.value = application.id;
  try {
    await api(`/api/user/partner-application/${application.id}/cancel`, { method: 'POST', body: JSON.stringify({ reason: 'Người dùng hủy hồ sơ từ trang đăng ký đối tác.' }) });
    cancelTarget.value = null;
    toast.success('Đã hủy hồ sơ thành công.');
    await loadApplications();
  } catch (err) {
    cancelError.value = err.message || 'Không thể hủy hồ sơ lúc này.';
  } finally {
    actioningApplicationId.value = '';
  }
}

function openApplicationDetail(application) {
  navigateToApplicationRoute({ name: 'partner-application-detail', params: { id: application.id } });
}

function openApplicationDocument(document, application) {
  if (!document || !application) return;
  navigateToApplicationRoute({ name: 'partner-application-document', params: { id: application.id, documentId: document.id } });
}

function canSubmitSignedApplication(application) {
  const doc = applicationWord(application);
  return application?.status === 'draft' && doc?.status === 'completed';
}

async function submitSignedApplication(application) {
  if (!application?.id || actioningApplicationId.value) return;

  actioningApplicationId.value = application.id;
  try {
    await api(`/api/user/partner-application/${application.id}/submit`, { method: 'POST' });
    toast.success('Hồ sơ đã được gửi để SportGo xét duyệt.');
    await loadApplications();
  } catch (err) {
    toast.error(err.message || 'Không thể gửi hồ sơ lúc này.');
  } finally {
    actioningApplicationId.value = '';
  }
}

// ─── Display helpers ──────────────────────────────────────────────────────────
function applicationPrimaryAction(application) {
  if (needsChangeAppendixSignature(application)) return { type: 'appendix', icon: 'fileText', label: 'Xem và ký phụ lục' };
  if (needsApplicationSignature(application)) return { type: 'application', icon: 'pencil', label: 'Xem và ký đơn' };
  if (needsContractSignature(application)) return { type: 'contract', icon: 'fileText', label: 'Xem và ký hợp đồng' };
  if (canSubmitSignedApplication(application)) return { type: 'submit', icon: 'send', label: 'Gửi hồ sơ' };
  if (application?.status === 'need_supplement') return { type: 'supplement', icon: 'edit', label: 'Bổ sung hồ sơ' };
  if (application?.status === 'rejected') return { type: 'duplicate', icon: 'copy', label: 'Tạo hồ sơ mới từ bản này' };
  return null;
}

function runApplicationPrimaryAction(application) {
  const action = applicationPrimaryAction(application);
  if (!action) return;
  if (action.type === 'appendix') return openApplicationDocument(changeAppendixWord(application), application);
  if (action.type === 'application') return openApplicationDocument(applicationWord(application), application);
  if (action.type === 'contract') return openApplicationDocument(contractWord(application), application);
  if (action.type === 'submit') return submitSignedApplication(application);
  if (action.type === 'supplement') return editApplication(application);
  if (action.type === 'duplicate') return duplicateApplication(application);
}

function needsApplicationSignature(application) {
  if (application?.status !== 'draft') return false;
  const doc = applicationWord(application);
  if (!doc || doc.status === 'completed') return false;
  return !doc.signatures?.some(s => s.signer_side === 'owner' && s.status === 'signed');
}

function needsContractSignature(application) {
  const doc = contractWord(application);
  if (!doc || doc.status === 'completed') return false;
  return !doc.signatures?.some(s => s.signer_side === 'owner' && s.status === 'signed');
}

function needsChangeAppendixSignature(application) {
  return Boolean(changeAppendixWord(application));
}

function applicationWord(application) {
  const docs = application.generated_documents || application.generatedDocuments || [];
  return docs.find((d) => d.document_type === 'partner_application_form');
}
function contractWord(application) {
  if (!['contract_pending_owner_signature'].includes(application.status)) return null;
  const contracts = application.contracts || [];
  const pendingContract = contracts.find((c) => c.status === 'pending_owner_signature');
  if (!pendingContract) return null;
  const doc = pendingContract.generated_document;
  if (doc) return { ...doc, partner_contract_id: pendingContract.id };
  // Fallback: search in generated_documents
  const docs = application.generated_documents || application.generatedDocuments || [];
  const contractDoc = docs.find((d) => d.document_type === 'partner_contract');
  if (contractDoc) return { ...contractDoc, partner_contract_id: pendingContract.id };
  return null;
}

function changeAppendixWord(application) {
  const docs = application.generated_documents || application.generatedDocuments || [];
  return docs.find((doc) => (
    ['venue_scale_appendix', 'venue_location_appendix'].includes(doc.document_type)
    && doc.status === 'pending_owner_signature'
    && !doc.signatures?.some((signature) => signature.signer_side === 'owner' && signature.status === 'signed')
  )) || null;
}
function canCancel(application) { return ['pending', 'submitted', 'reviewing', 'need_supplement', 'draft'].includes(application.status); }
function statusLabel(status) {
  return { draft: 'Chờ ký đơn', pending: 'Chờ xét duyệt', submitted: 'Chờ xét duyệt', reviewing: 'Đang xem xét', need_supplement: 'Cần bổ sung', contract_pending_owner_signature: 'Đã duyệt, chờ ký hợp đồng', contract_pending_sportgo_signature: 'Chờ SportGo ký', completed: 'Đang hoạt động', rejected: 'Bị từ chối', cancelled: 'Đã hủy' }[status] || status || '-';
}
function statusClass(status) {
  if (['rejected', 'cancelled'].includes(status)) return 'badge-red';
  if (status === 'completed') return 'badge-emerald';
  return 'badge-amber';
}
function coordinateText(a) { if (!a?.venue_latitude || !a?.venue_longitude) return '-'; return `${a.venue_latitude}, ${a.venue_longitude}`; }
function formatDate(value) {
  if (!value) return '-';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
function dateOnly(value) {
  if (!value) return '-';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleDateString('vi-VN');
}
function money(value) {
  const n = Number(value || 0);
  if (!Number.isFinite(n) || n <= 0) return '-';
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(n);
}
</script>

<style scoped>
.partner-portal-page,
.partner-portal-page--landing {
  background-color: var(--sg-surface, #f8fafc) !important;
  min-height: 100vh;
}

.partner-portal-page .portal-main.portal-main--landing {
  max-width: 100% !important;
  width: 100% !important;
  margin: 0 !important;
  padding: 0 !important;
  overflow-x: hidden;
}

.partner-portal-page .portal-main.portal-main--form {
  max-width: 1280px !important;
  margin: 24px auto 48px !important;
  padding: 0 24px !important;
}

.portal-user-sticky-banner {
  background: #f0fdf4;
  border-bottom: none;
  color: #166534;
  padding: 12px 24px;
}

.portal-banner-inner {
  max-width: 1280px;
  margin: 0 auto;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.portal-banner-info {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  font-weight: 400;
  color: #166534;
}

.portal-banner-actions {
  display: flex;
  gap: 10px;
}

.partner-terms-band {
  background: var(--sg-surface, #f8fafc);
  padding: 48px 24px 64px;
  color: #0f172a;
}

.partner-terms-inner {
  max-width: 1200px;
  margin: 0 auto;
}

.partner-terms-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 44px;
}

@media (max-width: 768px) {
  .partner-terms-heading {
    flex-direction: column;
    align-items: flex-start;
  }
}

.partner-terms-kicker {
  display: inline-block;
  color: #5c7e6e;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.8px;
  margin-bottom: 8px;
}

.partner-terms-title {
  margin: 0 0 10px;
  color: #0f172a;
  font-size: clamp(24px, 3vw, 32px);
  font-weight: 700;
  line-height: 1.3;
}

.partner-terms-notice {
  color: #475569;
  font-size: 15px;
  line-height: 1.6;
  margin: 0;
  max-width: 760px;
}

.partner-terms-version-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  color: #475569;
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 12.5px;
  font-weight: 600;
  white-space: nowrap;
}

.partner-terms-grid {
  display: grid;
  grid-template-columns: 1.2fr 0.8fr;
  gap: 32px;
  align-items: stretch;
}

@media (max-width: 960px) {
  .partner-terms-grid {
    grid-template-columns: 1fr;
    gap: 24px;
  }
}

.partner-fee-panel,
.partner-policy-panel {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  padding: 32px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.03);
}

.partner-panel-title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 24px;
}

.partner-panel-main-title {
  margin: 0 0 6px;
  color: #0f172a;
  font-size: 18px;
  font-weight: 700;
}

.partner-panel-summary {
  margin: 0;
  color: #64748b;
  font-size: 13.5px;
  line-height: 1.5;
}

.partner-billing-pill {
  background: #f2f6f4;
  color: #486858;
  border: 1px solid #d2e0d8;
  font-size: 12px;
  font-weight: 700;
  padding: 4px 12px;
  border-radius: 999px;
  white-space: nowrap;
}

.partner-fee-rows-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 24px;
}

.partner-fee-tier-row {
  background: #ffffff;
  border-radius: 12px;
  padding: 14px 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  box-shadow: 0 2px 6px rgba(15, 23, 42, 0.02);
}

.partner-fee-tier-name {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
  min-width: 100px;
}

.tier-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #5c7e6e;
}

.partner-fee-tier-price {
  display: flex;
  align-items: baseline;
  gap: 4px;
}

.partner-fee-tier-price strong {
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
}

.partner-fee-tier-price small {
  font-size: 12px;
  color: #64748b;
}

.discount-tag {
  background: #f0fdf4;
  color: #15803d;
  border: 1px solid #bbf7d0;
  font-size: 11.5px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 6px;
}

.partner-terms-footer-note {
  margin-top: auto;
  padding-top: 16px;
  border-top: none;
}

.partner-terms-footer-note p {
  margin: 0;
  color: #64748b;
  font-size: 12.5px;
  line-height: 1.55;
}

.partner-policy-head {
  margin-bottom: 20px;
}

.partner-policy-items-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.partner-policy-item {
  background: #ffffff;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 6px rgba(15, 23, 42, 0.02);
}

.partner-policy-summary {
  cursor: pointer;
  padding: 16px 18px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
  user-select: none;
}

.partner-policy-ver {
  font-size: 11px;
  font-weight: 700;
  background: #f1f5f9;
  color: #64748b;
  padding: 2px 8px;
  border-radius: 4px;
}

.partner-policy-content {
  padding: 0 18px 16px;
  font-size: 13px;
  line-height: 1.6;
  color: #475569;
  border-top: none;
}

.partner-policy-content p {
  margin: 8px 0 0;
  white-space: pre-line;
}

.portal-modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(15, 23, 42, 0.7);
  backdrop-filter: blur(4px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

.portal-modal-container {
  background: #ffffff;
  border-radius: 12px;
  width: 100%;
  max-width: 720px;
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

.portal-modal-header {
  padding: 20px 24px;
  border-bottom: none;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.portal-modal-header h3 {
  font-size: 18px;
  font-weight: 500;
  color: #0f172a;
  margin: 0 0 4px;
}

.portal-modal-header p {
  font-size: 13px;
  font-weight: 400;
  color: #64748b;
  margin: 0;
}

.portal-modal-close {
  background: transparent;
  border: none;
  font-size: 20px;
  color: #64748b;
  cursor: pointer;
  padding: 4px 8px;
}

.portal-modal-close:hover {
  color: #0f172a;
}

.portal-modal-body {
  padding: 24px;
  overflow-y: auto;
}

.draft-banner {
  background: #f8fafc !important;
  border: 1px solid #e2e8f0 !important;
  border-radius: 10px !important;
  padding: 16px 20px !important;
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  box-shadow: none !important;
}

.draft-banner .title {
  color: #0f172a !important;
  font-weight: 500 !important;
  font-size: 15px !important;
}

.draft-state {
  color: #d97706 !important;
  font-weight: 400 !important;
}

.draft-time {
  margin-top: 4px !important;
  color: #475569 !important;
  font-size: 13px !important;
  font-weight: 400 !important;
}

.draft-actions {
  display: flex !important;
  align-items: center !important;
  gap: 12px !important;
}

.draft-delete {
  background: transparent !important;
  border: none !important;
  color: #dc2626 !important;
  font-size: 13.5px !important;
  font-weight: 400 !important;
  cursor: pointer !important;
  padding: 6px 12px !important;
}

.draft-delete:hover {
  text-decoration: underline !important;
}

.draft-continue {
  background: #5c7e6e !important;
  color: #ffffff !important;
  border: none !important;
  border-radius: 6px !important;
  padding: 8px 16px !important;
  font-size: 13.5px !important;
  font-weight: 500 !important;
  cursor: pointer !important;
  box-shadow: none !important;
}

.draft-continue:hover {
  background: #486858 !important;
}

/* WIZARD FORM REDESIGN (SPACE OPTIMIZED 1280PX 2-COLUMN) */
.portal-form-wrapper {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0;
}

.wizard-top-header {
  margin-bottom: 24px;
}

.wizard-heading-title {
  font-size: 26px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 6px;
  letter-spacing: -0.3px;
}

.wizard-heading-sub {
  font-size: 14.5px;
  font-weight: 400;
  color: #64748b;
  margin: 0;
}

/* 2-COLUMN SIDEBAR GRID */
.portal-form-grid {
  display: grid;
  grid-template-columns: 300px 1fr;
  gap: 32px;
  align-items: start;
}

.portal-form-sidebar {
  position: sticky !important;
  top: 84px !important;
  align-self: start !important;
  z-index: 10 !important;
}

.portal-form-sidebar-sticky {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.portal-sidebar-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
}

.portal-sidebar-title {
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  color: #64748b;
  margin: 0 0 12px;
}

.wizard-steps-nav {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 0;
}

.wizard-step-link {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  text-decoration: none;
  color: #475569;
  font-size: 13.5px;
  cursor: pointer;
  transition: all 0.18s ease;
  width: 100%;
  text-align: left;
}

.wizard-step-link span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  min-width: 28px;
  border-radius: 50%;
  background: #f1f5f9;
  color: #475569;
  font-size: 13px;
  font-weight: 700;
  transition: all 0.18s ease;
}

.wizard-step-link .step-text {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.wizard-step-link .step-text strong {
  font-size: 13.5px;
  font-weight: 600;
  color: #1e293b;
  line-height: 1.25;
}

.wizard-step-link .step-text small {
  font-size: 11.5px;
  color: #64748b;
}

.wizard-step-link:hover {
  border-color: #cbd5e1;
  background: #f8fafc;
}

.wizard-step-link.is-active {
  border-color: #5c7e6e;
  background: #f2f6f4;
  box-shadow: 0 2px 8px rgba(92, 126, 110, 0.12);
}

.wizard-step-link.is-active span {
  background: #5c7e6e;
  color: #ffffff;
}

.wizard-step-link.is-active .step-text strong {
  color: #486858;
}

.portal-sidebar-terms-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 11px 16px;
  background: #f2f6f4;
  border: 1px solid #d2e0d8;
  border-radius: 12px;
  color: #486858;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.18s ease;
  width: 100%;
}

.portal-sidebar-terms-btn:hover {
  background: #e4eee8;
  border-color: #b8cdbf;
  color: #385244;
}

.portal-terms-modal-container {
  max-width: 920px !important;
  width: 92vw !important;
  max-height: 88vh !important;
  display: flex !important;
  flex-direction: column !important;
  background: #ffffff !important;
  border-radius: 16px !important;
  box-shadow: 0 20px 50px rgba(15, 23, 42, 0.12) !important;
}

.modal-terms-subtitle {
  font-size: 14px;
  font-weight: 500;
  color: #0f172a;
  margin: 0 0 14px;
}

.modal-fee-grid {
  display: grid !important;
  grid-template-columns: repeat(4, 1fr) !important;
  gap: 14px !important;
}

@media (max-width: 840px) {
  .modal-fee-grid {
    grid-template-columns: repeat(2, 1fr) !important;
  }
}

@media (max-width: 480px) {
  .modal-fee-grid {
    grid-template-columns: 1fr !important;
  }
  .portal-terms-modal-container {
    width: 95vw !important;
    max-height: 92vh !important;
  }
}

.modal-fee-card {
  background: #ffffff !important;
  border: 1px solid #e2e8f0 !important;
  border-radius: 12px !important;
  padding: 16px 18px !important;
  display: flex !important;
  flex-direction: column !important;
  gap: 8px !important;
  transition: all 0.15s ease !important;
}

.modal-fee-card:hover {
  border-color: #cbd5e1 !important;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04) !important;
}

.modal-fee-head {
  display: flex;
  align-items: center;
  font-size: 13.5px;
  color: #334155;
  font-weight: 500;
}

.tier-name {
  font-size: 13.5px;
  font-weight: 500;
  color: #334155;
}

.modal-fee-amount {
  display: flex;
  align-items: baseline;
  gap: 4px;
}

.tier-price-val {
  font-size: 18px;
  font-weight: 600;
  color: #5c7e6e;
}

.tier-price-unit {
  font-size: 12px;
  color: #64748b;
  font-weight: 400;
}

.modal-fee-discount {
  margin-top: 2px;
}

.discount-tag {
  display: inline-block;
  padding: 3px 8px;
  background: #f2f6f4;
  color: #486858;
  border: 1px solid #d2e0d8;
  border-radius: 6px;
  font-size: 11.5px;
  font-weight: 500;
}

.modal-policies-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.modal-policy-item {
  background: #ffffff !important;
  border: 1px solid #e2e8f0 !important;
  border-radius: 10px !important;
  overflow: hidden !important;
  transition: border-color 0.15s ease !important;
}

.modal-policy-item[open] {
  border-color: #cbd5e1 !important;
}

.modal-policy-summary {
  padding: 14px 18px;
  font-size: 14px;
  font-weight: 500;
  color: #0f172a;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  user-select: none;
}

.policy-title-text {
  font-size: 14px;
  font-weight: 500;
  color: #1e293b;
}

.modal-policy-ver {
  font-size: 11px;
  font-weight: 500;
  background: #f1f5f9;
  color: #64748b;
  padding: 2px 8px;
  border-radius: 4px;
  border: 1px solid #e2e8f0;
}

.modal-policy-body {
  padding: 0 18px 16px;
  font-size: 13.5px;
  color: #475569;
  line-height: 1.6;
  border-top: 1px solid #f1f5f9;
  margin-top: 2px;
  padding-top: 12px;
}

.portal-modal-footer {
  padding: 16px 24px;
  background: #ffffff !important;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
}

.portal-sidebar-exit-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 16px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  color: #64748b;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.18s ease;
  width: 100%;
}

.portal-sidebar-exit-btn:hover {
  background: #f8fafc;
  color: #0f172a;
  border-color: #cbd5e1;
}

@media (max-width: 992px) {
  .portal-form-grid {
    grid-template-columns: 1fr;
    gap: 20px;
  }
  .wizard-steps-nav {
    flex-direction: row;
    overflow-x: auto;
    padding-bottom: 4px;
  }
  .wizard-step-link {
    min-width: 180px;
  }
  .portal-form-sidebar-sticky {
    position: static;
  }
}

.portal-form-section,
.form-section {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 24px;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
}

.portal-section-header,
.form-section-header {
  margin-bottom: 16px !important;
  padding-bottom: 0 !important;
  border: none !important;
  border-bottom: none !important;
}

.portal-section-title,
.form-section-title {
  font-size: 18px !important;
  font-weight: 600 !important;
  color: #0f172a !important;
  margin: 0 !important;
  padding: 0 !important;
  border: none !important;
  border-bottom: none !important;
}

.portal-section-body,
.form-section-body {
  border: none !important;
  border-top: none !important;
  border-bottom: none !important;
  padding-top: 0 !important;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}

.form-group {
  display: flex !important;
  flex-direction: column !important;
  position: relative !important;
  margin-bottom: 0 !important;
  width: 100% !important;
}

.form-group.full-width {
  grid-column: span 2;
}

/* FIX VALIDATION ERROR DISPLAY */
.form-group .error-text,
.portal-form-wrapper .error-text,
.error-text {
  position: static !important;
  inset: auto !important;
  display: block !important;
  margin-top: 6px !important;
  margin-bottom: 0 !important;
  font-size: 12.5px !important;
  font-weight: 500 !important;
  color: #dc2626 !important;
  line-height: 1.45 !important;
  background: transparent !important;
  border: none !important;
  padding: 0 !important;
  z-index: 1 !important;
}

.form-group.has-error input,
.form-group.has-error select,
.form-group.has-error textarea,
.form-group input.has-error,
.form-group input.is-invalid,
.form-group select.has-error,
.form-select.has-error {
  border-color: #fca5a5 !important;
  background-color: #fef2f2 !important;
  color: #0f172a !important;
}

.form-group.has-error input:focus,
.form-group.has-error select:focus,
.form-group.has-error textarea:focus,
.form-select.has-error:focus {
  border-color: #dc2626 !important;
  box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15) !important;
}

.form-label {
  display: flex !important;
  flex-direction: row !important;
  align-items: center !important;
  gap: 4px !important;
  width: 100% !important;
  margin-bottom: 6px !important;
}

.form-label-text {
  display: inline !important;
  width: auto !important;
  font-size: 13.5px !important;
  font-weight: 500 !important;
  color: #1e293b !important;
}

.form-label span.required,
.required {
  display: inline-block !important;
  width: auto !important;
  min-width: 0 !important;
  max-width: max-content !important;
  flex: 0 0 auto !important;
  color: #dc2626 !important;
  font-size: 14px !important;
  font-weight: 500 !important;
  margin: 0 0 0 2px !important;
  padding: 0 !important;
  line-height: 1 !important;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #ffffff;
  color: #0f172a;
  font-size: 14px;
  font-weight: 400;
  box-sizing: border-box;
  transition: border-color 0.15s ease;
}

.form-group input::placeholder,
.form-group textarea::placeholder {
  color: #64748b !important;
  opacity: 1 !important;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #5c7e6e;
  box-shadow: 0 0 0 3px rgba(92, 126, 110, 0.15);
}

.confirmation-card {
  margin-top: 24px !important;
  padding: 16px 20px !important;
  background: #f8fafc !important;
  border: 1px solid #e2e8f0 !important;
  border-radius: 10px !important;
  transition: border-color 0.15s ease !important;
}

.confirmation-card.has-error {
  border-color: #fca5a5 !important;
  background: #fef2f2 !important;
}

.confirmation-label {
  display: flex !important;
  align-items: flex-start !important;
  gap: 12px !important;
  cursor: pointer !important;
  margin: 0 !important;
  user-select: none !important;
}

.confirmation-label input[type="checkbox"] {
  width: 18px !important;
  height: 18px !important;
  min-width: 18px !important;
  accent-color: #5c7e6e !important;
  margin-top: 2px !important;
  cursor: pointer !important;
}

.confirmation-label span {
  font-size: 13.5px !important;
  font-weight: 500 !important;
  color: #1e293b !important;
  line-height: 1.5 !important;
}

.confirmation-error {
  margin-top: 8px !important;
  font-size: 13px !important;
  color: #dc2626 !important;
  margin-bottom: 0 !important;
}

.wizard-footer {
  display: flex !important;
  justify-content: space-between !important;
  align-items: center !important;
  margin-top: 24px !important;
  padding-top: 0 !important;
  border-top: none !important;
  gap: 16px !important;
}

.wizard-footer-note {
  display: flex !important;
  align-items: center !important;
  gap: 8px !important;
  color: #64748b !important;
  font-size: 13px !important;
  font-weight: 400 !important;
}

.wizard-footer-note svg {
  color: #64748b !important;
  flex-shrink: 0 !important;
}

.wizard-actions {
  display: flex !important;
  align-items: center !important;
  gap: 12px !important;
}

.wizard-actions .btn-outline {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  height: 42px !important;
  padding: 0 20px !important;
  background: #ffffff !important;
  border: 1px solid #cbd5e1 !important;
  border-radius: 8px !important;
  color: #334155 !important;
  font-size: 14px !important;
  font-weight: 500 !important;
  cursor: pointer !important;
  transition: all 0.15s ease !important;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
}

.wizard-actions .btn-outline:hover {
  background: #f8fafc !important;
  border-color: #94a3b8 !important;
  color: #0f172a !important;
}

.wizard-actions .btn-primary {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 8px !important;
  height: 42px !important;
  padding: 0 24px !important;
  background: #5c7e6e !important;
  border: 1px solid #5c7e6e !important;
  border-radius: 8px !important;
  color: #ffffff !important;
  font-size: 14px !important;
  font-weight: 600 !important;
  cursor: pointer !important;
  transition: all 0.15s ease !important;
  box-shadow: 0 1px 3px 0 rgba(92, 126, 110, 0.25) !important;
}

.wizard-actions .btn-primary:hover {
  background: #486858 !important;
  border-color: #486858 !important;
}

.wizard-actions .btn-primary:disabled {
  opacity: 0.65 !important;
  cursor: not-allowed !important;
}

/* MAP PICKER & BUTTONS */
.portal-map-picker,
#partner-application-map {
  width: 100% !important;
  height: 320px !important;
  min-height: 320px !important;
  border-radius: 12px !important;
  border: 1px solid #cbd5e1 !important;
  overflow: hidden !important;
  z-index: 1 !important;
  background: #f1f5f9 !important;
  margin-top: 10px !important;
}

.map-picker-header {
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  margin-bottom: 8px !important;
  flex-wrap: wrap !important;
  gap: 10px !important;
}

.current-location-btn,
.current-location-button {
  display: inline-flex !important;
  align-items: center !important;
  gap: 6px !important;
  background: #ffffff !important;
  border: 1px solid #cbd5e1 !important;
  border-radius: 8px !important;
  padding: 6px 14px !important;
  color: #486858 !important;
  font-size: 13px !important;
  font-weight: 500 !important;
  cursor: pointer !important;
  transition: all 0.15s ease !important;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
}

.current-location-btn:hover,
.current-location-button:hover {
  background: #f2f6f4 !important;
  border-color: #5c7e6e !important;
  color: #385244 !important;
}

.input-with-action {
  display: flex !important;
  gap: 10px !important;
  align-items: center !important;
}

.input-with-action input {
  flex: 1 !important;
}

.btn-parse-map {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  white-space: nowrap !important;
  padding: 10px 16px !important;
  background: #f2f6f4 !important;
  border: 1px solid #d2e0d8 !important;
  border-radius: 8px !important;
  color: #486858 !important;
  font-size: 13px !important;
  font-weight: 500 !important;
  cursor: pointer !important;
  transition: all 0.15s ease !important;
}

.btn-parse-map:hover {
  background: #e4eee8 !important;
  border-color: #b8cdbf !important;
  color: #385244 !important;
}

.map-coordinates-bar {
  display: flex !important;
  gap: 20px !important;
  padding: 10px 14px !important;
  background: #f8fafc !important;
  border: 1px solid #e2e8f0 !important;
  border-radius: 8px !important;
  font-size: 13px !important;
  color: #475569 !important;
  margin-bottom: 8px !important;
  flex-wrap: wrap !important;
}

.map-coordinates-bar strong {
  color: #5c7e6e !important;
  font-weight: 600 !important;
}

.map-help {
  margin-top: 8px;
  font-size: 12.5px;
  color: #64748b;
}

/* COURT CONFIG ROWS */
.court-config-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-top: 16px;
}

.court-config-row {
  display: grid;
  grid-template-columns: 1fr 1fr auto;
  gap: 16px;
  align-items: flex-end;
}

.court-config-row .btn-danger {
  height: 42px;
  padding: 0 16px;
  background: #ffffff;
  border: 1px solid #fca5a5;
  color: #dc2626;
  border-radius: 8px;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
  box-shadow: none;
}

.court-config-row .btn-danger:hover {
  background: #fef2f2;
  border-color: #f87171;
}

/* AMENITIES CHECKBOX LIST */
.amenities-field {
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid #f1f5f9;
}

.amenities-label {
  font-size: 14px;
  font-weight: 500;
  color: #0f172a;
  margin-bottom: 12px;
}

.amenities-list {
  display: flex !important;
  flex-wrap: wrap !important;
  gap: 10px !important;
}

.amenity-option {
  display: inline-flex !important;
  align-items: center !important;
  gap: 8px !important;
  padding: 8px 14px !important;
  background: #ffffff !important;
  border: 1px solid #cbd5e1 !important;
  border-radius: 8px !important;
  font-size: 13.5px !important;
  font-weight: 500 !important;
  color: #334155 !important;
  cursor: pointer !important;
  white-space: nowrap !important;
  user-select: none !important;
  transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease !important;
  width: auto !important;
  box-sizing: border-box !important;
}

.amenity-option:hover {
  border-color: #5c7e6e !important;
  background: #f2f6f4 !important;
}

.amenity-option.selected {
  border-color: #5c7e6e !important;
  background: #f2f6f4 !important;
  color: #486858 !important;
  font-weight: 500 !important;
}

.amenity-option input[type="checkbox"] {
  width: 16px !important;
  height: 16px !important;
  accent-color: #5c7e6e !important;
  cursor: pointer !important;
  margin: 0 !important;
}

@media (max-width: 768px) {
  .court-config-row {
    grid-template-columns: 1fr;
  }
}
</style>

<style>
/* UNSCOPED OVERRIDE FOR VALIDATION ERROR TEXT TO PREVENT ADMIN.CSS OVERLAPPING */
.partner-portal-page .form-group {
  display: flex !important;
  flex-direction: column !important;
  position: relative !important;
  margin-bottom: 0 !important;
  width: 100% !important;
}

.partner-portal-page .error-text,
.portal-form-wrapper .error-text,
.wizard-form .error-text {
  position: static !important;
  inset: auto !important;
  display: block !important;
  margin-top: 6px !important;
  margin-bottom: 0 !important;
  font-size: 12.5px !important;
  font-weight: 500 !important;
  color: #dc2626 !important;
  line-height: 1.4 !important;
  background: transparent !important;
  border: none !important;
  padding: 0 !important;
  width: 100% !important;
  box-shadow: none !important;
}

.partner-portal-page .form-group.has-error input,
.partner-portal-page .form-group.has-error select,
.partner-portal-page .form-group.has-error textarea,
.partner-portal-page input.has-error,
.partner-portal-page input.is-invalid,
.partner-portal-page select.has-error,
.partner-portal-page .form-select.has-error {
  border-color: #fca5a5 !important;
  background-color: #fef2f2 !important;
  color: #0f172a !important;
}

.partner-portal-page .form-group.has-error input:focus,
.partner-portal-page .form-group.has-error select:focus,
.partner-portal-page .form-group.has-error textarea:focus,
.partner-portal-page .form-select.has-error:focus {
  border-color: #dc2626 !important;
  box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15) !important;
}

.partner-portal-page .map-suggestion-card {
  grid-column: span 2 !important;
  background: #f0fdf4 !important;
  border: 1px solid #bbf7d0 !important;
  border-radius: 10px !important;
  padding: 12px 16px !important;
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  gap: 16px !important;
  margin-bottom: 8px !important;
}

@media (max-width: 640px) {
  .partner-portal-page .map-suggestion-card {
    flex-direction: column !important;
    align-items: flex-start !important;
  }
}

.partner-portal-page .map-suggestion-content {
  display: flex !important;
  align-items: center !important;
  gap: 10px !important;
}

.partner-portal-page .map-suggestion-icon-wrap {
  width: 32px !important;
  height: 32px !important;
  border-radius: 50% !important;
  background: #dcfce7 !important;
  color: #15803d !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  flex-shrink: 0 !important;
}

.partner-portal-page .map-suggestion-title {
  font-size: 13px !important;
  font-weight: 600 !important;
  color: #14532d !important;
  display: block !important;
}

.partner-portal-page .map-suggestion-desc {
  font-size: 12.5px !important;
  color: #166534 !important;
  margin: 0 !important;
}

.partner-portal-page .btn-apply-map-suggestion {
  background: #5c7e6e !important;
  border-color: #5c7e6e !important;
  color: #ffffff !important;
  white-space: nowrap !important;
  font-weight: 600 !important;
  padding: 8px 16px !important;
  border-radius: 8px !important;
  cursor: pointer !important;
  transition: all 0.15s ease !important;
  box-shadow: 0 1px 3px rgba(92, 126, 110, 0.2) !important;
}

.partner-portal-page .btn-apply-map-suggestion:hover {
  background: #486858 !important;
  border-color: #486858 !important;
}

.partner-portal-page .map-status-card {
  grid-column: span 2 !important;
  background: #f8fafc !important;
  border: 1px solid #e2e8f0 !important;
  border-radius: 8px !important;
  padding: 10px 14px !important;
  display: flex !important;
  align-items: center !important;
  gap: 8px !important;
  font-size: 13px !important;
  color: #475569 !important;
  margin-bottom: 8px !important;
}

.partner-portal-page .map-status-icon {
  color: #5c7e6e !important;
}

/* Location Verification Popup Modal */
.location-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 4000;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(46, 66, 56, 0.75);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
}

.location-modal-card {
  width: min(520px, 100%);
  padding: 28px;
  border-radius: 20px;
  background: #ffffff;
  border: 2px solid #9ebcb0;
  box-shadow: 0 20px 50px rgba(46, 66, 56, 0.25);
  font-weight: 400;
  color: #2e4238;
}

.location-modal-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 20px 0;
  text-align: center;
}

.location-spinner {
  width: 36px;
  height: 36px;
  border: 3px solid #dce8e2;
  border-top-color: #5c7e6e;
  border-radius: 50%;
  animation: locationSpin 0.8s linear infinite;
}

@keyframes locationSpin {
  to { transform: rotate(360deg); }
}

.location-loading-text {
  margin: 0;
  color: #5c7e6e;
  font-size: 15px;
  font-weight: 400;
}

.location-modal-result {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.location-modal-title {
  margin: 0;
  color: #2e4238;
  font-size: 20px;
  font-weight: 400;
}

.location-modal-desc {
  margin: 0;
  color: #4d6e5f;
  font-size: 14px;
  font-weight: 400;
}

.location-detected-address {
  margin: 4px 0;
  color: #0f172a;
  font-size: 15px;
  font-weight: 600;
  line-height: 1.5;
}

.location-modal-question {
  margin: 0;
  color: #5c7e6e;
  font-size: 14px;
  font-weight: 400;
}

.location-modal-actions {
  display: flex;
  gap: 12px;
  margin-top: 6px;
}

.location-btn {
  flex: 1;
  height: 44px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 400;
  cursor: pointer;
  transition: all 0.2s ease;
}

.location-btn-accept {
  border: none;
  background: #5c7e6e;
  color: #ffffff;
}

.location-btn-accept:hover {
  background: #4d6e5f;
}

.location-btn-reject {
  border: 1px solid #9ebcb0;
  background: #f2f7f4;
  color: #2e4238;
}

.location-btn-reject:hover {
  background: #e2ece7;
  border-color: #5c7e6e;
}
</style>
