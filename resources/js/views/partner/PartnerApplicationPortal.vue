<template>
  <div class="partner-portal-page">
    <PublicNavbar />

    <main class="portal-main">
      <!-- ───── LIST VIEW ───── -->
      <template v-if="!formOpen">
        <div class="flex-between mb-4">
          <div>
            <p class="portal-label">SportGo Partner</p>
            <h1 class="portal-title">Đăng ký đối tác chủ sân</h1>
            <p class="portal-subtitle" style="margin-bottom: 0;">Gửi hồ sơ, theo dõi tiến trình xét duyệt và ký số văn bản ngay trên nền tảng.</p>
          </div>
          <button v-if="canRegister" type="button" class="btn btn-primary" @click="startNewApplication">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
            Đăng ký hồ sơ mới
          </button>
        </div>

        <div class="stat-grid">
          <div class="stat-card">
            <p class="stat-label">Tổng hồ sơ</p>
            <p class="stat-value">{{ applications.length }}</p>
            <p class="stat-label" style="color: var(--primary-color);">Đã gửi</p>
          </div>
          <div class="stat-card">
            <p class="stat-label">Đang xét duyệt</p>
            <p class="stat-value">{{ reviewingCount }}</p>
            <p class="stat-label" style="color: #b45309;">Chờ phản hồi</p>
          </div>
          <div class="stat-card">
            <p class="stat-label">Hồ sơ nháp</p>
            <p class="stat-value">{{ draft ? 1 : 0 }}</p>
            <p class="stat-label">Chưa gửi</p>
          </div>
        </div>

        <div v-if="draft" class="draft-banner">
          <div>
            <p class="title">{{ draft.venue_name || 'Chưa đặt tên cụm sân' }} <span style="font-weight: 400; color: #b45309;">— đang lưu nháp</span></p>
            <p style="font-size: 13px; color: #b45309; margin-top: 4px;">Lưu lúc {{ formatDate(draft.saved_at) }}</p>
          </div>
          <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn-secondary" style="background: transparent; border-color: #f59e0b; color: #b45309;" @click="clearDraft">Xóa nháp</button>
            <button type="button" class="btn btn-primary" style="background: #f59e0b; color: white; border-color: #f59e0b;" @click="continueDraft">Tiếp tục điền</button>
          </div>
        </div>

        <div class="flex-between mb-4">
          <p style="font-size: 14px; color: var(--text-muted);">{{ applications.length }} hồ sơ</p>
          <button type="button" class="btn btn-outline" @click="loadApplications">Làm mới</button>
        </div>

        <div v-if="loading" style="text-align: center; padding: 60px;">
          <p class="portal-subtitle">Đang tải hồ sơ...</p>
        </div>

        <div v-else-if="applications.length === 0 && !draft" class="portal-card" style="text-align: center; padding: 60px 20px;">
          <svg style="margin: 0 auto; height: 48px; color: #cbd5e1;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <h3 style="margin-top: 16px; font-weight: 600; font-size: 16px;">Chưa có hồ sơ nào</h3>
          <p style="margin-top: 8px; color: var(--text-muted); font-size: 14px;">Bắt đầu bằng cách tạo hồ sơ đăng ký đầu tiên của bạn.</p>
        </div>

        <div v-else>
          <article v-for="application in applications" :key="application.id" class="app-list-item">
            <div style="flex: 1; min-width: 0;">
              <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <h3 style="font-size: 16px; font-weight: 600;">{{ application.venue_name }}</h3>
                <span class="badge" :class="statusClass(application.status)">
                  {{ statusLabel(application.status) }}
                </span>
              </div>
              <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
                {{ application.venue_address }} • Gửi {{ formatDate(application.submitted_at) }}
              </p>

              <div v-if="application.status === 'rejected'" style="background: #fef2f2; border: 1px solid #fecaca; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 12px; display: inline-block;">
                <strong style="color: #991b1b;">Lý do từ chối:</strong> <span style="color: #b91c1c;">{{ application.status_reason || 'SportGo chưa cung cấp lý do chi tiết.' }}</span>
              </div>
              <div v-if="application.status === 'need_supplement'" style="background: #fffbeb; border: 1px solid #fde68a; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 12px; display: inline-block;">
                <strong style="color: #92400e;">Cần bổ sung hồ sơ:</strong> <span style="color: #b45309;">{{ application.status_reason || 'Vui lòng liên hệ SportGo để biết thêm chi tiết.' }}</span>
              </div>
              <div v-if="application.status === 'contract_pending_owner_signature'" style="background: #ecfdf5; border: 1px solid #a7f3d0; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 12px; display: inline-block;">
                <strong style="color: #065f46;">🎉 Hồ sơ đã được duyệt!</strong> <span style="color: #047857;">Hợp đồng hợp tác đã sẵn sàng. Vui lòng xem và ký hợp đồng để hoàn tất quá trình đăng ký.</span>
              </div>
            </div>

            <div class="app-list-actions">
              <button type="button" class="btn btn-secondary action-detail" title="Xem chi tiết" @click="openApplicationDetail(application)">
                <AppIcon name="eye" size="16" />
                Chi tiết
              </button>
              <button v-if="application.status === 'need_supplement'" type="button" class="btn btn-primary action-document" title="Bổ sung/chỉnh sửa hồ sơ" @click="editApplication(application)">
                <AppIcon name="edit" size="16" />
                Bổ sung
              </button>
              <button v-if="application.status === 'rejected'" type="button" class="btn btn-secondary action-document" title="Tạo bản sao hồ sơ đăng ký" @click="duplicateApplication(application)">
                <AppIcon name="copy" size="16" />
                Tạo bản sao
              </button>
              <button v-if="needsApplicationSignature(application)" type="button" class="btn btn-secondary action-document" title="Ký đơn đăng ký" @click="openApplicationDocument(applicationWord(application), application)">
                <AppIcon name="edit" size="16" />
                Ký đơn
              </button>
              <button v-if="needsContractSignature(application)" type="button" class="btn btn-primary" title="Ký hợp đồng" @click="openApplicationDocument(contractWord(application), application)">
                <AppIcon name="fileText" size="16" />
                Ký hợp đồng
              </button>
              <button v-if="canSubmitSignedApplication(application)" type="button" class="btn btn-primary action-submit" title="Gửi hồ sơ" @click="submitSignedApplication(application)">
                <AppIcon name="send" size="16" />
                Gửi hồ sơ
              </button>
              <button v-if="canCancel(application)" type="button" class="btn btn-outline action-cancel" title="Hủy hồ sơ" @click="cancelApplication(application)">
                <AppIcon name="trash" size="16" />
                Hủy
              </button>
            </div>
          </article>
        </div>
      </template>

      <!-- ───── FORM VIEW WIZARD ───── -->
      <template v-else>
        <div class="mb-4">
          <BackButton @click="formOpen = false" title="Quay lại danh sách" />
        </div>

        <div class="wizard-container">
          <!-- Header removed for single form layout -->

          <form novalidate @submit.prevent="submit" style="display: flex; flex-direction: column; flex: 1;">
            
            <div class="wizard-body">
              <div v-if="formBanner" class="notice error mb-4" style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px; border-radius: 8px;">
                {{ formBanner }}
              </div>

              <!-- STEP 1: Cá nhân -->
              <div class="step-content">
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
              <div class="step-content" style="margin-top: 40px;">
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
              <div class="step-content" style="margin-top: 40px;">
                <FormSection title="Địa chỉ và thông tin Cụm sân">
                  <div class="form-grid">
                    <FormField label="Tỉnh/Thành phố" required :error="fieldErrors.venue_province_code">
                      <BaseCombobox v-model="form.venue_province_code" :options="provinceOptions" placeholder="Tìm Tỉnh/Thành phố" :invalid="Boolean(fieldErrors.venue_province_code)" @select="onProvinceSelect" />
                    </FormField>
                    <FormField label="Phường/Xã" required :error="fieldErrors.venue_ward_code">
                      <BaseCombobox v-model="form.venue_ward_code" :options="wardOptions" placeholder="Tìm Phường/Xã" :disabled="!form.venue_province_code" :invalid="Boolean(fieldErrors.venue_ward_code)" @select="syncVenueAddress" />
                    </FormField>
                    <FormField class="full-width" label="Số nhà, tên đường" required :error="fieldErrors.street_address">
                      <input v-model.trim="form.street_address" :class="inputClass(fieldErrors.street_address)" placeholder="Ví dụ: 123 Nguyễn Hữu Cảnh" @input="syncVenueAddress" />
                    </FormField>
                    <FormField class="full-width" label="Link Google Maps (Bắt buộc để lấy tọa độ)" required :error="mapError || fieldErrors.venue_map_url">
                      <input v-model.trim="form.venue_map_url" :class="inputClass(mapError || fieldErrors.venue_map_url)" placeholder="Dán link Google Maps có tọa độ" @input="onMapUrlInput" />
                      <div v-if="mapSuggestion" style="margin-top: 8px; background: #fffbeb; border: 1px solid #fde68a; padding: 12px; border-radius: 8px;">
                        <p style="font-size: 13px; color: #92400e; margin-bottom: 8px;">{{ mapSuggestion.message }}</p>
                        <button v-if="mapSuggestion.province_code || mapSuggestion.ward_code" type="button" class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px;" @click="applyMapSuggestion">Cập nhật theo Google Maps</button>
                      </div>
                      <p v-else-if="mapStatus" style="margin-top: 4px; font-size: 13px; color: #059669;">{{ mapStatus }}</p>
                    </FormField>
                    <FormField class="full-width" label="Chọn vị trí trên bản đồ" required :error="fieldErrors.venue_coordinates">
                      <div class="map-picker-shell">
                        <div id="partner-application-map" class="map-picker"></div>
                        <div class="map-coordinate-grid">
                          <label :class="{ invalid: fieldErrors.venue_latitude }">
                            <span>Vĩ độ</span>
                            <input v-model.trim="form.venue_latitude" :class="inputClass(fieldErrors.venue_latitude)" inputmode="decimal" @input="sanitizeCoordinate('venue_latitude')" />
                          </label>
                          <label :class="{ invalid: fieldErrors.venue_longitude }">
                            <span>Kinh độ</span>
                            <input v-model.trim="form.venue_longitude" :class="inputClass(fieldErrors.venue_longitude)" inputmode="decimal" @input="sanitizeCoordinate('venue_longitude')" />
                          </label>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" style="margin-top: 8px; margin-bottom: 8px;" @click="getCurrentLocation">
                          📍 Lấy vị trí hiện tại
                        </button>
                        <p class="map-help">Click trên bản đồ hoặc kéo marker để chọn tọa độ cụm sân. Link Google Maps nếu có tọa độ sẽ tự đặt marker.</p>
                      </div>
                    </FormField>
                    <input type="hidden" :value="form.venue_latitude" name="venue_latitude" />
                    <input type="hidden" :value="form.venue_longitude" name="venue_longitude" />
                    
                    <FormField label="Tên cụm sân" required :error="fieldErrors.venue_name">
                      <input v-model.trim="form.venue_name" :class="inputClass(fieldErrors.venue_name)" />
                    </FormField>
                    <FormField label="Số điện thoại tại sân" required :error="fieldErrors.venue_phone">
                      <input v-model.trim="form.venue_phone" :class="inputClass(fieldErrors.venue_phone)" inputmode="tel" @input="sanitizePhoneCharacters('venue_phone')" />
                    </FormField>
                    <FormField label="Giờ mở cửa dự kiến" :error="fieldErrors.expected_opening_hours">
                      <input v-model.trim="form.expected_opening_hours" :class="inputClass(fieldErrors.expected_opening_hours)" placeholder="05:00 - 23:00" />
                    </FormField>
                    <FormField label="Email tại sân" :error="fieldErrors.venue_email">
                      <input v-model.trim="form.venue_email" :class="inputClass(fieldErrors.venue_email)" type="email" />
                    </FormField>
                  </div>
                </FormSection>

                <FormSection title="Cấu hình sân con" style="margin-top: 24px;">
                  <div class="form-grid">
                    <FormField label="Số lượng sân con" required :error="fieldErrors.court_count_total">
                      <input v-model.trim="form.court_count_total" :class="inputClass(fieldErrors.court_count_total)" inputmode="numeric" @input="onCourtCountInput" />
                    </FormField>
                    <FormField label="Giá cơ bản/giờ (VNĐ)" required :error="fieldErrors.base_price_per_hour">
                      <input v-model.trim="form.base_price_per_hour" :class="inputClass(fieldErrors.base_price_per_hour)" inputmode="numeric" @input="sanitizeDigitsField('base_price_per_hour')" />
                    </FormField>
                  </div>

                  <div style="margin-top: 16px; display: flex; flex-direction: column; gap: 12px;">
                    <div
                      v-for="(court, index) in form.courts"
                      :key="court.local_id"
                      style="display: grid; gap: 12px; background: #f8fafc; border: 1px solid var(--border-color); padding: 16px; border-radius: 12px; grid-template-columns: 1fr 1fr auto; align-items: end;"
                    >
                      <FormField :label="'Tên sân ' + (index + 1)" required :error="fieldErrors['courts.' + index + '.name']">
                        <input v-model.trim="court.name" :class="inputClass(fieldErrors['courts.' + index + '.name'])" />
                      </FormField>
                      <FormField label="Loại sân" required :error="fieldErrors['courts.' + index + '.court_type_id']">
                        <BaseCombobox v-model="court.court_type_id" :options="courtTypeOptions" placeholder="Chọn loại sân" :invalid="Boolean(fieldErrors['courts.' + index + '.court_type_id'])" />
                      </FormField>
                      <button
                        type="button"
                        class="btn btn-outline"
                        style="color: #ef4444; border-color: #fecaca; background: white;"
                        :disabled="form.courts.length <= 1"
                        @click="removeCourt(index)"
                      >
                        Xóa
                      </button>
                    </div>
                  </div>
                  
                  <div v-if="amenities.length" style="margin-top: 20px;">
                    <p class="form-label" style="margin-bottom: 8px;">Tiện ích có sẵn</p>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                      <label
                        v-for="amenity in amenities"
                        :key="amenity.id || amenity.name"
                        style="display: inline-flex; cursor: pointer; align-items: center; gap: 8px; border: 1px solid var(--border-color); background: white; padding: 6px 12px; border-radius: 20px; font-size: 13px; transition: all 0.2s;"
                        :style="form.amenities.includes(amenity.name) ? 'border-color: var(--primary-color); background: #ecfdf5;' : ''"
                      >
                        <input v-model="form.amenities" type="checkbox" :value="amenity.name" style="accent-color: var(--primary-color);" />
                        {{ amenity.name }}
                      </label>
                    </div>
                  </div>
                </FormSection>
              </div>

              <!-- STEP 4: Tài liệu & Ngân hàng -->
              <div class="step-content" style="margin-top: 40px;">
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

                <FormSection title="Tài liệu đính kèm" style="margin-top: 24px;">
                  <div class="form-grid">
                    <UploadBox title="CCCD/CMND người đại diện" required :files="files.identity" :error="fieldErrors.identity_documents" @change="setFiles('identity', $event)" @remove="removeFile('identity', $event)" />
                    <UploadBox title="Giấy ĐKKD/Pháp lý" required :files="files.business_license" :error="fieldErrors.business_license_documents" @change="setFiles('business_license', $event)" @remove="removeFile('business_license', $event)" />
                    <UploadBox title="Hình ảnh cơ sở/sân" required :files="files.facility" :error="fieldErrors.facility_images" @change="setFiles('facility', $event)" @remove="removeFile('facility', $event)" />
                    <UploadBox title="Chứng từ ngân hàng" required :files="files.bank" :error="fieldErrors.bank_documents" @change="setFiles('bank', $event)" @remove="removeFile('bank', $event)" />
                    <UploadBox title="Hợp đồng thuê mặt bằng" required :files="files.lease" :error="fieldErrors.lease_documents" @change="setFiles('lease', $event)" @remove="removeFile('lease', $event)" />
                    <UploadBox title="Giấy tờ khác" :files="files.additional" :error="fieldErrors.additional_documents" @change="setFiles('additional', $event)" @remove="removeFile('additional', $event)" />
                  </div>
                </FormSection>

                <div class="portal-card" style="background: #f8fafc; margin-top: 24px;" :class="fieldErrors.confirmed ? 'border-red-400' : ''">
                  <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer;">
                    <input v-model="confirmed" type="checkbox" style="margin-top: 4px; width: 18px; height: 18px; accent-color: var(--primary-color);" />
                    <span style="font-size: 14px; color: var(--text-main); line-height: 1.5;">
                      Tôi xác nhận thông tin trong hồ sơ là chính xác và đồng ý để SportGo kiểm tra tài liệu trước khi duyệt đối tác.
                    </span>
                  </label>
                  <p v-if="fieldErrors.confirmed" style="margin-top: 8px; margin-left: 30px; font-size: 13px; color: #ef4444;">{{ fieldErrors.confirmed }}</p>
                </div>
              </div>
            </div>

            <!-- Form Actions -->
            <div class="wizard-footer">
              <div></div>
              <div style="display: flex; gap: 12px;">
                <button type="button" class="btn btn-outline" @click="saveDraft">Lưu nháp</button>
                <button type="submit" class="btn btn-primary" :disabled="submitDisabled">
                  <span v-if="submitting" style="margin-right: 8px; display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite;"></span>
                  {{ submitting ? 'Đang xử lý...' : 'Gửi hồ sơ đăng ký' }}
                </button>
              </div>
            </div>
          </form>
        </div>
      </template>

    </main>

    <FloatingActions />
  </div>
