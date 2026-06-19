<template>
  <div class="partner-page">
    <PublicNavbar />

    <main class="shell">
      <section class="hero">
        <div>
          <p class="eyebrow">SportGo Partner</p>
          <h1>Đăng ký đối tác/chủ sân</h1>
          <p>Gửi hồ sơ chủ sân, xem lại các lần đăng ký và tải đơn đăng ký Mẫu 01 đã sinh từ hệ thống.</p>
        </div>
        <button class="btn primary" type="button" :disabled="formOpen || !canRegister" @click="startNewApplication">
          Đăng ký mới
        </button>
      </section>

      <section class="history-card">
        <div class="section-head">
          <div>
            <h2>Hồ sơ đăng ký của tôi</h2>
            <p>Hồ sơ đã gửi chỉ được xem và tải lại đơn đăng ký, không chỉnh sửa trực tiếp.</p>
          </div>
          <button class="btn ghost" type="button" @click="loadApplications">Làm mới</button>
        </div>

        <div v-if="draft" class="draft-row">
          <div>
            <strong>Nháp chưa gửi</strong>
            <p>{{ draft.venue_name || 'Chưa đặt tên cụm sân' }} · lưu lúc {{ formatDate(draft.saved_at) }}</p>
          </div>
          <div class="row-actions">
            <button class="btn secondary" type="button" @click="continueDraft">Tiếp tục chỉnh sửa</button>
            <button class="btn danger-soft" type="button" @click="clearDraft">Xóa nháp</button>
          </div>
        </div>

        <div v-if="loading" class="empty-state">Đang tải hồ sơ...</div>
        <div v-else-if="applications.length === 0 && !draft" class="empty-state">Bạn chưa có hồ sơ đăng ký đối tác nào.</div>
        <div v-else class="application-list">
          <article v-for="application in applications" :key="application.id" class="application-row">
            <div class="application-main">
              <div class="title-line">
                <strong>{{ application.venue_name }}</strong>
                <span :class="['status', application.status]">{{ statusLabel(application.status) }}</span>
              </div>
              <p>{{ application.venue_address }}</p>
              <small>Gửi ngày {{ formatDate(application.submitted_at) }} · {{ application.documents?.length || 0 }} tài liệu đính kèm</small>
              <p v-if="application.status === 'rejected'" class="reject-reason">
                Lý do từ chối: {{ application.status_reason || 'SportGo chưa nhập lý do cụ thể.' }}
              </p>
            </div>
            <div class="row-actions">
              <button class="btn secondary" type="button" @click="openApplicationDetail(application)">
                Xem chi tiết
              </button>
              <button v-if="applicationWord(application)" class="btn ghost" type="button" @click="downloadDocument(applicationWord(application))">
                Tải Mẫu 01
              </button>
              <button v-if="canCancel(application)" class="btn danger-soft" type="button" @click="cancelApplication(application)">
                Hủy hồ sơ
              </button>
              <button v-if="application.status === 'rejected'" class="btn secondary" type="button" @click="copyRejectedApplication(application)">
                Tạo lại từ hồ sơ này
              </button>
            </div>
          </article>
        </div>
      </section>

      <section v-if="selectedApplication" class="detail-drawer" role="dialog" aria-modal="true">
        <div class="drawer-card">
          <header class="drawer-head">
            <div>
              <p class="eyebrow">Hồ sơ đối tác</p>
              <h2>{{ selectedApplication.venue_name }}</h2>
              <span :class="['status', selectedApplication.status]">{{ statusLabel(selectedApplication.status) }}</span>
            </div>
            <button class="btn ghost" type="button" @click="selectedApplication = null">Đóng</button>
          </header>

          <div class="detail-grid">
            <div class="detail-block">
              <h3>Người đăng ký</h3>
              <p>{{ selectedApplication.applicant_full_name }} · {{ selectedApplication.applicant_phone }}</p>
              <small>{{ selectedApplication.applicant_email }} · sinh ngày {{ dateOnly(selectedApplication.applicant_birth_date) }}</small>
            </div>
            <div class="detail-block">
              <h3>Cụm sân</h3>
              <p>{{ selectedApplication.venue_address }}</p>
              <small>{{ selectedApplication.venue_ward }} · {{ selectedApplication.venue_province }} · {{ selectedApplication.venue_latitude }}, {{ selectedApplication.venue_longitude }}</small>
            </div>
            <div class="detail-block">
              <h3>Ngân hàng</h3>
              <p>{{ selectedApplication.bank_name }} · {{ selectedApplication.account_number }}</p>
              <small>{{ selectedApplication.account_holder_name }} · {{ selectedApplication.bank_verification_status === 'verified' ? 'Đã xác minh' : 'Chưa xác minh' }}</small>
            </div>
            <div class="detail-block">
              <h3>Cấu hình sân</h3>
              <p>{{ selectedApplication.court_count_total }} sân con · {{ money(selectedApplication.base_price_per_hour) }}/giờ</p>
              <small>{{ (selectedApplication.courts || []).map((court) => `${court.name} (${court.court_type?.name || court.court_type_name_snapshot || 'Loại sân'})`).join('; ') }}</small>
            </div>
          </div>

          <div class="document-section">
            <h3>Tài liệu đã gửi</h3>
            <div v-if="!(selectedApplication.documents || []).length" class="empty-files">Chưa có tài liệu đính kèm.</div>
            <div v-else class="submitted-docs">
              <button
                v-for="document in selectedApplication.documents"
                :key="document.id"
                class="doc-chip"
                type="button"
                @click="downloadApplicationDocument(document)"
              >
                {{ document.title || document.document_type }}
                <small>{{ document.document_group }}</small>
              </button>
            </div>
          </div>
        </div>
      </section>

      <form v-if="formOpen" class="wizard" novalidate @submit.prevent="submit">
        <nav class="steps" aria-label="Các bước đăng ký">
          <button
            v-for="(item, index) in steps"
            :key="item"
            type="button"
            :class="{ active: step === index, done: index < step }"
            @click="goToStep(index)"
          >
            <span>{{ index + 1 }}</span>{{ item }}
          </button>
        </nav>

        <section v-show="step === 0" class="step-panel">
          <div class="panel-intro">
            <h2>Thông tin người đăng ký / đơn vị kinh doanh</h2>
            <p>Thông tin này sẽ được đưa vào Mẫu 01 và dùng để SportGo liên hệ khi xét duyệt hồ sơ.</p>
          </div>

          <div class="subsection">
            <h3>Người đăng ký</h3>
            <div class="grid two">
              <Field label="Họ tên người đăng ký" required :error="fieldErrors.applicant_full_name">
                <input ref="applicant_full_name" v-model.trim="form.applicant_full_name" :class="inputClass('applicant_full_name')" />
              </Field>
              <Field label="Số điện thoại người đăng ký" required hint="Chỉ nhập 10 chữ số, bắt đầu bằng 0." :error="fieldErrors.applicant_phone">
                <input ref="applicant_phone" v-model.trim="form.applicant_phone" :class="inputClass('applicant_phone')" inputmode="numeric" @input="digitsOnly('applicant_phone', 10)" />
              </Field>
              <Field label="Email người đăng ký" required :error="fieldErrors.applicant_email">
                <input ref="applicant_email" v-model.trim="form.applicant_email" :class="inputClass('applicant_email')" type="email" />
              </Field>
              <Field label="Ngày sinh người đăng ký" required hint="Người đăng ký phải đủ 18 tuổi." :error="fieldErrors.applicant_birth_date">
                <input ref="applicant_birth_date" v-model="form.applicant_birth_date" :class="inputClass('applicant_birth_date')" type="date" @change="resetPreview" />
              </Field>
              <Field label="Loại chủ thể" required :error="fieldErrors.applicant_type">
                <select ref="applicant_type" v-model="form.applicant_type" :class="inputClass('applicant_type')">
                  <option value="individual">Cá nhân/hộ kinh doanh</option>
                  <option value="business">Hộ kinh doanh có giấy phép</option>
                  <option value="company">Doanh nghiệp</option>
                </select>
              </Field>
              <Field class="full" label="Địa chỉ liên hệ" required :error="fieldErrors.applicant_address">
                <textarea ref="applicant_address" v-model.trim="form.applicant_address" :class="inputClass('applicant_address')" rows="2"></textarea>
              </Field>
            </div>
          </div>

          <div class="subsection">
            <h3>Người đại diện và giấy tờ pháp lý</h3>
            <div class="grid three">
              <Field label="Người đại diện" required :error="fieldErrors.representative_name">
                <input ref="representative_name" v-model.trim="form.representative_name" :class="inputClass('representative_name')" />
              </Field>
              <Field label="Loại giấy tờ" required :error="fieldErrors.representative_identity_type">
                <select ref="representative_identity_type" v-model="form.representative_identity_type" :class="inputClass('representative_identity_type')" @change="normalizeIdentityNumber">
                  <option value="cccd">CCCD</option>
                  <option value="cmnd">CMND</option>
                  <option value="passport">Hộ chiếu</option>
                </select>
              </Field>
              <Field label="Số CCCD/CMND/Hộ chiếu" required :error="fieldErrors.representative_identity_number">
                <input ref="representative_identity_number" v-model.trim="form.representative_identity_number" :class="inputClass('representative_identity_number')" @input="normalizeIdentityNumber" />
              </Field>
              <Field label="Ngày cấp" :error="fieldErrors.representative_identity_issued_date">
                <input ref="representative_identity_issued_date" v-model="form.representative_identity_issued_date" :class="inputClass('representative_identity_issued_date')" type="date" />
              </Field>
              <Field label="Nơi cấp" :error="fieldErrors.representative_identity_issued_place">
                <input ref="representative_identity_issued_place" v-model.trim="form.representative_identity_issued_place" :class="inputClass('representative_identity_issued_place')" />
              </Field>
              <Field label="Chức vụ/vai trò" :error="fieldErrors.representative_position">
                <input ref="representative_position" v-model.trim="form.representative_position" :class="inputClass('representative_position')" />
              </Field>
            </div>
          </div>

          <div class="subsection">
            <h3>Đơn vị kinh doanh</h3>
            <div class="grid two">
              <Field label="Tên đơn vị/cá nhân kinh doanh" required :error="fieldErrors.business_name">
                <input ref="business_name" v-model.trim="form.business_name" :class="inputClass('business_name')" />
              </Field>
              <Field label="Mã số thuế" :error="fieldErrors.tax_code">
                <input ref="tax_code" v-model.trim="form.tax_code" :class="inputClass('tax_code')" @input="normalizeTaxCode" />
              </Field>
              <Field label="Số giấy đăng ký kinh doanh/pháp lý" required :error="fieldErrors.business_license_number">
                <input ref="business_license_number" v-model.trim="form.business_license_number" :class="inputClass('business_license_number')" />
              </Field>
              <Field label="Mã doanh nghiệp/hộ kinh doanh" :error="fieldErrors.business_code">
                <input ref="business_code" v-model.trim="form.business_code" :class="inputClass('business_code')" />
              </Field>
              <Field class="full" label="Địa chỉ pháp lý" required :error="fieldErrors.business_address">
                <textarea ref="business_address" v-model.trim="form.business_address" :class="inputClass('business_address')" rows="2"></textarea>
              </Field>
            </div>
          </div>
        </section>

        <section v-show="step === 1" class="step-panel">
          <div class="panel-intro">
            <h2>Địa chỉ và vị trí cụm sân</h2>
            <p>SportGo dùng địa giới 2 cấp: Tỉnh/Thành phố và Phường/Xã. Link Google Maps phải lấy được tọa độ.</p>
          </div>

          <div class="map-resolver">
            <Field label="Link Google Maps vị trí cụm sân" required hint="Dán link chia sẻ từ Google Maps, ví dụ maps.app.goo.gl hoặc google.com/maps." :error="fieldErrors.venue_map_url">
              <input ref="venue_map_url" v-model.trim="form.venue_map_url" :class="inputClass('venue_map_url')" type="url" @input="mapStatus = null" />
            </Field>
            <button class="btn secondary" type="button" :disabled="resolvingMap || !form.venue_map_url" @click="resolveMap">
              {{ resolvingMap ? 'Đang lấy vị trí...' : 'Lấy tọa độ từ link' }}
            </button>
          </div>
          <p v-if="mapStatus" :class="['status-message', mapStatus.type]">{{ mapStatus.message }}</p>

          <div class="grid two">
            <Field label="Tên cụm sân" required :error="fieldErrors.venue_name">
              <input ref="venue_name" v-model.trim="form.venue_name" :class="inputClass('venue_name')" />
            </Field>
            <Field label="Số điện thoại tại sân" required hint="Chỉ nhập 10 chữ số, bắt đầu bằng 0." :error="fieldErrors.venue_phone">
              <input ref="venue_phone" v-model.trim="form.venue_phone" :class="inputClass('venue_phone')" inputmode="numeric" @input="digitsOnly('venue_phone', 10)" />
            </Field>
            <Field label="Email tại sân" :error="fieldErrors.venue_email">
              <input ref="venue_email" v-model.trim="form.venue_email" :class="inputClass('venue_email')" type="email" />
            </Field>
            <Field label="Giờ mở cửa dự kiến" :error="fieldErrors.expected_opening_hours">
              <input ref="expected_opening_hours" v-model.trim="form.expected_opening_hours" :class="inputClass('expected_opening_hours')" placeholder="05:00 - 23:00" />
            </Field>
            <Field label="Tỉnh/Thành phố" required :error="fieldErrors.venue_province_code">
              <SearchableSelect
                ref="venue_province_code"
                v-model="form.venue_province_code"
                :options="provinces"
                placeholder="Tìm Tỉnh/Thành phố"
                :invalid="Boolean(fieldErrors.venue_province_code)"
                @change="onProvinceChange"
              />
            </Field>
            <Field label="Phường/Xã" required :error="fieldErrors.venue_ward_code">
              <SearchableSelect
                ref="venue_ward_code"
                v-model="form.venue_ward_code"
                :options="wards"
                placeholder="Tìm Phường/Xã"
                :disabled="!form.venue_province_code"
                :invalid="Boolean(fieldErrors.venue_ward_code)"
              />
            </Field>
            <Field class="full" label="Địa chỉ chi tiết cụm sân" required hint="Địa chỉ thực tế của cụm sân, không dùng quận/huyện 3 cấp." :error="fieldErrors.venue_address">
              <textarea ref="venue_address" v-model.trim="form.venue_address" :class="inputClass('venue_address')" rows="2"></textarea>
            </Field>
            <Field label="Vĩ độ" required :error="fieldErrors.venue_latitude">
              <input ref="venue_latitude" v-model.number="form.venue_latitude" :class="inputClass('venue_latitude')" type="number" step="0.0000001" />
            </Field>
            <Field label="Kinh độ" required :error="fieldErrors.venue_longitude">
              <input ref="venue_longitude" v-model.number="form.venue_longitude" :class="inputClass('venue_longitude')" type="number" step="0.0000001" />
            </Field>
            <Field class="full" label="Mô tả ngắn về cơ sở" :error="fieldErrors.venue_description">
              <textarea ref="venue_description" v-model.trim="form.venue_description" :class="inputClass('venue_description')" rows="3"></textarea>
            </Field>
            <Field class="full" label="Bãi xe/khu phụ trợ" :error="fieldErrors.parking_info">
              <textarea ref="parking_info" v-model.trim="form.parking_info" :class="inputClass('parking_info')" rows="2"></textarea>
            </Field>
          </div>

          <div class="amenities">
            <span>Tiện ích tại cụm sân</span>
            <label v-for="amenity in amenities" :key="amenity.id || amenity.name" class="chip">
              <input v-model="form.amenities" type="checkbox" :value="amenity.name" />
              {{ amenity.name }}
            </label>
          </div>
        </section>

        <section v-show="step === 2" class="step-panel">
          <div class="panel-intro">
            <h2>Tài khoản ngân hàng nhận tiền</h2>
            <p>Thông tin này dùng để SportGo đối soát và thanh toán cho chủ sân sau khi hồ sơ được duyệt.</p>
          </div>

          <div class="grid two">
            <Field label="Ngân hàng" required :error="fieldErrors.bank_code">
              <SearchableSelect
                ref="bank_code"
                v-model="form.bank_code"
                :options="banks"
                placeholder="Tìm ngân hàng"
                :invalid="Boolean(fieldErrors.bank_code)"
                @change="selectBank"
              />
            </Field>
            <Field label="Số tài khoản" required hint="Chỉ nhập chữ số, không nhập dấu cách hoặc ký tự khác." :error="fieldErrors.account_number">
              <input ref="account_number" v-model.trim="form.account_number" :class="inputClass('account_number')" inputmode="numeric" @input="onAccountNumberInput" />
            </Field>
            <Field label="Tên chủ tài khoản" required hint="Hệ thống tự điền sau khi kiểm tra số tài khoản thành công." :error="fieldErrors.account_holder_name">
              <input ref="account_holder_name" v-model.trim="form.account_holder_name" :class="inputClass('account_holder_name')" readonly placeholder="Chưa xác minh" />
            </Field>
            <Field label="Chi nhánh" :error="fieldErrors.bank_branch">
              <input ref="bank_branch" v-model.trim="form.bank_branch" :class="inputClass('bank_branch')" />
            </Field>
          </div>

          <div class="verification-box">
            <button class="btn secondary" type="button" :disabled="verifyingBank" @click="verifyBank">
              {{ verifyingBank ? 'Đang kiểm tra...' : 'Kiểm tra tài khoản' }}
            </button>
            <div v-if="bankVerification.message" :class="['verify-text', bankVerification.status]">
              <strong>{{ bankStatusTitle }}</strong>
              <p>{{ bankVerification.message }}</p>
              <small v-if="bankVerification.provider_account_name">Tên ngân hàng trả về: {{ bankVerification.provider_account_name }}</small>
            </div>
          </div>
        </section>

        <section v-show="step === 3" class="step-panel">
          <div class="panel-intro">
            <h2>Cấu hình cụm sân ban đầu</h2>
            <p>Giá cơ bản/giờ là giá chung ban đầu của cả cụm sân. Sân con chỉ cần tên sân và loại sân.</p>
          </div>

          <div class="grid three compact">
            <Field label="Số lượng sân con" required :error="fieldErrors.court_count_total">
              <input ref="court_count_total" v-model.number="form.court_count_total" :class="inputClass('court_count_total')" min="1" max="100" type="number" @change="syncCourtRows" />
            </Field>
            <Field label="Giá cơ bản/giờ của cụm sân" required hint="Áp dụng chung cho toàn cụm sân khi khởi tạo." :error="fieldErrors.base_price_per_hour">
              <input ref="base_price_per_hour" v-model.number="form.base_price_per_hour" :class="inputClass('base_price_per_hour')" min="1000" step="1000" type="number" />
            </Field>
            <div class="align-end">
              <button class="btn secondary" type="button" @click="syncCourtRows">Cập nhật danh sách sân</button>
            </div>
          </div>

          <div class="court-list">
            <article v-for="(court, index) in form.courts" :key="court.local_id" class="court-row">
              <Field :label="`Tên sân con ${index + 1}`" required :error="fieldErrors[`courts.${index}.name`]">
                <input :ref="`courts.${index}.name`" v-model.trim="court.name" :class="inputClass(`courts.${index}.name`)" />
              </Field>
              <Field label="Loại sân" required :error="fieldErrors[`courts.${index}.court_type_id`]">
                <SearchableSelect
                  :ref="`courts.${index}.court_type_id`"
                  v-model="court.court_type_id"
                  :options="usableCourtTypes"
                  value-key="id"
                  placeholder="Tìm loại sân con"
                  :invalid="Boolean(fieldErrors[`courts.${index}.court_type_id`])"
                />
              </Field>
              <button class="remove-btn" type="button" :disabled="form.courts.length === 1" @click="removeCourt(index)">Xóa</button>
            </article>
          </div>
          <button class="btn ghost" type="button" @click="addCourt">Thêm sân con</button>
        </section>

        <section v-show="step === 4" class="step-panel">
          <div class="panel-intro">
            <h2>Tài liệu đính kèm</h2>
            <p>Tải file theo từng nhóm để SportGo và admin biết chính xác mỗi tài liệu dùng cho mục đích nào.</p>
          </div>

          <div class="upload-grid">
            <UploadGroup
              title="CCCD/CMND người đăng ký"
              description="Tải mặt trước/mặt sau hoặc file PDF giấy tờ định danh."
              required
              :files="files.identity"
              :error="fieldErrors.identity_documents"
              @change="setFiles('identity', $event)"
              @remove="removeFile('identity', $event)"
            />
            <UploadGroup
              title="Giấy đăng ký kinh doanh/pháp lý"
              description="Giấy phép kinh doanh, giấy ủy quyền hoặc giấy tờ chứng minh quyền quản lý sân."
              required
              :files="files.business_license"
              :error="fieldErrors.business_license_documents"
              @change="setFiles('business_license', $event)"
              @remove="removeFile('business_license', $event)"
            />
            <UploadGroup
              title="Hình ảnh cơ sở/sân"
              description="Ảnh tổng quan, mặt sân, biển hiệu, khu phụ trợ. Có thể tải nhiều file."
              required
              :files="files.facility"
              :error="fieldErrors.facility_images"
              @change="setFiles('facility', $event)"
              @remove="removeFile('facility', $event)"
            />
            <UploadGroup
              title="Tài liệu bổ sung khác"
              description="Các tài liệu khác nếu bạn muốn SportGo xem xét thêm."
              :files="files.additional"
              :error="fieldErrors.additional_documents"
              @change="setFiles('additional', $event)"
              @remove="removeFile('additional', $event)"
            />
          </div>
        </section>

        <section v-show="step === 5" class="step-panel">
          <div class="panel-intro">
            <h2>Xem đơn đăng ký Word và xác nhận gửi</h2>
            <p>Hệ thống sinh Mẫu 01 từ template Word gốc. Hãy đọc kỹ nội dung trước khi gửi hồ sơ.</p>
          </div>

          <div class="preview-actions">
            <button class="btn secondary" type="button" :disabled="generatingPreview" @click="generatePreview">
              {{ generatingPreview ? 'Đang tạo Mẫu 01...' : previewDocument ? 'Tạo lại Mẫu 01' : 'Tạo Mẫu 01 để xem' }}
            </button>
            <button v-if="previewDocument" class="btn ghost" type="button" @click="downloadPreview">Tải file Word</button>
          </div>

          <div v-if="previewData" class="document-preview">
            <header>
              <p>CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</p>
              <strong>ĐƠN ĐỀ NGHỊ ĐĂNG KÝ TRỞ THÀNH ĐỐI TÁC/CHỦ SÂN SPORTGO</strong>
            </header>
            <dl>
              <dt>Người đăng ký</dt><dd>{{ previewData.applicant_full_name }} · {{ previewData.applicant_phone }} · {{ previewData.applicant_email }}</dd>
              <dt>Giấy tờ định danh</dt><dd>{{ previewData.representative_identity_type }} · {{ previewData.representative_identity_number }}</dd>
              <dt>Đơn vị kinh doanh</dt><dd>{{ previewData.business_name }} · MST {{ previewData.tax_code || '-' }}</dd>
              <dt>Cụm sân</dt><dd>{{ previewData.venue_name }} · {{ previewData.venue_address }}</dd>
              <dt>Khu vực 2 cấp</dt><dd>{{ previewData.venue_ward }} · {{ previewData.venue_province }}</dd>
              <dt>Google Maps</dt><dd>{{ previewData.venue_map_url }}</dd>
              <dt>Tọa độ</dt><dd>{{ previewData.venue_latitude }}, {{ previewData.venue_longitude }}</dd>
              <dt>Quy mô</dt><dd>{{ previewData.court_count_total }} sân con · giá cơ bản {{ previewData.base_price_per_hour_label }}/giờ</dd>
              <dt>Danh sách sân</dt><dd>{{ previewData.courts_summary }}</dd>
              <dt>Ngân hàng</dt><dd>{{ previewData.bank_name }} · {{ previewData.account_number }} · {{ previewData.account_holder_name }}</dd>
              <dt>Trạng thái ngân hàng</dt><dd>{{ previewData.bank_verification_label }}</dd>
              <dt>Tài liệu</dt><dd>{{ attachmentsSummary }}</dd>
            </dl>
            <p class="preview-note">File Word tải xuống được tạo từ Mẫu 01 gốc và có phụ lục thông tin đã điền ở cuối tài liệu.</p>
          </div>
          <div v-else class="empty-preview">Bấm “Tạo Mẫu 01 để xem” để hệ thống sinh file Word và hiển thị nội dung đọc được.</div>

          <label class="confirm-line" :class="{ invalid: fieldErrors.confirmed }">
            <input ref="confirmed" v-model="confirmed" type="checkbox" />
            <span>Tôi đã đọc nội dung đơn đăng ký đối tác/chủ sân và xác nhận thông tin trong đơn là chính xác.</span>
          </label>
          <p v-if="fieldErrors.confirmed" class="field-error">{{ fieldErrors.confirmed }}</p>
        </section>

        <p v-if="formBanner" class="alert">{{ formBanner }}</p>

        <footer class="actions">
          <button class="btn ghost" type="button" @click="cancelEditing">Hủy</button>
          <button class="btn ghost" type="button" @click="saveDraft">Lưu nháp</button>
          <button v-if="step > 0" class="btn secondary" type="button" @click="prevStep">Quay lại</button>
          <button v-if="step < steps.length - 1" class="btn primary" type="button" @click="nextStep">Tiếp tục</button>
          <button v-else class="btn primary" type="submit" :disabled="submitting">Gửi hồ sơ</button>
        </footer>
      </form>
    </main>
  </div>
