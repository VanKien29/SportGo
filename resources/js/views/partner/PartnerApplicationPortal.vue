<template>
  <div class="partner-portal-page">
    <PublicNavbar />

    <main class="portal-main">
      <!-- â”€â”€â”€â”€â”€ LIST VIEW â”€â”€â”€â”€â”€ -->
      <template v-if="!formOpen">
        <div class="flex-between mb-4">
          <div>
            <p class="portal-label">SportGo Partner</p>
            <h1 class="portal-title">ÄÄƒng kĂ½ Ä‘á»‘i tĂ¡c chá»§ sĂ¢n</h1>
            <p class="portal-subtitle" style="margin-bottom: 0;">Gá»­i há»“ sÆ¡, theo dĂµi tiáº¿n trĂ¬nh xĂ©t duyá»‡t vĂ  kĂ½ sá»‘ vÄƒn báº£n ngay trĂªn ná»n táº£ng.</p>
          </div>
          <button v-if="canRegister" type="button" class="btn btn-primary" @click="startNewApplication">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
            ÄÄƒng kĂ½ há»“ sÆ¡ má»›i
          </button>
        </div>

        <div class="stat-grid">
          <div class="stat-card">
            <p class="stat-label">Tá»•ng há»“ sÆ¡</p>
            <p class="stat-value">{{ applications.length }}</p>
            <p class="stat-label" style="color: var(--primary-color);">ÄĂ£ gá»­i</p>
          </div>
          <div class="stat-card">
            <p class="stat-label">Äang xĂ©t duyá»‡t</p>
            <p class="stat-value">{{ reviewingCount }}</p>
            <p class="stat-label" style="color: #b45309;">Chá» pháº£n há»“i</p>
          </div>
          <div class="stat-card">
            <p class="stat-label">Há»“ sÆ¡ nhĂ¡p</p>
            <p class="stat-value">{{ draft ? 1 : 0 }}</p>
            <p class="stat-label">ChÆ°a gá»­i</p>
          </div>
        </div>

        <div v-if="draft" class="draft-banner">
          <div>
            <p class="title">{{ draft.venue_name || 'ChÆ°a Ä‘áº·t tĂªn cá»¥m sĂ¢n' }} <span style="font-weight: 400; color: #b45309;">â€” Ä‘ang lÆ°u nhĂ¡p</span></p>
            <p style="font-size: 13px; color: #b45309; margin-top: 4px;">LÆ°u lĂºc {{ formatDate(draft.saved_at) }}</p>
          </div>
          <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn-secondary" style="background: transparent; border-color: #f59e0b; color: #b45309;" @click="clearDraft">XĂ³a nhĂ¡p</button>
            <button type="button" class="btn btn-primary" style="background: #f59e0b; color: white; border-color: #f59e0b;" @click="continueDraft">Tiáº¿p tá»¥c Ä‘iá»n</button>
          </div>
        </div>

        <div class="flex-between mb-4">
          <p style="font-size: 14px; color: var(--text-muted);">{{ applications.length }} há»“ sÆ¡</p>
          <button type="button" class="btn btn-outline" @click="loadApplications">LĂ m má»›i</button>
        </div>

        <div v-if="loading" style="text-align: center; padding: 60px;">
          <p class="portal-subtitle">Äang táº£i há»“ sÆ¡...</p>
        </div>

        <div v-else-if="applications.length === 0 && !draft" class="portal-card" style="text-align: center; padding: 60px 20px;">
          <svg style="margin: 0 auto; height: 48px; color: #cbd5e1;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <h3 style="margin-top: 16px; font-weight: 600; font-size: 16px;">ChÆ°a cĂ³ há»“ sÆ¡ nĂ o</h3>
          <p style="margin-top: 8px; color: var(--text-muted); font-size: 14px;">Báº¯t Ä‘áº§u báº±ng cĂ¡ch táº¡o há»“ sÆ¡ Ä‘Äƒng kĂ½ Ä‘áº§u tiĂªn cá»§a báº¡n.</p>
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
                {{ application.venue_address }} â€¢ Gá»­i {{ formatDate(application.submitted_at) }}
              </p>

              <div v-if="application.status === 'rejected'" style="background: #fef2f2; border: 1px solid #fecaca; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 12px; display: inline-block;">
                <strong style="color: #991b1b;">LĂ½ do tá»« chá»‘i:</strong> <span style="color: #b91c1c;">{{ application.status_reason || 'SportGo chÆ°a cung cáº¥p lĂ½ do chi tiáº¿t.' }}</span>
              </div>
              <div v-if="application.status === 'need_supplement'" style="background: #fffbeb; border: 1px solid #fde68a; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 12px; display: inline-block;">
                <strong style="color: #92400e;">Cáº§n bá»• sung há»“ sÆ¡:</strong> <span style="color: #b45309;">{{ application.status_reason || 'Vui lĂ²ng liĂªn há»‡ SportGo Ä‘á»ƒ biáº¿t thĂªm chi tiáº¿t.' }}</span>
              </div>
              <div v-if="application.status === 'contract_pending_owner_signature'" style="background: #ecfdf5; border: 1px solid #a7f3d0; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 12px; display: inline-block;">
                <strong style="color: #065f46;">đŸ‰ Há»“ sÆ¡ Ä‘Ă£ Ä‘Æ°á»£c duyá»‡t!</strong> <span style="color: #047857;">Há»£p Ä‘á»“ng há»£p tĂ¡c Ä‘Ă£ sáºµn sĂ ng. Vui lĂ²ng xem vĂ  kĂ½ há»£p Ä‘á»“ng Ä‘á»ƒ hoĂ n táº¥t quĂ¡ trĂ¬nh Ä‘Äƒng kĂ½.</span>
              </div>
            </div>

            <div class="app-list-actions">
              <button type="button" class="btn btn-secondary action-detail icon-only" title="Xem chi tiáº¿t" @click="openApplicationDetail(application)">
                <AppIcon name="eye" size="16" />
              </button>
              <button v-if="needsApplicationSignature(application)" type="button" class="btn btn-secondary action-document icon-only" title="KĂ½ Ä‘Æ¡n Ä‘Äƒng kĂ½" @click="openApplicationDocument(applicationWord(application), application)">
                <AppIcon name="edit" size="16" />
              </button>
              <button v-if="needsContractSignature(application)" type="button" class="btn btn-primary icon-only" title="KĂ½ Há»£p Ä‘á»“ng" @click="openApplicationDocument(contractWord(application), application)">
                <AppIcon name="fileText" size="16" />
              </button>
              <button v-if="canSubmitSignedApplication(application)" type="button" class="btn btn-primary action-submit icon-only" title="Gá»­i há»“ sÆ¡" @click="submitSignedApplication(application)">
                <AppIcon name="send" size="16" />
              </button>
              <button v-if="canCancel(application)" type="button" class="btn btn-outline action-cancel icon-only" title="Há»§y há»“ sÆ¡" @click="cancelApplication(application)">
                <AppIcon name="trash" size="16" />
              </button>
            </div>
          </article>
        </div>
      </template>

      <!-- â”€â”€â”€â”€â”€ FORM VIEW WIZARD â”€â”€â”€â”€â”€ -->
      <template v-else>
        <div class="mb-4">
          <BackButton @click="formOpen = false" title="Quay láº¡i danh sĂ¡ch" />
        </div>

        <div class="wizard-container">
          <!-- Header removed for single form layout -->

          <form novalidate @submit.prevent="submit" style="display: flex; flex-direction: column; flex: 1;">
            
            <div class="wizard-body">
              <div v-if="formBanner" class="notice error mb-4" style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px; border-radius: 8px;">
                {{ formBanner }}
              </div>

              <!-- STEP 1: CĂ¡ nhĂ¢n -->
              <div class="step-content">
                <FormSection title="ThĂ´ng tin ngÆ°á»i Ä‘Äƒng kĂ½ / Ä‘áº¡i diá»‡n">
                  <div class="form-grid">
                    <FormField label="Há» tĂªn ngÆ°á»i Ä‘Äƒng kĂ½" required :error="fieldErrors.applicant_full_name">
                      <input v-model.trim="form.applicant_full_name" :class="inputClass(fieldErrors.applicant_full_name)" />
                    </FormField>
                    <FormField label="Sá»‘ Ä‘iá»‡n thoáº¡i" required :error="fieldErrors.applicant_phone">
                      <input v-model.trim="form.applicant_phone" :class="inputClass(fieldErrors.applicant_phone)" inputmode="tel" @input="sanitizePhoneCharacters('applicant_phone')" />
                    </FormField>
                    <FormField label="Email" required :error="fieldErrors.applicant_email">
                      <input v-model.trim="form.applicant_email" :class="inputClass(fieldErrors.applicant_email)" type="email" />
                    </FormField>
                    <FormField label="NgĂ y sinh" required :error="fieldErrors.applicant_birth_date">
                      <input v-model="form.applicant_birth_date" :class="inputClass(fieldErrors.applicant_birth_date)" type="date" />
                    </FormField>
                    <FormField label="Loáº¡i chá»§ thá»ƒ" required :error="fieldErrors.applicant_type">
                      <BaseCombobox v-model="form.applicant_type" :options="applicantTypeOptions" placeholder="Chá»n loáº¡i chá»§ thá»ƒ" :invalid="Boolean(fieldErrors.applicant_type)" />
                    </FormField>
                    <FormField label="NgÆ°á»i Ä‘áº¡i diá»‡n phĂ¡p luáº­t" required :error="fieldErrors.representative_name">
                      <input v-model.trim="form.representative_name" :class="inputClass(fieldErrors.representative_name)" />
                    </FormField>
                    <FormField label="Loáº¡i giáº¥y tá» Ä‘áº¡i diá»‡n" required :error="fieldErrors.representative_identity_type">
                      <BaseCombobox v-model="form.representative_identity_type" :options="identityTypeOptions" placeholder="Chá»n loáº¡i giáº¥y tá»" :invalid="Boolean(fieldErrors.representative_identity_type)" @update:model-value="normalizeIdentityNumber" />
                    </FormField>
                    <FormField label="Sá»‘ CCCD/CMND/Há»™ chiáº¿u" required :error="fieldErrors.representative_identity_number">
                      <input v-model.trim="form.representative_identity_number" :class="inputClass(fieldErrors.representative_identity_number)" @input="normalizeIdentityNumber" />
                    </FormField>
                    <FormField label="NgĂ y cáº¥p" :error="fieldErrors.representative_identity_issued_date">
                      <input v-model="form.representative_identity_issued_date" :class="inputClass(fieldErrors.representative_identity_issued_date)" type="date" />
                    </FormField>
                    <FormField label="NÆ¡i cáº¥p" :error="fieldErrors.representative_identity_issued_place">
                      <input v-model.trim="form.representative_identity_issued_place" :class="inputClass(fieldErrors.representative_identity_issued_place)" />
                    </FormField>
                  </div>
                </FormSection>
              </div>

              <!-- STEP 2: Kinh doanh -->
              <div class="step-content" style="margin-top: 40px;">
                <FormSection title="ThĂ´ng tin kinh doanh">
                  <div class="form-grid">
                    <FormField label="TĂªn Ä‘Æ¡n vá»‹ / CĂ¡ nhĂ¢n kinh doanh" required :error="fieldErrors.business_name">
                      <input v-model.trim="form.business_name" :class="inputClass(fieldErrors.business_name)" />
                    </FormField>
                    <FormField label="MĂ£ sá»‘ thuáº¿" :error="fieldErrors.tax_code">
                      <input v-model.trim="form.tax_code" :class="inputClass(fieldErrors.tax_code)" inputmode="numeric" @input="normalizeTaxCode" />
                    </FormField>
                    <FormField label="Sá»‘ giáº¥y Ä‘Äƒng kĂ½ kinh doanh/phĂ¡p lĂ½" required :error="fieldErrors.business_license_number">
                      <input v-model.trim="form.business_license_number" :class="inputClass(fieldErrors.business_license_number)" />
                    </FormField>
                    <FormField label="MĂ£ doanh nghiá»‡p/há»™ kinh doanh (náº¿u cĂ³)" :error="fieldErrors.business_code">
                      <input v-model.trim="form.business_code" :class="inputClass(fieldErrors.business_code)" />
                    </FormField>
                    <FormField class="full-width" label="Äá»‹a chá»‰ liĂªn há»‡" required :error="fieldErrors.applicant_address">
                      <textarea v-model.trim="form.applicant_address" :class="textareaClass(fieldErrors.applicant_address)" rows="2"></textarea>
                    </FormField>
                    <FormField class="full-width" label="Äá»‹a chá»‰ phĂ¡p lĂ½ (trĂªn giáº¥y tá»)" required :error="fieldErrors.business_address">
                      <textarea v-model.trim="form.business_address" :class="textareaClass(fieldErrors.business_address)" rows="2"></textarea>
                    </FormField>
                  </div>
                </FormSection>
              </div>

              <!-- STEP 3: Cá»¥m sĂ¢n -->
              <div class="step-content" style="margin-top: 40px;">
                <FormSection title="Äá»‹a chá»‰ vĂ  thĂ´ng tin Cá»¥m sĂ¢n">
                  <div class="form-grid">
                    <FormField label="Tá»‰nh/ThĂ nh phá»‘" required :error="fieldErrors.venue_province_code">
                      <BaseCombobox v-model="form.venue_province_code" :options="provinceOptions" placeholder="TĂ¬m Tá»‰nh/ThĂ nh phá»‘" :invalid="Boolean(fieldErrors.venue_province_code)" @select="onProvinceSelect" />
                    </FormField>
                    <FormField label="PhÆ°á»ng/XĂ£" required :error="fieldErrors.venue_ward_code">
                      <BaseCombobox v-model="form.venue_ward_code" :options="wardOptions" placeholder="TĂ¬m PhÆ°á»ng/XĂ£" :disabled="!form.venue_province_code" :invalid="Boolean(fieldErrors.venue_ward_code)" @select="syncVenueAddress" />
                    </FormField>
                    <FormField class="full-width" label="Sá»‘ nhĂ , tĂªn Ä‘Æ°á»ng" required :error="fieldErrors.street_address">
                      <input v-model.trim="form.street_address" :class="inputClass(fieldErrors.street_address)" placeholder="VĂ­ dá»¥: 123 Nguyá»…n Há»¯u Cáº£nh" @input="syncVenueAddress" />
                    </FormField>
                    <FormField class="full-width" label="Link Google Maps (Báº¯t buá»™c Ä‘á»ƒ láº¥y tá»a Ä‘á»™)" required :error="mapError || fieldErrors.venue_map_url">
                      <input v-model.trim="form.venue_map_url" :class="inputClass(mapError || fieldErrors.venue_map_url)" placeholder="DĂ¡n link Google Maps cĂ³ tá»a Ä‘á»™" @input="onMapUrlInput" />
                      <div v-if="mapSuggestion" style="margin-top: 8px; background: #fffbeb; border: 1px solid #fde68a; padding: 12px; border-radius: 8px;">
                        <p style="font-size: 13px; color: #92400e; margin-bottom: 8px;">{{ mapSuggestion.message }}</p>
                        <button v-if="mapSuggestion.province_code || mapSuggestion.ward_code" type="button" class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px;" @click="applyMapSuggestion">Cáº­p nháº­t theo Google Maps</button>
                      </div>
                      <p v-else-if="mapStatus" style="margin-top: 4px; font-size: 13px; color: #059669;">{{ mapStatus }}</p>
                    </FormField>
                    <FormField class="full-width" label="Chá»n vá»‹ trĂ­ trĂªn báº£n Ä‘á»“" required :error="fieldErrors.venue_coordinates">
                      <div class="map-picker-shell">
                        <div id="partner-application-map" class="map-picker"></div>
                        <div class="map-coordinate-grid">
                          <label :class="{ invalid: fieldErrors.venue_latitude }">
                            <span>VÄ© Ä‘á»™</span>
                            <input v-model.trim="form.venue_latitude" :class="inputClass(fieldErrors.venue_latitude)" inputmode="decimal" @input="sanitizeCoordinate('venue_latitude')" />
                          </label>
                          <label :class="{ invalid: fieldErrors.venue_longitude }">
                            <span>Kinh Ä‘á»™</span>
                            <input v-model.trim="form.venue_longitude" :class="inputClass(fieldErrors.venue_longitude)" inputmode="decimal" @input="sanitizeCoordinate('venue_longitude')" />
                          </label>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" style="margin-top: 8px; margin-bottom: 8px;" @click="getCurrentLocation">
                          đŸ“ Láº¥y vá»‹ trĂ­ hiá»‡n táº¡i
                        </button>
                        <p class="map-help">Click trĂªn báº£n Ä‘á»“ hoáº·c kĂ©o marker Ä‘á»ƒ chá»n tá»a Ä‘á»™ cá»¥m sĂ¢n. Link Google Maps náº¿u cĂ³ tá»a Ä‘á»™ sáº½ tá»± Ä‘áº·t marker.</p>
                      </div>
                    </FormField>
                    <input type="hidden" :value="form.venue_latitude" name="venue_latitude" />
                    <input type="hidden" :value="form.venue_longitude" name="venue_longitude" />
                    
                    <FormField label="TĂªn cá»¥m sĂ¢n" required :error="fieldErrors.venue_name">
                      <input v-model.trim="form.venue_name" :class="inputClass(fieldErrors.venue_name)" />
                    </FormField>
                    <FormField label="Sá»‘ Ä‘iá»‡n thoáº¡i táº¡i sĂ¢n" required :error="fieldErrors.venue_phone">
                      <input v-model.trim="form.venue_phone" :class="inputClass(fieldErrors.venue_phone)" inputmode="tel" @input="sanitizePhoneCharacters('venue_phone')" />
                    </FormField>
                    <FormField label="Giá» má»Ÿ cá»­a dá»± kiáº¿n" :error="fieldErrors.expected_opening_hours">
                      <input v-model.trim="form.expected_opening_hours" :class="inputClass(fieldErrors.expected_opening_hours)" placeholder="05:00 - 23:00" />
                    </FormField>
                    <FormField label="Email táº¡i sĂ¢n" :error="fieldErrors.venue_email">
                      <input v-model.trim="form.venue_email" :class="inputClass(fieldErrors.venue_email)" type="email" />
                    </FormField>
                  </div>
                </FormSection>

                <FormSection title="Cáº¥u hĂ¬nh sĂ¢n con" style="margin-top: 24px;">
                  <div class="form-grid">
                    <FormField label="Sá»‘ lÆ°á»£ng sĂ¢n con" required :error="fieldErrors.court_count_total">
                      <input v-model.trim="form.court_count_total" :class="inputClass(fieldErrors.court_count_total)" inputmode="numeric" @input="onCourtCountInput" />
                    </FormField>
                    <FormField label="GiĂ¡ cÆ¡ báº£n/giá» (VNÄ)" required :error="fieldErrors.base_price_per_hour">
                      <input v-model.trim="form.base_price_per_hour" :class="inputClass(fieldErrors.base_price_per_hour)" inputmode="numeric" @input="sanitizeDigitsField('base_price_per_hour')" />
                    </FormField>
                  </div>

                  <div style="margin-top: 16px; display: flex; flex-direction: column; gap: 12px;">
                    <div
                      v-for="(court, index) in form.courts"
                      :key="court.local_id"
                      style="display: grid; gap: 12px; background: #f8fafc; border: 1px solid var(--border-color); padding: 16px; border-radius: 12px; grid-template-columns: 1fr 1fr auto; align-items: end;"
                    >
                      <FormField :label="'TĂªn sĂ¢n ' + (index + 1)" required :error="fieldErrors['courts.' + index + '.name']">
                        <input v-model.trim="court.name" :class="inputClass(fieldErrors['courts.' + index + '.name'])" />
                      </FormField>
                      <FormField label="Loáº¡i sĂ¢n" required :error="fieldErrors['courts.' + index + '.court_type_id']">
                        <BaseCombobox v-model="court.court_type_id" :options="courtTypeOptions" placeholder="Chá»n loáº¡i sĂ¢n" :invalid="Boolean(fieldErrors['courts.' + index + '.court_type_id'])" />
                      </FormField>
                      <button
                        type="button"
                        class="btn btn-outline"
                        style="color: #ef4444; border-color: #fecaca; background: white;"
                        :disabled="form.courts.length <= 1"
                        @click="removeCourt(index)"
                      >
                        XĂ³a
                      </button>
                    </div>
                  </div>
                  
                  <div v-if="amenities.length" style="margin-top: 20px;">
                    <p class="form-label" style="margin-bottom: 8px;">Tiá»‡n Ă­ch cĂ³ sáºµn</p>
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

              <!-- STEP 4: TĂ i liá»‡u & NgĂ¢n hĂ ng -->
              <div class="step-content" style="margin-top: 40px;">
                <FormSection title="ThĂ´ng tin ngĂ¢n hĂ ng">
                  <div class="form-grid">
                    <FormField label="NgĂ¢n hĂ ng" required :error="fieldErrors.bank_code">
                      <BaseCombobox v-model="form.bank_code" :options="bankOptions" placeholder="TĂ¬m ngĂ¢n hĂ ng" :invalid="Boolean(fieldErrors.bank_code)" @select="selectBank" />
                    </FormField>
                    <FormField label="Sá»‘ tĂ i khoáº£n" required :error="fieldErrors.account_number">
                      <input v-model.trim="form.account_number" :class="inputClass(fieldErrors.account_number)" inputmode="numeric" @input="onAccountNumberInput" />
                    </FormField>
                    <FormField label="TĂªn chá»§ tĂ i khoáº£n" required :error="fieldErrors.account_holder_name">
                      <input
                        v-model.trim="form.account_holder_name"
                        :class="inputClass(fieldErrors.account_holder_name)"
                        placeholder="Viáº¿t IN HOA khĂ´ng dáº¥u"
                        @input="onManualBankHolderInput()"
                      />
                    </FormField>
                    <FormField label="Chi nhĂ¡nh" :error="fieldErrors.bank_branch">
                      <input v-model.trim="form.bank_branch" :class="inputClass(fieldErrors.bank_branch)" />
                    </FormField>
                  </div>
                </FormSection>

                <FormSection title="TĂ i liá»‡u Ä‘Ă­nh kĂ¨m" style="margin-top: 24px;">
                  <div class="form-grid">
                    <UploadBox title="CCCD/CMND ngÆ°á»i Ä‘áº¡i diá»‡n" required :files="files.identity" :error="fieldErrors.identity_documents" @change="setFiles('identity', $event)" @remove="removeFile('identity', $event)" />
                    <UploadBox title="Giáº¥y ÄKKD/PhĂ¡p lĂ½" required :files="files.business_license" :error="fieldErrors.business_license_documents" @change="setFiles('business_license', $event)" @remove="removeFile('business_license', $event)" />
                    <UploadBox title="HĂ¬nh áº£nh cÆ¡ sá»Ÿ/sĂ¢n" required :files="files.facility" :error="fieldErrors.facility_images" @change="setFiles('facility', $event)" @remove="removeFile('facility', $event)" />
                    <UploadBox title="Chá»©ng tá»« ngĂ¢n hĂ ng" required :files="files.bank" :error="fieldErrors.bank_documents" @change="setFiles('bank', $event)" @remove="removeFile('bank', $event)" />
                    <UploadBox title="Há»£p Ä‘á»“ng thuĂª máº·t báº±ng" required :files="files.lease" :error="fieldErrors.lease_documents" @change="setFiles('lease', $event)" @remove="removeFile('lease', $event)" />
                    <UploadBox title="Giáº¥y tá» khĂ¡c" :files="files.additional" :error="fieldErrors.additional_documents" @change="setFiles('additional', $event)" @remove="removeFile('additional', $event)" />
                  </div>
                </FormSection>

                <div class="portal-card" style="background: #f8fafc; margin-top: 24px;" :class="fieldErrors.confirmed ? 'border-red-400' : ''">
                  <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer;">
                    <input v-model="confirmed" type="checkbox" style="margin-top: 4px; width: 18px; height: 18px; accent-color: var(--primary-color);" />
                    <span style="font-size: 14px; color: var(--text-main); line-height: 1.5;">
                      TĂ´i xĂ¡c nháº­n thĂ´ng tin trong há»“ sÆ¡ lĂ  chĂ­nh xĂ¡c vĂ  Ä‘á»“ng Ă½ Ä‘á»ƒ SportGo kiá»ƒm tra tĂ i liá»‡u trÆ°á»›c khi duyá»‡t Ä‘á»‘i tĂ¡c.
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
                <button type="button" class="btn btn-outline" @click="saveDraft">LÆ°u nhĂ¡p</button>
                <button type="submit" class="btn btn-primary" :disabled="submitDisabled">
                  <span v-if="submitting" style="margin-right: 8px; display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite;"></span>
                  {{ submitting ? 'Äang xá»­ lĂ½...' : 'Gá»­i há»“ sÆ¡ Ä‘Äƒng kĂ½' }}
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