</template>

<style scoped>
@import "../../../css/partner/partner.css";

.form-group {
  min-width: 0;
}

.form-group :deep(.combo-wrapper) {
  width: 100%;
}

.form-group input:not([type="checkbox"]):not([type="radio"]),
.form-group textarea,
:deep(.form-select) {
  width: 100%;
  min-height: 44px;
  border: 1px solid #dbe3ef;
  border-radius: 10px;
  background: #fff;
  padding: 10px 12px;
  color: #0f172a;
  font-size: 14px;
  line-height: 1.4;
  outline: none;
  transition: border-color .18s ease, box-shadow .18s ease;
}

.form-group textarea {
  min-height: 72px;
  resize: vertical;
}

.form-group input:not([type="checkbox"]):not([type="radio"]):focus,
.form-group textarea:focus,
:deep(.form-select:focus) {
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, .16);
}

.form-group input.border-red-400,
.form-group textarea.has-error,
:deep(.form-select.has-error) {
  border-color: #f87171;
}

.map-picker-shell {
  display: grid;
  gap: 12px;
}

.map-picker {
  min-height: 320px;
  width: 100%;
  overflow: hidden;
  border: 1px solid #dbe3ef;
  border-radius: 12px;
  background: #eef2f7;
}

.map-coordinate-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.map-coordinate-grid label {
  display: grid;
  gap: 6px;
  font-size: 13px;
  font-weight: 700;
  color: #334155;
}