</template>

<script>
import PublicNavbar from '../components/PublicNavbar.vue';
import { api, apiDownload, apiFormData } from '../services/api.js';
import { getAuth } from '../stores/auth.js';

const DRAFT_KEY = 'sportgo_partner_application_draft_v2';

function localId() {
  return Math.random().toString(36).slice(2);
}

const Field = {
  props: ['label', 'required', 'hint', 'error'],
  template: `
    <label class="field">
      <span>{{ label }} <b v-if="required">*</b></span>
      <slot />
      <small v-if="hint" class="hint">{{ hint }}</small>
      <small v-if="error" class="field-error">{{ error }}</small>
    </label>
  `,
};

const SearchableSelect = {
  props: {
    modelValue: [String, Number],
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Chọn dữ liệu' },
    labelKey: { type: String, default: 'name' },
    valueKey: { type: String, default: 'code' },
    disabled: Boolean,
    invalid: Boolean,
  },
  emits: ['update:modelValue', 'change'],
  data() {
    return {
      open: false,
      search: '',
    };
  },
  computed: {
    selectedOption() {
      return this.options.find((option) => String(option[this.valueKey]) === String(this.modelValue));
    },
    displayValue() {
      return this.open ? this.search : (this.selectedOption ? this.optionLabel(this.selectedOption) : '');
    },
    filteredOptions() {
      const keyword = this.normalize(this.search);
      if (!keyword) return this.options.slice(0, 80);
      return this.options
        .filter((option) => this.normalize(this.optionLabel(option)).includes(keyword))
        .slice(0, 80);
    },
  },
  watch: {
    modelValue() {
      if (!this.open) this.search = '';
    },
  },
  methods: {
    focus() {
      this.$refs.input?.focus();
    },
    optionLabel(option) {
      const base = option?.[this.labelKey] || '';
      if (option?.short_name && option?.name && option.short_name !== option.name) {
        return `${option.short_name} - ${option.name}`;
      }
      return base;
    },
    normalize(value) {
      return String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
    },
    choose(option) {
      this.$emit('update:modelValue', option[this.valueKey]);
      this.$emit('change', option);
      this.search = '';
      this.open = false;
    },
    clear() {
      this.$emit('update:modelValue', '');
      this.$emit('change', null);
      this.search = '';
      this.open = false;
      this.focus();
    },
  },
  template: `
    <div class="combo" :class="{ open, invalid, disabled }">
      <input
        ref="input"
        type="text"
        :disabled="disabled"
        :placeholder="placeholder"
        :value="displayValue"
        @focus="open = true"
        @input="search = $event.target.value; open = true"
        @keydown.esc="open = false"
      />
      <button v-if="modelValue && !disabled" class="combo-clear" type="button" @mousedown.prevent="clear">×</button>
      <div v-if="open && !disabled" class="combo-menu">
        <button
          v-for="option in filteredOptions"
          :key="option[valueKey]"
          type="button"
          :class="{ active: String(option[valueKey]) === String(modelValue) }"
          @mousedown.prevent="choose(option)"
        >
          {{ optionLabel(option) }}
        </button>
        <p v-if="!filteredOptions.length">Không tìm thấy dữ liệu phù hợp.</p>
      </div>
    </div>
  `,
};