// â”€â”€â”€ Constants â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

const DRAFT_KEY = 'sportgo_partner_application_draft_v3';
const BANK_CACHE_KEY = 'sportgo_partner_banks_v2';
const BANK_CACHE_TTL = 24 * 60 * 60 * 1000;

// â”€â”€â”€ Inline components â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

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

// â”€â”€â”€ State â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

// â”€â”€â”€ Static options â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const applicantTypeOptions = [
  { value: 'individual', label: 'CĂ¡ nhĂ¢n/há»™ kinh doanh' },
  { value: 'business', label: 'Há»™ kinh doanh cĂ³ giáº¥y phĂ©p' },
  { value: 'company', label: 'Doanh nghiá»‡p' },
];
const identityTypeOptions = [
  { value: 'cccd', label: 'CCCD' },
  { value: 'cmnd', label: 'CMND' },
  { value: 'passport', label: 'Há»™ chiáº¿u' },
];

const form = reactive(defaultForm(user));

// â”€â”€â”€ Computed â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const bankOptions = computed(() => banks.value.map((b) => ({ ...b, value: b.code, label: `${b.short_name || b.code} - ${b.name || b.code}` })));
const provinceOptions = computed(() => provinces.value.map((p) => ({ ...p, value: p.code, label: p.name })));
const wardOptions = computed(() => wards.value.map((w) => ({ ...w, value: w.code, label: w.name })));
const courtTypeOptions = computed(() => courtTypes.value.filter((t) => t.is_active !== false && Number(t.children_count || 0) === 0).map((t) => ({ ...t, value: t.id, label: t.name })));
const submitDisabled = computed(() => submitting.value);
const reviewingCount = computed(() => applications.value.filter((a) => ['pending', 'submitted', 'reviewing'].includes(a.status)).length);

