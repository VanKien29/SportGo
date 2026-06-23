<template>
  <div class="min-h-screen w-full bg-gray-50 flex flex-col">
    <PublicNavbar />

    <main class="mx-auto w-full max-w-5xl px-4 pb-16 mt-20 pt-8 sm:px-6 lg:px-8 flex-1">

      <!-- ───── LIST VIEW ───── -->
      <template v-if="!formOpen">

        <!-- Page header -->
        <div class="mb-6">
          <p class="text-xs font-semibold uppercase tracking-widest text-emerald-600">SportGo Partner</p>
          <h1 class="mt-1 text-2xl font-semibold text-gray-900">Đăng ký đối tác chủ sân</h1>
          <p class="mt-1 text-sm text-gray-500">Gửi hồ sơ, theo dõi tiến trình xét duyệt và ký số văn bản ngay trên nền tảng.</p>
        </div>

        <!-- Stat cards -->
        <div class="mb-6 grid grid-cols-3 gap-3">
          <div class="rounded-xl bg-white border border-gray-100 px-4 py-3 shadow-sm">
            <p class="text-xs text-gray-400">Tổng hồ sơ</p>
            <p class="mt-1 text-xl font-semibold text-gray-900">{{ applications.length }}</p>
            <p class="mt-0.5 text-xs text-emerald-600">Đã gửi</p>
          </div>
          <div class="rounded-xl bg-white border border-gray-100 px-4 py-3 shadow-sm">
            <p class="text-xs text-gray-400">Đang xét duyệt</p>
            <p class="mt-1 text-xl font-semibold text-gray-900">{{ reviewingCount }}</p>
            <p class="mt-0.5 text-xs text-amber-500">Chờ phản hồi</p>
          </div>
          <div class="rounded-xl bg-white border border-gray-100 px-4 py-3 shadow-sm">
            <p class="text-xs text-gray-400">Hồ sơ nháp</p>
            <p class="mt-1 text-xl font-semibold text-gray-900">{{ draft ? 1 : 0 }}</p>
            <p class="mt-0.5 text-xs text-gray-400">Chưa gửi</p>
          </div>
        </div>

        <!-- Draft bar -->
        <div v-if="draft" class="mb-4 flex items-center justify-between gap-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
          <div class="flex items-center gap-3 min-w-0">
            <span class="h-2 w-2 shrink-0 rounded-full bg-amber-400"></span>
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-900 truncate">
                {{ draft.venue_name || 'Chưa đặt tên cụm sân' }}
                <span class="font-normal text-gray-500"> — đang lưu nháp</span>
              </p>
              <p class="text-xs text-gray-400 mt-0.5">Lưu lúc {{ formatDate(draft.saved_at) }}</p>
            </div>
          </div>
          <div class="flex shrink-0 items-center gap-2">
            <button type="button" class="rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 transition" @click="continueDraft">
              Tiếp tục điền
            </button>
            <button type="button" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition" @click="clearDraft">
              Xóa nháp
            </button>
          </div>
        </div>

        <!-- Toolbar -->
        <div class="mb-4 flex items-center justify-between">
          <p class="text-sm text-gray-400">{{ applications.length }} hồ sơ</p>
          <div class="flex items-center gap-2">
            <button type="button" class="flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-600 shadow-sm hover:bg-gray-50 transition" @click="loadApplications">
              <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              Làm mới
            </button>
            <button v-if="canRegister" type="button" class="flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 transition" @click="startNewApplication">
              <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              Đăng ký hồ sơ mới
            </button>
          </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-20">
          <svg class="h-6 w-6 animate-spin text-gray-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span class="ml-2 text-sm text-gray-400">Đang tải hồ sơ...</span>
        </div>

        <!-- Empty state -->
        <div v-else-if="applications.length === 0 && !draft" class="rounded-xl border border-dashed border-gray-200 bg-white py-16 text-center">
          <svg class="mx-auto h-10 w-10 text-gray-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <h3 class="mt-4 text-sm font-semibold text-gray-900">Chưa có hồ sơ nào</h3>
          <p class="mt-1 text-sm text-gray-400">Bắt đầu bằng cách tạo hồ sơ đăng ký đầu tiên của bạn.</p>
          <button v-if="canRegister" type="button" class="mt-5 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 transition" @click="startNewApplication">
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Tạo hồ sơ đăng ký
          </button>
        </div>

        <!-- Application list -->
        <div v-else class="space-y-3">
          <article
            v-for="application in applications"
            :key="application.id"
            class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm transition hover:border-gray-200 hover:shadow-md"
          >
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
              <!-- Left: info -->
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="text-base font-semibold text-gray-900">{{ application.venue_name }}</h3>
                  <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusClass(application.status)">
                    <span class="h-1.5 w-1.5 rounded-full" :class="statusDotClass(application.status)"></span>
                    {{ statusLabel(application.status) }}
                  </span>
                </div>
                <div class="mt-1.5 flex items-center gap-1.5 text-xs text-gray-400">
                  <svg class="h-3.5 w-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                  <span class="truncate">{{ application.venue_address }}</span>
                  <span class="text-gray-200">·</span>
                  <svg class="h-3.5 w-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span>Gửi {{ formatDate(application.submitted_at) }}</span>
                </div>

                <!-- Rejection reason -->
                <div v-if="application.status === 'rejected'" class="mt-3 rounded-lg border border-red-100 bg-red-50 px-3 py-2.5 text-xs text-red-700">
                  <p class="font-semibold mb-1">Lý do từ chối</p>
                  <p class="text-red-600">{{ application.status_reason || 'SportGo chưa cung cấp lý do chi tiết.' }}</p>
                </div>

                <!-- Need supplement -->
                <div v-if="application.status === 'need_supplement'" class="mt-3 rounded-lg border border-amber-100 bg-amber-50 px-3 py-2.5 text-xs text-amber-700">
                  <p class="font-semibold mb-1">Cần bổ sung hồ sơ</p>
                  <p class="text-amber-600">{{ application.status_reason || 'Vui lòng liên hệ SportGo để biết thêm chi tiết.' }}</p>
                </div>

                <!-- Contract pending owner signature -->
                <div v-if="application.status === 'contract_pending_owner_signature'" class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-xs text-emerald-700">
                  <p class="font-semibold mb-1">🎉 Hồ sơ đã được duyệt!</p>
                  <p class="text-emerald-600">Hợp đồng hợp tác đã sẵn sàng. Vui lòng xem và ký hợp đồng để hoàn tất quá trình đăng ký.</p>
                </div>
              </div>

              <!-- Right: actions -->
              <div class="flex shrink-0 flex-wrap items-center gap-2">
                <button
                  type="button"
                  class="rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-xs font-medium text-gray-600 hover:bg-gray-50 transition"
                  @click="selectedApplication = application"
                >
                  Chi tiết
                </button>

                <button
                  v-if="applicationWord(application)"
                  type="button"
                  class="flex items-center gap-1.5 rounded-lg bg-blue-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-blue-700 transition"
                  @click="viewDocument(applicationWord(application), application)"
                >
                  <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                  </svg>
                  Xem &amp; Ký Mẫu 01
                </button>

                <button
                  v-if="contractWord(application)"
                  type="button"
                  class="flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-700 transition"
                  @click="viewContractDocument(contractWord(application), application)"
                >
                  <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  Xem &amp; Ký Hợp đồng
                </button>

                <button
                  v-if="canCancel(application)"
                  type="button"
                  class="rounded-lg border border-red-100 bg-white px-3.5 py-2 text-xs font-medium text-red-600 hover:bg-red-50 transition"
                  @click="cancelApplication(application)"
                >
                  Hủy hồ sơ
                </button>
              </div>
            </div>
          </article>
        </div>
      </template>

      <!-- ───── DETAIL MODAL ───── -->
      <Teleport to="body">
        <section
          v-if="selectedApplication"
          class="fixed inset-0 z-[600] grid place-items-center bg-gray-900/50 p-4"
          role="dialog"
          aria-modal="true"
          @click.self="selectedApplication = null"
        >
          <div class="max-h-[calc(100vh-2rem)] w-full max-w-3xl overflow-auto rounded-xl bg-white p-6 shadow-xl">
            <header class="flex items-start justify-between gap-4 border-b border-gray-100 pb-4">
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-emerald-600">Hồ sơ đối tác</p>
                <h2 class="mt-1 text-lg font-semibold text-gray-900">{{ selectedApplication.venue_name }}</h2>
                <span class="mt-2 inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusClass(selectedApplication.status)">
                  <span class="h-1.5 w-1.5 rounded-full" :class="statusDotClass(selectedApplication.status)"></span>
                  {{ statusLabel(selectedApplication.status) }}
                </span>
              </div>
              <button type="button" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 transition" @click="selectedApplication = null">
                Đóng
              </button>
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
              <div class="md:col-span-2 rounded-lg border border-gray-100 bg-gray-50 p-4">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Tài liệu đính kèm</h3>
                <div v-if="selectedApplication.documents?.length" class="flex flex-wrap gap-2">
                  <button v-for="doc in selectedApplication.documents" :key="doc.id" @click="viewFile(doc.file_path)" type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition">
                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                    {{ doc.title || doc.document_type || 'Tài liệu' }}
                  </button>
                </div>
                <p v-else class="text-sm text-gray-500">Chưa có tài liệu đính kèm.</p>
              </div>
            </div>
          </div>
        </section>
      </Teleport>

      <!-- ───── FORM VIEW ───── -->
      <template v-if="formOpen">
        <div class="mb-6 flex items-center gap-3">
          <BackButton @click="formOpen = false" title="Quay lại danh sách" />
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-emerald-600">SportGo Partner</p>
            <h2 class="text-lg font-semibold text-gray-900">Điền đơn đăng ký đối tác</h2>
          </div>
        </div>

        <form class="space-y-5" novalidate @submit.prevent="submit">
          <!-- Error banner -->
          <div v-if="formBanner" class="rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ formBanner }}
          </div>

          <!-- Section: Thông tin cá nhân -->
          <FormSection title="Thông tin cá nhân">
            <div class="grid gap-4 md:grid-cols-2">
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

          <!-- Section: Thông tin ngân hàng -->
          <FormSection title="Thông tin ngân hàng">
            <div class="grid gap-4 md:grid-cols-2">
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
                  placeholder="Nhập tên chủ tài khoản (viết IN HOA không dấu)"
                  @input="onManualBankHolderInput()"
                />
              </FormField>
              <FormField label="Chi nhánh" :error="fieldErrors.bank_branch">
                <input v-model.trim="form.bank_branch" :class="inputClass(fieldErrors.bank_branch)" />
              </FormField>
            </div>
          </FormSection>

          <!-- Section: Địa chỉ sân -->
          <FormSection title="Địa chỉ sân">
            <div class="grid gap-4 md:grid-cols-2">
              <FormField label="Tỉnh/Thành phố" required :error="fieldErrors.venue_province_code">
                <BaseCombobox v-model="form.venue_province_code" :options="provinceOptions" placeholder="Tìm Tỉnh/Thành phố" :invalid="Boolean(fieldErrors.venue_province_code)" @select="onProvinceSelect" />
              </FormField>
              <FormField label="Phường/Xã" required :error="fieldErrors.venue_ward_code">
                <BaseCombobox v-model="form.venue_ward_code" :options="wardOptions" placeholder="Tìm Phường/Xã" :disabled="!form.venue_province_code" :invalid="Boolean(fieldErrors.venue_ward_code)" @select="syncVenueAddress" />
              </FormField>
              <FormField class="md:col-span-2" label="Số nhà, tên đường" required :error="fieldErrors.street_address">
                <input v-model.trim="form.street_address" :class="inputClass(fieldErrors.street_address)" placeholder="Ví dụ: 123 Nguyễn Hữu Cảnh" @input="syncVenueAddress" />
              </FormField>
              <FormField class="md:col-span-2" label="Link Google Maps" required :error="mapError || fieldErrors.venue_map_url">
                <input v-model.trim="form.venue_map_url" :class="inputClass(mapError || fieldErrors.venue_map_url)" placeholder="Dán link Google Maps có tọa độ" @input="onMapUrlInput" />
                <div v-if="mapSuggestion" class="mt-2 rounded-lg border border-amber-100 bg-amber-50 p-3 text-xs text-amber-800">
                  <p>{{ mapSuggestion.message }}</p>
                  <button type="button" class="mt-2 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700 transition" @click="applyMapSuggestion">
                    Cập nhật theo Google Maps
                  </button>
                </div>
                <p v-else-if="mapStatus" class="mt-1 text-xs text-emerald-600">{{ mapStatus }}</p>
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

          <!-- Section: Cấu hình sân -->
          <FormSection title="Cấu hình sân">
            <div class="grid gap-4 md:grid-cols-2">
              <FormField label="Số lượng sân con" required :error="fieldErrors.court_count_total">
                <input v-model.number="form.court_count_total" :class="inputClass(fieldErrors.court_count_total)" type="number" min="1" max="100" @input="syncCourtRows" />
              </FormField>
              <FormField label="Giá cơ bản/giờ (VNĐ)" required :error="fieldErrors.base_price_per_hour">
                <input v-model.number="form.base_price_per_hour" :class="inputClass(fieldErrors.base_price_per_hour)" type="number" min="1000" step="1000" />
              </FormField>
            </div>

            <div class="mt-4 space-y-3">
              <div
                v-for="(court, index) in form.courts"
                :key="court.local_id"
                class="grid gap-3 rounded-lg border border-gray-100 bg-gray-50 p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]"
              >
                <FormField :label="`Tên sân ${index + 1}`" required :error="fieldErrors[`courts.${index}.name`]">
                  <input v-model.trim="court.name" :class="inputClass(fieldErrors[`courts.${index}.name`])" />
                </FormField>
                <FormField label="Loại sân" required :error="fieldErrors[`courts.${index}.court_type_id`]">
                  <BaseCombobox v-model="court.court_type_id" :options="courtTypeOptions" placeholder="Chọn loại sân" :invalid="Boolean(fieldErrors[`courts.${index}.court_type_id`])" />
                </FormField>
                <button
                  type="button"
                  class="self-end rounded-lg border border-red-100 bg-white px-3 py-2.5 text-xs font-medium text-red-600 hover:bg-red-50 transition disabled:cursor-not-allowed disabled:opacity-40"
                  :disabled="form.courts.length <= 1"
                  @click="removeCourt(index)"
                >
                  Xóa
                </button>
              </div>
            </div>

            <div v-if="amenities.length" class="mt-4 flex flex-wrap gap-2">
              <label
                v-for="amenity in amenities"
                :key="amenity.id || amenity.name"
                class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-700 hover:border-gray-300 transition"
              >
                <input v-model="form.amenities" class="h-3.5 w-3.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" type="checkbox" :value="amenity.name" />
                {{ amenity.name }}
              </label>
            </div>
          </FormSection>

          <!-- Section: Tài liệu -->
          <FormSection title="Tài liệu đính kèm">
            <div class="grid gap-4 md:grid-cols-2">
              <UploadBox title="CCCD/CMND người đại diện" required :files="files.identity" :error="fieldErrors.identity_documents" @change="setFiles('identity', $event)" @remove="removeFile('identity', $event)" />
              <UploadBox title="Giấy đăng ký kinh doanh/pháp lý" required :files="files.business_license" :error="fieldErrors.business_license_documents" @change="setFiles('business_license', $event)" @remove="removeFile('business_license', $event)" />
              <UploadBox title="Hình ảnh cơ sở/sân" required :files="files.facility" :error="fieldErrors.facility_images" @change="setFiles('facility', $event)" @remove="removeFile('facility', $event)" />
              <UploadBox title="Tài liệu bổ sung" :files="files.additional" :error="fieldErrors.additional_documents" @change="setFiles('additional', $event)" @remove="removeFile('additional', $event)" />
            </div>
          </FormSection>

          <!-- Confirm checkbox -->
          <div class="rounded-lg border border-gray-100 bg-white p-4">
            <label class="flex items-start gap-3">
              <input v-model="confirmed" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" type="checkbox" />
              <span class="text-sm text-gray-600">Tôi xác nhận thông tin trong hồ sơ là chính xác và đồng ý để SportGo kiểm tra tài liệu trước khi duyệt đối tác.</span>
            </label>
            <p v-if="fieldErrors.confirmed" class="mt-1 ml-7 text-xs text-red-500">{{ fieldErrors.confirmed }}</p>
          </div>

          <!-- Submit row -->
          <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
            <button type="button" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 transition" @click="saveDraft">
              Lưu nháp
            </button>
            <button
              type="submit"
              class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="submitDisabled"
            >
              <span v-if="submitting" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-emerald-300 border-t-white"></span>
              {{ submitting ? 'Đang xử lý...' : 'Gửi hồ sơ đăng ký' }}
            </button>
          </div>
        </form>
      </template>
    </main>

    <DocumentViewerModal :show="showDocumentViewer" :document="viewingDocument" @close="closeDocumentViewer">
      <template #actions v-if="needsSignature(viewingDocument)">
        <button type="button" class="w-full rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition" style="background-color: #059669; color: #ffffff; border: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" @click="openSignaturePad" onmouseover="this.style.backgroundColor='#047857'" onmouseout="this.style.backgroundColor='#059669'">
          {{ signingContract ? 'Ký hợp đồng' : 'Ký xác nhận văn bản' }}
        </button>
      </template>
    </DocumentViewerModal>

    <SignaturePadModal :show="showSignaturePad" :saving="savingSignature" @close="showSignaturePad = false" @confirm="submitSignature" />

    <FloatingActions />
  </div>