const UploadGroup = {
  props: ['title', 'description', 'required', 'files', 'error'],
  emits: ['change', 'remove'],
  methods: {
    fileSize(file) {
      if (!file?.size) return '';
      return `${(file.size / 1024 / 1024).toFixed(2)} MB`;
    },
    openFile(file) {
      const url = URL.createObjectURL(file);
      window.open(url, '_blank', 'noopener');
      setTimeout(() => URL.revokeObjectURL(url), 60000);
    },
  },
  template: `
    <section class="upload-box" :class="{ invalid: error }">
      <div>
        <h3>{{ title }} <b v-if="required">*</b></h3>
        <p>{{ description }}</p>
      </div>
      <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" @change="$emit('change', $event)" />
      <small v-if="error" class="field-error">{{ error }}</small>
      <ul v-if="files.length" class="file-list">
        <li v-for="(file, index) in files" :key="file.name + index">
          <span>{{ file.name }}</span>
          <small>{{ fileSize(file) }} · sẵn sàng tải lên</small>
          <button type="button" @click="openFile(file)">Xem</button>
          <button type="button" @click="$emit('remove', index)">Xóa</button>
        </li>
      </ul>
      <p v-else class="empty-files">Chưa chọn file.</p>
    </section>
  `,
};

export default {
  name: 'PartnerApplicationPortal',
  components: { PublicNavbar, Field, SearchableSelect, UploadGroup },
  data() {
    const user = getAuth();
    return {
      user,
      loading: false,
      applications: [],
      selectedApplication: null,
      canRegister: true,
      draft: null,
      formOpen: false,
      step: 0,
      steps: ['Người đăng ký', 'Địa chỉ', 'Ngân hàng', 'Cụm sân', 'Tài liệu', 'Xác nhận'],
      form: this.defaultForm(user),
      files: this.blankFiles(),
      provinces: [],
      wards: [],
      banks: [],
      courtTypes: [],
      amenities: [],
      fieldErrors: {},
      formBanner: '',
      resolvingMap: false,
      mapStatus: null,
      verifyingBank: false,
      bankVerification: {},
      generatingPreview: false,
      previewDocument: null,
      previewData: null,
      confirmed: false,
      submitting: false,
    };
  },
  computed: {
    attachmentsSummary() {
      return [
        `${this.files.identity.length} file CCCD/CMND`,
        `${this.files.business_license.length} file pháp lý`,
        `${this.files.facility.length} ảnh/tài liệu cơ sở`,
        `${this.files.additional.length} tài liệu bổ sung`,
      ].join(', ');
    },
    bankStatusTitle() {
      const status = this.bankVerification.status;
      if (status === 'verified') return 'Đã xác minh';
      if (status === 'lookup_not_configured') return 'Chưa cấu hình kiểm tra ngân hàng';
      if (status === 'provider_unavailable') return 'Không kết nối được dịch vụ ngân hàng';
      if (status === 'name_mismatch') return 'Tên chủ tài khoản không khớp';
      return 'Chưa xác minh';
    },
    usableCourtTypes() {
      return this.courtTypes.filter((type) => type.is_active !== false && Number(type.children_count || 0) === 0);
    },
  },
  async created() {
    if (!this.user) {
      this.$router.replace({ name: 'login' });
      return;
    }

    this.loadDraft();
    await Promise.all([
      this.loadApplications(),
      this.loadBanks(),
      this.loadProvinces(),
      this.loadCourtTypes(),
      this.loadAmenities(),
    ]);
  },
  methods: {
    defaultForm(user) {
      return {
        applicant_full_name: user?.fullName || '',
        applicant_phone: user?.phone || '',
        applicant_email: user?.email || '',
        applicant_birth_date: '',
        applicant_address: '',
        applicant_type: 'individual',
        representative_name: user?.fullName || '',
        representative_identity_type: 'cccd',
        representative_identity_number: '',
        representative_identity_issued_date: '',
        representative_identity_issued_place: '',
        representative_position: 'Chủ cơ sở',
        business_name: '',
        tax_code: '',
        business_code: '',
        business_license_number: '',
        business_address: '',
        venue_name: '',
        venue_address: '',
        venue_province_code: '',
        venue_ward_code: '',
        venue_map_url: '',
        venue_latitude: '',
        venue_longitude: '',
        venue_phone: user?.phone || '',
        venue_email: user?.email || '',
        venue_description: '',
        expected_opening_hours: '05:00 - 23:00',
        parking_info: '',
        amenities: [],
        court_count_total: 1,
        base_price_per_hour: '',
        courts: [{ local_id: localId(), name: 'Sân 1', court_type_id: '', note: '' }],
        bank_name: '',
        bank_code: '',
        bank_bin: '',
        account_number: '',
        account_holder_name: '',
        bank_branch: '',
      };
    },
    blankFiles() {
      return { identity: [], business_license: [], facility: [], additional: [] };
    },
    async loadApplications() {
      this.loading = true;
      try {
        const response = await api('/api/user/partner-application');
        this.applications = response.data?.history || [];
        this.canRegister = Boolean(response.data?.can_register);
      } finally {
        this.loading = false;
      }
    },
    async loadBanks() {
      const response = await api('/api/user/partner-application/banks');
      this.banks = response.data || [];
    },
    async loadProvinces() {
      const response = await api('/api/user/partner-application/provinces');
      this.provinces = response.data || [];
    },
    async loadWards(provinceCode) {
      if (!provinceCode) {
        this.wards = [];
        return;
      }
      const response = await api(`/api/user/partner-application/provinces/${provinceCode}/wards`);
      this.wards = response.data || [];
    },
    async loadCourtTypes() {
      const response = await api('/api/court-types');
      this.courtTypes = response.data || [];
    },
    async loadAmenities() {
      const response = await api('/api/amenities?active_only=1');
      this.amenities = response.data || [];
    },
    startNewApplication() {
      this.form = this.defaultForm(this.user);
      this.files = this.blankFiles();
      this.fieldErrors = {};
      this.formBanner = '';
      this.previewDocument = null;
      this.previewData = null;
      this.confirmed = false;
      this.step = 0;
      this.formOpen = true;
    },
    loadDraft() {
      try {
        this.draft = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null');
      } catch {
        this.draft = null;
      }
    },
    async continueDraft() {
      if (!this.draft) return;
      this.form = {
        ...this.defaultForm(this.user),
        ...this.draft,
        courts: (this.draft.courts || []).map((court) => ({ local_id: court.local_id || localId(), ...court })),
      };
      await this.loadWards(this.form.venue_province_code);
      this.formOpen = true;
      this.step = 0;
    },
    clearDraft() {
      localStorage.removeItem(DRAFT_KEY);
      this.draft = null;
    },
    saveDraft() {
      const payload = { ...this.form, saved_at: new Date().toISOString() };
      localStorage.setItem(DRAFT_KEY, JSON.stringify(payload));
      this.draft = payload;
      this.formBanner = 'Đã lưu nháp trên trình duyệt này.';
    },
    cancelEditing() {
      if (window.confirm('Hủy thao tác hiện tại? Dữ liệu chưa lưu sẽ mất.')) {
        this.formOpen = false;
      }
    },
    async goToStep(target) {
      if (target <= this.step) {
        this.step = target;
        this.formBanner = '';
        return;
      }

      for (let index = this.step; index < target; index += 1) {
        if (!this.validateStep(index)) {
          this.step = index;
          return;
        }
      }

      this.step = target;
      if (target === 5) await this.generatePreview();
    },
    nextStep() {
      if (this.validateStep(this.step)) {
        this.step += 1;
        if (this.step === 5) this.generatePreview();
      }
    },
    prevStep() {
      this.step -= 1;
      this.formBanner = '';
    },
    validateStep(step) {
      this.fieldErrors = {};
      this.formBanner = '';
      const required = (field, message) => {
        if (this.empty(this.form[field])) this.fieldErrors[field] = message;
      };

      if (step === 0) {
        ['applicant_full_name', 'applicant_phone', 'applicant_email', 'applicant_birth_date', 'applicant_address', 'representative_name', 'representative_identity_number', 'business_name', 'business_license_number', 'business_address'].forEach((field) => required(field, 'Trường này là bắt buộc.'));
        if (this.form.applicant_phone && !this.validPhone(this.form.applicant_phone)) this.fieldErrors.applicant_phone = 'Số điện thoại phải gồm 10 chữ số và bắt đầu bằng 0.';
        if (this.form.applicant_email && !this.validEmail(this.form.applicant_email)) this.fieldErrors.applicant_email = 'Email không đúng định dạng.';
        if (this.form.applicant_birth_date && !this.isAdult(this.form.applicant_birth_date)) this.fieldErrors.applicant_birth_date = 'Người đăng ký phải đủ 18 tuổi.';
        if (this.form.representative_identity_number && !this.validIdentityNumber()) this.fieldErrors.representative_identity_number = 'Số giấy tờ không đúng định dạng đã chọn.';
        if (this.form.tax_code && !this.validTaxCode(this.form.tax_code)) this.fieldErrors.tax_code = 'Mã số thuế phải gồm 10 số hoặc 13 số, có thể dùng dấu gạch trước 3 số cuối.';
      }

      if (step === 1) {
        ['venue_map_url', 'venue_name', 'venue_phone', 'venue_address', 'venue_province_code', 'venue_ward_code', 'venue_latitude', 'venue_longitude'].forEach((field) => required(field, 'Trường này là bắt buộc.'));
        if (this.form.venue_phone && !this.validPhone(this.form.venue_phone)) this.fieldErrors.venue_phone = 'Số điện thoại sân phải gồm 10 chữ số và bắt đầu bằng 0.';
        if (this.form.venue_email && !this.validEmail(this.form.venue_email)) this.fieldErrors.venue_email = 'Email sân không đúng định dạng.';
        if (this.form.venue_map_url && !this.validGoogleMapUrl(this.form.venue_map_url)) this.fieldErrors.venue_map_url = 'Vui lòng nhập link Google Maps hợp lệ.';
        if (!this.validCoordinate(this.form.venue_latitude, -90, 90)) this.fieldErrors.venue_latitude = 'Vĩ độ không hợp lệ.';
        if (!this.validCoordinate(this.form.venue_longitude, -180, 180)) this.fieldErrors.venue_longitude = 'Kinh độ không hợp lệ.';
      }

      if (step === 2) {
        ['bank_code', 'account_number'].forEach((field) => required(field, 'Trường này là bắt buộc.'));
        if (this.form.account_number && !/^\d{6,19}$/.test(this.form.account_number)) this.fieldErrors.account_number = 'Số tài khoản chỉ được nhập 6-19 chữ số.';
        if (this.bankVerification.status !== 'verified') this.fieldErrors.account_number = this.bankVerification.message || 'Vui lòng kiểm tra tài khoản ngân hàng thành công trước khi tiếp tục.';
        if (!this.form.account_holder_name) this.fieldErrors.account_holder_name = 'Tên chủ tài khoản sẽ được tự động điền sau khi xác minh thành công.';
      }

      if (step === 3) {
        required('court_count_total', 'Vui lòng nhập số lượng sân con.');
        required('base_price_per_hour', 'Vui lòng nhập giá cơ bản/giờ của cụm sân.');
        if (!Number.isInteger(Number(this.form.court_count_total)) || Number(this.form.court_count_total) < 1) this.fieldErrors.court_count_total = 'Số lượng sân con phải là số nguyên dương.';
        if (Number(this.form.base_price_per_hour) <= 0) this.fieldErrors.base_price_per_hour = 'Giá cơ bản/giờ phải lớn hơn 0.';
        this.form.courts.forEach((court, index) => {
          if (this.empty(court.name)) this.fieldErrors[`courts.${index}.name`] = 'Vui lòng nhập tên sân con.';
          if (this.empty(court.court_type_id)) this.fieldErrors[`courts.${index}.court_type_id`] = 'Vui lòng chọn loại sân.';
          if (court.court_type_id && !this.usableCourtTypes.some((type) => String(type.id) === String(court.court_type_id))) {
            this.fieldErrors[`courts.${index}.court_type_id`] = 'Chỉ được chọn loại sân con đang hoạt động và không còn loại con bên dưới.';
          }
        });
      }

      if (step === 4) {
        if (!this.files.identity.length) this.fieldErrors.identity_documents = 'Vui lòng tải CCCD/CMND người đăng ký.';
        if (!this.files.business_license.length) this.fieldErrors.business_license_documents = 'Vui lòng tải giấy đăng ký kinh doanh hoặc giấy tờ pháp lý.';
        if (!this.files.facility.length) this.fieldErrors.facility_images = 'Vui lòng tải hình ảnh cơ sở/sân.';
        this.validateFiles();
      }

      if (step === 5) {
        if (!this.previewDocument) this.fieldErrors.confirmed = 'Vui lòng tạo và đọc Mẫu 01 trước khi gửi.';
        else if (!this.confirmed) this.fieldErrors.confirmed = 'Bạn cần xác nhận đã đọc đơn đăng ký trước khi gửi.';
      }

      const valid = Object.keys(this.fieldErrors).length === 0;
      if (!valid) this.focusFirstError();
      return valid;
    },
    validateFiles() {
      const allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
      Object.entries(this.files).forEach(([group, files]) => {
        const invalid = files.find((file) => !allowed.includes(file.type) || file.size > 10 * 1024 * 1024);
        if (invalid) {
          const map = { identity: 'identity_documents', business_license: 'business_license_documents', facility: 'facility_images', additional: 'additional_documents' };
          this.fieldErrors[map[group]] = 'File chỉ hỗ trợ JPG, PNG, WEBP, PDF và tối đa 10MB/file.';
        }
      });
    },
    empty(value) {
      return value === null || value === undefined || String(value).trim() === '';
    },
    validPhone(value) {
      return /^0[0-9]{9}$/.test(value);
    },
    validTaxCode(value) {
      return /^\d{10}(-?\d{3})?$/.test(value);
    },
    validIdentityNumber() {
      const value = this.form.representative_identity_number;
      if (this.form.representative_identity_type === 'cccd') return /^\d{12}$/.test(value);
      if (this.form.representative_identity_type === 'cmnd') return /^\d{9}(\d{3})?$/.test(value);
      return /^[A-Z0-9]{6,20}$/i.test(value);
    },
    isAdult(value) {
      const birthday = new Date(value);
      if (Number.isNaN(birthday.getTime())) return false;
      const today = new Date();
      let age = today.getFullYear() - birthday.getFullYear();
      const monthDiff = today.getMonth() - birthday.getMonth();
      if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthday.getDate())) age -= 1;
      return age >= 18;
    },
    digitsOnly(field, maxLength = 99) {
      this.form[field] = String(this.form[field] || '').replace(/\D/g, '').slice(0, maxLength);
      this.resetPreview();
    },
    normalizeIdentityNumber() {
      if (this.form.representative_identity_type === 'passport') {
        this.form.representative_identity_number = String(this.form.representative_identity_number || '')
          .replace(/[^a-z0-9]/gi, '')
          .toUpperCase()
          .slice(0, 20);
      } else {
        const maxLength = this.form.representative_identity_type === 'cccd' ? 12 : 12;
        this.form.representative_identity_number = String(this.form.representative_identity_number || '').replace(/\D/g, '').slice(0, maxLength);
      }
      this.resetPreview();
    },
    normalizeTaxCode() {
      this.form.tax_code = String(this.form.tax_code || '').replace(/[^\d-]/g, '').slice(0, 14);
      this.resetPreview();
    },
    validEmail(value) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    },
    validGoogleMapUrl(value) {
      try {
        const host = new URL(value).hostname;
        return host.includes('google.') || host.includes('goo.gl') || host.includes('maps.app.goo.gl');
      } catch {
        return false;
      }
    },
    validCoordinate(value, min, max) {
      const number = Number(value);
      return Number.isFinite(number) && number >= min && number <= max;
    },
    inputClass(field) {
      return { invalid: Boolean(this.fieldErrors[field]) };
    },
    focusFirstError() {
      this.formBanner = 'Vui lòng kiểm tra các trường đang báo lỗi.';
      this.$nextTick(() => {
        const first = Object.keys(this.fieldErrors)[0];
        const ref = this.$refs[first];
        const el = Array.isArray(ref) ? ref[0] : ref;
        if (el?.focus) el.focus();
      });
    },
    async onProvinceChange() {
      this.form.venue_ward_code = '';
      await this.loadWards(this.form.venue_province_code);
    },
    async resolveMap() {
      this.fieldErrors.venue_map_url = '';
      if (!this.validGoogleMapUrl(this.form.venue_map_url)) {
        this.fieldErrors.venue_map_url = 'Vui lòng nhập link Google Maps hợp lệ.';
        this.focusFirstError();
        return;
      }
      this.resolvingMap = true;
      try {
        const response = await api('/api/user/partner-application/resolve-map', {
          method: 'POST',
          body: JSON.stringify({ url: this.form.venue_map_url }),
        });
        const data = response.data || {};
        this.form.venue_latitude = data.latitude || this.form.venue_latitude;
        this.form.venue_longitude = data.longitude || this.form.venue_longitude;
        this.form.venue_address = data.address || this.form.venue_address;
        if (data.province_code) {
          this.form.venue_province_code = data.province_code;
          await this.loadWards(data.province_code);
          this.form.venue_ward_code = data.ward_code || this.form.venue_ward_code;
        }
        this.mapStatus = {
          type: data.province_code && data.ward_code ? 'success' : 'warning',
          message: data.province_code && data.ward_code
            ? 'Đã lấy tọa độ và tự điền tỉnh/phường từ link Google Maps.'
            : 'Đã lấy được tọa độ. Vui lòng kiểm tra và chọn Tỉnh/Thành phố, Phường/Xã thủ công nếu còn trống.',
        };
      } catch (error) {
        this.fieldErrors.venue_map_url = error.data?.errors?.url?.[0] || error.message;
        this.focusFirstError();
      } finally {
        this.resolvingMap = false;
      }
    },
    selectBank(option = null) {
      const bank = option || this.banks.find((item) => item.code === this.form.bank_code);
      this.form.bank_name = bank?.short_name || bank?.name || '';
      this.form.bank_bin = bank?.bin || '';
      this.resetBankVerification();
    },
    onAccountNumberInput() {
      this.form.account_number = this.form.account_number.replace(/\D/g, '').slice(0, 19);
      this.resetBankVerification();
    },
    resetBankVerification() {
      this.bankVerification = {};
      this.form.account_holder_name = '';
      this.resetPreview();
    },
    resetPreview() {
      this.previewDocument = null;
      this.previewData = null;
      this.confirmed = false;
    },
    async verifyBank() {
      this.fieldErrors.account_number = '';
      if (!this.form.bank_code || !/^\d{6,19}$/.test(this.form.account_number)) {
        this.fieldErrors.account_number = 'Vui lòng chọn ngân hàng và nhập số tài khoản hợp lệ.';
        this.focusFirstError();
        return;
      }
      this.form.account_holder_name = '';
      this.verifyingBank = true;
      try {
        const response = await api('/api/user/partner-application/verify-bank-account', {
          method: 'POST',
          body: JSON.stringify({
            bank_code: this.form.bank_code,
            bank_bin: this.form.bank_bin,
            account_number: this.form.account_number,
          }),
        });
        this.bankVerification = response.data || {};
        if (this.bankVerification.status === 'verified' && this.bankVerification.provider_account_name) {
          this.form.account_holder_name = this.bankVerification.provider_account_name;
        }
      } catch (error) {
        this.bankVerification = { status: 'error', message: error.message };
      } finally {
        this.verifyingBank = false;
      }
    },
    syncCourtRows() {
      const total = Math.max(1, Number(this.form.court_count_total || 1));
      while (this.form.courts.length < total) {
        this.form.courts.push({
          local_id: localId(),
          name: `Sân ${this.form.courts.length + 1}`,
          court_type_id: this.form.courts[0]?.court_type_id || '',
          note: '',
        });
      }
      if (this.form.courts.length > total) {
        this.form.courts = this.form.courts.slice(0, total);
      }
    },
    addCourt() {
      this.form.courts.push({ local_id: localId(), name: `Sân ${this.form.courts.length + 1}`, court_type_id: '', note: '' });
      this.form.court_count_total = this.form.courts.length;
    },
    removeCourt(index) {
      this.form.courts.splice(index, 1);
      this.form.court_count_total = this.form.courts.length;
    },
    setFiles(group, event) {
      this.files[group] = Array.from(event.target.files || []);
      this.previewDocument = null;
      this.previewData = null;
    },
    removeFile(group, index) {
      this.files[group].splice(index, 1);
      this.previewDocument = null;
      this.previewData = null;
    },
    buildPayload() {
      return {
        ...this.form,
        court_count_total: Number(this.form.court_count_total),
        base_price_per_hour: Number(this.form.base_price_per_hour),
        courts: this.form.courts.map((court) => ({
          name: court.name,
          court_type_id: court.court_type_id,
          note: court.note || '',
        })),
        attachments_summary: this.attachmentsSummary,
      };
    },
    async generatePreview() {
      if (![0, 1, 2, 3, 4].every((index) => this.validateStep(index))) return;
      this.generatingPreview = true;
      try {
        const response = await api('/api/user/partner-application/preview', {
          method: 'POST',
          body: JSON.stringify(this.buildPayload()),
        });
        this.previewDocument = response.data?.document || null;
        this.previewData = response.data?.preview || null;
        this.fieldErrors.confirmed = '';
      } catch (error) {
        this.applyBackendErrors(error);
      } finally {
        this.generatingPreview = false;
      }
    },
    downloadPreview() {
      if (this.previewDocument?.id) {
        apiDownload(`/api/files/documents/${this.previewDocument.id}/download`);
      }
    },
    downloadDocument(document) {
      apiDownload(`/api/files/documents/${document.id}/download`);
    },
    downloadApplicationDocument(document) {
      if (!document?.id) return;
      apiDownload(`/api/user/partner-application/documents/${document.id}/download`);
    },
    openApplicationDetail(application) {
      this.selectedApplication = application;
    },
    async submit() {
      const invalidStep = [0, 1, 2, 3, 4, 5].find((index) => !this.validateStep(index));
      if (invalidStep !== undefined) {
        this.step = invalidStep;
        return;
      }
      this.submitting = true;
      try {
        const formData = new FormData();
        const payload = this.buildPayload();
        Object.entries(payload).forEach(([key, value]) => {
          if (['courts', 'amenities'].includes(key)) formData.append(key, JSON.stringify(value));
          else if (value !== null && value !== undefined) formData.append(key, value);
        });
        formData.append('confirmed', '1');
        this.files.identity.forEach((file) => formData.append('identity_documents[]', file));
        this.files.business_license.forEach((file) => formData.append('business_license_documents[]', file));
        this.files.facility.forEach((file) => formData.append('facility_images[]', file));
        this.files.additional.forEach((file) => formData.append('additional_documents[]', file));

        await apiFormData('/api/user/partner-application', formData);
        this.clearDraft();
        this.formOpen = false;
        this.formBanner = '';
        await this.loadApplications();
      } catch (error) {
        this.applyBackendErrors(error);
      } finally {
        this.submitting = false;
      }
    },
    applyBackendErrors(error) {
      const errors = error.data?.errors || {};
      this.fieldErrors = Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : messages]));
      this.formBanner = error.message || 'Vui lòng kiểm tra lại thông tin hồ sơ.';
      if (!Object.keys(this.fieldErrors).length) this.formBanner = error.message;
      this.focusFirstError();
    },
    async cancelApplication(application) {
      if (!window.confirm(`Hủy hồ sơ đăng ký cho ${application.venue_name}?`)) return;
      await api(`/api/user/partner-application/${application.id}/cancel`, {
        method: 'POST',
        body: JSON.stringify({ reason: 'Người dùng hủy hồ sơ từ trang đăng ký đối tác.' }),
      });
      await this.loadApplications();
    },
    copyRejectedApplication(application) {
      this.startNewApplication();
      this.form = {
        ...this.defaultForm(this.user),
        applicant_full_name: application.applicant_full_name || this.user.fullName,
        applicant_phone: application.applicant_phone || this.user.phone,
        applicant_email: application.applicant_email || this.user.email,
        applicant_birth_date: application.applicant_birth_date || '',
        applicant_address: application.applicant_address || '',
        applicant_type: application.applicant_type || 'individual',
        representative_name: application.representative_name || this.user.fullName,
        representative_identity_type: application.representative_identity_type || 'cccd',
        representative_identity_number: application.representative_identity_number || '',
        business_name: application.business_name || '',
        tax_code: application.tax_code || '',
        business_code: application.business_code || '',
        business_license_number: application.business_license_number || '',
        business_address: application.business_address || '',
        venue_name: application.venue_name || '',
        venue_address: application.venue_address || '',
        venue_map_url: application.venue_map_url || '',
        venue_latitude: application.venue_latitude || '',
        venue_longitude: application.venue_longitude || '',
        venue_phone: application.venue_phone || '',
        venue_email: application.venue_email || '',
        venue_description: application.venue_description || '',
        expected_opening_hours: application.expected_opening_hours || '05:00 - 23:00',
        parking_info: application.parking_info || '',
        amenities: application.amenities || [],
        court_count_total: application.courts?.length || 1,
        base_price_per_hour: application.base_price_per_hour || '',
        courts: (application.courts || [{ name: 'Sân 1' }]).map((court) => ({
          local_id: localId(),
          name: court.name,
          court_type_id: court.court_type_id || '',
          note: court.note || '',
        })),
        bank_name: application.bank_name || '',
        bank_code: application.bank_code || '',
        account_number: application.account_number || '',
        account_holder_name: application.account_holder_name || '',
        bank_branch: application.bank_branch || '',
      };
    },
    applicationWord(application) {
      const docs = application.generated_documents || application.generatedDocuments || [];
      return docs.find((doc) => doc.document_type === 'partner_application_form');
    },
    canCancel(application) {
      return ['pending', 'submitted', 'reviewing', 'need_supplement', 'draft'].includes(application.status);
    },
    statusLabel(status) {
      return {
        draft: 'Nháp',
        pending: 'Chờ xét duyệt',
        submitted: 'Chờ xét duyệt',
        reviewing: 'Đang xem xét',
        need_supplement: 'Cần bổ sung',
        contract_pending_owner_signature: 'Đã duyệt, chờ ký hợp đồng',
        contract_pending_sportgo_signature: 'Chờ SportGo ký',
        completed: 'Đang hoạt động',
        rejected: 'Bị từ chối',
        cancelled: 'Đã hủy',
      }[status] || status || '-';
    },
    formatDate(value) {
      if (!value) return '-';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    },
    dateOnly(value) {
      if (!value) return '-';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleDateString('vi-VN');
    },
    money(value) {
      const number = Number(value || 0);
      if (!Number.isFinite(number) || number <= 0) return '-';
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(number);
    },
  },
};
</script>