.map-coordinate-grid label.invalid input {
  border-color: #f87171;
}

.map-help {
  margin: 0;
  color: #64748b;
  font-size: 13px;
  line-height: 1.45;
}

@media (max-width: 640px) {
  .map-coordinate-grid {
    grid-template-columns: 1fr;
  }
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
<script setup>
import { computed, defineComponent, h, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import PublicNavbar from '../../components/PublicNavbar.vue';
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
  props: { title: { type: String, required: true } },
  setup(props, { slots }) {
    return () => h('section', { class: 'portal-card' }, [
      h('div', { class: '' }, [
        h('h2', { class: 'form-section-title' }, props.title),
      ]),
      slots.default?.(),
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
    return () => h('div', { class: ['form-group', attrs.class] }, [
      h('label', { class: 'form-label' }, [
        props.label,
        props.required ? h('span', { class: 'required' }, '* ') : null,
      ]),
      slots.default?.(),
      props.error ? h('p', { class: 'error-text' }, props.error) : null,
    ]);
  },
});

// ─── State ───────────────────────────────────────────────────────────────────
const route = useRoute();
const router = useRouter();
const user = getAuth();

const loading = ref(false);
const applications = ref([]);
const canRegister = ref(true);
const draft = ref(null);
const formOpen = ref(false);
const fieldErrors = reactive({});
const formBanner = ref('');
const provinces = ref([]);
const wards = ref([]);
const banks = ref([]);
const courtTypes = ref([]);
const amenities = ref([]);
const files = reactive(blankFiles());
const confirmed = ref(false);
const submitting = ref(false);
const mapError = ref('');
const mapStatus = ref('');
const mapSuggestion = ref(null);
const mapTimer = ref(null);
const mapInstance = ref(null);
const mapMarker = ref(null);
const mapReverseBusy = ref(false);
const editingApplicationId = ref('');
const existingDocumentTypes = ref(new Set());

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

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
  if (!user) { router.replace({ name: 'login' }); return; }
  loadDraft();
  await Promise.all([loadApplications(), loadBanks(), loadProvinces(), loadCourtTypes(), loadAmenities()]);
  await openDraftFromRoute();
});