// â”€â”€â”€ Lifecycle â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

// â”€â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function defaultForm(authUser) {
  return {
    applicant_full_name: authUser?.fullName || '', applicant_phone: authUser?.phone || '',
    applicant_email: authUser?.email || '', applicant_birth_date: '', applicant_address: '',
    applicant_type: 'individual', representative_name: authUser?.fullName || '',
    representative_identity_type: 'cccd', representative_identity_number: '',
    representative_identity_issued_date: '', representative_identity_issued_place: '',
    representative_position: 'Chá»§ cÆ¡ sá»Ÿ', business_name: '', tax_code: '', business_code: '',
    business_license_number: '', business_address: '', venue_name: '', street_address: '',
    venue_address: '', venue_province_code: '', venue_ward_code: '', venue_map_url: '',
    venue_latitude: '', venue_longitude: '', venue_phone: authUser?.phone || '',
    venue_email: authUser?.email || '', venue_description: '', expected_opening_hours: '05:00 - 23:00',
    parking_info: '', amenities: [], court_count_total: 1, base_price_per_hour: '',
    courts: [{ local_id: localId(), name: 'SĂ¢n 1', court_type_id: '', note: '' }],
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
// â”€â”€â”€ Data loaders â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
async function loadApplications() {
  loading.value = true;
  try { const r = await api('/api/user/partner-application'); applications.value = r.data?.history || []; canRegister.value = Boolean(r.data?.can_register); } finally { loading.value = false; }
}
async function loadBanks() {
  const cached = readCache(BANK_CACHE_KEY);
  if (cached?.length) { banks.value = cached; return; }
  try { const r = await api('/api/user/partner-application/banks'); banks.value = normalizeList(r.data); if (banks.value.length) writeCache(BANK_CACHE_KEY, banks.value, BANK_CACHE_TTL); } catch (e) { console.error('Lá»—i táº£i ngĂ¢n hĂ ng:', e); }
}
async function loadProvinces() { const r = await api('/api/user/partner-application/provinces'); provinces.value = normalizeList(r.data); }
async function loadWards(code) { if (!code) return; const r = await api(`/api/user/partner-application/provinces/${code}/wards`); wards.value = normalizeList(r.data); }
async function loadCourtTypes() { const r = await api('/api/court-types'); courtTypes.value = normalizeList(r.data); }
async function loadAmenities() { const r = await api('/api/amenities?active_only=1'); amenities.value = normalizeList(r.data); }

// â”€â”€â”€ Form lifecycle â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
  if (showMessage) formBanner.value = 'Đã lưu nháp hồ sơ trên trình duyệt.';
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
  if (application?.status === 'draft') {
    editingApplicationId.value = id;
    loadApplicationIntoForm(application);
    formOpen.value = true;
    if (form.venue_province_code) await loadWards(form.venue_province_code);
    syncVenueAddress();
    formBanner.value = 'Bạn đang sửa bản nháp. Bấm gửi để hệ thống tạo lại đơn đăng ký mới.';
    return;
  }

  if (draft.value) {
    editingApplicationId.value = id;
    await continueDraft();
  }
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
    name: court.name || `Sân ${index + 1}`,
    court_type_id: court.court_type_id || '',
    note: court.note || '',
  }));

  return rows.length ? rows : [{ local_id: localId(), name: 'Sân 1', court_type_id: '', note: '' }];
}