<style scoped>
.partner-page {
  min-height: 100vh;
  background: #f8fafc;
}

.shell {
  width: min(1180px, calc(100% - 32px));
  margin: 0 auto;
  padding: 96px 0 56px;
}

.hero,
.section-head,
.title-line,
.row-actions,
.actions,
.map-resolver,
.preview-actions,
.verification-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.hero {
  margin-bottom: 20px;
}

.eyebrow {
  margin: 0 0 6px;
  color: #15803d;
  font-size: 12px;
  font-weight: 900;
  text-transform: uppercase;
}

h1,
h2,
h3,
p {
  margin-top: 0;
}

h1 {
  margin-bottom: 8px;
  color: #0f172a;
  font-size: 32px;
}

h2 {
  margin-bottom: 8px;
  color: #0f172a;
  font-size: 22px;
}

h3 {
  margin-bottom: 10px;
  color: #0f172a;
  font-size: 16px;
}

.hero p,
.section-head p,
.panel-intro p,
.hint,
.empty-state,
.application-row p,
.application-row small,
.upload-box p,
.empty-files {
  color: #64748b;
}

.history-card,
.wizard {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.history-card {
  padding: 20px;
}

.draft-row,
.application-row {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding: 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f8fafc;
}

.draft-row {
  margin: 14px 0;
  background: #ecfdf5;
}

.application-list {
  display: grid;
  gap: 12px;
  margin-top: 12px;
}

.application-main {
  min-width: 0;
}

.status {
  border-radius: 999px;
  padding: 4px 9px;
  background: #e2e8f0;
  color: #334155;
  font-size: 12px;
  font-weight: 900;
}

.status.rejected,
.status.cancelled {
  background: #fee2e2;
  color: #991b1b;
}

.status.completed {
  background: #dcfce7;
  color: #166534;
}

.reject-reason {
  margin: 10px 0 0;
  color: #991b1b;
  font-weight: 800;
}

.wizard {
  margin-top: 20px;
  overflow: hidden;
}

.steps {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  border-bottom: 1px solid #e2e8f0;
}

.steps button {
  min-height: 64px;
  border: 0;
  border-right: 1px solid #e2e8f0;
  background: #fff;
  color: #64748b;
  font-weight: 900;
  cursor: pointer;
}

.steps button:last-child {
  border-right: 0;
}

.steps button.active {
  background: #ecfdf5;
  color: #166534;
}

.steps button.done {
  color: #15803d;
}

.steps span {
  display: inline-grid;
  place-items: center;
  width: 24px;
  height: 24px;
  margin-right: 6px;
  border-radius: 50%;
  background: #e2e8f0;
}

.step-panel {
  padding: 24px;
}

.panel-intro {
  max-width: 760px;
  margin-bottom: 22px;
}

.subsection {
  margin-bottom: 24px;
  padding-bottom: 20px;
  border-bottom: 1px solid #eef2f7;
}

.subsection:last-child {
  border-bottom: 0;
}

.grid {
  display: grid;
  gap: 16px;
}

.grid.two {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.grid.three {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.grid.compact {
  align-items: end;
}

.field {
  display: grid;
  gap: 7px;
  color: #0f172a;
  font-size: 13px;
  font-weight: 900;
}

.field b,
.upload-box b {
  color: #dc2626;
}

.full {
  grid-column: 1 / -1;
}

input,
select,
textarea {
  width: 100%;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 10px 12px;
  color: #0f172a;
  font: inherit;
  font-weight: 500;
  outline: none;
}

textarea {
  resize: vertical;
}

input:focus,
select:focus,
textarea:focus {
  border-color: #16a34a;
  box-shadow: 0 0 0 3px #dcfce7;
}

.combo {
  position: relative;
}

.combo input {
  padding-right: 42px;
}

.combo.invalid input {
  border-color: #ef4444;
  background: #fff7f7;
}

.combo-clear {
  position: absolute;
  top: 50%;
  right: 8px;
  width: 28px;
  height: 28px;
  transform: translateY(-50%);
  border: 0;
  border-radius: 50%;
  background: #e2e8f0;
  color: #334155;
  font-weight: 900;
  cursor: pointer;
}

.combo-menu {
  position: absolute;
  z-index: 30;
  top: calc(100% + 6px);
  left: 0;
  right: 0;
  max-height: 280px;
  overflow: auto;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
}

.combo-menu button {
  display: block;
  width: 100%;
  border: 0;
  border-bottom: 1px solid #f1f5f9;
  background: #fff;
  padding: 10px 12px;
  color: #0f172a;
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.combo-menu button:hover,
.combo-menu button.active {
  background: #ecfdf5;
  color: #166534;
}

.combo-menu p {
  margin: 0;
  padding: 12px;
  color: #64748b;
}

.invalid,
.upload-box.invalid {
  border-color: #ef4444 !important;
  background: #fff7f7;
}

.field-error {
  color: #b91c1c;
  font-weight: 800;
}

.map-resolver {
  align-items: end;
  margin-bottom: 12px;
}

.map-resolver .field {
  flex: 1;
}

.status-message,
.alert {
  padding: 12px 14px;
  border-radius: 8px;
  font-weight: 800;
}

.status-message.success {
  background: #dcfce7;
  color: #166534;
}

.status-message.warning,
.alert {
  background: #fef3c7;
  color: #92400e;
}

.amenities {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 18px;
  padding: 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.amenities > span {
  width: 100%;
  font-weight: 900;
}

.chip,
.confirm-line {
  display: flex;
  align-items: center;
  gap: 8px;
}

.chip {
  padding: 8px 10px;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  background: #f8fafc;
  font-weight: 700;
}

.chip input,
.confirm-line input {
  width: auto;
}

.verification-box {
  justify-content: flex-start;
  align-items: flex-start;
  margin-top: 16px;
}

.verify-text {
  padding: 12px;
  border-radius: 8px;
  background: #f1f5f9;
}

.verify-text.verified {
  background: #dcfce7;
  color: #166534;
}

.verify-text.name_mismatch,
.verify-text.not_found,
.verify-text.invalid_bank,
.verify-text.invalid_account_number,
.verify-text.lookup_not_configured,
.verify-text.provider_unavailable,
.verify-text.error {
  background: #fee2e2;
  color: #991b1b;
}

.court-list {
  display: grid;
  gap: 12px;
  margin: 18px 0;
}

.court-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 90px;
  align-items: end;
  gap: 12px;
  padding: 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f8fafc;
}

.align-end {
  align-self: end;
}

.upload-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.upload-box {
  display: grid;
  gap: 12px;
  align-content: start;
  min-height: 210px;
  border: 1px dashed #94a3b8;
  border-radius: 8px;
  padding: 16px;
  background: #f8fafc;
}

.file-list {
  display: grid;
  gap: 8px;
  padding: 0;
  margin: 0;
  list-style: none;
}

.file-list li {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto auto;
  gap: 4px 10px;
  padding: 10px;
  border-radius: 8px;
  background: #fff;
}

.file-list span {
  overflow-wrap: anywhere;
  font-weight: 800;
}

.file-list small {
  color: #64748b;
}

.file-list button {
  grid-row: span 2;
  border: 0;
  background: transparent;
  color: #b91c1c;
  font-weight: 900;
  cursor: pointer;
}

.file-list button:first-of-type {
  color: #166534;
}

.detail-drawer {
  position: fixed;
  inset: 0;
  z-index: 600;
  display: grid;
  place-items: center;
  padding: 24px;
  background: rgba(15, 23, 42, 0.45);
}

.drawer-card {
  width: min(980px, 100%);
  max-height: calc(100vh - 48px);
  overflow: auto;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
  padding: 22px;
  box-shadow: 0 24px 80px rgba(15, 23, 42, 0.24);
}

.drawer-head {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 18px;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.detail-block,
.document-section {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 14px;
  background: #f8fafc;
}

.detail-block p {
  margin-bottom: 6px;
  font-weight: 900;
}

.detail-block small {
  color: #64748b;
  overflow-wrap: anywhere;
}

.document-section {
  margin-top: 14px;
}

.submitted-docs {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.doc-chip {
  display: grid;
  gap: 3px;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  background: #f0fdf4;
  padding: 10px 12px;
  color: #14532d;
  font-weight: 900;
  cursor: pointer;
}

.doc-chip small {
  color: #166534;
  font-weight: 700;
}

.preview-actions {
  justify-content: flex-start;
  margin-bottom: 16px;
}

.document-preview {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 24px;
  background: #fff;
}

.document-preview header {
  margin-bottom: 20px;
  text-align: center;
}

.document-preview header p {
  margin-bottom: 8px;
  font-weight: 900;
}

.document-preview dl {
  display: grid;
  grid-template-columns: 190px minmax(0, 1fr);
  gap: 10px 16px;
}

.document-preview dt {
  color: #64748b;
  font-weight: 900;
}

.document-preview dd {
  min-width: 0;
  margin: 0;
  overflow-wrap: anywhere;
}

.preview-note {
  margin-top: 18px;
  padding: 12px;
  border-radius: 8px;
  background: #eff6ff;
  color: #1d4ed8;
  font-weight: 800;
}

.empty-preview {
  padding: 24px;
  border: 1px dashed #94a3b8;
  border-radius: 8px;
  color: #64748b;
  text-align: center;
}

.confirm-line {
  margin-top: 16px;
  padding: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-weight: 900;
}

.actions {
  justify-content: flex-end;
  padding: 18px 24px;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
}

.btn,
.remove-btn {
  border: 1px solid transparent;
  border-radius: 8px;
  padding: 10px 14px;
  font-weight: 900;
  cursor: pointer;
}

.btn.primary {
  background: #16a34a;
  color: #fff;
}

.btn.secondary {
  background: #0f172a;
  color: #fff;
}

.btn.ghost {
  border-color: #cbd5e1;
  background: #fff;
  color: #0f172a;
}

.btn.danger-soft,
.remove-btn {
  background: #fee2e2;
  color: #991b1b;
}

button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

@media (max-width: 920px) {
  .hero,
  .section-head,
  .draft-row,
  .application-row,
  .map-resolver,
  .verification-box {
    align-items: stretch;
    flex-direction: column;
  }

  .steps {
    grid-template-columns: repeat(2, 1fr);
  }

  .grid.two,
  .grid.three,
  .upload-grid,
  .court-row,
  .detail-grid,
  .document-preview dl {
    grid-template-columns: 1fr;
  }

  .actions {
    flex-wrap: wrap;
  }
}
</style>