</template>

<script setup>
import { computed, defineComponent, h, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import PublicNavbar from '../../components/PublicNavbar.vue';
import FloatingActions from '../../components/FloatingActions.vue';
import BackButton from '../../components/BackButton.vue';
import DocumentViewerModal from '../../components/DocumentViewerModal.vue';
import SignaturePadModal from '../../components/SignaturePadModal.vue';
import { getAuth } from '../../stores/auth.js';
import { api, apiDownload, apiFormData } from '../../services/api.js';

// ─── Constants ───────────────────────────────────────────────────────────────
const DRAFT_KEY = 'sportgo_partner_application_draft_v3';
const BANK_CACHE_KEY = 'sportgo_partner_banks_v2';
const BANK_CACHE_TTL = 24 * 60 * 60 * 1000;

// ─── Inline components ───────────────────────────────────────────────────────
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
    const optionValue = (o) => String(o?.value ?? o?.code ?? o?.id ?? '');
    const optionLabel = (o) => String(o?.label ?? o?.name ?? o?.short_name ?? '');
    const selected = computed(() => props.options.find((o) => optionValue(o) === String(props.modelValue)) || null);
    const filtered = computed(() => {
      const kw = query.value.trim().toLowerCase();
      if (!kw || (selected.value && query.value === optionLabel(selected.value))) return props.options;
      return props.options.filter((o) => optionLabel(o).toLowerCase().includes(kw));
    });
    watch(selected, (o) => { if (!open.value) query.value = o ? optionLabel(o) : ''; }, { immediate: true });
    const choose = (o) => { emit('update:modelValue', optionValue(o)); emit('select', o); query.value = optionLabel(o); open.value = false; };
    const onInput = (e) => { query.value = e.target.value; open.value = true; };
    const onBlur = () => { window.setTimeout(() => { open.value = false; query.value = selected.value ? optionLabel(selected.value) : ''; }, 130); };
    return { open, query, filtered, selected, optionValue, optionLabel, choose, onInput, onBlur };
  },
  template: `
    <div class="relative">
      <div class="relative">
        <input
          :value="query" :placeholder="placeholder" :disabled="disabled"
          class="w-full rounded-lg border px-3 py-2.5 pr-10 text-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-400"
          :class="invalid ? 'border-red-400' : 'border-gray-200'"
          @focus="!disabled && (open = true)" @blur="onBlur" @input="onInput"
        />
        <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
        </svg>
      </div>
      <div v-if="open && !disabled" class="absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-gray-100 bg-white py-1 shadow-lg">
        <button v-for="o in filtered" :key="optionValue(o)" type="button"
          class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-emerald-50"
          :class="optionValue(o) === String(modelValue) ? 'bg-emerald-50 text-emerald-700' : 'text-gray-700'"
          @mousedown.prevent="choose(o)">
          <span class="truncate">{{ optionLabel(o) }}</span>
          <svg v-if="optionValue(o) === String(modelValue)" class="ml-3 h-3.5 w-3.5 shrink-0 text-emerald-600" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.31a1 1 0 0 1-1.42.005L3.29 9.27a1 1 0 1 1 1.42-1.41l4.04 4.04 6.54-6.604a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd"/>
          </svg>
        </button>
        <p v-if="filtered.length === 0" class="px-3 py-2 text-sm text-gray-400">Không tìm thấy lựa chọn phù hợp.</p>
      </div>
    </div>
  `,
});

