<template>
    <div class="schedule-lock-master-workspace">
        <!-- Floating Lock Button (sticky bottom bar) -->
        <Teleport to="body">
            <div v-if="unlockMode || hasLockSelection" class="sticky-bottom-bar">
                <div class="sticky-bottom-inner">
                    <template v-if="unlockMode">
                        <div class="sticky-bottom-info">
                            <strong>
                                {{ selectedUnlockSlots.length }} ô khóa đã chọn
                            </strong>
                            <span>
                                Chỉ chọn được các ô đang khóa trong ngày
                                {{ date(form.start_date) }}
                            </span>
                        </div>
                        <div class="sticky-bottom-actions">
                            <button
                                type="button"
                                class="sticky-btn-clear sticky-btn-box"
                                @click="cancelUnlockMode"
                            >
                                Hủy
                            </button>
                            <button
                                type="button"
                                class="sticky-btn-all-unlock"
                                :disabled="unlocking || !unlockableSlots.length"
                                @click="selectAllUnlockableSlots"
                            >
                                Mở khóa toàn bộ
                            </button>
                            <button
                                type="button"
                                class="sticky-btn-unlock"
                                :disabled="
                                    unlocking || !selectedUnlockSlots.length
                                "
                                @click="unlockConfirmOpen = true"
                            >
                                Xác nhận mở khóa
                            </button>
                        </div>
                    </template>
                    <template v-else>
                        <div class="sticky-bottom-info">
                            <strong v-if="isFullDaySelection">
                                {{ selectedCourtIds.length }} sân khóa cả ngày
                            </strong>
                            <strong v-else>{{ selectedSlots.length }} ô đã chọn</strong>
                            <span v-if="isFullDaySelection">
                                Toàn bộ giờ hoạt động · {{ dateRangeLabel }}
                            </span>
                            <span v-else>{{ dateRangeLabel }}</span>
                        </div>
                        <div class="sticky-bottom-actions">
                            <button
                                type="button"
                                class="sticky-btn-clear"
                                @click="clearSelection"
                            >
                                Bỏ chọn
                            </button>
                            <button
                                type="button"
                                class="sticky-btn-submit"
                                :disabled="saving || previewing || !canSubmit"
                                @click="createLock"
                            >
                                {{
                                    saving
                                        ? "Đang khóa..."
                                        : previewing
                                          ? "Đang kiểm tra..."
                                          : lockButtonLabel
                                }}
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </Teleport>

        <!-- Main Master Content Surface (Exact match to ClusterGeneralInfoTab) -->
        <div class="cluster-profile-surface standalone">
            
            <!-- SECTION 1: Cấu hình ngày & Chọn sân khóa cả ngày -->
            <div class="profile-section-card">
                <div class="tab-section-header">
                    <div>
                        <h2>Khóa lịch sân & Cấu hình khoảng ngày</h2>
                        <p class="section-subtitle">
                            Chọn ngày, lý do khóa và chọn nhanh các sân cần khóa toàn bộ giờ hoạt động.
                        </p>
                    </div>
                    <span v-if="locks.length" class="count-pill">{{ locks.length }} khoảng đã khóa</span>
                </div>

                <div v-if="error" class="alert error">{{ error }}</div>
                <div v-if="notice" class="alert success">{{ notice }}</div>

        <!-- Conflict Preview Modal (unchanged logic) -->
        <div
            v-if="lockConflictPreview"
            class="modal-backdrop"
            @click.self="closeConflictPreview"
        >
            <section
                class="conflict-modal"
                :class="{ 'emergency-flow': !conflictPreviewOnly }"
            >
                <header>
                    <div>
                        <p class="eyebrow">
                            {{
                                conflictPreviewOnly
                                    ? "LỊCH TRÙNG TRONG PHẠM VI KHÓA"
                                    : "XỬ LÝ KHÓA ĐỘT XUẤT"
                            }}
                        </p>
                        <h3>
                            {{ lockConflictPreview.affected_count }} booking
                            bị ảnh hưởng
                        </h3>
                    </div>
                    <button
                        type="button"
                        class="icon-close"
                        @click="closeConflictPreview"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="14"
                            height="14"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </header>

                <p class="conflict-help">
                    {{
                        conflictPreviewOnly
                            ? "Các booking dưới đây nằm trong ngày, sân và khung giờ đang chọn."
                            : "Chọn phương án xử lý cho từng booking trước khi xác nhận khóa đột xuất."
                    }}
                </p>

                <div
                    v-if="!lockConflictPreview.items?.length"
                    class="conflict-empty"
                >
                    Không có booking nào trùng với phạm vi đã chọn.
                </div>

                <div class="conflict-list">
                    <article
                        v-for="item in lockConflictPreview.items"
                        :key="item.booking_item_id"
                        class="conflict-card"
                    >
                        <div class="conflict-main">
                            <strong>{{
                                item.booking_code || "Booking"
                            }}</strong>
                            <span
                                >{{ item.customer?.name || "Khách hàng" }} ·
                                {{ item.customer?.phone || "-" }}</span
                            >
                            <small>
                                {{ date(item.booking_date) }} ·
                                {{ item.court?.name || "Sân" }} ·
                                {{ time(item.start_time) }} -
                                {{ time(item.end_time) }} ·
                                {{ bookingStatusLabel(item.booking_status) }} ·
                                {{ paymentStatusLabel(item.payment_status) }}
                            </small>
                            <small
                                v-if="item.is_playing"
                                class="incident-summary"
                            >
                                Đang chơi · đã dùng
                                {{ item.incident?.played_minutes || 0 }} phút ·
                                còn {{ item.incident?.remaining_minutes || 0 }}
                                phút · dự kiến hoàn
                                {{
                                    currency(
                                        item.incident?.estimated_refund_amount,
                                    )
                                }}
                            </small>
                            <div
                                v-if="item.affected_range"
                                class="conflict-impact"
                            >
                                <span>
                                    Bị khóa:
                                    {{ time(item.affected_range.start_time) }}
                                    -
                                    {{ time(item.affected_range.end_time) }}
                                    ·
                                    {{ currency(item.affected_range.subtotal) }}
                                </span>
                                <span>
                                    Cả khung:
                                    {{ time(item.full_item_range.start_time) }}
                                    -
                                    {{ time(item.full_item_range.end_time) }}
                                    ·
                                    {{ currency(item.full_item_range.subtotal) }}
                                </span>
                            </div>
                        </div>

                        <div v-if="!conflictPreviewOnly" class="conflict-actions">
                            <div class="resolution-group">
                                <span class="resolution-label">Phạm vi xử lý</span>
                                <div class="scope-switch">
                                    <button
                                        type="button"
                                        :class="{
                                            active:
                                                lockResolutions[
                                                    item.booking_item_id
                                                ].scope === 'affected',
                                        }"
                                        :aria-pressed="
                                            lockResolutions[
                                                item.booking_item_id
                                            ].scope === 'affected'
                                        "
                                        @click="setResolutionScope(item, 'affected')"
                                    >
                                        Chỉ phần bị khóa
                                    </button>
                                    <button
                                        type="button"
                                        :class="{
                                            active:
                                                lockResolutions[
                                                    item.booking_item_id
                                                ].scope === 'booking_item',
                                        }"
                                        :aria-pressed="
                                            lockResolutions[
                                                item.booking_item_id
                                            ].scope === 'booking_item'
                                        "
                                        @click="setResolutionScope(item, 'booking_item')"
                                    >
                                        Cả khung booking
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="alternativesForResolution(item).length"
                                class="resolution-group"
                            >
                                <span class="resolution-label">Sân thay thế</span>
                                <select
                                    v-model="
                                        lockResolutions[item.booking_item_id]
                                            .venue_court_id
                                    "
                                    :disabled="
                                        lockResolutions[item.booking_item_id]
                                            .action !== 'switch'
                                    "
                                    class="conflict-select"
                                    aria-label="Sân thay thế"
                                >
                                    <option
                                        v-for="court in alternativesForResolution(item)"
                                        :key="court.id"
                                        :value="court.id"
                                    >
                                        {{ court.name }}
                                    </option>
                                </select>
                            </div>
                            <div v-else class="no-alternative">
                                <strong>Không có sân thay thế</strong>
                                <span>
                                    Các sân cùng loại đều đã có lịch hoặc cũng
                                    nằm trong phạm vi sắp khóa.
                                </span>
                            </div>

                            <div class="resolution-group">
                                <span class="resolution-label">Cách xử lý</span>
                                <div class="conflict-radios">
                                    <button
                                        v-if="alternativesForResolution(item).length"
                                        type="button"
                                        class="resolution-option"
                                        :class="{
                                            active:
                                                lockResolutions[
                                                    item.booking_item_id
                                                ].action === 'switch',
                                        }"
                                        :aria-pressed="
                                            lockResolutions[
                                                item.booking_item_id
                                            ].action === 'switch'
                                        "
                                        @click="setResolutionAction(item, 'switch')"
                                    >
                                        Đổi sang sân cùng loại
                                    </button>
                                    <button
                                        type="button"
                                        class="resolution-option danger"
                                        :class="{
                                            active:
                                                lockResolutions[
                                                    item.booking_item_id
                                                ].action === 'cancel',
                                        }"
                                        :aria-pressed="
                                            lockResolutions[
                                                item.booking_item_id
                                            ].action === 'cancel'
                                        "
                                        @click="setResolutionAction(item, 'cancel')"
                                    >
                                        {{
                                            lockResolutions[item.booking_item_id]
                                                .scope === "booking_item"
                                                ? item.payment_status === "paid"
                                                    ? "Hủy cả khung và hoàn vào ví"
                                                    : "Hủy cả khung booking"
                                                : item.payment_status === "paid"
                                                ? "Hủy và hoàn vào ví"
                                                : "Hủy phần bị khóa"
                                        }}
                                    </button>
                                    <button
                                        v-if="item.payment_status === 'paid'"
                                        type="button"
                                        class="resolution-option cash"
                                        :class="{
                                            active:
                                                lockResolutions[
                                                    item.booking_item_id
                                                ].action === 'cash_refund',
                                        }"
                                        :aria-pressed="
                                            lockResolutions[
                                                item.booking_item_id
                                            ].action === 'cash_refund'
                                        "
                                        @click="setResolutionAction(item, 'cash_refund')"
                                    >
                                        {{
                                            lockResolutions[item.booking_item_id]
                                                .scope === "booking_item"
                                                ? "Hoàn tiền mặt cả khung"
                                                : "Hoàn tiền mặt phần bị khóa"
                                        }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <footer>
                    <button
                        type="button"
                        class="secondary-btn"
                        @click="closeConflictPreview"
                    >
                        Đóng
                    </button>
                    <button
                        v-if="!conflictPreviewOnly"
                        type="button"
                        class="primary-btn"
                        :disabled="saving || !canResolveEmergencyLock"
                        @click="createLockWithResolutions"
                    >
                        {{
                            saving
                                ? "Đang xử lý..."
                                : "Xác nhận khóa đột xuất"
                        }}
                    </button>
                </footer>
            </section>
        </div>

        <div
            v-if="selectedLockDetail"
            class="modal-backdrop"
            @click.self="closeLockDetail"
        >
            <section
                class="lock-detail-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="lock-detail-title"
            >
                <header>
                    <div>
                        <p class="eyebrow">THÔNG TIN KHÓA LỊCH</p>
                        <h3 id="lock-detail-title">
                            {{ selectedLockDetail.venue_court?.name || "Sân" }}
                        </h3>
                    </div>
                    <button
                        type="button"
                        class="icon-close"
                        aria-label="Đóng"
                        @click="closeLockDetail"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="14"
                            height="14"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </header>

                <dl class="lock-detail-list">
                    <div>
                        <dt>Ngày áp dụng</dt>
                        <dd>{{ date(lockDate(selectedLockDetail)) }}</dd>
                    </div>
                    <div>
                        <dt>Khung giờ</dt>
                        <dd>
                            {{ time(selectedLockDetail.start_time) }} -
                            {{ time(selectedLockDetail.end_time) }}
                        </dd>
                    </div>
                    <div>
                        <dt>Loại khóa</dt>
                        <dd>
                            {{
                                selectedLockDetail.lock_type_label ||
                                (selectedLockDetail.lock_type === "emergency"
                                    ? "Khóa đột xuất"
                                    : "Khóa thủ công")
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt>Trạng thái</dt>
                        <dd>
                            <span
                                class="lock-status"
                                :class="selectedLockDetail.status || 'active'"
                            >
                                {{
                                    selectedLockDetail.status_label ||
                                    "Đang khóa"
                                }}
                            </span>
                        </dd>
                    </div>
                </dl>

                <div class="lock-detail-reason">
                    <span>Lý do</span>
                    <p>
                        {{ selectedLockDetail.reason || "Không có lý do" }}
                    </p>
                </div>

                <footer>
                    <button
                        type="button"
                        class="secondary-btn"
                        @click="closeLockDetail"
                    >
                        Đóng
                    </button>
                </footer>
            </section>
        </div>

        <ConfirmModal
            v-model="unlockConfirmOpen"
            title="Xác nhận mở khóa"
            :message="unlockConfirmMessage"
            consequence="Chỉ các ô đã chọn được mở; phần còn lại của khoảng khóa vẫn được giữ nguyên."
            confirm-text="Mở các ô đã chọn"
            cancel-text="Quay lại chọn"
            type="danger"
            @confirm="confirmUnlockSelection"
        />

        <!-- ===== TOP: Config panel ===== -->
        <div class="config-strip">
            <div class="config-left">
                <MiniCalendar
                    mode="range"
                    :start-date="form.start_date"
                    :end-date="form.end_date"
                    :min-date="today"
                    @update:start-date="handleCalendarStartDateUpdate"
                    @update:end-date="handleCalendarEndDateUpdate"
                />
            </div>
            <div class="config-right">
                <div class="config-section">
                    <label class="reason-label">
                        <span>Lý do khóa</span>
                        <textarea
                            v-model.trim="form.reason"
                            rows="3"
                            maxlength="500"
                            placeholder="Ví dụ: Bảo trì mặt sân, nghỉ lễ, sự kiện nội bộ..."
                            :disabled="unlockMode"
                            required
                        />
                    </label>
                </div>

                <div class="config-section court-target-section">
                    <div class="court-picker">
                        <div class="picker-head">
                            <div>
                                <strong>Khóa cả ngày theo sân</strong>
                                <small>
                                    Chọn sân để khóa toàn bộ giờ hoạt động trong
                                    ngày hoặc khoảng ngày đã chọn.
                                </small>
                            </div>
                            <button
                                type="button"
                                :disabled="
                                    unlockMode ||
                                    !selectableFullDayCourtIds.length
                                "
                                @click="toggleAllCourts"
                            >
                                {{
                                    !selectableFullDayCourtIds.length
                                        ? "Đã khóa hết"
                                        : allSelectableFullDayCourtsSelected
                                          ? "Bỏ chọn tất cả"
                                          : "Chọn tất cả"
                                }}
                            </button>
                        </div>
                        <div class="court-chip-grid">
                            <label
                                v-for="court in scheduleCourts"
                                :key="court.id"
                                :class="{
                                    active: selectedCourtIds.includes(court.id),
                                    locked: isFullDayCourtLocked(court.id),
                                }"
                            >
                                <input
                                    v-model="selectedCourtIds"
                                    type="checkbox"
                                    :value="court.id"
                                    :disabled="
                                        unlockMode ||
                                        isFullDayCourtLocked(court.id)
                                    "
                                    @change="handleFullDayCourtSelection"
                                />
                                <span>
                                    <strong>{{ court.name }}</strong>
                                    <small>
                                        {{
                                            isFullDayCourtLocked(court.id)
                                                ? "Đã khóa cả ngày"
                                                : court.court_type?.name || "-"
                                        }}
                                    </small>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 2: Lưới xem & Chọn giờ khóa trực quan -->
            <div class="profile-section-card">
                <div class="tab-section-header">
                    <div>
                        <h2>Lưới thời gian & Chọn khung giờ khóa</h2>
                        <p class="section-subtitle">
                            {{ isDateRange ? 'Đang chọn khoảng ngày: ' + dateRangeLabel : 'Lưới giờ trong ngày: ' + dateRangeLabel }}
                        </p>
                    </div>
                    <div class="legend">
                        <span><i class="dot-available"></i>Trống</span>
                        <span><i class="dot-booking"></i>Đã đặt</span>
                        <span><i class="dot-manual"></i>Đã khóa</span>
                        <span>
                            <i :class="unlockMode ? 'dot-unlock-selected' : 'dot-selected'"></i>
                            {{ unlockMode ? 'Chọn mở' : 'Đang chọn' }}
                        </span>
                    </div>
                </div>

                <div class="schedule-actions-row">
                    <button
                        class="attention-btn"
                        type="button"
                        :disabled="unlockMode || previewing || !hasLockSelection"
                        @click="openAttentionPreview"
                    >
                        {{ previewing ? 'Đang kiểm tra...' : 'Xem lịch trùng' }}
                    </button>
                    <button
                        class="unlock-mode-btn"
                        :class="{ active: unlockMode }"
                        type="button"
                        :disabled="!hasManagedLocksOnSchedule || unlocking"
                        @click="toggleUnlockMode"
                    >
                        {{ unlockMode ? 'Thoát mở khóa' : 'Mở khóa' }}
                    </button>
                </div>

                <div class="quick-ranges">
                <button
                    v-for="range in dynamicQuickRanges"
                    :key="range.key"
                    type="button"
                    :class="{ active: activeTimePeriod === range.key }"
                    :disabled="loading"
                    @click="activeTimePeriod = range.key"
                >
                    <strong>{{ range.label }}</strong>
                    <small>{{ range.range }}</small>
                </button>
            </div>

            <div v-if="loading" class="state">Đang tải lịch sân...</div>
            <div v-else-if="!selectedClusterId" class="state">
                Vui lòng chọn cụm sân.
            </div>
            <div v-else-if="!scheduleCourts.length" class="state">
                Cụm sân chưa có sân đang hoạt động.
            </div>
            <div v-else class="schedule-wrap">
                <div class="schedule-grid" :style="scheduleGridStyle">
                    <div class="grid-head sticky-col">Sân \ Giờ</div>
                    <div
                        v-for="slot in activePeriodSlots"
                        :key="slot.start_time"
                        class="grid-head time-head"
                    >
                        {{ time(slot.start_time) }}
                    </div>

                    <template v-for="court in scheduleCourts" :key="court.id">
                        <div class="court-cell sticky-col">
                            <strong>{{ court.name }}</strong>
                            <span>{{ court.court_type?.name }}</span>
                        </div>
                        <button
                            v-for="slot in activePeriodSlots"
                            :key="`${court.id}-${slot.start_time}`"
                            class="slot-cell"
                            :class="[
                                slotClass(court.id, slot),
                            ]"
                            :title="slotTitle(court.id, slot)"
                            type="button"
                            :disabled="!canInteractSlot(court.id, slot)"
                            :aria-pressed="
                                unlockMode
                                    ? isUnlockSelected(court.id, slot)
                                    : isSelected(court.id, slot)
                            "
                            @click="pickSlot(court, slot)"
                        />
                    </template>
                </div>
            </div>
            </div>

            <!-- SECTION 3: Khoảng lịch đã khóa -->
            <div v-if="locks.length" class="profile-section-card">
                <div class="tab-section-header">
                    <div>
                        <h2>Danh sách khoảng lịch đã khóa</h2>
                        <p class="section-subtitle">{{ lockSummaryLabel }}</p>
                    </div>
                </div>

                <div class="lock-table" role="table" aria-label="Khoảng lịch đã khóa">
                    <div class="lock-table-head" role="row">
                        <span>Sân và ngày</span>
                        <span>Khung giờ</span>
                        <span>Lý do</span>
                        <span>Trạng thái</span>
                    </div>
                    <div v-for="lock in locks" :key="lock.id" class="lock-table-row" role="row">
                        <div>
                            <strong>{{ lock.venue_court?.name || "Sân chưa rõ" }}</strong>
                            <small>
                                {{ date(lockDate(lock)) }} ·
                                {{ lock.lock_type_label || "Khóa thủ công" }}
                            </small>
                        </div>
                        <strong class="lock-time">
                            {{ time(lock.start_time) }} - {{ time(lock.end_time) }}
                        </strong>
                        <span class="lock-reason">{{ lock.reason || "Không có lý do" }}</span>
                        <span class="lock-status" :class="lock.status || 'active'">
                            {{ lock.status_label || "Đang khóa" }}
                        </span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<script>
import { bookingService } from "../../services/bookingService.js";
import { ownerScheduleLockService } from "../../services/ownerScheduleLocks.js";
import ConfirmModal from "../../components/ConfirmModal.vue";
import MiniCalendar from "../../components/MiniCalendar.vue";

export default {
    name: "OwnerScheduleLocks",
    components: { ConfirmModal, MiniCalendar },
    data() {
        const today = new Date().toISOString().split("T")[0];

        return {
            today,
            selectedClusterId: localStorage.getItem("selected_cluster") || "",
            locks: [],
            scheduleSlots: [],
            scheduleCourts: [],
            scheduleSlotStatuses: [],
            scheduleBusyIntervals: [],
            loading: true,
            saving: false,
            unlocking: false,
            error: "",
            notice: "",
            form: {
                start_date: today,
                end_date: today,
                reason: "",
            },
            selectedSlots: [],
            selectedCourtIds: [],
            activeTimePeriod: "morning",
            previewing: false,
            pendingLockPayload: null,
            lockConflictPreview: null,
            lockResolutions: {},
            conflictPreviewOnly: false,
            selectedLockDetail: null,
            unlockMode: false,
            selectedUnlockSlots: [],
            unlockConfirmOpen: false,
            scheduleRequestId: 0,
            locksRequestId: 0,
            fullDayLockedCourtIds: [],
        };
    },
    computed: {
        isFullDaySelection() {
            return this.selectedCourtIds.length > 0;
        },
        hasLockSelection() {
            return this.isFullDaySelection || this.selectedSlots.length > 0;
        },
        canSubmit() {
            return (
                !this.unlockMode &&
                this.hasLockSelection &&
                this.form.start_date &&
                this.form.end_date &&
                this.form.reason
            );
        },
        unlockConfirmMessage() {
            const courtCount = new Set(
                this.selectedUnlockSlots.map((slot) => slot.venue_court_id),
            ).size;

            return `Bạn đang mở ${this.selectedUnlockSlots.length} ô khóa trên ${courtCount} sân trong ngày ${this.date(this.form.start_date)}.`;
        },
        unlockableSlots() {
            if (!this.scheduleCourts.length || !this.activePeriodSlots.length) {
                return [];
            }

            return this.scheduleCourts.flatMap((court) =>
                this.activePeriodSlots
                    .map((slot) => {
                        const status = this.statusFor(court.id, slot);
                        if (!this.isManagedLockStatus(status)) return null;

                        const interval = this.busyIntervalFor(court.id, slot);
                        const lockId =
                            status?.schedule_lock_id ||
                            interval?.schedule_lock_id;
                        if (!lockId) return null;

                        return {
                            key: this.unlockSlotKey(court.id, slot.start_time),
                            schedule_lock_id: lockId,
                            venue_court_id: court.id,
                            court_name: court.name,
                            start_time: this.withSeconds(slot.start_time),
                            end_time: this.withSeconds(slot.end_time),
                        };
                    })
                    .filter(Boolean),
            );
        },
        hasManagedLocksOnSchedule() {
            return this.scheduleBusyIntervals.some(
                (interval) =>
                    interval.source === "slot_lock" &&
                    ["manual", "emergency"].includes(interval.status),
            );
        },
        dateRangeLabel() {
            if (this.form.start_date === this.form.end_date) {
                return this.date(this.form.start_date);
            }

            return `${this.date(this.form.start_date)} - ${this.date(this.form.end_date)}`;
        },
        isDateRange() {
            return Boolean(
                this.form.start_date &&
                    this.form.end_date &&
                    this.form.start_date !== this.form.end_date,
            );
        },
        dateCount() {
            const start = new Date(`${this.form.start_date}T00:00:00`);
            const end = new Date(`${this.form.end_date}T00:00:00`);
            if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
                return 0;
            }

            return Math.max(Math.floor((end - start) / 86400000) + 1, 0);
        },
        selectedCourtCount() {
            if (this.isFullDaySelection) {
                return this.selectedCourtIds.length;
            }

            return new Set(
                this.selectedSlots.map((slot) => slot.venue_court_id),
            ).size;
        },
        selectionSummary() {
            if (this.isFullDaySelection) {
                return `Cả ngày theo giờ hoạt động · ${this.selectedCourtCount} sân`;
            }
            if (!this.hasLockSelection) {
                return "Chọn thời gian trên bảng bên phải";
            }

            return `Thời gian: ${this.selectedTimeText} · ${this.selectedCourtCount} sân`;
        },
        lockButtonLabel() {
            return this.isFullDaySelection
                ? "Kiểm tra và khóa cả ngày"
                : "Kiểm tra và khóa lịch";
        },
        fullDayLockedCourtIdSet() {
            return new Set(
                this.fullDayLockedCourtIds.map((courtId) => String(courtId)),
            );
        },
        selectableFullDayCourtIds() {
            return this.scheduleCourts
                .filter((court) => !this.isFullDayCourtLocked(court.id))
                .map((court) => court.id);
        },
        allSelectableFullDayCourtsSelected() {
            return (
                this.selectableFullDayCourtIds.length > 0 &&
                this.selectedCourtIds.length ===
                    this.selectableFullDayCourtIds.length
            );
        },
        canResolveEmergencyLock() {
            const items = this.lockConflictPreview?.items || [];
            if (!items.length) return false;

            return items.every((item) => {
                const resolution =
                    this.lockResolutions[item.booking_item_id];
                if (!resolution?.action) return false;
                if (resolution.action === "switch") {
                    return (
                        Boolean(resolution.venue_court_id) &&
                        this.alternativesForResolution(item).some(
                            (court) =>
                                String(court.id) ===
                                String(resolution.venue_court_id),
                        )
                    );
                }
                return ["cancel", "cash_refund"].includes(
                    resolution.action,
                );
            });
        },
        selectedTimeText() {
            const ranges = [
                ...new Set(
                    this.selectedSlots
                        .map(
                            (slot) =>
                                `${this.time(slot.start_time)} - ${this.time(slot.end_time)}`,
                        )
                        .sort(),
                ),
            ];

            if (!ranges.length) return "-";
            if (ranges.length <= 2) return ranges.join(", ");

            return `${ranges.slice(0, 2).join(", ")} +${ranges.length - 2} khung`;
        },
        lockSummaryLabel() {
            const dates = [
                ...new Set(this.locks.map((lock) => this.lockDate(lock))),
            ].filter(Boolean);

            if (!dates.length) return `${this.locks.length} khoảng`;
            if (dates.length === 1) {
                return `${this.locks.length} khoảng · ${this.date(dates[0])}`;
            }

            const sortedDates = dates.sort();
            return `${this.locks.length} khoảng · ${dates.length} ngày (${this.date(sortedDates[0])} - ${this.date(sortedDates[sortedDates.length - 1])})`;
        },
        dynamicQuickRanges() {
            const slotStarts = this.scheduleSlots.map((slot) =>
                this.minutes(slot.start_time),
            );
            const slotEnds = this.scheduleSlots.map((slot) =>
                this.minutes(slot.end_time),
            );

            const open = slotStarts.length ? Math.min(...slotStarts) : 6 * 60;
            const close = Math.max(
                slotEnds.length ? Math.max(...slotEnds) : 22 * 60,
                open + 30,
            );
            const ranges = [
                {
                    key: "morning",
                    label: "Sáng",
                    startMinutes: open,
                    endMinutes: Math.min(close, 12 * 60),
                },
                {
                    key: "afternoon",
                    label: "Chiều",
                    startMinutes: Math.max(open, 12 * 60),
                    endMinutes: Math.min(close, 18 * 60),
                },
                {
                    key: "evening",
                    label: "Tối",
                    startMinutes: Math.max(open, 18 * 60),
                    endMinutes: close,
                },
            ]
                .filter((range) => range.endMinutes > range.startMinutes)
                .map((range) => ({
                    ...range,
                    start: this.minutesToTime(range.startMinutes),
                    end: this.minutesToTime(range.endMinutes),
                    range: `${this.minutesToTime(range.startMinutes)} - ${this.minutesToTime(range.endMinutes)}`,
                }));

            return ranges.length
                ? ranges
                : [
                      {
                          key: "all",
                          label: "Cả ngày",
                          startMinutes: open,
                          endMinutes: close,
                          start: this.minutesToTime(open),
                          end: this.minutesToTime(close),
                          range: `${this.minutesToTime(open)} - ${this.minutesToTime(close)}`,
                      },
                  ];
        },
        activePeriod() {
            return (
                this.dynamicQuickRanges.find(
                    (range) => range.key === this.activeTimePeriod,
                ) || this.dynamicQuickRanges[0]
            );
        },
        activePeriodSlots() {
            return this.scheduleSlots.filter((slot) => {
                const start = this.minutes(slot.start_time);
                return (
                    start >= this.activePeriod.startMinutes &&
                    start < this.activePeriod.endMinutes
                );
            });
        },
        scheduleGridStyle() {
            return {
                gridTemplateColumns: `minmax(150px, .9fr) repeat(${this.activePeriodSlots.length}, minmax(54px, 1fr))`,
            };
        },
    },
    async mounted() {
        window.addEventListener(
            "owner-cluster-changed",
            this.handleClusterChanged,
        );
        await this.loadData();
    },
    beforeUnmount() {
        window.removeEventListener(
            "owner-cluster-changed",
            this.handleClusterChanged,
        );
    },
    methods: {
        async handleCalendarStartDateUpdate(value) {
            if (!value || value === this.form.start_date) return;

            this.form.start_date = value;
            await this.handleStartDateChange();
        },
        async handleCalendarEndDateUpdate(value) {
            if (!value || value === this.form.end_date) return;

            this.form.end_date = value;
            await this.handleEndDateChange();
        },
        async handleClusterChanged(event) {
            this.selectedClusterId =
                event.detail?.id ||
                localStorage.getItem("selected_cluster") ||
                "";
            this.cancelUnlockMode();
            this.clearSelection();
            await this.loadData();
        },
        async handleStartDateChange() {
            if (
                this.form.end_date &&
                this.form.end_date < this.form.start_date
            ) {
                this.form.end_date = this.form.start_date;
            }

            this.cancelUnlockMode();
            this.clearSelection();
            await Promise.all([this.loadSchedule(), this.loadLocks()]);
        },
        async handleEndDateChange() {
            if (
                this.form.start_date &&
                this.form.end_date < this.form.start_date
            ) {
                this.form.end_date = this.form.start_date;
            }

            this.cancelUnlockMode();
            await this.loadLocks();
        },
        async loadData() {
            this.loading = true;
            this.error = "";

            if (!this.selectedClusterId) {
                this.loading = false;
                return;
            }

            try {
                await Promise.all([this.loadSchedule(), this.loadLocks()]);
            } catch (error) {
                this.error = error.message || "Không thể tải lịch sân.";
            } finally {
                this.loading = false;
            }
        },
        async loadSchedule() {
            if (!this.selectedClusterId || !this.form.start_date) return;

            const requestId = ++this.scheduleRequestId;
            const clusterId = String(this.selectedClusterId);
            const bookingDate = this.form.start_date;
            const response = await bookingService.getSchedule({
                venue_cluster_id: clusterId,
                booking_date: bookingDate,
                booking_type: "single",
            });

            if (
                requestId !== this.scheduleRequestId ||
                clusterId !== String(this.selectedClusterId) ||
                bookingDate !== this.form.start_date
            ) {
                return;
            }

            this.scheduleSlots = response.time_slots || [];
            this.scheduleCourts = response.courts || [];
            this.scheduleSlotStatuses = response.slot_statuses || [];
            this.scheduleBusyIntervals = response.busy_intervals || [];
            this.selectedCourtIds = this.selectedCourtIds.filter((courtId) =>
                this.scheduleCourts.some(
                    (court) => String(court.id) === String(courtId),
                ),
            );
            this.pruneLockedFullDayCourtSelections();
            this.ensureActiveTimePeriod();
        },
        async loadLocks() {
            if (!this.selectedClusterId || !this.form.start_date) return;

            const requestId = ++this.locksRequestId;
            const clusterId = String(this.selectedClusterId);
            const startDate = this.form.start_date;
            const endDate = this.form.end_date || startDate;
            const response = await ownerScheduleLockService.list({
                venue_cluster_id: clusterId,
                start_date: startDate,
                end_date: endDate,
            });

            if (
                requestId !== this.locksRequestId ||
                clusterId !== String(this.selectedClusterId) ||
                startDate !== this.form.start_date ||
                endDate !== (this.form.end_date || this.form.start_date)
            ) {
                return;
            }

            this.locks = response.data || [];
            this.fullDayLockedCourtIds =
                response.meta?.full_day_locked_court_ids || [];
            this.pruneLockedFullDayCourtSelections();
        },
        async createLock() {
            if (!this.canSubmit) return;

            this.error = "";
            this.notice = "";
            try {
                const payload = this.buildLockPayload();
                this.previewing = true;
                const preview = await ownerScheduleLockService.preview(payload);
                const data = preview.data || {};

                if ((data.affected_count || 0) > 0) {
                    this.pendingLockPayload = {
                        ...payload,
                        lock_type: "emergency",
                    };
                    this.lockConflictPreview = data;
                    this.conflictPreviewOnly = false;
                    this.lockResolutions = this.defaultLockResolutions(
                        data.items || [],
                    );
                    return;
                }

                await this.finalizeLock(payload);
            } catch (error) {
                this.error = error.message || "Không thể khóa khung giờ.";
            } finally {
                this.previewing = false;
            }
        },
        buildLockPayload() {
            const payload = {
                start_date: this.form.start_date,
                end_date: this.form.end_date,
                lock_type: "manual",
                reason: this.form.reason,
            };

            if (this.isFullDaySelection) {
                return {
                    ...payload,
                    full_day: true,
                    venue_court_ids: [...this.selectedCourtIds],
                };
            }

            return {
                ...payload,
                slots: this.buildSelectedRanges(),
            };
        },
        async openAttentionPreview() {
            if (!this.hasLockSelection) return;

            this.previewing = true;
            this.error = "";
            try {
                const payload = this.buildLockPayload();
                if (String(payload.reason || "").trim().length < 3) {
                    delete payload.reason;
                }
                const response = await ownerScheduleLockService.preview(payload);
                const data = response.data || { affected_count: 0, items: [] };
                this.pendingLockPayload = null;
                this.lockConflictPreview = data;
                this.lockResolutions = {};
                this.conflictPreviewOnly = true;
            } catch (error) {
                this.error = error.message || "Không thể kiểm tra lịch trùng.";
            } finally {
                this.previewing = false;
            }
        },
        defaultLockResolutions(items = []) {
            return items.reduce((result, item) => {
                const firstAlternative = item.alternatives?.[0]?.id || "";
                result[item.booking_item_id] = {
                    booking_item_id: item.booking_item_id,
                    scope: "affected",
                    action: firstAlternative ? "switch" : "cancel",
                    venue_court_id: firstAlternative,
                };
                return result;
            }, {});
        },
        async createLockWithResolutions() {
            if (!this.pendingLockPayload) return;

            const resolutions = Object.values(this.lockResolutions).map(
                (item) => ({
                    booking_item_id: item.booking_item_id,
                    scope: item.scope || "affected",
                    action: item.action,
                    venue_court_id:
                        item.action === "switch" ? item.venue_court_id : null,
                }),
            );

            await this.finalizeLock({
                ...this.pendingLockPayload,
                resolutions,
            });
        },
        async finalizeLock(payload) {
            this.saving = true;
            this.error = "";
            this.notice = "";
            try {
                const response = await ownerScheduleLockService.create(payload);
                this.notice = response.message;
                this.form.reason = "";
                this.closeConflictPreview();
                this.clearSelection();
                await Promise.all([this.loadSchedule(), this.loadLocks()]);
            } catch (error) {
                this.error = error.message || "Không thể khóa khung giờ.";
            } finally {
                this.saving = false;
            }
        },
        closeConflictPreview() {
            this.pendingLockPayload = null;
            this.lockConflictPreview = null;
            this.lockResolutions = {};
            this.conflictPreviewOnly = false;
        },
        alternativesForResolution(item) {
            const resolution = this.lockResolutions[item.booking_item_id] || {};
            return resolution.scope === "booking_item"
                ? item.full_item_alternatives || []
                : item.alternatives || [];
        },
        setResolutionScope(item, scope) {
            const resolution = this.lockResolutions[item.booking_item_id];
            if (!resolution) return;

            resolution.scope = scope;
            this.normalizeResolution(item);
        },
        setResolutionAction(item, action) {
            const resolution = this.lockResolutions[item.booking_item_id];
            if (!resolution) return;

            resolution.action = action;
            this.normalizeResolution(item);
        },
        normalizeResolution(item) {
            const resolution = this.lockResolutions[item.booking_item_id];
            if (!resolution) return;

            const alternatives = this.alternativesForResolution(item);
            const selectedStillAvailable = alternatives.some(
                (court) => String(court.id) === String(resolution.venue_court_id),
            );

            if (resolution.action === "switch" && !alternatives.length) {
                resolution.action = "cancel";
                resolution.venue_court_id = "";
                return;
            }

            if (!selectedStillAvailable) {
                resolution.venue_court_id = alternatives[0]?.id || "";
            }
        },
        closeLockDetail() {
            this.selectedLockDetail = null;
        },
        toggleUnlockMode() {
            if (this.unlockMode) {
                this.cancelUnlockMode();
                return;
            }

            this.clearSelection();
            this.closeLockDetail();
            this.error = "";
            this.notice = "";
            this.unlockMode = true;
            this.selectedUnlockSlots = [];
        },
        cancelUnlockMode() {
            this.unlockMode = false;
            this.selectedUnlockSlots = [];
            this.unlockConfirmOpen = false;
        },
        selectAllUnlockableSlots() {
            this.selectedUnlockSlots = this.unlockableSlots.map((slot) => ({
                ...slot,
            }));
        },
        async confirmUnlockSelection() {
            if (!this.selectedUnlockSlots.length || this.unlocking) return;

            this.unlocking = true;
            this.error = "";
            this.notice = "";
            try {
                const response = await ownerScheduleLockService.unlock({
                    ranges: this.buildUnlockRanges(),
                });
                this.notice = response.message || "Đã mở các ô được chọn.";
                this.cancelUnlockMode();
                await Promise.all([this.loadSchedule(), this.loadLocks()]);
            } catch (error) {
                this.error = error.message || "Không thể mở lại khung giờ.";
            } finally {
                this.unlocking = false;
            }
        },
        buildUnlockRanges() {
            const ranges = [...this.selectedUnlockSlots]
                .sort((left, right) => {
                    const lockCompare = String(left.schedule_lock_id).localeCompare(
                        String(right.schedule_lock_id),
                    );
                    if (lockCompare !== 0) return lockCompare;

                    return this.minutes(left.start_time) - this.minutes(right.start_time);
                })
                .map((slot) => ({
                    schedule_lock_id: slot.schedule_lock_id,
                    start_time: this.withSeconds(slot.start_time),
                    end_time: this.withSeconds(slot.end_time),
                }));
            const merged = [];

            ranges.forEach((range) => {
                const previous = merged[merged.length - 1];
                if (
                    previous &&
                    String(previous.schedule_lock_id) ===
                        String(range.schedule_lock_id) &&
                    previous.end_time === range.start_time
                ) {
                    previous.end_time = range.end_time;
                    return;
                }

                merged.push({ ...range });
            });

            return merged;
        },
        statusFor(courtId, slot) {
            const status = this.scheduleSlotStatuses.find(
                (item) =>
                    String(item.venue_court_id) === String(courtId) &&
                    this.withSeconds(item.start_time) ===
                        this.withSeconds(slot.start_time),
            );
            const interval = this.busyIntervalFor(courtId, slot);

            if (status && !interval) return status;
            if (!interval) return status || null;

            return {
                ...(status || {}),
                venue_court_id: courtId,
                start_time: slot.start_time,
                end_time: slot.end_time,
                is_available: false,
                busy_source: interval.source,
                busy_status: interval.status,
                schedule_lock_id: interval.schedule_lock_id,
                lock_reason: interval.reason,
            };
        },
        busyIntervalFor(courtId, slot) {
            const start = this.minutes(slot.start_time);
            const end = this.minutes(slot.end_time);

            return (
                this.scheduleBusyIntervals.find(
                    (interval) =>
                        String(interval.venue_court_id) === String(courtId) &&
                        this.minutes(interval.start_time) < end &&
                        this.minutes(interval.end_time) > start,
                ) || null
            );
        },
        isBusy(courtId, slot) {
            return !this.statusFor(courtId, slot)?.is_available;
        },
        canSelectSlot(courtId, slot) {
            const status = this.statusFor(courtId, slot);
            if (!status || status.is_available) return true;
            if (status.busy_source === "slot_lock") return false;
            if (status.slot_status === "past") return false;

            return status.busy_source === "booking" || !status.busy_source;
        },
        isManagedLockStatus(status) {
            return (
                status?.busy_source === "slot_lock" &&
                ["manual", "emergency"].includes(status.busy_status)
            );
        },
        isPastStatus(status) {
            return status?.slot_status === "past";
        },
        isAdvanceNoticeStatus(status) {
            return status?.slot_status === "too_early";
        },
        canInteractSlot(courtId, slot) {
            const status = this.statusFor(courtId, slot);
            if (this.unlockMode) {
                return this.isManagedLockStatus(status);
            }

            return (
                this.isManagedLockStatus(status) ||
                this.canSelectSlot(courtId, slot)
            );
        },
        slotClass(courtId, slot) {
            const status = this.statusFor(courtId, slot);
            if (this.isUnlockSelected(courtId, slot))
                return "manual unlock-selected";
            if (this.isSelected(courtId, slot)) return "selected";
            if (!status || status.is_available || this.isAdvanceNoticeStatus(status))
                return "available";
            if (this.isPastStatus(status)) return "unavailable";
            if (status.busy_source === "booking") return "booking";
            if (["manual", "emergency"].includes(status.busy_status))
                return "manual";
            return "unavailable";
        },
        slotTitle(courtId, slot) {
            const status = this.statusFor(courtId, slot);
            if (this.unlockMode) {
                if (this.isUnlockSelected(courtId, slot)) {
                    return `${this.time(slot.start_time)} - ${this.time(slot.end_time)} · Đã chọn để mở khóa`;
                }
                if (this.isManagedLockStatus(status)) {
                    return `${this.time(slot.start_time)} - ${this.time(slot.end_time)} · Bấm để chọn mở khóa`;
                }

                return "Chế độ mở khóa chỉ cho chọn các ô đang khóa";
            }
            if (this.isSelected(courtId, slot))
                return `${this.time(slot.start_time)} - ${this.time(slot.end_time)} · Đã chọn`;
            if (!status || status.is_available || this.isAdvanceNoticeStatus(status))
                return `${this.time(slot.start_time)} - ${this.time(slot.end_time)} · Trống`;
            if (this.isPastStatus(status)) {
                return `${this.time(slot.start_time)} - ${this.time(slot.end_time)} không thể chọn`;
            }
            if (status.busy_source === "booking") {
                return "Đã có booking · chọn để khóa đột xuất và xử lý";
            }
            if (["manual", "emergency"].includes(status.busy_status))
                return `Đã khóa: ${status.lock_reason || "Không có lý do"} · Bấm để xem thông tin`;
            return "Không thể chọn khung giờ này";
        },
        pickSlot(court, slot) {
            const status = this.statusFor(court.id, slot);
            if (this.unlockMode) {
                if (this.isManagedLockStatus(status)) {
                    this.toggleUnlockSlot(court, slot, status);
                }
                return;
            }

            if (this.isManagedLockStatus(status)) {
                this.openLockDetail(court, slot, status);
                return;
            }

            if (!this.canSelectSlot(court.id, slot)) return;

            this.selectedCourtIds = [];

            const key = this.slotKey(court.id, slot.start_time);
            const existingIndex = this.selectedSlots.findIndex(
                (selected) => selected.key === key,
            );

            if (existingIndex >= 0) {
                this.selectedSlots.splice(existingIndex, 1);
                return;
            }

            this.selectedSlots.push({
                key,
                venue_court_id: court.id,
                court_name: court.name,
                start_time: this.withSeconds(slot.start_time),
                end_time: this.withSeconds(slot.end_time),
            });
        },
        toggleUnlockSlot(court, slot, status) {
            const interval = this.busyIntervalFor(court.id, slot);
            const lockId =
                status?.schedule_lock_id || interval?.schedule_lock_id;
            if (!lockId) {
                this.error = "Không tìm thấy khoảng khóa tương ứng. Vui lòng tải lại lịch.";
                return;
            }

            const key = this.unlockSlotKey(court.id, slot.start_time);
            const existingIndex = this.selectedUnlockSlots.findIndex(
                (selected) => selected.key === key,
            );
            if (existingIndex >= 0) {
                this.selectedUnlockSlots.splice(existingIndex, 1);
                return;
            }

            this.selectedUnlockSlots.push({
                key,
                schedule_lock_id: lockId,
                venue_court_id: court.id,
                court_name: court.name,
                start_time: this.withSeconds(slot.start_time),
                end_time: this.withSeconds(slot.end_time),
            });
        },
        openLockDetail(court, slot, status) {
            const interval = this.busyIntervalFor(court.id, slot);
            const lockId =
                status?.schedule_lock_id || interval?.schedule_lock_id;
            const lock =
                this.locks.find(
                    (item) => String(item.id) === String(lockId),
                ) ||
                this.locks.find(
                    (item) =>
                        String(item.venue_court_id) === String(court.id) &&
                        this.lockDate(item) === this.form.start_date &&
                        this.minutes(item.start_time) <
                            this.minutes(slot.end_time) &&
                        this.minutes(item.end_time) >
                            this.minutes(slot.start_time),
                );

            this.selectedLockDetail =
                lock ||
                {
                    id: lockId,
                    venue_cluster_id: this.selectedClusterId,
                    venue_court_id: court.id,
                    venue_court: court,
                    booking_date: this.form.start_date,
                    start_time: interval?.start_time || slot.start_time,
                    end_time: interval?.end_time || slot.end_time,
                    reason:
                        interval?.reason ||
                        status?.lock_reason ||
                        "Không có lý do",
                    lock_type:
                        interval?.status || status?.busy_status || "manual",
                    status: "active",
                    status_label: "Đang khóa",
                };
        },
        slotKey(courtId, startTime) {
            return `${courtId}-${this.withSeconds(startTime)}`;
        },
        unlockSlotKey(courtId, startTime) {
            return `unlock-${this.slotKey(courtId, startTime)}`;
        },
        isSelected(courtId, slot) {
            const key = this.slotKey(courtId, slot.start_time);
            return this.selectedSlots.some((item) => item.key === key);
        },
        isUnlockSelected(courtId, slot) {
            const key = this.unlockSlotKey(courtId, slot.start_time);
            return this.selectedUnlockSlots.some((item) => item.key === key);
        },
        clearSelection() {
            this.selectedSlots = [];
            this.selectedCourtIds = [];
        },
        isFullDayCourtLocked(courtId) {
            return this.fullDayLockedCourtIdSet.has(String(courtId));
        },
        pruneLockedFullDayCourtSelections() {
            if (!this.selectedCourtIds.length) return;

            this.selectedCourtIds = this.selectedCourtIds.filter(
                (courtId) => !this.isFullDayCourtLocked(courtId),
            );
        },
        handleFullDayCourtSelection() {
            this.pruneLockedFullDayCourtSelections();
            if (this.selectedCourtIds.length) {
                this.selectedSlots = [];
            }
        },
        toggleAllCourts() {
            if (this.allSelectableFullDayCourtsSelected) {
                this.selectedCourtIds = [];
                return;
            }

            this.selectedCourtIds = [...this.selectableFullDayCourtIds];
            this.selectedSlots = [];
        },
        bookingStatusLabel(status) {
            return (
                {
                    pending_approval: "Chờ duyệt",
                    pending_payment: "Chờ thanh toán",
                    confirmed: "Đã xác nhận",
                    checked_in: "Đang chơi",
                    completed: "Đã hoàn thành",
                }[status] || "Đang hiệu lực"
            );
        },
        paymentStatusLabel(status) {
            return status === "paid" ? "Đã thanh toán" : "Chưa thanh toán";
        },
        buildSelectedRanges() {
            const grouped = [...this.selectedSlots]
                .sort((a, b) => a.start_time.localeCompare(b.start_time))
                .reduce((result, slot) => {
                    if (!result[slot.venue_court_id])
                        result[slot.venue_court_id] = [];
                    result[slot.venue_court_id].push(slot);
                    return result;
                }, {});

            return Object.entries(grouped).flatMap(([courtId, slots]) => {
                const ranges = [];

                slots.forEach((slot) => {
                    const previous = ranges[ranges.length - 1];
                    if (previous && previous.end_time === slot.start_time) {
                        previous.end_time = slot.end_time;
                    } else {
                        ranges.push({
                            venue_court_id: courtId,
                            start_time: slot.start_time,
                            end_time: slot.end_time,
                        });
                    }
                });

                return ranges;
            });
        },
        withSeconds(value) {
            return value.length === 5 ? `${value}:00` : value;
        },
        time(value) {
            return (value || "").slice(0, 5);
        },
        minutesToTime(minutes) {
            const normalized = Math.max(0, Math.min(24 * 60, minutes));
            const hour = Math.floor(normalized / 60);
            const minute = normalized % 60;
            return `${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}`;
        },
        ensureActiveTimePeriod() {
            if (
                !this.dynamicQuickRanges.some(
                    (range) => range.key === this.activeTimePeriod,
                )
            ) {
                this.activeTimePeriod =
                    this.dynamicQuickRanges[0]?.key || "morning";
            }
        },
        minutes(value) {
            const normalized = this.withSeconds(value || "00:00:00");
            if (normalized.startsWith("24:00")) return 24 * 60;
            const [hour, minute] = normalized
                .slice(0, 5)
                .split(":")
                .map(Number);
            return hour * 60 + minute;
        },
        isoDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, "0");
            const day = String(date.getDate()).padStart(2, "0");
            return `${year}-${month}-${day}`;
        },
        lockDate(lock) {
            return (
                lock?.booking_date ||
                lock?.start_date ||
                lock?.date ||
                this.form.start_date
            );
        },
        date(value) {
            if (!value) return "-";
            return new Intl.DateTimeFormat("vi-VN").format(
                new Date(`${value}T00:00:00`),
            );
        },
        currency(value) {
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
                maximumFractionDigits: 0,
            }).format(Number(value || 0));
        },
    },
};
</script>