// â”€â”€â”€ Input handlers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

// â”€â”€â”€ Bank verification â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
  form.account_holder_name = String(form.account_holder_name || '').toUpperCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/Ä‘/g, "d").replace(/Ä/g, "D");
}

// â”€â”€â”€ Address / Map â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
    mapStatus.value = 'ÄĂ£ cáº­p nháº­t Ä‘á»‹a chá»‰ theo tá»a Ä‘á»™ báº£n Ä‘á»“.';
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
        alert('KhĂ´ng thá»ƒ láº¥y Ä‘Æ°á»£c vá»‹ trĂ­. Vui lĂ²ng kiá»ƒm tra quyá»n truy cáº­p vá»‹ trĂ­ cá»§a trĂ¬nh duyá»‡t.');
      }
    );
  } else {
    alert('TrĂ¬nh duyá»‡t cá»§a báº¡n khĂ´ng há»— trá»£ tĂ­nh nÄƒng Ä‘á»‹nh vá»‹.');
  }
}

function applyPickedCoordinates(point) {
  const lat = Number(point.lat).toFixed(7);
  const lng = Number(point.lng).toFixed(7);
  form.venue_latitude = lat;
  form.venue_longitude = lng;
  form.venue_map_url = googleMapsPointUrl(lat, lng);
  mapStatus.value = 'ÄĂ£ chá»n tá»a Ä‘á»™ trĂªn báº£n Ä‘á»“, Ä‘ang cáº­p nháº­t Ä‘á»‹a chá»‰...';
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
  mapError.value = ''; mapStatus.value = 'Äang xá»­ lĂ½ link...';
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
  } catch (e) { console.error('Lá»—i phĂ¢n giáº£i map:', e); }
  const coords = extractCoordinates(urlToResolve);
  if (!coords && !form.venue_latitude) { mapStatus.value = ''; mapError.value = 'KhĂ´ng láº¥y Ä‘Æ°á»£c tá»a Ä‘á»™ tá»« link Google Maps nĂ y. Vui lĂ²ng dĂ¹ng link Ä‘áº§y Ä‘á»§ cĂ³ tá»a Ä‘á»™.'; return; }
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
    mapSuggestion.value = { province_code: '', ward_code: '', message: 'Không xác minh được Tỉnh/Thành phố và Phường/Xã từ tọa độ này. Vui lòng chọn lại vị trí rõ hơn trên bản đồ.' };
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
    mapSuggestion.value = { province_code: rp, ward_code: rw, message: 'Không xác định được Tỉnh/Thành phố và Phường/Xã từ tọa độ này. Vui lòng chọn lại vị trí rõ hơn trên bản đồ.' };
    return;
  }
  
  if ((options.applyLocation || !form.venue_province_code) && rp) {
    form.venue_province_code = rp;
    await loadWards(rp);
    if (rw) form.venue_ward_code = rw;
    syncVenueAddress();
    mapStatus.value = 'ÄĂ£ cáº­p nháº­t Ä‘á»‹a chá»‰ theo tá»a Ä‘á»™ trĂªn báº£n Ä‘á»“.';
    return;
  }
  if (!pc && !wc) {
    syncVenueAddress();
    if (!form.venue_province_code) mapStatus.value = 'ÄĂ£ láº¥y tá»a Ä‘á»™ vĂ  Ä‘á»‹a chá»‰ Ä‘Æ°á»ng. Vui lĂ²ng chá»n Tá»‰nh/ThĂ nh phá»‘.';
    else mapStatus.value = 'Vá»‹ trĂ­ báº£n Ä‘á»“ khá»›p vá»›i Ä‘á»‹a chá»‰ Ä‘Ă£ chá»n.';
    return;
  }
  const cur = [wards.value.find((w) => String(w.code) === String(form.venue_ward_code))?.name, provinces.value.find((p) => String(p.code) === String(form.venue_province_code))?.name].filter(Boolean).join(', ') || 'chÆ°a chá»n';
  const res = [resolved.ward, resolved.province].filter(Boolean).join(', ') || resolved.address || 'vá»‹ trĂ­ Google Maps';
  mapSuggestion.value = { province_code: rp, ward_code: rw, message: `Vá»‹ trĂ­ trĂªn Google Maps thuá»™c ${res} â€” khĂ¡c vá»›i Ä‘á»‹a chá»‰ báº¡n Ä‘Ă£ chá»n (${cur}).` };
}