onBeforeUnmount(() => {
  clearTimeout(bankTimer.value);
  clearTimeout(mapTimer.value);
  destroyMapPicker();
});

watch(() => form.venue_province_code, async (code, old) => {
  if (code !== old) { form.venue_ward_code = ''; wards.value = []; await loadWards(code); syncVenueAddress(); }
});
watch(() => form.venue_ward_code, syncVenueAddress);
watch(formOpen, async (open) => {
  if (open) {
    await nextTick();
    initMapPicker();
    return;
  }
  destroyMapPicker();
});
watch(() => [form.venue_latitude, form.venue_longitude], updateMapPickerMarker);

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
  return ['w-full rounded-lg border px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 disabled:cursor-not-allowed disabled:bg-gray-50', error ? 'border-red-400' : 'border-gray-200', extra].filter(Boolean).join(' ');
}
function textareaClass(error) { return ['form-textarea', error ? 'has-error' : '']; }
// ─── Data loaders ─────────────────────────────────────────────────────────────
async function loadApplications() {
  loading.value = true;
  try { const r = await api('/api/user/partner-application'); applications.value = r.data?.history || []; canRegister.value = Boolean(r.data?.can_register); } finally { loading.value = false; }
}
async function loadBanks() {
  const cached = readCache(BANK_CACHE_KEY);
  if (cached?.length) { banks.value = cached; return; }
  try { const r = await api('/api/user/partner-application/banks'); banks.value = normalizeList(r.data); if (banks.value.length) writeCache(BANK_CACHE_KEY, banks.value, BANK_CACHE_TTL); } catch (e) { console.error('Lỗi tải ngân hàng:', e); }
}
async function loadProvinces() { const r = await api('/api/user/partner-application/provinces'); provinces.value = normalizeList(r.data); }
async function loadWards(code) { if (!code) return; const r = await api(`/api/user/partner-application/provinces/${code}/wards`); wards.value = normalizeList(r.data); }
async function loadCourtTypes() { const r = await api('/api/court-types'); courtTypes.value = normalizeList(r.data); }
async function loadAmenities() { const r = await api('/api/amenities?active_only=1'); amenities.value = normalizeList(r.data); }