<style scoped>
/* ===== Master Workspace Layout (Exact match to ClusterGeneralInfoTab) ===== */
.schedule-lock-master-workspace {
    display: flex;
    flex-direction: column;
    gap: 0;
    width: 100%;
    padding-bottom: 80px; /* space for sticky bar */
    font-family: var(--admin-font-family, inherit);
}

.schedule-lock-master-workspace :where(button, input, textarea, select) {
    font-family: inherit;
}

.cluster-profile-surface {
    display: flex;
    flex-direction: column;
    background: var(--admin-surface, #ffffff);
    border-radius: 0;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.profile-section-card {
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.profile-section-card + .profile-section-card {
    border-top: 1px solid var(--admin-border-soft, #f1f5f9);
}

.tab-section-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.tab-section-header h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 400;
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

.schedule-actions-row {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
}

/* ===== Alerts ===== */
.alert {
    padding: 13px 15px;
    border-radius: 10px;
    font-weight: 400;
}
.alert.error {
    background: #fee2e2;
    color: #991b1b;
}
.alert.success {
    background: #dcfce7;
    color: #166534;
}

/* ===== Config Strip (calendar + settings) ===== */
.config-strip {
    display: flex;
    flex-wrap: wrap;
    gap: 24px;
    align-items: flex-start;
    width: 100%;
    padding: 0;
    border: none;
    background: transparent;
    box-shadow: none;
}

.config-left {
    flex: 0 0 auto;
    width: fit-content;
    max-width: 100%;
}

.config-left :deep(.mini-cal) {
    border: 1px solid var(--admin-border-soft, #e2e8f0);
    border-radius: 12px;
    padding: 16px;
    background: #ffffff;
    width: fit-content;
    max-width: 100%;
    box-sizing: border-box;
}

.config-right {
    flex: 1 1 360px;
    min-width: 300px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    align-self: stretch;
}

.config-section {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
}

.lock-preview-panel {
    grid-column: 1 / -1;
    align-self: stretch;
}

.config-label {
    margin: 0;
    color: #64748b;
    font-size: 12px;
    font-weight: 400;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.lock-flow-note {
    min-height: 64px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
    margin: 0;
    padding: 12px 14px;
    border: 1px solid #dbe8d8;
    border-radius: 10px;
    background:
        linear-gradient(
            90deg,
            rgba(34, 197, 94, 0.08),
            rgba(255, 255, 255, 0.95)
        ),
        #fff;
}

.lock-flow-note strong {
    color: #14532d;
    font-size: 13px;
    font-weight: 400;
}

.lock-flow-note span {
    color: #64756b;
    font-size: 12px;
    font-weight: 400;
    line-height: 1.45;
}

.incident-summary {
    color: #b45309 !important;
    font-weight: 400;
}

/* ===== Reason ===== */
.reason-label {
    display: grid;
    gap: 7px;
}
.reason-label span {
    color: #334155;
    font-size: 13px;
    font-weight: 400;
}
.reason-label textarea {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    padding: 10px 11px;
    background: #fff;
    color: #0f172a;
    font: inherit;
    min-height: 76px;
    resize: vertical;
}

/* ===== Court Picker ===== */
.court-picker {
    display: grid;
    gap: 12px;
    padding: 0;
    border: none;
    border-radius: 0;
    background: transparent;
}
.picker-head {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #14532d;
}
.picker-head > div {
    display: grid;
    flex: 1;
    gap: 2px;
}
.picker-head strong {
    font-size: 14px;
}
.picker-head small {
    color: #64748b;
    font-size: 11px;
    font-weight: 400;
    line-height: 1.4;
}
.picker-head span {
    padding: 4px 8px;
    border-radius: 999px;
    background: var(--admin-primary-soft, #dcfce7);
    color: var(--admin-primary-dark, #166534);
    font-size: 12px;
    font-weight: 400;
}
.picker-head button {
    border: 0;
    background: transparent;
    color: var(--admin-primary, #16a34a);
    font: inherit;
    font-size: 12px;
    font-weight: 400;
    cursor: pointer;
}
.court-chip-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 8px;
}
.court-chip-grid label {
    position: relative;
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 56px;
    padding: 10px 32px 10px 12px;
    border: 1px solid #d9e8d9;
    border-radius: 10px;
    background: #fff;
    cursor: pointer;
    transition: all 0.15s ease;
}
.court-chip-grid label::after {
    content: "";
    position: absolute;
    top: 10px;
    right: 10px;
    width: 16px;
    height: 16px;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    background: #fff;
    box-shadow: inset 0 0 0 3px #fff;
}
.court-chip-grid label.never-hover-class-placeholder {
    border-color: var(--admin-border);
    background: var(--admin-hover);
}
.court-chip-grid label.active {
    border-color: var(--admin-primary);
    background: var(--admin-primary-soft);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}
.court-chip-grid label.active::after {
    border-color: var(--admin-primary);
    background: var(--admin-primary);
}
.court-chip-grid label.locked {
    border-color: #d7e2d8;
    background: #f8faf9;
    cursor: not-allowed;
    opacity: 0.72;
}
.court-chip-grid label.locked::after {
    border-color: #cbd5e1;
    background: #e2e8f0;
    box-shadow: none;
}
.court-chip-grid label.locked strong {
    color: #64748b;
}
.court-chip-grid label.locked small {
    color: #b45309;
}
.court-chip-grid input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    pointer-events: none;
}
.court-chip-grid span {
    display: grid;
    gap: 2px;
}
.court-chip-grid strong {
    color: #1f2f25;
    font-size: 13px;
}
.court-chip-grid small {
    color: #64748b;
    font-size: 12px;
    font-weight: 400;
}

/* ===== Preview (collapsible) ===== */
.preview-details {
    border: 1px solid #d9e8d9;
    border-radius: 12px;
    background: #fbfefc;
    overflow: hidden;
    width: 100%;
    height: 100%;
    min-width: 0;
    max-width: 100%;
}

.preview-details[open] {
    width: 100%;
    border-radius: 12px;
}

.preview-details summary {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 46px;
    padding: 10px 13px;
    cursor: pointer;
    user-select: none;
}

.preview-details summary::-webkit-details-marker {
    display: none;
}

.preview-details summary::after {
    content: "Xem";
    margin-left: auto;
    color: #15803d;
    font-size: 12px;
    font-weight: 400;
}

.preview-details[open] summary::after {
    content: "Thu gọn";
}

.preview-details summary strong {
    color: #16231a;
    font-size: 13px;
}
.preview-details summary span {
    color: #64748b;
    font-size: 12px;
    font-weight: 400;
}
.preview-details summary em {
    color: #b45309;
    font-size: 12px;
    font-style: normal;
    font-weight: 400;
}

.lock-empty-preview {
    width: 100%;
    min-width: 0;
    height: 100%;
    min-height: 74px;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    border: 1px solid #d9e8d9;
    border-radius: 10px;
    background:
        linear-gradient(
            180deg,
            rgba(240, 253, 244, 0.72),
            rgba(255, 255, 255, 0.95)
        ),
        #fff;
}

.lock-empty-preview::before {
    content: "";
    flex: 0 0 auto;
    width: 8px;
    height: 34px;
    border-radius: 999px;
    background: #22c55e;
    opacity: 0.75;
}

.lock-empty-preview strong {
    flex: 0 0 auto;
    color: #14532d;
    font-size: 14px;
    font-weight: 400;
}

.lock-empty-preview span {
    color: #607267;
    font-size: 12px;
    font-weight: 400;
    line-height: 1.45;
    max-width: 760px;
}

.lock-preview-list {
    display: grid;
    gap: 6px;
    max-height: 200px;
    overflow-y: auto;
    padding: 0 14px 14px;
}
.lock-preview-list article {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 8px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
}
.lock-preview-list article.busy {
    border-color: #fed7aa;
    background: #fff7ed;
}
.lock-preview-list article > div {
    display: grid;
    gap: 2px;
    min-width: 0;
}
.lock-preview-list strong {
    color: #1f2937;
    font-size: 12px;
}
.lock-preview-list small {
    color: #64748b;
    font-size: 11px;
}
.lock-preview-list article > span {
    flex: 0 0 auto;
    padding: 3px 8px;
    border-radius: 999px;
    background: #eef2ff;
    color: #475569;
    font-size: 11px;
    font-weight: 400;
}
.lock-preview-list article.busy > span {
    background: #ffedd5;
    color: #c2410c;
}
.preview-more {
    display: block;
    padding: 4px 14px 12px;
    color: #607267;
    font-size: 12px;
}

/* ===== Schedule Card ===== */
.schedule-card {
    border: none;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    overflow: visible;
}
.schedule-headline {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    padding: 16px 20px;
    border-bottom: 1px solid #e2e8f0;
}
.schedule-headline-left h3 {
    margin: 0;
    color: #0f172a;
}
.schedule-date-note {
    display: block;
    margin-top: 4px;
    color: #64748b;
    font-size: 12px;
    font-weight: 400;
}
.schedule-headline-right {
    display: flex;
    align-items: center;
    gap: 16px;
}
.attention-btn {
    min-height: 36px;
    padding: 7px 12px;
    border: 1px solid #f59e0b;
    border-radius: 7px;
    background: #fffbeb;
    color: #92400e;
    font: inherit;
    font-size: 12px;
    font-weight: 400;
    white-space: nowrap;
    cursor: pointer;
}
.attention-btn:disabled {
    border-color: #dbe4de;
    background: #f7f9f7;
    color: #94a3b8;
    cursor: not-allowed;
}
.unlock-mode-btn {
    min-height: 36px;
    padding: 7px 12px;
    border: 1px solid #d8b7b3;
    border-radius: var(--admin-radius-sm, 7px);
    background: #fff;
    color: #8b4a44;
    font: inherit;
    font-size: 12px;
    font-weight: 400;
    white-space: nowrap;
    cursor: pointer;
}
.unlock-mode-btn.active {
    border-color: #b97870;
    background: #f3e3e0;
    color: #743c36;
}
.unlock-mode-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.eyebrow {
    margin: 0 0 4px;
    color: var(--admin-muted, #64748b);
    font-size: 11px;
    font-weight: 400;
    letter-spacing: 0.1em;
}

/* ===== Legend ===== */
.legend {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
    color: #64748b;
    font-size: 11px;
    font-weight: 400;
}
.legend span {
    display: flex;
    align-items: center;
    gap: 5px;
}
.legend i {
    width: 11px;
    height: 11px;
    border-radius: 3px;
    border: 1px solid #cbd5e1;
}
.dot-available {
    background: #fff;
}
.dot-booking {
    background: #cbd5e1;
}
.dot-manual {
    background: #fca5a5;
}
.dot-selected {
    background: var(--admin-primary, #16a34a);
    border-color: var(--admin-primary, #16a34a);
}
.dot-unlock-selected {
    background: #f3e3e0;
    border-color: #b97870 !important;
    box-shadow: inset 0 0 0 2px #fff;
}

/* ===== Quick Ranges ===== */
.quick-ranges {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 0;
    border-bottom: none;
    background: transparent;
    margin-top: 4px;
}
.quick-ranges button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 38px;
    padding: 8px 14px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fff;
    color: #344238;
    font: inherit;
    font-weight: 400;
    cursor: pointer;
    transition: all 0.15s ease;
}
.quick-ranges button strong {
    font-size: 14px;
    font-weight: 400;
}
.quick-ranges button small {
    font-size: 12px;
    font-weight: 400;
    opacity: 0.78;
}
.quick-ranges button.never-hover-class-placeholder:not(:disabled):not(.active) {
    border-color: var(--admin-border);
    background: var(--admin-hover);
    transform: translateY(-1px);
    box-shadow: 0 4px 9px rgba(0, 0, 0, 0.04);
}
.quick-ranges button.active {
    border-color: var(--admin-primary, #16a34a);
    background: var(--admin-primary, #16a34a);
    color: #fff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
.quick-ranges button.active strong,
.quick-ranges button.active small {
    color: #fff;
}
.quick-ranges button:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

/* ===== States ===== */
.state {
    padding: 40px;
    text-align: center;
    color: #64748b;
    font-weight: 400;
}

/* ===== Schedule Grid ===== */
.schedule-wrap {
    max-width: 100%;
    overflow-x: auto;
}
.schedule-grid {
    display: grid;
    width: 100%;
    min-width: 760px;
}
.grid-head,
.court-cell,
.slot-cell {
    min-height: 48px;
    border-right: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
}
.grid-head {
    display: grid;
    place-items: center;
    background: #f2f7ef;
    color: #334238;
    font-size: 11px;
    font-weight: 400;
}
.time-head {
    padding: 8px 4px;
    white-space: nowrap;
}
.sticky-col {
    position: sticky;
    left: 0;
    z-index: 2;
}
.grid-head.sticky-col {
    z-index: 3;
}
.court-cell {
    display: grid;
    align-content: center;
    gap: 3px;
    padding: 8px 10px;
    background: #fff;
}
.court-cell strong {
    color: #0f172a;
    font-size: 12px;
}
.court-cell span {
    color: #64748b;
    font-size: 11px;
}
.slot-cell {
    min-width: 0;
    padding: 0;
    border-top: 0;
    border-left: 0;
    background: #fff;
    cursor: pointer;
    transition:
        background 0.12s ease,
        box-shadow 0.12s ease;
}
.slot-cell.available.never-hover-class-placeholder {
    background: #d1fae5;
    box-shadow: inset 0 0 0 1px rgba(5, 150, 105, 0.35);
}
.slot-cell.booking {
    background: #cbd5e1;
}
.slot-cell.unavailable {
    background: #f8fafc;
    cursor: not-allowed;
}
.slot-cell.manual {
    background: repeating-linear-gradient(
        -45deg,
        #fca5a5,
        #fca5a5 4px,
        #fecaca 4px,
        #fecaca 8px
    );
    cursor: pointer;
}
.slot-cell.manual.unlock-selected {
    background: #fff;
    box-shadow:
        inset 0 0 0 3px #b97870,
        inset 0 0 0 999px rgba(185, 120, 112, 0.13);
}
.schedule-card.unlock-mode .slot-cell:not(.manual) {
    opacity: 0.42;
}
.schedule-card.unlock-mode .slot-cell.manual:not(.unlock-selected) {
    box-shadow: inset 0 0 0 1px rgba(139, 74, 68, 0.28);
}
.slot-cell.selected {
    background: var(--admin-primary, #16a34a);
    box-shadow: inset 0 0 0 2px var(--admin-primary-light, #22c55e);
}
.slot-cell.selected.never-hover-class-placeholder {
    background: var(--admin-primary-dark, #15803d);
}
.slot-cell:disabled {
    cursor: not-allowed;
}
.slot-cell.court-not-targeted {
    opacity: 0.48;
}

/* ===== Existing Locks Section ===== */
.locks-section {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 8px 28px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}
.locks-summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 20px;
    cursor: pointer;
    user-select: none;
}
.locks-summary > div {
    display: grid;
    gap: 3px;
}
.locks-summary strong {
    color: #0f172a;
    font-size: 15px;
}
.locks-summary span {
    color: #64748b;
    font-size: 12px;
    font-weight: 400;
}
.lock-table {
    margin: 0 20px 18px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}
.lock-table-head,
.lock-table-row {
    display: grid;
    grid-template-columns: minmax(150px, 1.1fr) 130px minmax(180px, 1.5fr) 112px;
    align-items: center;
    gap: 12px;
}
.lock-table-head {
    min-height: 34px;
    padding: 7px 12px;
    background: #f7faf8;
    color: #64748b;
    font-size: 11px;
    font-weight: 400;
}
.lock-table-row {
    min-height: 54px;
    padding: 8px 12px;
    border-top: 1px solid #edf1ee;
    color: #26352c;
    font-size: 12px;
}
.lock-table-row > div {
    display: grid;
    gap: 2px;
    min-width: 0;
}
.lock-table-row > div strong,
.lock-time {
    color: #1f2f25;
    font-size: 12px;
}
.lock-table-row small {
    color: #718096;
    font-size: 11px;
}
.lock-reason {
    min-width: 0;
    overflow: hidden;
    color: #5f6f65;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.lock-status {
    width: fit-content;
    padding: 4px 7px;
    border-radius: 5px;
    background: #dcfce7;
    color: #166534;
    font-size: 11px;
    font-weight: 400;
}
.lock-status.upcoming {
    background: #eff6ff;
    color: #1d4ed8;
}
.lock-status.ended {
    background: #f1f5f9;
    color: #64748b;
}
.lock-list {
    display: grid;
    gap: 8px;
    padding: 0 20px 20px;
}
.lock-row {
    display: grid;
    grid-template-columns: minmax(190px, 260px) minmax(0, 1fr);
    align-items: center;
    gap: 14px;
    padding: 12px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
}
.lock-row-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    min-width: 0;
}
.lock-row-head > div {
    display: grid;
    gap: 2px;
    min-width: 0;
}
.lock-row-head strong {
    overflow: hidden;
    color: #0f172a;
    font-size: 14px;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.lock-row-head span {
    color: #64748b;
    font-size: 12px;
    font-weight: 400;
    white-space: nowrap;
}
.lock-row-head button,
.text-danger-btn {
    flex: 0 0 auto;
    border: 0;
    background: transparent;
    color: #dc2626;
    font: inherit;
    font-size: 12px;
    font-weight: 400;
    cursor: pointer;
}
.lock-chip-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    min-width: 0;
}
.lock-chip-list button {
    display: grid;
    grid-template-columns: auto minmax(70px, 1fr) auto;
    align-items: center;
    gap: 8px;
    max-width: 320px;
    border: 1px solid #fecaca;
    border-radius: 999px;
    padding: 7px 10px;
    background: #fff7f7;
    color: #991b1b;
    font: inherit;
    text-align: left;
    cursor: pointer;
    transition: all 0.12s ease;
}
.lock-chip-time {
    font-size: 12px;
    font-weight: 400;
    white-space: nowrap;
}
.lock-chip-reason {
    min-width: 0;
    overflow: hidden;
    color: #64748b;
    font-size: 11px;
    font-weight: 400;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.lock-chip-action {
    color: #dc2626;
    font-size: 11px;
    font-weight: 400;
}
.lock-chip-list button.never-hover-class-placeholder:not(:disabled) {
    border-color: #fca5a5;
    background: #fff5f5;
}
.lock-chip-list button:disabled,
.lock-row-head button:disabled,
.text-danger-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

@media (max-width: 900px) {
    .lock-row {
        grid-template-columns: 1fr;
        align-items: stretch;
    }
}

/* ===== Sticky Bottom Bar ===== */
.sticky-bottom-bar {
    position: fixed;
    bottom: 16px;
    left: var(--owner-sidebar-width, 280px);
    right: 24px;
    z-index: 100;
    display: flex;
    justify-content: center;
    padding: 0;
    font-family: var(--admin-font-family, inherit);
    pointer-events: none;
}
.sticky-bottom-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    width: min(1400px, 100%);
    margin: 0;
    padding: 14px 20px;
    border: 1px solid #d9e8d9;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(12px);
    box-shadow: 0 -4px 24px rgba(15, 23, 42, 0.12);
    pointer-events: auto;
}
.sticky-bottom-info {
    display: grid;
    gap: 3px;
}
.sticky-bottom-info strong {
    color: #0f172a;
    font-size: 15px;
}
.sticky-bottom-info span {
    color: #64748b;
    font-size: 12px;
    font-weight: 400;
}
.sticky-bottom-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}
.sticky-btn-clear {
    border: 0;
    background: transparent;
    color: #dc2626;
    font: inherit;
    font-size: 13px;
    font-weight: 400;
    cursor: pointer;
}
.sticky-btn-box {
    min-height: 42px;
    padding: 0 16px;
    border: 1px solid #e6c5c0;
    border-radius: 8px;
    background: #fff7f6;
    color: #a4443b;
}
.sticky-btn-all-unlock {
    min-height: 42px;
    padding: 0 18px;
    border: 1px solid #d1bd86;
    border-radius: 8px;
    background: #fff8e6;
    color: #765f2d;
    font: inherit;
    font-weight: 400;
    cursor: pointer;
}
.sticky-btn-submit {
    min-height: 42px;
    padding: 0 20px;
    border: 0;
    border-radius: 10px;
    background: var(--admin-primary, #16a34a);
    color: #fff;
    font: inherit;
    font-weight: 400;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transition: all 0.15s ease;
}
.sticky-btn-submit.never-hover-class-placeholder:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.14);
}
.sticky-btn-submit:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
.sticky-btn-unlock {
    min-height: 42px;
    padding: 0 20px;
    border: 1px solid #c88e87;
    border-radius: var(--admin-radius-sm, 8px);
    background: #a85f57;
    color: #fff;
    font: inherit;
    font-weight: 400;
    cursor: pointer;
}
.sticky-btn-unlock:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.sticky-btn-all-unlock:disabled,
.sticky-btn-box:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

/* ===== Buttons ===== */
.primary-btn,
.secondary-btn {
    border: 0;
    border-radius: 9px;
    padding: 10px 16px;
    font: inherit;
    font-weight: 400;
    cursor: pointer;
}
.primary-btn {
    background: var(--admin-primary, #16a34a);
    color: #fff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
.secondary-btn {
    border: 1px solid var(--admin-border, #e5e7eb);
    background: #fff;
    color: var(--admin-text, #0f172a);
}
.btn-compact {
    padding: 8px 14px;
    font-size: 13px;
}
.primary-btn:disabled,
.secondary-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

/* ===== Modal ===== */
.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 120;
    display: grid;
    place-items: center;
    padding: 24px;
    background: rgba(15, 23, 42, 0.55);
    font-family: var(--admin-font-family, inherit);
}
.modal-backdrop :where(button, input, textarea, select) {
    font-family: inherit;
}
.conflict-modal {
    width: min(980px, 100%);
    max-height: min(760px, calc(100vh - 48px));
    display: grid;
    grid-template-rows: auto auto minmax(0, 1fr) auto;
    gap: 14px;
    overflow: hidden;
    border-radius: 14px;
    border: 1px solid #d7ead7;
    background: #fff;
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.24);
}
.conflict-modal header,
.conflict-modal footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 18px 20px 0;
}
.conflict-modal footer {
    padding: 0 20px 18px;
}
.conflict-modal h3 {
    margin: 0;
    color: #163222;
    font-size: 20px;
}
.icon-close {
    width: 36px;
    height: 36px;
    display: grid;
    place-items: center;
    border: 1px solid #d8e8d8;
    border-radius: 8px;
    background: #fff;
    color: #334155;
    cursor: pointer;
}
.lock-detail-modal {
    width: min(520px, 100%);
    display: grid;
    gap: 16px;
    overflow: hidden;
    border: 1px solid var(--admin-border, #d7ead7);
    border-radius: var(--admin-radius-lg, 12px);
    background: var(--admin-surface, #fff);
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.24);
}
.lock-detail-modal header,
.lock-detail-modal footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 18px 20px 0;
}
.lock-detail-modal h3 {
    margin: 0;
    color: var(--admin-text, #163222);
    font-size: 20px;
}
.lock-detail-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0;
    margin: 0 20px;
    border-top: 1px solid var(--admin-border, #e2e8f0);
    border-bottom: 1px solid var(--admin-border, #e2e8f0);
}
.lock-detail-list > div {
    display: grid;
    gap: 5px;
    min-width: 0;
    padding: 13px 0;
}
.lock-detail-list > div:nth-child(odd) {
    padding-right: 16px;
}
.lock-detail-list > div:nth-child(even) {
    padding-left: 16px;
    border-left: 1px solid var(--admin-border, #e2e8f0);
}
.lock-detail-list dt {
    color: var(--admin-muted, #64748b);
    font-size: 11px;
    font-weight: 400;
}
.lock-detail-list dd {
    min-width: 0;
    margin: 0;
    color: var(--admin-text, #1f2f25);
    font-size: 13px;
    font-weight: 400;
}
.lock-detail-reason {
    display: grid;
    gap: 5px;
    margin: 0 20px;
}
.lock-detail-reason > span {
    color: var(--admin-muted, #64748b);
    font-size: 11px;
    font-weight: 400;
}
.lock-detail-reason p {
    margin: 0;
    color: var(--admin-text, #334238);
    font-size: 13px;
    line-height: 1.5;
}
.lock-detail-modal footer {
    padding: 0 20px 18px;
}
.conflict-help {
    margin: 0 20px;
    padding: 12px 14px;
    border-radius: 8px;
    background: #f0fdf4;
    color: #496355;
    font-size: 13px;
    line-height: 1.45;
}
.conflict-list {
    display: grid;
    gap: 10px;
    min-height: 0;
    overflow-y: auto;
    padding: 0 20px;
}
.conflict-card {
    display: grid;
    grid-template-columns: minmax(260px, 0.92fr) minmax(340px, 1.08fr);
    gap: 18px;
    align-items: start;
    padding: 14px;
    border: 1px solid #e2eadf;
    border-radius: 10px;
    background: #fbfffb;
}
.conflict-main {
    display: grid;
    gap: 4px;
}
.conflict-main strong {
    color: #12301f;
    font-size: 14px;
}
.conflict-main span,
.conflict-main small {
    color: #5d7165;
    font-size: 12px;
    line-height: 1.35;
}
.conflict-impact {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 4px;
}
.conflict-impact span {
    min-height: 26px;
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border: 1px solid #dbe8df;
    border-radius: 7px;
    background: #fff;
    color: #43564a;
    font-size: 12px;
    font-weight: 400;
}
.conflict-actions {
    display: grid;
    gap: 12px;
}
.resolution-group {
    display: grid;
    gap: 6px;
}
.resolution-label {
    color: #64748b;
    font-size: 11px;
    font-weight: 400;
    letter-spacing: 0;
    text-transform: uppercase;
}
.scope-switch {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}
.scope-switch button {
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 9px 12px;
    border: 1px solid #d7e5da;
    border-radius: 7px;
    background: #fff;
    color: #475569;
    font-family: inherit;
    font-size: 12px;
    font-weight: 400;
    line-height: 1.35;
    text-align: center;
    cursor: pointer;
}
.scope-switch button.active {
    border-color: #79aa86;
    background: #edf7ef;
    color: #1f5130;
    box-shadow: inset 3px 0 0 #6a9d79;
}
.conflict-select {
    width: 100%;
    height: 42px;
    border: 1px solid #d8e8d8;
    border-radius: 8px;
    padding: 0 10px;
    background: #fff;
    color: #1f2937;
    font: inherit;
    font-weight: 400;
}
.no-alternative {
    display: grid;
    gap: 3px;
    padding: 10px 12px;
    border: 1px solid #dfe8e1;
    border-radius: 7px;
    background: #f6f8f6;
}
.no-alternative strong {
    color: #46564b;
    font-size: 12px;
}
.no-alternative span {
    color: #718078;
    font-size: 11px;
    line-height: 1.4;
}
.conflict-radios {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}
.resolution-option {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 44px;
    padding: 9px 12px;
    border: 1px solid #cddfd1;
    border-radius: 7px;
    background: #fff;
    color: #2f5a3a;
    font-family: inherit;
    font-size: 13px;
    font-weight: 400;
    line-height: 1.35;
    text-align: center;
    cursor: pointer;
}
.resolution-option.active {
    border-color: #74a584;
    background: #eaf4ed;
    box-shadow: inset 3px 0 0 #5f9872;
}
.resolution-option.danger {
    color: #80534e;
}
.resolution-option.danger.active {
    border-color: #d3aaa4;
    background: #f3e3e0;
    box-shadow: inset 3px 0 0 #b97870;
}
.resolution-option.cash {
    color: #765f2d;
}
.resolution-option.cash.active {
    border-color: #d1bd86;
    background: #f3eddc;
    box-shadow: inset 3px 0 0 #b69a54;
}
.resolution-option:last-child:nth-child(odd) {
    grid-column: 1 / -1;
}
.conflict-empty {
    padding: 28px 18px;
    border: 1px dashed #cfe0d2;
    border-radius: 8px;
    background: #f8fcf9;
    color: #607267;
    text-align: center;
    font-weight: 400;
}

/* ===== Responsive ===== */
@media (max-width: 860px) {
    .conflict-card {
        grid-template-columns: 1fr;
    }
    .scope-switch,
    .conflict-radios {
        grid-template-columns: 1fr;
    }
    .lock-detail-list {
        grid-template-columns: 1fr;
    }
    .lock-detail-list > div:nth-child(n) {
        padding: 11px 0;
        border-left: 0;
    }
    .lock-detail-list > div + div {
        border-top: 1px solid var(--admin-border, #e2e8f0);
    }
    .sticky-bottom-bar {
        left: 12px;
        right: 12px;
        bottom: 12px;
    }

    .config-strip {
        grid-template-columns: 1fr;
    }
    .config-left {
        width: 100%;
    }
    .config-left :deep(.mini-cal) {
        max-width: 100%;
    }

    .lock-empty-preview {
        width: 100%;
        min-width: 0;
        align-items: flex-start;
    }
    .preview-details {
        width: 100%;
        min-width: 0;
    }
    .config-left {
        display: flex;
        justify-content: center;
        width: 100%;
    }
    .schedule-headline {
        flex-direction: column;
        gap: 12px;
    }
    .schedule-headline-right {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    .legend {
        justify-content: flex-start;
    }
    .lock-table {
        margin: 0 12px 14px;
        border: 0;
        overflow: visible;
    }
    .lock-table-head {
        display: none;
    }
    .lock-table-row {
        grid-template-columns: 1fr auto;
        gap: 6px 12px;
        margin-top: 8px;
        border: 1px solid #e2e8f0;
        border-radius: 7px;
    }
    .lock-reason {
        grid-column: 1 / -1;
        white-space: normal;
    }
    .quick-ranges {
        padding: 14px;
    }
    .quick-ranges button {
        flex: 1 1 120px;
    }
    .sticky-bottom-inner {
        flex-direction: column;
        gap: 12px;
        text-align: center;
    }
}
</style>