async function applyMapSuggestion() {
  if (!mapSuggestion.value) return;
  if (mapSuggestion.value.province_code) { form.venue_province_code = mapSuggestion.value.province_code; await loadWards(form.venue_province_code); }
  if (mapSuggestion.value.ward_code) form.venue_ward_code = mapSuggestion.value.ward_code;
  mapSuggestion.value = null; mapStatus.value = 'ÄĂ£ cáº­p nháº­t Ä‘á»‹a chá»‰ theo Google Maps.'; syncVenueAddress();
}

// â”€â”€â”€ Courts â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function syncCourtRows() {
  const total = Math.max(1, Number(form.court_count_total || 1));
  while (form.courts.length < total) form.courts.push({ local_id: localId(), name: `SĂ¢n ${form.courts.length + 1}`, court_type_id: form.courts[0]?.court_type_id || '', note: '' });
  if (form.courts.length > total) form.courts.splice(total);
}
function removeCourt(index) { if (form.courts.length <= 1) return; form.courts.splice(index, 1); form.court_count_total = form.courts.length; }

// â”€â”€â”€ Files â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function setFiles(group, event) { files[group] = Array.from(event.target.files || []); }
function removeFile(group, index) { files[group].splice(index, 1); }
function hasDocumentForGroup(group) {
  return files[group]?.length > 0 || existingDocumentTypes.value.has(group);
}