// ─── Form lifecycle ───────────────────────────────────────────────────────────
function startNewApplication() {
  editingApplicationId.value = '';
  existingDocumentTypes.value = new Set();
  resetForm(defaultForm(user));
  formOpen.value = true;
}

function resetForm(next) {
  Object.assign(form, next);
  Object.assign(files, blankFiles());
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
  if (showMessage) formBanner.value = '�? l�u nh�p h? s� tr�n tr?nh duy?t.';
}

function saveDraft() {
  persistDraft(true);
}

function loadDraft() {
  try { draft.value = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch { draft.value = null; }
}

async function continueDraft() {
  if (!draft.value) return;
  editingApplicationId.value = draft.value.editing_application_id || editingApplicationId.value || '';
  resetForm({ ...defaultForm(user), ...draft.value });
  formOpen.value = true;
  if (form.venue_province_code) await loadWards(form.venue_province_code);
}

function clearDraft() {
  localStorage.removeItem(DRAFT_KEY);
  draft.value = null;
  editingApplicationId.value = '';
  existingDocumentTypes.value = new Set();
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
  loadApplicationIntoForm(application);
  existingDocumentTypes.value = new Set();
  confirmed.value = false;
  formOpen.value = true;
  if (form.venue_province_code) await loadWards(form.venue_province_code);
  syncVenueAddress();
  formBanner.value = 'Đã tạo bản sao thông tin từ hồ sơ bị từ chối. Vui lòng kiểm tra, tải lại giấy tờ bắt buộc và gửi hồ sơ mới.';
}

function loadApplicationIntoForm(application) {
  existingDocumentTypes.value = new Set((application.documents || application.uploaded_documents || []).map((doc) => doc.document_type));
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
  confirmed.value = true;
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
    name: court.name || `S�n ${index + 1}`,
    court_type_id: court.court_type_id || '',
    note: court.note || '',
  }));

  return rows.length ? rows : [{ local_id: localId(), name: 'S�n 1', court_type_id: '', note: '' }];
}