const FormSection = defineComponent({
  name: 'FormSection',
  props: { title: { type: String, required: true } },
  setup(props, { slots }) {
    return () => h('section', { class: 'rounded-xl border border-gray-100 bg-white p-5 shadow-sm' }, [
      h('div', { class: 'mb-4 border-b border-gray-50 pb-3' }, [
        h('h2', { class: 'text-sm font-semibold text-gray-900' }, props.title),
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
    return () => h('label', { class: ['block', attrs.class] }, [
      h('span', { class: 'mb-1.5 block text-xs font-medium text-gray-600' }, [
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
    return () => h('section', { class: ['rounded-lg border border-gray-100 bg-gray-50 p-4', attrs.class] }, [
      h('h3', { class: 'text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3' }, props.title),
      h('dl', { class: 'grid gap-1.5 text-sm sm:grid-cols-[140px_minmax(0,1fr)]' }, props.items.flatMap(([label, value]) => [
        h('dt', { class: 'text-xs text-gray-400' }, label),
        h('dd', { class: 'break-words text-sm text-gray-900' }, value || '-'),
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
      const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
      return `${(bytes / 1024 ** i).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
    };
    return { emit, fileSize };
  },
  template: `
    <div class="rounded-lg border border-dashed p-4 transition" :class="error ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-gray-50 hover:border-gray-300'">
      <label class="block cursor-pointer">
        <span class="text-xs font-medium text-gray-600">{{ title }}<span v-if="required" class="ml-1 text-red-500">*</span></span>
        <input class="mt-2 block w-full text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-700 hover:file:bg-emerald-100" type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" @change="emit('change', $event)" />
      </label>
      <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
      <ul v-if="files.length" class="mt-3 space-y-1.5">
        <li v-for="(file, idx) in files" :key="file.name + idx" class="flex items-center justify-between gap-2 rounded-lg bg-white px-3 py-1.5 text-xs border border-gray-100">
          <span class="truncate text-gray-600">{{ file.name }} · {{ fileSize(file) }}</span>
          <button type="button" class="shrink-0 text-xs font-medium text-red-500 hover:text-red-700" @click="emit('remove', idx)">Xóa</button>
        </li>
      </ul>
      <p v-else class="mt-2 text-xs text-gray-400">Chưa chọn file.</p>
    </div>
  `,
});

// ─── State ───────────────────────────────────────────────────────────────────
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
const mapError = ref('');
const mapStatus = ref('');
const mapSuggestion = ref(null);
const mapTimer = ref(null);
const showDocumentViewer = ref(false);
const viewingDocument = ref(null);
const showSignaturePad = ref(false);
const savingSignature = ref(false);
const signingContract = ref(false);
const signingApplicationId = ref(null);

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
});

onBeforeUnmount(() => {
  clearTimeout(bankTimer.value);
  clearTimeout(mapTimer.value);
});

watch(() => form.venue_province_code, async (code, old) => {
  if (code !== old) { form.venue_ward_code = ''; wards.value = []; await loadWards(code); syncVenueAddress(); }
});
watch(() => form.venue_ward_code, syncVenueAddress);

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

function blankFiles() { return { identity: [], business_license: [], facility: [], additional: [] }; }
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
function textareaClass(error) { return `${inputClass(error)} resize-y`; }

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
function startNewApplication() { resetForm(defaultForm(user)); formOpen.value = true; }

function resetForm(next) {
  Object.assign(form, next);
  Object.assign(files, blankFiles());
  clearErrors();
  formBanner.value = '';
  confirmed.value = false;
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
  try { draft.value = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch { draft.value = null; }
}

async function continueDraft() {
  if (!draft.value) return;
  resetForm({ ...defaultForm(user), ...draft.value });
  formOpen.value = true;
  if (form.venue_province_code) await loadWards(form.venue_province_code);
}

function clearDraft() { localStorage.removeItem(DRAFT_KEY); draft.value = null; }

// ─── Input handlers ───────────────────────────────────────────────────────────
function digitsOnly(field, max) { form[field] = String(form[field] || '').replace(/\D/g, '').slice(0, max); }

function normalizeIdentityNumber() {
  const v = String(form.representative_identity_number || '');
  form.representative_identity_number = form.representative_identity_type === 'passport' ? v.replace(/[^a-zA-Z0-9]/g, '').toUpperCase().slice(0, 20) : v.replace(/\D/g, '').slice(0, 12);
}

function normalizeTaxCode() { form.tax_code = String(form.tax_code || '').replace(/[^\d-]/g, '').slice(0, 14); }

// ─── Bank verification ────────────────────────────────────────────────────────
function selectBank(bank) { form.bank_name = bank?.short_name || bank?.name || ''; form.bank_bin = bank?.bin || ''; }
function onAccountNumberInput() { form.account_number = String(form.account_number || '').replace(/\D/g, '').slice(0, 19); }
function onManualBankHolderInput() {
  form.account_holder_name = String(form.account_holder_name || '').toUpperCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/đ/g, "d").replace(/Đ/g, "D");
}

// ─── Address / Map ────────────────────────────────────────────────────────────
function onProvinceSelect() { form.venue_ward_code = ''; wards.value = []; syncVenueAddress(); }

function syncVenueAddress() {
  const province = provinces.value.find((p) => String(p.code) === String(form.venue_province_code))?.name;
  const ward = wards.value.find((w) => String(w.code) === String(form.venue_ward_code))?.name;
  form.venue_address = [form.street_address, ward, province].filter(Boolean).join(', ');
}

function onMapUrlInput() {
  clearTimeout(mapTimer.value); mapError.value = ''; mapStatus.value = ''; mapSuggestion.value = null;
  form.venue_latitude = ''; form.venue_longitude = '';
  if (!form.venue_map_url) return;
  mapTimer.value = window.setTimeout(resolveMapUrl, 500);
}

async function resolveMapUrl() {
  mapError.value = ''; mapStatus.value = 'Đang xử lý link...';
  try {
    const r = await api('/api/user/partner-application/resolve-map', { method: 'POST', body: JSON.stringify({ url: form.venue_map_url }) });
    const resolved = r.data || {};
    if (resolved.latitude && resolved.longitude) { form.venue_latitude = resolved.latitude; form.venue_longitude = resolved.longitude; compareResolvedAddress(resolved); return; }
  } catch (e) { console.error('Lỗi phân giải map:', e); }
  const coords = extractCoordinates(form.venue_map_url);
  if (!coords && !form.venue_latitude) { mapStatus.value = ''; mapError.value = 'Không lấy được tọa độ từ link Google Maps này. Vui lòng dùng link đầy đủ có tọa độ.'; return; }
  if (coords) { form.venue_latitude = coords.latitude; form.venue_longitude = coords.longitude; mapStatus.value = 'Đã lấy tọa độ từ link Google Maps.'; }
}

function extractCoordinates(url) {
  const d = decodeURIComponent(url || '');
  for (const p of [/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/, /!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/, /[?&](?:q|ll|query)=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/, /[?&]center=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/]) {
    const m = d.match(p); if (m) return { latitude: Number(m[1]), longitude: Number(m[2]) };
  }
  return null;
}

function compareResolvedAddress(resolved) {
  const rp = resolved.province_code || '', rw = resolved.ward_code || '';
  const pc = rp && rp !== form.venue_province_code, wc = rw && rw !== form.venue_ward_code;
  if (!form.venue_province_code && rp) {
    form.venue_province_code = rp;
    loadWards(rp).then(() => { if (rw) form.venue_ward_code = rw; });
    mapStatus.value = 'Đã tự động điền địa chỉ từ Google Maps.';
    if (resolved.address && !form.street_address) form.street_address = resolved.address.split(',')[0] || '';
    return;
  }
  if (!pc && !wc) { mapStatus.value = 'Vị trí Google Maps khớp với địa chỉ đã chọn.'; return; }
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
  if (form.applicant_phone && !/^0\d{9}$/.test(form.applicant_phone)) fieldErrors.applicant_phone = 'Số điện thoại phải gồm 10 chữ số và bắt đầu bằng 0.';
  if (form.venue_phone && !/^0\d{9}$/.test(form.venue_phone)) fieldErrors.venue_phone = 'Số điện thoại sân phải gồm 10 chữ số và bắt đầu bằng 0.';
  if (form.applicant_email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.applicant_email)) fieldErrors.applicant_email = 'Email không đúng định dạng.';
  if (form.venue_email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.venue_email)) fieldErrors.venue_email = 'Email sân không đúng định dạng.';
  if (!isValidIdentity()) fieldErrors.representative_identity_number = 'Số giấy tờ không đúng định dạng đã chọn.';
  if (!form.venue_latitude || !form.venue_longitude) fieldErrors.venue_map_url = 'Vui lòng dùng link Google Maps có tọa độ hợp lệ.';
  // if (!bankVerified.value && !bankManualMode.value) fieldErrors.account_number = bankError.value || 'Vui lòng chờ xác minh tài khoản ngân hàng thành công.';
  if (!form.account_holder_name) fieldErrors.account_holder_name = 'Vui lòng nhập tên chủ tài khoản.';
  if (!files.identity.length) fieldErrors.identity_documents = 'Vui lòng tải lên CCCD/CMND.';
  if (!files.business_license.length) fieldErrors.business_license_documents = 'Vui lòng tải lên giấy tờ pháp lý.';
  if (!files.facility.length) fieldErrors.facility_images = 'Vui lòng tải lên hình ảnh cơ sở.';
  if (!confirmed.value) fieldErrors.confirmed = 'Vui lòng xác nhận thông tin trước khi gửi.';
  form.courts.forEach((c, i) => {
    if (!c.name) fieldErrors[`courts.${i}.name`] = 'Vui lòng nhập tên sân.';
    if (!c.court_type_id) fieldErrors[`courts.${i}.court_type_id`] = 'Vui lòng chọn loại sân.';
  });
  return Object.keys(fieldErrors).length === 0;
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
  if (!validateForm()) { formBanner.value = `Vui lòng kiểm tra lại các trường đang báo lỗi. (${Object.keys(fieldErrors).join(', ')})`; return; }
  submitting.value = true;
  try {
    syncVenueAddress();
    const payload = { ...form, court_count_total: Number(form.court_count_total), base_price_per_hour: Number(form.base_price_per_hour), courts: form.courts.map((c) => ({ name: c.name, court_type_id: c.court_type_id, note: c.note || '' })) };
    const formData = new FormData();
    Object.entries(payload).forEach(([k, v]) => {
      if (k === 'street_address') return;
      if (['courts', 'amenities'].includes(k)) formData.append(k, JSON.stringify(v || []));
      else if (v !== null && v !== undefined) formData.append(k, v);
    });
    formData.append('confirmed', '1');
    files.identity.forEach((f) => formData.append('identity_documents[]', f));
    files.business_license.forEach((f) => formData.append('business_license_documents[]', f));
    files.facility.forEach((f) => formData.append('facility_images[]', f));
    files.additional.forEach((f) => formData.append('additional_documents[]', f));
    await apiFormData('/api/user/partner-application', formData);
    clearDraft(); formOpen.value = false; await loadApplications();
  } catch (e) {
    clearErrors();
    const errors = e.data?.errors || {};
    Object.entries(errors).forEach(([f, m]) => { fieldErrors[f] = Array.isArray(m) ? m[0] : m; });
    formBanner.value = e.message || 'Vui lòng kiểm tra lại thông tin hồ sơ.';
  } finally { submitting.value = false; }
}

// ─── Application actions ──────────────────────────────────────────────────────
async function cancelApplication(application) {
  if (!window.confirm(`Hủy hồ sơ đăng ký cho ${application.venue_name}?`)) return;
  await api(`/api/user/partner-application/${application.id}/cancel`, { method: 'POST', body: JSON.stringify({ reason: 'Người dùng hủy hồ sơ từ trang đăng ký đối tác.' }) });
  await loadApplications();
}

// ─── Document / Signature ─────────────────────────────────────────────────────
function viewDocument(doc, application) {
  signingContract.value = false;
  signingApplicationId.value = application?.id || null;
  viewingDocument.value = { ...doc, download_url: `/api/files/documents/${doc.id}/download` };
  showDocumentViewer.value = true;
}
function viewContractDocument(doc, application) {
  signingContract.value = true;
  signingApplicationId.value = application?.id || null;
  viewingDocument.value = { ...doc, download_url: `/api/files/documents/${doc.id}/download`, status: 'pending_owner_signature' };
  showDocumentViewer.value = true;
}
function closeDocumentViewer() { showDocumentViewer.value = false; signingContract.value = false; signingApplicationId.value = null; setTimeout(() => { viewingDocument.value = null; }, 300); }

async function viewFile(path) {
  if (!path) return;
  const newWin = window.open('', '_blank');
  if (newWin) newWin.document.write('<div style="font-family:sans-serif;padding:20px;text-align:center;">Đang tải dữ liệu file...</div>');
  try {
    const token = localStorage.getItem('auth_token') || JSON.parse(localStorage.getItem('sportgo_auth') || 'null')?.token;
    const response = await fetch(`/api/auth/files/download?path=${encodeURIComponent(path)}`, { headers: { Authorization: `Bearer ${token}` } });
    if (!response.ok) throw new Error('Không thể tải file');
    
    const contentType = (response.headers.get('content-type') || '').toLowerCase();
    const canPreviewInBrowser = contentType.includes('pdf') || contentType.startsWith('image/');
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    
    if (canPreviewInBrowser) {
      if (newWin) {
        newWin.location.href = url;
        newWin.addEventListener('unload', () => setTimeout(() => URL.revokeObjectURL(url), 10000));
      } else {
        window.location.href = url;
      }
    } else {
      if (newWin) newWin.close();
      const disposition = response.headers.get('content-disposition') || '';
      const filenameFromHeader = disposition.match(/filename\*?=(?:UTF-8''|")?([^";]+)/i)?.[1];
      const fallbackName = decodeURIComponent(String(path).split('/').pop() || 'downloaded-file');
      const filename = decodeURIComponent((filenameFromHeader || fallbackName).replace(/"/g, ''));
      
      const downloadLink = document.createElement('a');
      downloadLink.href = url;
      downloadLink.download = filename;
      document.body.appendChild(downloadLink);
      downloadLink.click();
      document.body.removeChild(downloadLink);
      setTimeout(() => URL.revokeObjectURL(url), 60000);
    }
  } catch (e) {
    if (newWin) newWin.close();
    alert(e.message || 'Lỗi tải file');
  }
}

const needsSignature = (doc) => doc?.status === 'pending_owner_signature';
function openSignaturePad() { showSignaturePad.value = true; }
async function submitSignature(base64Image) {
  if (!viewingDocument.value) return;
  savingSignature.value = true;
  try {
    let r;
    if (signingContract.value) {
      // Sign the contract
      r = await api('/api/user/partner-application/sign-contract', { method: 'POST', body: JSON.stringify({ contract_id: viewingDocument.value.partner_contract_id || null, signature_image: base64Image }) });
    } else {
      // Sign the application form (Mẫu 01)
      const appId = signingApplicationId.value || viewingDocument.value.partner_application_id;
      r = await api(`/api/user/partner-application/${appId}/sign-document`, { method: 'POST', body: JSON.stringify({ signature_image: base64Image }) });
    }
    alert(r.message || 'Ký văn bản thành công!');
    showSignaturePad.value = false; closeDocumentViewer(); loadApplications();
  } catch (e) { alert(e.message || 'Có lỗi xảy ra khi gửi chữ ký.'); } finally { savingSignature.value = false; }
}

// ─── Display helpers ──────────────────────────────────────────────────────────
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
  return { pending: 'Chờ xét duyệt', submitted: 'Chờ xét duyệt', reviewing: 'Đang xem xét', need_supplement: 'Cần bổ sung', contract_pending_owner_signature: 'Đã duyệt, chờ ký hợp đồng', contract_pending_sportgo_signature: 'Chờ SportGo ký', completed: 'Đang hoạt động', rejected: 'Bị từ chối', cancelled: 'Đã hủy' }[status] || status || '-';
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