// â”€â”€â”€ Validation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function validateForm() {
  clearErrors();
  const required = {
    applicant_full_name: 'Vui lĂ²ng nháº­p há» tĂªn ngÆ°á»i Ä‘Äƒng kĂ½.',
    applicant_phone: 'Vui lĂ²ng nháº­p sá»‘ Ä‘iá»‡n thoáº¡i.',
    applicant_email: 'Vui lĂ²ng nháº­p email.',
    applicant_birth_date: 'Vui lĂ²ng nháº­p ngĂ y sinh.',
    applicant_address: 'Vui lĂ²ng nháº­p Ä‘á»‹a chá»‰ liĂªn há»‡.',
    representative_name: 'Vui lĂ²ng nháº­p ngÆ°á»i Ä‘áº¡i diá»‡n.',
    representative_identity_number: 'Vui lĂ²ng nháº­p sá»‘ giáº¥y tá».',
    business_name: 'Vui lĂ²ng nháº­p tĂªn Ä‘Æ¡n vá»‹ kinh doanh.',
    business_license_number: 'Vui lĂ²ng nháº­p sá»‘ giáº¥y Ä‘Äƒng kĂ½.',
    business_address: 'Vui lĂ²ng nháº­p Ä‘á»‹a chá»‰ phĂ¡p lĂ½.',
    bank_code: 'Vui lĂ²ng chá»n ngĂ¢n hĂ ng.',
    account_number: 'Vui lĂ²ng nháº­p sá»‘ tĂ i khoáº£n.',
    street_address: 'Vui lĂ²ng nháº­p sá»‘ nhĂ , tĂªn Ä‘Æ°á»ng.',
    venue_map_url: 'Vui lĂ²ng nháº­p link Google Maps.',
    venue_province_code: 'Vui lĂ²ng chá»n Tá»‰nh/ThĂ nh phá»‘.',
    venue_ward_code: 'Vui lĂ²ng chá»n PhÆ°á»ng/XĂ£.',
    venue_name: 'Vui lĂ²ng nháº­p tĂªn cá»¥m sĂ¢n.',
    venue_phone: 'Vui lĂ²ng nháº­p sá»‘ Ä‘iá»‡n thoáº¡i táº¡i sĂ¢n.',
    court_count_total: 'Vui lĂ²ng nháº­p sá»‘ lÆ°á»£ng sĂ¢n con.',
    base_price_per_hour: 'Vui lĂ²ng nháº­p giĂ¡ cÆ¡ báº£n.',
  };
  Object.entries(required).forEach(([f, m]) => { if (!form[f]) fieldErrors[f] = m; });
  if (form.applicant_birth_date && new Date(form.applicant_birth_date) > new Date(new Date().setFullYear(new Date().getFullYear() - 18))) fieldErrors.applicant_birth_date = 'NgÆ°á»i Ä‘Äƒng kĂ½ pháº£i Ä‘á»§ 18 tuá»•i.';
  if (form.applicant_phone && !/^(0\d{9}|\+84\d{9})$/.test(form.applicant_phone)) fieldErrors.applicant_phone = 'Sá»‘ Ä‘iá»‡n thoáº¡i pháº£i cĂ³ 10 sá»‘ vĂ  báº¯t Ä‘áº§u báº±ng 0 hoáº·c +84.';
  if (form.venue_phone && !/^(0\d{9}|\+84\d{9})$/.test(form.venue_phone)) fieldErrors.venue_phone = 'Sá»‘ Ä‘iá»‡n thoáº¡i sĂ¢n pháº£i cĂ³ 10 sá»‘ vĂ  báº¯t Ä‘áº§u báº±ng 0 hoáº·c +84.';
  if (form.applicant_email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.applicant_email)) fieldErrors.applicant_email = 'Email khĂ´ng Ä‘Ăºng Ä‘á»‹nh dáº¡ng.';
  if (form.venue_email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.venue_email)) fieldErrors.venue_email = 'Email sĂ¢n khĂ´ng Ä‘Ăºng Ä‘á»‹nh dáº¡ng.';
  if (form.tax_code && !/^\d{10}(-?\d{3})?$/.test(form.tax_code)) fieldErrors.tax_code = 'MĂ£ sá»‘ thuáº¿ pháº£i gá»“m 10 sá»‘ hoáº·c 13 sá»‘, cĂ³ thá»ƒ cĂ³ dáº¥u gáº¡ch ngang sau 10 sá»‘.';
  if (form.account_number && !/^\d+$/.test(form.account_number)) fieldErrors.account_number = 'Sá»‘ tĂ i khoáº£n chá»‰ Ä‘Æ°á»£c nháº­p chá»¯ sá»‘.';
  if (!isValidIdentity()) fieldErrors.representative_identity_number = 'Sá»‘ giáº¥y tá» khĂ´ng Ä‘Ăºng Ä‘á»‹nh dáº¡ng Ä‘Ă£ chá»n.';
  if (!validLatitude(form.venue_latitude) || !validLongitude(form.venue_longitude)) {
    fieldErrors.venue_map_url = 'Vui lĂ²ng dĂ¹ng link Google Maps cĂ³ tá»a Ä‘á»™ há»£p lá»‡ hoáº·c chá»n vá»‹ trĂ­ trĂªn báº£n Ä‘á»“.';
    fieldErrors.venue_coordinates = 'Vui lĂ²ng chá»n vá»‹ trĂ­ há»£p lá»‡ trĂªn báº£n Ä‘á»“.';
    if (!validLatitude(form.venue_latitude)) fieldErrors.venue_latitude = 'VÄ© Ä‘á»™ pháº£i tá»« -90 Ä‘áº¿n 90.';
    if (!validLongitude(form.venue_longitude)) fieldErrors.venue_longitude = 'Kinh Ä‘á»™ pháº£i tá»« -180 Ä‘áº¿n 180.';
  }
  if (mapSuggestion.value) {
    fieldErrors.venue_ward_code = 'PhÆ°á»ng/XĂ£ Ä‘ang chá»n chÆ°a khá»›p vá»›i tá»a Ä‘á»™ báº£n Ä‘á»“. Vui lĂ²ng báº¥m â€œCáº­p nháº­t theo Google Mapsâ€ hoáº·c chá»n láº¡i vá»‹ trĂ­.';
    fieldErrors.venue_coordinates = 'Tá»a Ä‘á»™ báº£n Ä‘á»“ chÆ°a khá»›p vá»›i Ä‘á»‹a chá»‰ Ä‘Ă£ chá»n.';
  }
  if (mapReverseBusy.value) {
    fieldErrors.venue_coordinates = 'Hệ thống đang cập nhật địa chỉ theo tọa độ. Vui lòng chờ hoàn tất rồi gửi lại.';
  }
  const courtCount = Number(form.court_count_total);
  if (!Number.isInteger(courtCount) || courtCount < 1 || courtCount > 100) fieldErrors.court_count_total = 'Sá»‘ lÆ°á»£ng sĂ¢n con pháº£i tá»« 1 Ä‘áº¿n 100.';
  const basePrice = Number(form.base_price_per_hour);
  if (!Number.isFinite(basePrice) || basePrice < 1000) fieldErrors.base_price_per_hour = 'GiĂ¡ cÆ¡ báº£n pháº£i tá»« 1.000 VNÄ trá»Ÿ lĂªn.';
  // if (!bankVerified.value && !bankManualMode.value) fieldErrors.account_number = bankError.value || 'Vui lĂ²ng chá» xĂ¡c minh tĂ i khoáº£n ngĂ¢n hĂ ng thĂ nh cĂ´ng.';
  if (!form.account_holder_name) fieldErrors.account_holder_name = 'Vui lĂ²ng nháº­p tĂªn chá»§ tĂ i khoáº£n.';
  if (!hasDocumentForGroup('identity')) fieldErrors.identity_documents = 'Vui lĂ²ng táº£i lĂªn CCCD/CMND.';
  if (!hasDocumentForGroup('business_license')) fieldErrors.business_license_documents = 'Vui lĂ²ng táº£i lĂªn giáº¥y tá» phĂ¡p lĂ½.';
  if (!hasDocumentForGroup('facility')) fieldErrors.facility_images = 'Vui lĂ²ng táº£i lĂªn hĂ¬nh áº£nh cÆ¡ sá»Ÿ.';
  if (!hasDocumentForGroup('bank')) fieldErrors.bank_documents = 'Vui lĂ²ng táº£i lĂªn chá»©ng tá»« ngĂ¢n hĂ ng.';
  if (!hasDocumentForGroup('lease')) fieldErrors.lease_documents = 'Vui lĂ²ng táº£i lĂªn há»£p Ä‘á»“ng hoáº·c giáº¥y tá» thuĂª máº·t báº±ng.';
  if (!confirmed.value) fieldErrors.confirmed = 'Vui lĂ²ng xĂ¡c nháº­n thĂ´ng tin trÆ°á»›c khi gá»­i.';
  form.courts.forEach((c, i) => {
    if (!c.name) fieldErrors[`courts.${i}.name`] = 'Vui lĂ²ng nháº­p tĂªn sĂ¢n.';
    if (!c.court_type_id) fieldErrors[`courts.${i}.court_type_id`] = 'Vui lĂ²ng chá»n loáº¡i sĂ¢n.';
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

// â”€â”€â”€ Submit â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
      formBanner.value = e.message || 'Vui lĂ²ng kiá»ƒm tra láº¡i thĂ´ng tin há»“ sÆ¡.';
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  } finally { submitting.value = false; }
}