// ─── Input handlers ───────────────────────────────────────────────────────────
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

function initMapPicker() {
  if (mapInstance.value) return;
  const container = document.getElementById('partner-application-map');
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
  setTimeout(() => mapInstance.value?.invalidateSize(), 150);
}

function destroyMapPicker() {
  if (!mapInstance.value) return;
  mapInstance.value.remove();
  mapInstance.value = null;
  mapMarker.value = null;
}

function getCurrentLocation() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (position) => {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        applyPickedCoordinates({ lat, lng });
        if (mapInstance.value) {
          mapInstance.value.setView([lat, lng], 15);
          if (mapMarker.value) mapMarker.value.setLatLng([lat, lng]);
        }
      },
      () => {
        alert('Không thể lấy được vị trí. Vui lòng kiểm tra quyền truy cập vị trí của trình duyệt.');
      }
    );
  } else {
    alert('Trình duyệt của bạn không hỗ trợ tính năng định vị.');
  }
}

function applyPickedCoordinates(point) {
  const lat = Number(point.lat).toFixed(7);
  const lng = Number(point.lng).toFixed(7);
  form.venue_latitude = lat;
  form.venue_longitude = lng;
  form.venue_map_url = googleMapsPointUrl(lat, lng);
  mapStatus.value = 'Đã chọn tọa độ trên bản đồ, đang cập nhật địa chỉ...';
  mapError.value = '';
  mapSuggestion.value = null;
  delete fieldErrors.venue_coordinates;
  delete fieldErrors.venue_latitude;
  delete fieldErrors.venue_longitude;
  delete fieldErrors.venue_map_url;
  reverseCoordinates(lat, lng, { overwriteStreet: true, applyLocation: true });
}

function googleMapsPointUrl(lat, lng) {
  return `https://www.google.com/maps?q=${lat},${lng}`;
}

