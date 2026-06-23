<template>
  <div class="min-h-screen w-screen bg-gray-50 overflow-x-hidden">
    <PublicNavbar />

    <main class="mx-auto w-full max-w-6xl px-4 pb-12 pt-28 sm:px-6 lg:px-8">
      <section class="mb-6 flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">SportGo Partner</p>
          <h1 class="mt-1 text-2xl font-semibold text-gray-900">Đăng ký đối tác/chủ sân</h1>
          <p class="mt-1 text-sm text-gray-500">Gửi hồ sơ đăng ký, tải lại Mẫu 01 đã sinh và theo dõi trạng thái xét duyệt.</p>
        </div>
      </section>

      <section class="mb-6 rounded-lg border border-gray-200 bg-white p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-base font-semibold text-gray-900">Hồ sơ đăng ký của tôi</h2>
            <p class="mt-1 text-sm text-gray-500">Hồ sơ đã gửi chỉ được xem lại, tải file Word hoặc hủy khi còn chờ xử lý.</p>
          </div>
          <div class="flex items-center gap-3">
            <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="loadApplications">
              Làm mới
            </button>
            <button
              type="button"
              class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="formOpen || !canRegister"
              @click="startNewApplication"
            >
              Đăng ký mới
            </button>
          </div>
        </div>

        <div v-if="draft" class="mt-4 flex flex-col gap-3 rounded-lg border border-blue-100 bg-blue-50 p-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-sm font-semibold text-gray-900">Nháp chưa gửi</p>
            <p class="mt-1 text-sm text-gray-600">{{ draft.venue_name || 'Chưa đặt tên cụm sân' }} · lưu lúc {{ formatDate(draft.saved_at) }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button type="button" class="rounded-lg bg-gray-900 px-3 py-2 text-sm font-semibold text-white" @click="continueDraft">Tiếp tục</button>
            <button type="button" class="rounded-lg bg-red-50 px-3 py-2 text-sm font-semibold text-red-700" @click="clearDraft">Xóa nháp</button>
          </div>
        </div>

        <div v-if="loading" class="mt-5 rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">Đang tải hồ sơ...</div>
        <div v-else-if="applications.length === 0 && !draft" class="mt-5 rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
          Bạn chưa có hồ sơ đăng ký đối tác nào.
        </div>
        <div v-else class="mt-5 space-y-3">
          <article v-for="application in applications" :key="application.id" class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="font-semibold text-gray-900">{{ application.venue_name }}</h3>
                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(application.status)">{{ statusLabel(application.status) }}</span>
              </div>
              <p class="mt-1 break-words text-sm text-gray-500">{{ application.venue_address }}</p>
              <p class="mt-1 text-xs text-gray-400">Gửi ngày {{ formatDate(application.submitted_at) }}</p>
              <p v-if="application.status === 'rejected'" class="mt-2 text-sm font-medium text-red-600">
                Lý do từ chối: {{ application.status_reason || 'SportGo chưa nhập lý do cụ thể.' }}
              </p>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="rounded-lg bg-gray-900 px-3 py-2 text-sm font-semibold text-white" @click="selectedApplication = application">Xem</button>
              <button v-if="applicationWord(application)" type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100" @click="viewDocument(applicationWord(application), application)">
                Xem & Ký Mẫu 01
              </button>
              <button v-if="canCancel(application)" type="button" class="rounded-lg bg-red-50 px-3 py-2 text-sm font-semibold text-red-700" @click="cancelApplication(application)">
                Hủy
              </button>
            </div>
          </article>
        </div>
      </section>

      <section v-if="selectedApplication" class="fixed inset-0 z-[600] grid place-items-center bg-gray-900/50 p-4" role="dialog" aria-modal="true" @click.self="selectedApplication = null">
        <div class="max-h-[calc(100vh-2rem)] w-full max-w-4xl overflow-auto rounded-lg bg-white p-5 shadow-xl">
          <header class="flex flex-col gap-3 border-b border-gray-200 pb-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Hồ sơ đối tác</p>
              <h2 class="mt-1 text-xl font-semibold text-gray-900">{{ selectedApplication.venue_name }}</h2>
              <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(selectedApplication.status)">
                {{ statusLabel(selectedApplication.status) }}
              </span>
            </div>
            <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700" @click="selectedApplication = null">Đóng</button>
          </header>

          <div class="mt-5 grid gap-4 md:grid-cols-2">
            <InfoBlock title="Người đăng ký" :items="[
              ['Họ tên', selectedApplication.applicant_full_name],
              ['Điện thoại', selectedApplication.applicant_phone],
              ['Email', selectedApplication.applicant_email],
              ['Ngày sinh', dateOnly(selectedApplication.applicant_birth_date)],
            ]" />
            <InfoBlock title="Ngân hàng" :items="[
              ['Ngân hàng', selectedApplication.bank_name],
              ['Số tài khoản', selectedApplication.account_number],
              ['Chủ tài khoản', selectedApplication.account_holder_name],
              ['Trạng thái', selectedApplication.bank_verification_status === 'verified' ? 'Đã xác minh' : 'Chưa xác minh'],
            ]" />
            <InfoBlock class="md:col-span-2" title="Cụm sân" :items="[
              ['Địa chỉ', selectedApplication.venue_address],
              ['Tọa độ', coordinateText(selectedApplication)],
              ['Số sân con', selectedApplication.court_count_total],
              ['Giá cơ bản', money(selectedApplication.base_price_per_hour)],
            ]" />
          </div>
        </div>
      </section>

      <form v-if="formOpen" class="space-y-6" novalidate @submit.prevent="submit">
        <div v-if="formBanner" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          {{ formBanner }}
        </div>

        <FormSection title="Thông tin cá nhân">
          <div class="grid gap-5 md:grid-cols-2">
            <FormField label="Họ tên người đăng ký" required :error="fieldErrors.applicant_full_name">
              <input v-model.trim="form.applicant_full_name" :class="inputClass(fieldErrors.applicant_full_name)" />
            </FormField>
            <FormField label="Số điện thoại" required :error="fieldErrors.applicant_phone">
              <input v-model.trim="form.applicant_phone" :class="inputClass(fieldErrors.applicant_phone)" inputmode="numeric" @input="digitsOnly('applicant_phone', 10)" />
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
            <FormField label="Người đại diện" required :error="fieldErrors.representative_name">
              <input v-model.trim="form.representative_name" :class="inputClass(fieldErrors.representative_name)" />
            </FormField>
            <FormField label="Loại giấy tờ" required :error="fieldErrors.representative_identity_type">
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
            <FormField label="Tên đơn vị/cá nhân kinh doanh" required :error="fieldErrors.business_name">
              <input v-model.trim="form.business_name" :class="inputClass(fieldErrors.business_name)" />
            </FormField>
            <FormField label="Mã số thuế" :error="fieldErrors.tax_code">
              <input v-model.trim="form.tax_code" :class="inputClass(fieldErrors.tax_code)" @input="normalizeTaxCode" />
            </FormField>
            <FormField label="Số giấy đăng ký kinh doanh/pháp lý" required :error="fieldErrors.business_license_number">
              <input v-model.trim="form.business_license_number" :class="inputClass(fieldErrors.business_license_number)" />
            </FormField>
            <FormField label="Mã doanh nghiệp/hộ kinh doanh" :error="fieldErrors.business_code">
              <input v-model.trim="form.business_code" :class="inputClass(fieldErrors.business_code)" />
            </FormField>
            <FormField class="md:col-span-2" label="Địa chỉ liên hệ" required :error="fieldErrors.applicant_address">
              <textarea v-model.trim="form.applicant_address" :class="textareaClass(fieldErrors.applicant_address)" rows="3"></textarea>
            </FormField>
            <FormField class="md:col-span-2" label="Địa chỉ pháp lý" required :error="fieldErrors.business_address">
              <textarea v-model.trim="form.business_address" :class="textareaClass(fieldErrors.business_address)" rows="3"></textarea>
            </FormField>
          </div>
        </FormSection>

        <FormSection title="Thông tin ngân hàng">
          <div class="grid gap-5 md:grid-cols-2">
            <FormField label="Ngân hàng" required :error="fieldErrors.bank_code">
              <BaseCombobox
                v-model="form.bank_code"
                :options="bankOptions"
                placeholder="Tìm ngân hàng"
                :invalid="Boolean(fieldErrors.bank_code)"
                @select="selectBank"
              />
            </FormField>
            <FormField label="Số tài khoản" required :error="bankError || fieldErrors.account_number">
              <div class="relative">
                <input v-model.trim="form.account_number" :class="inputClass(bankError || fieldErrors.account_number, 'pr-10')" inputmode="numeric" @input="onAccountNumberInput" />
                <span v-if="verifyingBank" class="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin rounded-full border-2 border-blue-200 border-t-blue-600"></span>
              </div>
            </FormField>
            <FormField label="Tên chủ tài khoản" required :error="fieldErrors.account_holder_name">
              <div class="relative">
                <input
                  v-model.trim="form.account_holder_name"
                  :class="bankManualMode
                    ? inputClass(fieldErrors.account_holder_name)
                    : 'w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2.5 pr-10 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500'"
                  :readonly="!bankManualMode"
                  :placeholder="verifyingBank ? 'Đang xác minh...' : bankManualMode ? 'Nhập đúng tên chủ tài khoản (viết IN HOA)' : 'Tự động điền sau khi xác minh'"
                  @input="bankManualMode && onManualBankHolderInput()"
                />
                <span v-if="bankVerified && !bankManualMode" class="absolute right-3 top-1/2 -translate-y-1/2 text-green-600">
                  <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.31a1 1 0 0 1-1.42.005L3.29 9.27a1 1 0 1 1 1.42-1.41l4.04 4.04 6.54-6.604a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd"/></svg>
                </span>
                <span v-if="bankManualMode && form.account_holder_name" class="absolute right-3 top-1/2 -translate-y-1/2 text-amber-500">
                  <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.168 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 6a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                </span>
                <span v-if="verifyingBank" class="absolute inset-y-2 left-3 right-10 rounded bg-gray-200/70"></span>
              </div>
              <p v-if="bankVerified && !bankManualMode" class="mt-1 text-xs font-medium text-green-600">✓ Tài khoản đã được xác minh tự động.</p>
              <p v-else-if="bankManualMode && form.account_holder_name" class="mt-1 text-xs font-medium text-amber-600">⚠ Nhập thủ công — Admin sẽ xác minh khi duyệt hồ sơ.</p>
            </FormField>
            <FormField label="Chi nhánh" :error="fieldErrors.bank_branch">
              <input v-model.trim="form.bank_branch" :class="inputClass(fieldErrors.bank_branch)" />
            </FormField>
          </div>
        </FormSection>

        <FormSection title="Địa chỉ sân">
          <div class="grid gap-5 md:grid-cols-2">
            <FormField label="Tỉnh/Thành phố" required :error="fieldErrors.venue_province_code">
              <BaseCombobox
                v-model="form.venue_province_code"
                :options="provinceOptions"
                placeholder="Tìm Tỉnh/Thành phố"
                :invalid="Boolean(fieldErrors.venue_province_code)"
                @select="onProvinceSelect"
              />
            </FormField>
            <FormField label="Phường/Xã" required :error="fieldErrors.venue_ward_code">
              <BaseCombobox
                v-model="form.venue_ward_code"
                :options="wardOptions"
                placeholder="Tìm Phường/Xã"
                :disabled="!form.venue_province_code"
                :invalid="Boolean(fieldErrors.venue_ward_code)"
                @select="syncVenueAddress"
              />
            </FormField>
            <FormField class="md:col-span-2" label="Số nhà, tên đường" required :error="fieldErrors.street_address">
              <input v-model.trim="form.street_address" :class="inputClass(fieldErrors.street_address)" placeholder="Ví dụ: 123 Nguyễn Hữu Cảnh" @input="syncVenueAddress" />
            </FormField>
            <FormField class="md:col-span-2" label="Link Google Maps" required :error="mapError || fieldErrors.venue_map_url">
              <input v-model.trim="form.venue_map_url" :class="inputClass(mapError || fieldErrors.venue_map_url)" placeholder="Dán link Google Maps có tọa độ" @input="onMapUrlInput" />
              <div v-if="mapSuggestion" class="mt-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                <p>{{ mapSuggestion.message }}</p>
                <button type="button" class="mt-2 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700" @click="applyMapSuggestion">
                  Cập nhật theo Google Maps
                </button>
              </div>
              <p v-else-if="mapStatus" class="mt-1 text-xs font-medium text-green-600">{{ mapStatus }}</p>
            </FormField>
            <input type="hidden" :value="form.venue_latitude" name="venue_latitude" />
            <input type="hidden" :value="form.venue_longitude" name="venue_longitude" />
            <FormField label="Tên cụm sân" required :error="fieldErrors.venue_name">
              <input v-model.trim="form.venue_name" :class="inputClass(fieldErrors.venue_name)" />
            </FormField>
            <FormField label="Số điện thoại tại sân" required :error="fieldErrors.venue_phone">
              <input v-model.trim="form.venue_phone" :class="inputClass(fieldErrors.venue_phone)" inputmode="numeric" @input="digitsOnly('venue_phone', 10)" />
            </FormField>
            <FormField label="Email tại sân" :error="fieldErrors.venue_email">
              <input v-model.trim="form.venue_email" :class="inputClass(fieldErrors.venue_email)" type="email" />
            </FormField>
            <FormField label="Giờ mở cửa dự kiến" :error="fieldErrors.expected_opening_hours">
              <input v-model.trim="form.expected_opening_hours" :class="inputClass(fieldErrors.expected_opening_hours)" placeholder="05:00 - 23:00" />
            </FormField>
            <FormField class="md:col-span-2" label="Mô tả ngắn về cơ sở" :error="fieldErrors.venue_description">
              <textarea v-model.trim="form.venue_description" :class="textareaClass(fieldErrors.venue_description)" rows="3"></textarea>
            </FormField>
            <FormField class="md:col-span-2" label="Bãi xe/khu phụ trợ" :error="fieldErrors.parking_info">
              <textarea v-model.trim="form.parking_info" :class="textareaClass(fieldErrors.parking_info)" rows="3"></textarea>
            </FormField>
          </div>
        </FormSection>

        <FormSection title="Cấu hình sân">
          <div class="grid gap-5 md:grid-cols-2">
            <FormField label="Số lượng sân con" required :error="fieldErrors.court_count_total">
              <input v-model.number="form.court_count_total" :class="inputClass(fieldErrors.court_count_total)" type="number" min="1" max="100" @input="syncCourtRows" />
            </FormField>
            <FormField label="Giá cơ bản/giờ" required :error="fieldErrors.base_price_per_hour">
              <input v-model.number="form.base_price_per_hour" :class="inputClass(fieldErrors.base_price_per_hour)" type="number" min="1000" step="1000" />
            </FormField>
          </div>

          <div class="mt-5 space-y-3">
            <div v-for="(court, index) in form.courts" :key="court.local_id" class="grid gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
              <FormField :label="`Tên sân ${index + 1}`" required :error="fieldErrors[`courts.${index}.name`]">
                <input v-model.trim="court.name" :class="inputClass(fieldErrors[`courts.${index}.name`])" />
              </FormField>
              <FormField label="Loại sân" required :error="fieldErrors[`courts.${index}.court_type_id`]">
                <BaseCombobox v-model="court.court_type_id" :options="courtTypeOptions" placeholder="Chọn loại sân" :invalid="Boolean(fieldErrors[`courts.${index}.court_type_id`])" />
              </FormField>
              <button type="button" class="self-end rounded-lg bg-red-50 px-3 py-2.5 text-sm font-semibold text-red-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="form.courts.length <= 1" @click="removeCourt(index)">
                Xóa
              </button>
            </div>
          </div>

          <div v-if="amenities.length" class="mt-5 flex flex-wrap gap-2">
            <label v-for="amenity in amenities" :key="amenity.id || amenity.name" class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700">
              <input v-model="form.amenities" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" type="checkbox" :value="amenity.name" />
              {{ amenity.name }}
            </label>
          </div>
        </FormSection>

        <FormSection title="Tài liệu đính kèm">
          <div class="grid gap-5 md:grid-cols-2">
            <UploadBox title="CCCD/CMND người đại diện" required :files="files.identity" :error="fieldErrors.identity_documents" @change="setFiles('identity', $event)" @remove="removeFile('identity', $event)" />
            <UploadBox title="Giấy đăng ký kinh doanh/pháp lý" required :files="files.business_license" :error="fieldErrors.business_license_documents" @change="setFiles('business_license', $event)" @remove="removeFile('business_license', $event)" />
            <UploadBox title="Hình ảnh cơ sở/sân" required :files="files.facility" :error="fieldErrors.facility_images" @change="setFiles('facility', $event)" @remove="removeFile('facility', $event)" />
            <UploadBox title="Tài liệu bổ sung" :files="files.additional" :error="fieldErrors.additional_documents" @change="setFiles('additional', $event)" @remove="removeFile('additional', $event)" />
          </div>
        </FormSection>

        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
          <label class="flex items-start gap-3 text-sm font-medium text-gray-700">
            <input v-model="confirmed" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" type="checkbox" />
            <span>Tôi xác nhận thông tin trong hồ sơ là chính xác và đồng ý để SportGo kiểm tra tài liệu trước khi duyệt đối tác.</span>
          </label>
          <p v-if="fieldErrors.confirmed" class="mt-1 text-xs text-red-500">{{ fieldErrors.confirmed }}</p>
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-gray-200 pt-5 sm:flex-row sm:items-center sm:justify-end">
          <button type="button" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="saveDraft">
            Lưu nháp
          </button>
          <button
            type="submit"
            class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="submitDisabled"
          >
            <span v-if="submitting" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-blue-200 border-t-white"></span>
            {{ submitting ? 'Đang xử lý...' : 'Gửi hồ sơ đăng ký' }}
          </button>
        </div>
      </form>
    </main>
    
    <DocumentViewerModal
      :show="showDocumentViewer"
      :document="viewingDocument"
      @close="closeDocumentViewer"
    >
      <template #actions v-if="needsSignature(viewingDocument)">
        <button type="button" class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 transition" @click="openSignaturePad">
          Ký xác nhận văn bản
        </button>
      </template>
    </DocumentViewerModal>

    <SignaturePadModal
      :show="showSignaturePad"
      :saving="savingSignature"
      @close="showSignaturePad = false"
      @confirm="submitSignature"
    />
  </div>