// â”€â”€â”€ Application actions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
async function cancelApplication(application) {
  if (!window.confirm(`Há»§y há»“ sÆ¡ Ä‘Äƒng kĂ½ cho ${application.venue_name}?`)) return;
  try {
    await api(`/api/user/partner-application/${application.id}/cancel`, { method: 'POST', body: JSON.stringify({ reason: 'NgÆ°á»i dĂ¹ng há»§y há»“ sÆ¡ tá»« trang Ä‘Äƒng kĂ½ Ä‘á»‘i tĂ¡c.' }) });
    alert('ÄĂ£ há»§y há»“ sÆ¡ thĂ nh cĂ´ng.');
    await loadApplications();
  } catch (err) {
    alert(err.message || 'KhĂ´ng thá»ƒ há»§y há»“ sÆ¡ lĂºc nĂ y.');
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

// â”€â”€â”€ Display helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
  return { draft: 'Chá» kĂ½ Ä‘Æ¡n', pending: 'Chá» xĂ©t duyá»‡t', submitted: 'Chá» xĂ©t duyá»‡t', reviewing: 'Äang xem xĂ©t', need_supplement: 'Cáº§n bá»• sung', contract_pending_owner_signature: 'ÄĂ£ duyá»‡t, chá» kĂ½ há»£p Ä‘á»“ng', contract_pending_sportgo_signature: 'Chá» SportGo kĂ½', completed: 'Äang hoáº¡t Ä‘á»™ng', rejected: 'Bá»‹ tá»« chá»‘i', cancelled: 'ÄĂ£ há»§y' }[status] || status || '-';
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