function updateMapPickerMarker() {
  if (!mapInstance.value || !mapMarker.value) return;
  if (!validLatitude(form.venue_latitude) || !validLongitude(form.venue_longitude)) return;
  const lat = Number(form.venue_latitude);
  const lng = Number(form.venue_longitude);
  const current = mapMarker.value.getLatLng();
  if (Math.abs(current.lat - lat) < 0.000001 && Math.abs(current.lng - lng) < 0.000001) return;
  mapMarker.value.setLatLng([lat, lng]);
  mapInstance.value.setView([lat, lng], mapInstance.value.getZoom() || 15);
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
  mapError.value = ''; mapStatus.value = 'Đang xử lý link...';
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
      await compareResolvedAddress(resolved, { overwriteStreet: false, applyLocation: false });
      return;
    }
  } catch (e) { console.error('Lỗi phân giải map:', e); }
  const coords = extractCoordinates(urlToResolve);
  if (!coords && !form.venue_latitude) { mapStatus.value = ''; mapError.value = 'Không lấy được tọa độ từ link Google Maps này. Vui lòng dùng link đầy đủ có tọa độ.'; return; }
  if (coords) {
    form.venue_latitude = Number(coords.latitude).toFixed(7);
    form.venue_longitude = Number(coords.longitude).toFixed(7);
    await reverseCoordinates(form.venue_latitude, form.venue_longitude, { overwriteStreet: false, applyLocation: false });
  }
}

function extractCoordinates(url) {
  const d = decodeURIComponent(url || '');
  for (const p of [/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/, /!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/, /[?&](?:q|ll|query)=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/, /[?&]center=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/]) {
    const m = d.match(p); if (m) return { latitude: Number(m[1]), longitude: Number(m[2]) };
  }
  return null;
}

async function reverseCoordinates(latitude, longitude, options = {}) {
  if (!validLatitude(latitude) || !validLongitude(longitude)) return;
  mapReverseBusy.value = true;
  try {
    const r = await api('/api/user/partner-application/reverse-map', {
      method: 'POST',
      body: JSON.stringify({ latitude, longitude }),
    });
    await compareResolvedAddress(r.data || {}, options);
  } catch (e) {
    mapStatus.value = '';
    mapSuggestion.value = { province_code: '', ward_code: '', message: 'Kh�ng x�c minh ��?c T?nh/Th�nh ph? v� Ph�?ng/X? t? t?a �? n�y. Vui l?ng ch?n l?i v? tr� r? h�n tr�n b?n �?.' };
  } finally {
    mapReverseBusy.value = false;
  }
}

function streetFromAddress(address) {
  return String(address || '').split(',').map((part) => part.trim()).filter(Boolean)[0] || '';
}

async function compareResolvedAddress(resolved, options = {}) {
  const rp = resolved.province_code || '', rw = resolved.ward_code || '';
  const pc = rp && rp !== form.venue_province_code, wc = rw && rw !== form.venue_ward_code;
  if (resolved.address && (options.overwriteStreet || !form.street_address)) form.street_address = streetFromAddress(resolved.address);

  if (!rp || !rw) {
    mapStatus.value = '';
    mapSuggestion.value = { province_code: rp, ward_code: rw, message: 'Kh�ng x�c �?nh ��?c T?nh/Th�nh ph? v� Ph�?ng/X? t? t?a �? n�y. Vui l?ng ch?n l?i v? tr� r? h�n tr�n b?n �?.' };
    return;
  }
  
  if ((options.applyLocation || !form.venue_province_code) && rp) {
    form.venue_province_code = rp;
    await loadWards(rp);
    if (rw) form.venue_ward_code = rw;
    syncVenueAddress();
    mapStatus.value = 'Đã cập nhật địa chỉ theo tọa độ trên bản đồ.';
    return;
  }
  if (!pc && !wc) {
    syncVenueAddress();
    if (!form.venue_province_code) mapStatus.value = 'Đã lấy tọa độ và địa chỉ đường. Vui lòng chọn Tỉnh/Thành phố.';
    else mapStatus.value = 'Vị trí bản đồ khớp với địa chỉ đã chọn.';
    return;
  }
  const cur = [wards.value.find((w) => String(w.code) === String(form.venue_ward_code))?.name, provinces.value.find((p) => String(p.code) === String(form.venue_province_code))?.name].filter(Boolean).join(', ') || 'chưa chọn';
  const res = [resolved.ward, resolved.province].filter(Boolean).join(', ') || resolved.address || 'vị trí Google Maps';
  mapSuggestion.value = { province_code: rp, ward_code: rw, message: `Vị trí trên Google Maps thuộc ${res} — khác với địa chỉ bạn đã chọn (${cur}).` };
}