</template>

<script setup>
import { computed, defineComponent, h, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import PublicNavbar from '../components/PublicNavbar.vue';
import DocumentViewerModal from '../components/DocumentViewerModal.vue';
import SignaturePadModal from '../components/SignaturePadModal.vue';
import { getAuth } from '../stores/auth.js';
import { api, apiDownload, apiFormData } from '../services/api.js';

const DRAFT_KEY = 'sportgo_partner_application_draft_v3';
const BANK_CACHE_KEY = 'sportgo_partner_banks_v2';
const BANK_CACHE_TTL = 24 * 60 * 60 * 1000;

const BaseCombobox = defineComponent({
  name: 'BaseCombobox',
  props: {
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Chọn' },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
  },
  emits: ['update:modelValue', 'select'],
  setup(props, { emit }) {
    const open = ref(false);
    const query = ref('');

    const optionValue = (option) => String(option?.value ?? option?.code ?? option?.id ?? '');
    const optionLabel = (option) => String(option?.label ?? option?.name ?? option?.short_name ?? '');
    const selected = computed(() => props.options.find((option) => optionValue(option) === String(props.modelValue)) || null);
    const filtered = computed(() => {
      const keyword = query.value.trim().toLowerCase();
      if (!keyword || selected.value && query.value === optionLabel(selected.value)) return props.options;
      return props.options.filter((option) => optionLabel(option).toLowerCase().includes(keyword));
    });

    watch(selected, (option) => {
      if (!open.value) query.value = option ? optionLabel(option) : '';
    }, { immediate: true });

    const choose = (option) => {
      emit('update:modelValue', optionValue(option));
      emit('select', option);
      query.value = optionLabel(option);
      open.value = false;
    };

    const onInput = (event) => {
      query.value = event.target.value;
      open.value = true;
    };

    const onBlur = () => {
      window.setTimeout(() => {
        open.value = false;
        query.value = selected.value ? optionLabel(selected.value) : '';
      }, 130);
    };

    return { open, query, filtered, selected, optionValue, optionLabel, choose, onInput, onBlur };
  },
  template: `
    <div class="relative">
      <div class="relative">
        <input
          :value="query"
          :placeholder="placeholder"
          :disabled="disabled"
          class="w-full rounded-lg border px-3 py-2.5 pr-10 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-400"
          :class="invalid ? 'border-red-400' : 'border-gray-300'"
          @focus="!disabled && (open = true)"
          @blur="onBlur"
          @input="onInput"
        />
        <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
        </svg>
      </div>
      <div v-if="open && !disabled" class="absolute z-30 mt-1 max-h-72 w-full overflow-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
        <button
          v-for="option in filtered"
          :key="optionValue(option)"
          type="button"
          class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-blue-50"
          :class="optionValue(option) === String(modelValue) ? 'bg-blue-50 text-blue-700' : 'text-gray-700'"
          @mousedown.prevent="choose(option)"
        >
          <span class="truncate">{{ optionLabel(option) }}</span>
          <svg v-if="optionValue(option) === String(modelValue)" class="ml-3 h-4 w-4 shrink-0 text-blue-600" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.31a1 1 0 0 1-1.42.005L3.29 9.27a1 1 0 1 1 1.42-1.41l4.04 4.04 6.54-6.604a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd"/>
          </svg>
        </button>
        <p v-if="filtered.length === 0" class="px-3 py-2 text-sm text-gray-500">Không tìm thấy lựa chọn phù hợp.</p>
      </div>
    </div>
  `,
});

const FormSection = defineComponent({
  name: 'FormSection',
  props: { title: { type: String, required: true } },
  setup(props, { slots }) {
    return () => h('section', { class: 'rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md' }, [
      h('div', { class: 'mb-5 border-b border-gray-100 pb-4' }, [
        h('h2', { class: 'text-lg font-bold text-gray-900 tracking-tight' }, props.title)
      ]),
      h('div', slots.default?.()),
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
    return () => h('label', { class: ['block', attrs.class] }, [
      h('span', { class: 'mb-1.5 block text-sm font-medium text-gray-700' }, [
        props.label,
        props.required ? h('span', { class: 'ml-1 text-red-500' }, '*') : null,
      ]),
      slots.default?.(),
      props.error ? h('p', { class: 'mt-1 text-xs text-red-500' }, props.error) : null,
    ]);
  },
});

const InfoBlock = defineComponent({
  name: 'InfoBlock',
  props: {
    title: { type: String, required: true },
    items: { type: Array, default: () => [] },
  },
  setup(props, { attrs }) {
    return () => h('section', { class: ['rounded-lg border border-gray-200 bg-gray-50 p-4', attrs.class] }, [
      h('h3', { class: 'text-sm font-semibold text-gray-900' }, props.title),
      h('dl', { class: 'mt-3 grid gap-2 text-sm sm:grid-cols-[150px_minmax(0,1fr)]' }, props.items.flatMap(([label, value]) => [
        h('dt', { class: 'font-medium text-gray-500' }, label),
        h('dd', { class: 'break-words text-gray-900' }, value || '-'),
      ])),
    ]);
  },
});

const UploadBox = defineComponent({
  name: 'UploadBox',
  props: {
    title: { type: String, required: true },
    required: { type: Boolean, default: false },
    files: { type: Array, default: () => [] },
    error: { type: String, default: '' },
  },
  emits: ['change', 'remove'],
  setup(props, { emit }) {
    const fileSize = (file) => {
      const bytes = Number(file?.size || 0);
      if (!bytes) return '0 B';
      const units = ['B', 'KB', 'MB', 'GB'];
      const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
      return `${(bytes / 1024 ** index).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
    };
    return { emit, fileSize };
  },
  template: `
    <div class="rounded-lg border border-dashed p-4" :class="error ? 'border-red-300 bg-red-50' : 'border-gray-300 bg-gray-50'">
      <label class="block">
        <span class="text-sm font-medium text-gray-700">{{ title }}<span v-if="required" class="ml-1 text-red-500">*</span></span>
        <input class="mt-2 block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100" type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" @change="emit('change', $event)" />
      </label>
      <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
      <ul v-if="files.length" class="mt-3 space-y-2">
        <li v-for="(file, index) in files" :key="file.name + index" class="flex items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 text-sm">
          <span class="min-w-0 truncate text-gray-700">{{ file.name }} · {{ fileSize(file) }}</span>
          <button type="button" class="shrink-0 text-xs font-semibold text-red-600" @click="emit('remove', index)">Xóa</button>
        </li>
      </ul>
      <p v-else class="mt-3 text-xs text-gray-500">Chưa chọn file.</p>
    </div>
  `,
});

const router = useRouter();
const user = getAuth();

const loading = ref(false);
const applications = ref([]);
const selectedApplication = ref(null);
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
const verifyingBank = ref(false);
const bankVerified = ref(false);
const bankManualMode = ref(false);
const bankError = ref('');
const bankTimer = ref(null);
const bankRequestId = ref(0);
const mapError = ref('');
const mapStatus = ref('');
const mapSuggestion = ref(null);
const mapTimer = ref(null);

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

const bankOptions = computed(() => banks.value.map((bank) => ({
  ...bank,
  value: bank.code,
  label: `${bank.short_name || bank.code} - ${bank.name || bank.code}`,
})));
const provinceOptions = computed(() => provinces.value.map((province) => ({ ...province, value: province.code, label: province.name })));
const wardOptions = computed(() => wards.value.map((ward) => ({ ...ward, value: ward.code, label: ward.name })));
const courtTypeOptions = computed(() => courtTypes.value
  .filter((type) => type.is_active !== false && Number(type.children_count || 0) === 0)
  .map((type) => ({ ...type, value: type.id, label: type.name })));
const submitDisabled = computed(() => submitting.value || verifyingBank.value || (!bankVerified.value && !bankManualMode.value));

onMounted(async () => {
  if (!user) {
    router.replace({ name: 'login' });
    return;
  }

  loadDraft();
  await Promise.all([loadApplications(), loadBanks(), loadProvinces(), loadCourtTypes(), loadAmenities()]);
});

onBeforeUnmount(() => {
  clearTimeout(bankTimer.value);
  clearTimeout(mapTimer.value);
});

watch(() => form.venue_province_code, async (code, oldCode) => {
  if (code !== oldCode) {
    form.venue_ward_code = '';
    wards.value = [];
    await loadWards(code);
    syncVenueAddress();
  }
});

watch(() => form.venue_ward_code, syncVenueAddress);

function defaultForm(authUser) {
  return {
    applicant_full_name: authUser?.fullName || '',
    applicant_phone: authUser?.phone || '',
    applicant_email: authUser?.email || '',
    applicant_birth_date: '',
    applicant_address: '',
    applicant_type: 'individual',
    representative_name: authUser?.fullName || '',
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
    street_address: '',
    venue_address: '',
    venue_province_code: '',
    venue_ward_code: '',
    venue_map_url: '',
    venue_latitude: '',
    venue_longitude: '',
    venue_phone: authUser?.phone || '',
    venue_email: authUser?.email || '',
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
}

function blankFiles() {
  return { identity: [], business_license: [], facility: [], additional: [] };
}

function localId() {
  return `local-${Math.random().toString(36).slice(2)}-${Date.now()}`;
}

async function loadApplications() {
  loading.value = true;
  try {
    const response = await api('/api/user/partner-application');
    applications.value = response.data?.history || [];
    canRegister.value = Boolean(response.data?.can_register);
  } finally {
    loading.value = false;
  }
}

async function loadBanks() {
  const cached = readCache(BANK_CACHE_KEY);
  if (cached && cached.length > 0) {
    banks.value = cached;
    return;
  }

  try {
    const response = await api('/api/user/partner-application/banks');
    banks.value = normalizeList(response.data);
    if (banks.value.length > 0) {
      writeCache(BANK_CACHE_KEY, banks.value, BANK_CACHE_TTL);
    }
  } catch (error) {
    console.error('Lỗi khi tải danh sách ngân hàng:', error);
  }
}

async function loadProvinces() {
  const response = await api('/api/user/partner-application/provinces');
  provinces.value = normalizeList(response.data);
}

async function loadWards(provinceCode) {
  if (!provinceCode) return;
  const response = await api(`/api/user/partner-application/provinces/${provinceCode}/wards`);
  wards.value = normalizeList(response.data);
}

async function loadCourtTypes() {
  const response = await api('/api/court-types');
  courtTypes.value = normalizeList(response.data);
}

async function loadAmenities() {
  const response = await api('/api/amenities?active_only=1');
  amenities.value = normalizeList(response.data);
}

async function apiWithFallback(primary, fallback, options = {}) {
  try {
    return await api(primary, options);
  } catch (error) {
    if ([404, 405].includes(error.status)) return api(fallback, options);
    throw error;
  }
}

function normalizeList(data) {
  if (Array.isArray(data)) return data;
  if (Array.isArray(data?.data)) return data.data;
  return [];
}

function readCache(key) {
  try {
    const payload = JSON.parse(localStorage.getItem(key) || 'null');
    if (!payload || Date.now() > payload.expires_at) return null;
    return payload.value;
  } catch {
    return null;
  }
}

function writeCache(key, value, ttl) {
  localStorage.setItem(key, JSON.stringify({ value, expires_at: Date.now() + ttl }));
}

function startNewApplication() {
  resetForm(defaultForm(user));
  formOpen.value = true;
}

function resetForm(nextForm) {
  Object.assign(form, nextForm);
  Object.assign(files, blankFiles());
  clearErrors();
  formBanner.value = '';
  confirmed.value = false;
  bankVerified.value = false;
  bankManualMode.value = false;
  bankError.value = '';
  mapError.value = '';
  mapStatus.value = '';
  mapSuggestion.value = null;
}

function saveDraft() {
  const payload = { ...form, saved_at: new Date().toISOString() };
  localStorage.setItem(DRAFT_KEY, JSON.stringify(payload));
  draft.value = payload;
  formBanner.value = 'Đã lưu nháp hồ sơ trên trình duyệt.';
}

function loadDraft() {
  try {
    draft.value = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null');
  } catch {
    draft.value = null;
  }
}

async function continueDraft() {
  if (!draft.value) return;
  resetForm({ ...defaultForm(user), ...draft.value });
  formOpen.value = true;
  if (form.venue_province_code) await loadWards(form.venue_province_code);
  runBankDebounce();
}

function clearDraft() {
  localStorage.removeItem(DRAFT_KEY);
  draft.value = null;
}

function inputClass(error, extra = '') {
  return [
    'w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-400',
    error ? 'border-red-400' : '',
    extra,
  ].filter(Boolean).join(' ');
}

function textareaClass(error) {
  return `${inputClass(error)} resize-y`;
}

function digitsOnly(field, maxLength) {
  form[field] = String(form[field] || '').replace(/\D/g, '').slice(0, maxLength);
}

function normalizeIdentityNumber() {
  const value = String(form.representative_identity_number || '');
  form.representative_identity_number = form.representative_identity_type === 'passport'
    ? value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase().slice(0, 20)
    : value.replace(/\D/g, '').slice(0, 12);
}

function normalizeTaxCode() {
  form.tax_code = String(form.tax_code || '').replace(/[^\d-]/g, '').slice(0, 14);
}

function selectBank(bank) {
  form.bank_name = bank?.short_name || bank?.name || '';
  form.bank_bin = bank?.bin || '';
  resetBankVerification();
  runBankDebounce();
}

function onAccountNumberInput() {
  form.account_number = String(form.account_number || '').replace(/\D/g, '').slice(0, 19);
  resetBankVerification();
  runBankDebounce();
}

function resetBankVerification() {
  clearTimeout(bankTimer.value);
  bankVerified.value = false;
  bankManualMode.value = false;
  bankError.value = '';
  form.account_holder_name = '';
  fieldErrors.account_number = '';
  fieldErrors.account_holder_name = '';
}

function runBankDebounce() {
  clearTimeout(bankTimer.value);
  if (!form.bank_code || String(form.account_number || '').length < 6) return;
  bankTimer.value = window.setTimeout(verifyBankAccount, 500);
}

async function verifyBankAccount() {
  const requestId = bankRequestId.value + 1;
  bankRequestId.value = requestId;
  verifyingBank.value = true;
  bankError.value = '';

  try {
    const response = await api('/api/user/partner-application/verify-bank-account', {
      method: 'POST',
      body: JSON.stringify({
        bank_code: form.bank_code,
        bank_bin: form.bank_bin,
        account_number: form.account_number,
        account_holder_name: form.account_holder_name || '',
      }),
    });
    if (requestId !== bankRequestId.value) return;

    const result = response.data || {};

    // Xác minh tự động thành công (VietQR)
    if (result.status === 'verified' && result.provider_account_name) {
      form.account_holder_name = result.provider_account_name;
      bankVerified.value = true;
      bankManualMode.value = false;
      bankError.value = '';
      return;
    }

    // Chế độ nhập thủ công - chưa có VietQR key
    if (result.status === 'manual_input_required') {
      bankManualMode.value = true;
      bankVerified.value = false;
      bankError.value = '';
      return;
    }

    // Người dùng đã nhập tên thủ công và được chấp nhận
    if (result.status === 'manual_input' && result.verified) {
      form.account_holder_name = result.provider_account_name || form.account_holder_name;
      bankManualMode.value = true;
      bankVerified.value = false; // Không đánh dấu verified vì chưa xác minh tự động
      bankError.value = '';
      return;
    }

    form.account_holder_name = '';
    bankVerified.value = false;
    bankManualMode.value = false;
    bankError.value = result.message || 'Không xác minh được tài khoản ngân hàng.';
  } catch (error) {
    if (requestId !== bankRequestId.value) return;
    form.account_holder_name = '';
    bankVerified.value = false;
    bankManualMode.value = false;
    bankError.value = error.message || 'Không xác minh được tài khoản ngân hàng.';
  } finally {
    if (requestId === bankRequestId.value) verifyingBank.value = false;
  }
}

function onManualBankHolderInput() {
  form.account_holder_name = String(form.account_holder_name || '').toUpperCase();
}

function onProvinceSelect() {
  form.venue_ward_code = '';
  wards.value = [];
  syncVenueAddress();
}

function syncVenueAddress() {
  const province = findProvince(form.venue_province_code)?.name;
  const ward = findWard(form.venue_ward_code)?.name;
  form.venue_address = [form.street_address, ward, province].filter(Boolean).join(', ');
}

function onMapUrlInput() {
  clearTimeout(mapTimer.value);
  mapError.value = '';
  mapStatus.value = '';
  mapSuggestion.value = null;
  form.venue_latitude = '';
  form.venue_longitude = '';

  if (!form.venue_map_url) return;
  mapTimer.value = window.setTimeout(resolveMapUrl, 500);
}

async function resolveMapUrl() {
  mapError.value = '';
  mapStatus.value = 'Đang xử lý link...';

  try {
    const response = await api('/api/user/partner-application/resolve-map', {
      method: 'POST',
      body: JSON.stringify({ url: form.venue_map_url }),
    });
    const resolved = response.data || {};
    
    if (resolved.latitude && resolved.longitude) {
      form.venue_latitude = resolved.latitude;
      form.venue_longitude = resolved.longitude;
      compareResolvedAddress(resolved);
      return;
    }
  } catch (error) {
    console.error('Lỗi khi phân giải link map:', error);
  }

  const coordinates = extractCoordinates(form.venue_map_url);
  if (!coordinates && !form.venue_latitude) {
    mapStatus.value = '';
    mapError.value = 'Không lấy được tọa độ từ link Google Maps này. Vui lòng dùng link đầy đủ có tọa độ.';
    return;
  }

  if (coordinates) {
    form.venue_latitude = coordinates.latitude;
    form.venue_longitude = coordinates.longitude;
    mapStatus.value = 'Đã lấy tọa độ từ link Google Maps.';
  }
}

function extractCoordinates(url) {
  const decoded = decodeURIComponent(url || '');
  const patterns = [
    /@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/,
    /!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/,
    /[?&](?:q|ll|query)=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/,
    /[?&]center=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/,
  ];
  for (const pattern of patterns) {
    const matches = decoded.match(pattern);
    if (matches) return { latitude: Number(matches[1]), longitude: Number(matches[2]) };
  }
  return null;
}

function compareResolvedAddress(resolved) {
  const resolvedProvince = resolved.province_code || '';
  const resolvedWard = resolved.ward_code || '';
  const provinceChanged = resolvedProvince && resolvedProvince !== form.venue_province_code;
  const wardChanged = resolvedWard && resolvedWard !== form.venue_ward_code;

  if (!form.venue_province_code && resolvedProvince) {
    form.venue_province_code = resolvedProvince;
    loadWards(resolvedProvince).then(() => {
      if (resolvedWard) form.venue_ward_code = resolvedWard;
    });
    mapStatus.value = 'Đã tự động điền địa chỉ từ Google Maps.';
    if (resolved.address && !form.street_address) {
      form.street_address = resolved.address.split(',')[0] || '';
    }
    return;
  }

  if (!provinceChanged && !wardChanged) {
    mapStatus.value = 'Vị trí Google Maps khớp với địa chỉ đã chọn.';
    return;
  }

  const currentText = [findWard(form.venue_ward_code)?.name, findProvince(form.venue_province_code)?.name].filter(Boolean).join(', ') || 'chưa chọn';
  const resolvedText = [resolved.ward, resolved.province].filter(Boolean).join(', ') || resolved.address || 'vị trí Google Maps';

  mapSuggestion.value = {
    province_code: resolvedProvince,
    ward_code: resolvedWard,
    message: `Vị trí trên Google Maps thuộc ${resolvedText} — khác với địa chỉ bạn đã chọn (${currentText}).`,
  };
}

async function applyMapSuggestion() {
  if (!mapSuggestion.value) return;
  if (mapSuggestion.value.province_code) {
    form.venue_province_code = mapSuggestion.value.province_code;
    await loadWards(form.venue_province_code);
  }
  if (mapSuggestion.value.ward_code) form.venue_ward_code = mapSuggestion.value.ward_code;
  mapSuggestion.value = null;
  mapStatus.value = 'Đã cập nhật địa chỉ theo Google Maps.';
  syncVenueAddress();
}

function findProvince(code) {
  return provinces.value.find((province) => String(province.code) === String(code));
}

function findWard(code) {
  return wards.value.find((ward) => String(ward.code) === String(code));
}

function syncCourtRows() {
  const total = Math.max(1, Number(form.court_count_total || 1));
  while (form.courts.length < total) {
    form.courts.push({ local_id: localId(), name: `Sân ${form.courts.length + 1}`, court_type_id: form.courts[0]?.court_type_id || '', note: '' });
  }
  if (form.courts.length > total) form.courts.splice(total);
}

function removeCourt(index) {
  if (form.courts.length <= 1) return;
  form.courts.splice(index, 1);
  form.court_count_total = form.courts.length;
}

function setFiles(group, event) {
  files[group] = Array.from(event.target.files || []);
}

function removeFile(group, index) {
  files[group].splice(index, 1);
}

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

  Object.entries(required).forEach(([field, message]) => {
    if (!form[field]) fieldErrors[field] = message;
  });

  if (form.applicant_birth_date && new Date(form.applicant_birth_date) > new Date(new Date().setFullYear(new Date().getFullYear() - 18))) {
    fieldErrors.applicant_birth_date = 'Người đăng ký phải đủ 18 tuổi.';
  }
  if (form.applicant_phone && !/^0\d{9}$/.test(form.applicant_phone)) fieldErrors.applicant_phone = 'Số điện thoại phải gồm 10 chữ số và bắt đầu bằng 0.';
  if (form.venue_phone && !/^0\d{9}$/.test(form.venue_phone)) fieldErrors.venue_phone = 'Số điện thoại sân phải gồm 10 chữ số và bắt đầu bằng 0.';
  if (form.applicant_email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.applicant_email)) fieldErrors.applicant_email = 'Email không đúng định dạng.';
  if (form.venue_email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.venue_email)) fieldErrors.venue_email = 'Email sân không đúng định dạng.';
  if (!isValidIdentity()) fieldErrors.representative_identity_number = 'Số giấy tờ không đúng định dạng đã chọn.';
  if (!form.venue_latitude || !form.venue_longitude) fieldErrors.venue_map_url = 'Vui lòng dùng link Google Maps có tọa độ hợp lệ.';
  if (!bankVerified.value && !bankManualMode.value) fieldErrors.account_number = bankError.value || 'Vui lòng chờ xác minh tài khoản ngân hàng thành công.';
  if (bankManualMode.value && !form.account_holder_name) fieldErrors.account_holder_name = 'Vui lòng nhập tên chủ tài khoản.';
  if (!files.identity.length) fieldErrors.identity_documents = 'Vui lòng tải lên CCCD/CMND.';
  if (!files.business_license.length) fieldErrors.business_license_documents = 'Vui lòng tải lên giấy tờ pháp lý.';
  if (!files.facility.length) fieldErrors.facility_images = 'Vui lòng tải lên hình ảnh cơ sở.';
  if (!confirmed.value) fieldErrors.confirmed = 'Vui lòng xác nhận thông tin trước khi gửi.';

  form.courts.forEach((court, index) => {
    if (!court.name) fieldErrors[`courts.${index}.name`] = 'Vui lòng nhập tên sân.';
    if (!court.court_type_id) fieldErrors[`courts.${index}.court_type_id`] = 'Vui lòng chọn loại sân.';
  });

  return Object.keys(fieldErrors).length === 0;
}

function isValidIdentity() {
  const value = form.representative_identity_number || '';
  if (form.representative_identity_type === 'cccd') return /^\d{12}$/.test(value);
  if (form.representative_identity_type === 'cmnd') return /^\d{9}(\d{3})?$/.test(value);
  return /^[A-Z0-9]{6,20}$/i.test(value);
}

function clearErrors() {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key]);
}

function buildPayload() {
  syncVenueAddress();
  return {
    ...form,
    court_count_total: Number(form.court_count_total),
    base_price_per_hour: Number(form.base_price_per_hour),
    courts: form.courts.map((court) => ({
      name: court.name,
      court_type_id: court.court_type_id,
      note: court.note || '',
    })),
  };
}

async function submit() {
  formBanner.value = '';
  if (!validateForm()) {
    const errorFields = Object.keys(fieldErrors).join(', ');
    formBanner.value = `Vui lòng kiểm tra lại các trường đang báo lỗi. (Các trường lỗi: ${errorFields})`;
    return;
  }

  submitting.value = true;
  try {
    const formData = new FormData();
    const payload = buildPayload();
    Object.entries(payload).forEach(([key, value]) => {
      if (key === 'street_address') return;
      if (['courts', 'amenities'].includes(key)) formData.append(key, JSON.stringify(value || []));
      else if (value !== null && value !== undefined) formData.append(key, value);
    });
    formData.append('confirmed', '1');
    files.identity.forEach((file) => formData.append('identity_documents[]', file));
    files.business_license.forEach((file) => formData.append('business_license_documents[]', file));
    files.facility.forEach((file) => formData.append('facility_images[]', file));
    files.additional.forEach((file) => formData.append('additional_documents[]', file));

    await apiFormData('/api/user/partner-application', formData);
    clearDraft();
    formOpen.value = false;
    await loadApplications();
  } catch (error) {
    applyBackendErrors(error);
  } finally {
    submitting.value = false;
  }
}

function applyBackendErrors(error) {
  clearErrors();
  const errors = error.data?.errors || {};
  Object.entries(errors).forEach(([field, messages]) => {
    fieldErrors[field] = Array.isArray(messages) ? messages[0] : messages;
  });
  formBanner.value = error.message || 'Vui lòng kiểm tra lại thông tin hồ sơ.';
}

async function cancelApplication(application) {
  if (!window.confirm(`Hủy hồ sơ đăng ký cho ${application.venue_name}?`)) return;
  await api(`/api/user/partner-application/${application.id}/cancel`, {
    method: 'POST',
    body: JSON.stringify({ reason: 'Người dùng hủy hồ sơ từ trang đăng ký đối tác.' }),
  });
  await loadApplications();
}

function applicationWord(application) {
  const docs = application.generated_documents || application.generatedDocuments || [];
  return docs.find((doc) => doc.document_type === 'partner_application_form');
}

function downloadDocument(document) {
  if (!document?.id) return;
  apiDownload(`/api/files/documents/${document.id}/download`);
}

function canCancel(application) {
  return ['pending', 'submitted', 'reviewing', 'need_supplement', 'draft'].includes(application.status);
}

function statusLabel(status) {
  return {
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
}

function statusClass(status) {
  if (['rejected', 'cancelled'].includes(status)) return 'bg-red-100 text-red-700';
  if (status === 'completed') return 'bg-green-100 text-green-700';
  return 'bg-amber-100 text-amber-700';
}

function coordinateText(application) {
  if (!application?.venue_latitude || !application?.venue_longitude) return '-';
  return `${application.venue_latitude}, ${application.venue_longitude}`;
}

function formatDate(value) {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function dateOnly(value) {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString('vi-VN');
}

function money(value) {
  const number = Number(value || 0);
  if (!Number.isFinite(number) || number <= 0) return '-';
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(number);
}
</script>