async function applyMapSuggestion() {
  if (!mapSuggestion.value) return;
  if (mapSuggestion.value.province_code) { form.venue_province_code = mapSuggestion.value.province_code; await loadWards(form.venue_province_code); }
  if (mapSuggestion.value.ward_code) form.venue_ward_code = mapSuggestion.value.ward_code;
  mapSuggestion.value = null; mapStatus.value = 'Đã cập nhật địa chỉ theo Google Maps.'; syncVenueAddress();
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
  return files[group]?.length > 0 || existingDocumentTypes.value.has(group);
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
  if (form.account_number && !/^\d+$/.test(form.account_number)) fieldErrors.account_number = 'Số tài khoản chỉ được nhập chữ số.';
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
    fieldErrors.venue_coordinates = 'H? th?ng �ang c?p nh?t �?a ch? theo t?a �?. Vui l?ng ch? ho�n t?t r?i g?i l?i.';
  }
  const courtCount = Number(form.court_count_total);
  if (!Number.isInteger(courtCount) || courtCount < 1 || courtCount > 100) fieldErrors.court_count_total = 'Số lượng sân con phải từ 1 đến 100.';
  const basePrice = Number(form.base_price_per_hour);
  if (!Number.isFinite(basePrice) || basePrice < 1000) fieldErrors.base_price_per_hour = 'Giá cơ bản phải từ 1.000 VNĐ trở lên.';
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
  const first = document.querySelector('.border-red-400, .border-red-300, .has-error');
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
      router.push({ name: 'partner-application-document', params: { id: application.id, documentId: doc.id }, query: { from: 'registration' } });
      return;
    }
    formOpen.value = false; await loadApplications();
  } catch (e) {
    clearErrors();
    const errors = e.data?.errors || {};
    Object.entries(errors).forEach(([f, m]) => { fieldErrors[f] = Array.isArray(m) ? m[0] : m; });
    if (Object.keys(errors).length) {
      await focusFirstError();
    } else {
      formBanner.value = e.message || 'Vui lòng kiểm tra lại thông tin hồ sơ.';
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  } finally { submitting.value = false; }
}

// ─── Application actions ──────────────────────────────────────────────────────
async function cancelApplication(application) {
  if (!window.confirm(`Hủy hồ sơ đăng ký cho ${application.venue_name}?`)) return;
  try {
    await api(`/api/user/partner-application/${application.id}/cancel`, { method: 'POST', body: JSON.stringify({ reason: 'Người dùng hủy hồ sơ từ trang đăng ký đối tác.' }) });
    alert('Đã hủy hồ sơ thành công.');
    await loadApplications();
  } catch (err) {
    alert(err.message || 'Không thể hủy hồ sơ lúc này.');
  }
}

function openApplicationDetail(application) {
  router.push({ name: 'partner-application-detail', params: { id: application.id } });
}

function openApplicationDocument(document, application) {
  if (!document || !application) return;
  router.push({ name: 'partner-application-document', params: { id: application.id, documentId: document.id } });
}

function canSubmitSignedApplication(application) {
  const doc = applicationWord(application);
  return application?.status === 'draft' && doc?.status === 'completed';
}

async function submitSignedApplication(application) {
  if (!application?.id) return;
  await api(`/api/user/partner-application/${application.id}/submit`, { method: 'POST' });
  await loadApplications();
}

// ─── Display helpers ──────────────────────────────────────────────────────────
function needsApplicationSignature(application) {
  const doc = applicationWord(application);
  if (!doc || doc.status === 'completed') return false;
  return !doc.signatures?.some(s => s.signer_side === 'owner' && s.status === 'signed');
}

function needsContractSignature(application) {
  const doc = contractWord(application);
  if (!doc || doc.status === 'completed') return false;
  return !doc.signatures?.some(s => s.signer_side === 'owner' && s.status === 'signed');
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
function canCancel(application) { return ['pending', 'submitted', 'reviewing', 'need_supplement', 'draft'].includes(application.status); }
function statusLabel(status) {
  return { draft: 'Chờ ký đơn', pending: 'Chờ xét duyệt', submitted: 'Chờ xét duyệt', reviewing: 'Đang xem xét', need_supplement: 'Cần bổ sung', contract_pending_owner_signature: 'Đã duyệt, chờ ký hợp đồng', contract_pending_sportgo_signature: 'Chờ SportGo ký', completed: 'Đang hoạt động', rejected: 'Bị từ chối', cancelled: 'Đã hủy' }[status] || status || '-';
}
function statusClass(status) {
  if (['rejected', 'cancelled'].includes(status)) return 'bg-red-50 text-red-700';
  if (status === 'completed') return 'bg-emerald-50 text-emerald-700';
  if (status === 'need_supplement') return 'bg-amber-50 text-amber-700';
  return 'bg-amber-50 text-amber-700';
}
function statusDotClass(status) {
  if (['rejected', 'cancelled'].includes(status)) return 'bg-red-400';
  if (status === 'completed') return 'bg-emerald-400';
  return 'bg-amber-400';
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
<style>
@import "../../../css/partner/partner.css";

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
