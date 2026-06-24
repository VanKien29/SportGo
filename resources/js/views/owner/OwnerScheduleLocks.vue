<template>
    <section class="schedule-lock-page">
        <!-- Floating Lock Button -->
        <div
            v-if="selectedSlots.length"
            class="floating-add-container"
            style="z-index: 100"
        >
            <button
                class="btn-float-add"
                type="button"
                :disabled="saving || previewing || !canSubmit"
                @click="createLock"
                title="Khóa lịch"
            >
                <AppIcon name="lock" size="20" />
                <span class="btn-float-text"
                    >Khóa {{ selectedSlots.length }} ô</span
                >
            </button>
        </div>

        <div v-if="error" class="alert error">{{ error }}</div>
        <div v-if="notice" class="alert success">{{ notice }}</div>

        <div
            v-if="lockConflictPreview"
            class="modal-backdrop"
            @click.self="closeConflictPreview"
        >
            <section class="conflict-modal">
                <header>
                    <div>
                        <p class="eyebrow">KHÓA SÂN ĐỘT XUẤT</p>
                        <h3>
                            {{ lockConflictPreview.affected_count }} booking bị
                            ảnh hưởng
                        </h3>
                    </div>
                    <button
                        type="button"
                        class="icon-close"
                        @click="closeConflictPreview"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                          <line x1="18" y1="6" x2="6" y2="18"></line>
                          <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </header>

                <p class="conflict-help">
                    Ưu tiên đổi sang sân cùng loại còn trống. Nếu không đổi
                    được, chọn hoàn ví hoặc ghi nhận đã hoàn tiền mặt tại sân.
                </p>

                <div class="conflict-list">
                    <article
                        v-for="item in lockConflictPreview.items"
                        :key="item.booking_item_id"
                        class="conflict-row"
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
                                {{ item.court?.name || "Sân" }} ·
                                {{ time(item.start_time) }} -
                                {{ time(item.end_time) }} ·
                                {{
                                    item.payment_status === "paid"
                                        ? "Đã thanh toán"
                                        : "Chưa thanh toán"
                                }}
                            </small>
                        </div>

                        <div class="conflict-actions">
                            <label
                                v-if="item.alternatives?.length"
                                class="radio-line"
                            >
                                <input
                                    v-model="
                                        lockResolutions[item.booking_item_id]
                                            .action
                                    "
                                    type="radio"
                                    value="switch"
                                />
                                Đổi sân
                            </label>
                            <select
                                v-if="item.alternatives?.length"
                                v-model="
                                    lockResolutions[item.booking_item_id]
                                        .venue_court_id
                                "
                                :disabled="
                                    lockResolutions[item.booking_item_id]
                                        .action !== 'switch'
                                "
                            >
                                <option
                                    v-for="court in item.alternatives"
                                    :key="court.id"
                                    :value="court.id"
                                >
                                    {{ court.name }}
                                </option>
                            </select>
                            <label class="radio-line danger">
                                <input
                                    v-model="
                                        lockResolutions[item.booking_item_id]
                                            .action
                                    "
                                    type="radio"
                                    value="cancel"
                                />
                                Hủy/hoàn ví
                            </label>
                            <label class="radio-line cash">
                                <input
                                    v-model="
                                        lockResolutions[item.booking_item_id]
                                            .action
                                    "
                                    type="radio"
                                    value="cash_refund"
                                />
                                Đã hoàn tiền mặt
                            </label>
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
                        type="button"
                        class="primary-btn"
                        :disabled="saving"
                        @click="createLockWithResolutions"
                    >
                        {{ saving ? "Đang xử lý..." : "Khóa và xử lý booking" }}
                    </button>
                </footer>
            </section>
        </div>

        <div class="content-grid">
            <aside class="side-panel">
                <article class="form-card">
                    <div class="card-head compact-head">
                        <div>
                            <p class="eyebrow">TẠO KHÓA MỚI</p>
                            <h3>Chọn khoảng không nhận khách</h3>
                        </div>
                    </div>

                    <form @submit.prevent="createLock">
                        <div class="mode-switch">
                            <button
                                type="button"
                                :class="{ active: form.lock_mode === 'slots' }"
                                @click="setLockMode('slots')"
                            >
                                Theo khung giờ
                            </button>
                            <button
                                type="button"
                                :class="{
                                    active: form.lock_mode === 'whole_day',
                                }"
                                @click="setLockMode('whole_day')"
                            >
                                Theo ngày
                            </button>
                        </div>

                        <div class="date-grid">
                            <label>
                                Từ ngày
                                <input
                                    v-model="form.start_date"
                                    type="date"
                                    :min="today"
                                    required
                                    @change="handleStartDateChange"
                                />
                            </label>
                            <label>
                                Đến ngày
                                <input
                                    v-model="form.end_date"
                                    type="date"
                                    :min="form.start_date || today"
                                    required
                                    @change="handleEndDateChange"
                                />
                            </label>
                        </div>

                        <div
                            v-if="form.lock_mode === 'slots'"
                            class="selection-box"
                            :class="{ empty: !selectedSlots.length }"
                        >
                            <strong>{{ selectionSummary }}</strong>
                            <span>{{ dateRangeLabel }}</span>
                            <button
                                v-if="selectedSlots.length"
                                type="button"
                                @click="clearSelection"
                            >
                                Bỏ chọn tất cả
                            </button>
                        </div>

                        <div v-else class="court-picker">
                            <div class="picker-head">
                                <strong>Chọn sân áp dụng</strong>
                                <span>{{ selectedCourtIds.length }} sân</span>
                                <button type="button" @click="toggleAllCourts">
                                    {{
                                        selectedCourtIds.length ===
                                        scheduleCourts.length
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
                                        active: selectedCourtIds.includes(
                                            court.id,
                                        ),
                                    }"
                                >
                                    <input
                                        v-model="selectedCourtIds"
                                        type="checkbox"
                                        :value="court.id"
                                    />
                                    <span>
                                        <strong>{{ court.name }}</strong>
                                        <small>{{
                                            court.court_type?.name || "-"
                                        }}</small>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <section
                            v-if="lockPreviewRows.length"
                            class="lock-preview-panel"
                        >
                            <div class="preview-headline">
                                <div>
                                    <strong>Preview áp dụng</strong>
                                    <span
                                        >{{ lockPreviewStats.total }} lượt
                                        khóa</span
                                    >
                                </div>
                                <em v-if="lockPreviewStats.knownBusy">
                                    {{ lockPreviewStats.knownBusy }} lượt đang
                                    bận ở ngày đang xem
                                </em>
                            </div>

                            <div class="lock-preview-list">
                                <article
                                    v-for="row in lockPreviewRows.slice(0, 12)"
                                    :key="row.key"
                                    :class="{ busy: row.isBusy }"
                                >
                                    <div>
                                        <strong>{{ row.dateLabel }}</strong>
                                        <small
                                            >{{ row.courtName }} ·
                                            {{ row.timeText }}</small
                                        >
                                    </div>
                                    <span>{{ row.statusLabel }}</span>
                                </article>
                            </div>

                            <small
                                v-if="lockPreviewRows.length > 12"
                                class="preview-more"
                            >
                                Còn {{ lockPreviewRows.length - 12 }} lượt khóa
                                khác.
                            </small>
                        </section>

                        <label>
                            Lý do khóa
                            <textarea
                                v-model.trim="form.reason"
                                rows="4"
                                maxlength="500"
                                placeholder="Ví dụ: Bảo trì mặt sân, nghỉ lễ, sự kiện nội bộ..."
                                required
                            />
                        </label>

                        <button
                            class="primary-btn"
                            type="submit"
                            :disabled="saving || previewing || !canSubmit"
                        >
                            {{
                                saving
                                    ? "Đang khóa..."
                                    : previewing
                                      ? "Đang kiểm tra..."
                                      : lockButtonLabel
                            }}
                        </button>
                    </form>
                </article>

                <article class="locks-card">
                    <div class="card-head compact-head">
                        <div>
                            <p class="eyebrow">MỞ KHÓA</p>
                            <h3>Khoảng đã khóa</h3>
                            <span>{{ date(form.start_date) }}</span>
                        </div>
                        <button
                            v-if="locks.length"
                            class="text-danger-btn"
                            type="button"
                            :disabled="Boolean(deletingId)"
                            @click="
                                removeLocks(
                                    locks,
                                    'Mở tất cả khoảng đã khóa trong ngày này?',
                                )
                            "
                        >
                            Mở tất cả
                        </button>
                    </div>

                    <div v-if="!locks.length" class="state compact-state">
                        Ngày này chưa có khoảng khóa thủ công.
                    </div>
                    <div v-else class="lock-list">
                        <div
                            v-for="group in lockGroups"
                            :key="group.courtId"
                            class="lock-group"
                        >
                            <div class="lock-group-head">
                                <div>
                                    <strong>{{ group.courtName }}</strong>
                                    <span>{{ group.items.length }} khoảng</span>
                                </div>
                                <button
                                    type="button"
                                    :disabled="Boolean(deletingId)"
                                    @click="
                                        removeLocks(
                                            group.items,
                                            `Mở tất cả khoảng khóa của ${group.courtName}?`,
                                        )
                                    "
                                >
                                    Mở sân này
                                </button>
                            </div>
                            <div class="lock-chip-list">
                                <button
                                    v-for="lock in group.items"
                                    :key="lock.id"
                                    type="button"
                                    :disabled="Boolean(deletingId)"
                                    :title="lock.reason || 'Mở khoảng khóa'"
                                    @click="removeLock(lock)"
                                >
                                    <strong>
                                        {{ time(lock.start_time) }} -
                                        {{ time(lock.end_time) }}
                                    </strong>
                                    <span>Mở</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </article>
            </aside>

            <article class="schedule-card">
                <div class="card-head schedule-headline">
                    <div>
                        <p class="eyebrow">TRẠNG THÁI TRONG NGÀY</p>
                        <h3>{{ date(form.start_date) }}</h3>
                    </div>
                    <div class="header-actions">
                        <div class="legend">
                            <span><i class="available"></i>Trống</span>
                            <span><i class="booking"></i>Đã đặt</span>
                            <span><i class="holding"></i>Đang giữ</span>
                            <span><i class="manual"></i>Đã khóa</span>
                        </div>
                        <button
                            class="secondary-btn btn-refresh"
                            type="button"
                            :disabled="loading"
                            @click="loadData"
                        >
                            Làm mới
                        </button>
                    </div>
                </div>

                <div class="quick-ranges">
                    <button
                        v-for="range in quickRanges"
                        :key="range.key"
                        type="button"
                        :class="{ active: activeTimePeriod === range.key }"
                        :disabled="loading"
                        @click="activeTimePeriod = range.key"
                    >
                        <strong>{{ range.label }}</strong>
                        <small>{{ range.start }} - {{ range.end }}</small>
                    </button>
                </div>

                <div v-if="loading" class="state">Đang tải lịch sân...</div>
                <div v-else-if="!selectedClusterId" class="state">
                    Vui lòng chọn cụm sân ở thanh bên.
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

                        <template
                            v-for="court in scheduleCourts"
                            :key="court.id"
                        >
                            <div class="court-cell sticky-col">
                                <strong>{{ court.name }}</strong>
                                <span>{{ court.court_type?.name }}</span>
                            </div>
                            <button
                                v-for="slot in activePeriodSlots"
                                :key="`${court.id}-${slot.start_time}`"
                                class="slot-cell"
                                :class="slotClass(court.id, slot)"
                                :title="slotTitle(court.id, slot)"
                                type="button"
                                :disabled="isBusy(court.id, slot)"
                                :aria-pressed="isSelected(court.id, slot)"
                                @click="pickSlot(court, slot)"
                            />
                        </template>
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>

<script>
import { bookingService } from "../../services/bookingService.js";
import { ownerScheduleLockService } from "../../services/ownerScheduleLocks.js";

export default {
    name: "OwnerScheduleLocks",
    data() {
        const today = new Date().toISOString().split("T")[0];

        return {
            today,
            selectedClusterId: localStorage.getItem("selected_cluster") || "",
            locks: [],
            scheduleSlots: [],
            scheduleCourts: [],
            scheduleSlotStatuses: [],
            loading: true,
            saving: false,
            deletingId: "",
            error: "",
            notice: "",
            form: {
                start_date: today,
                end_date: today,
                lock_mode: "slots",
                reason: "",
            },
            selectedSlots: [],
            selectedCourtIds: [],
            quickRanges: [
                { key: "morning", label: "Sáng", start: "06:00", end: "12:00" },
                {
                    key: "afternoon",
                    label: "Chiều",
                    start: "12:00",
                    end: "18:00",
                },
                { key: "evening", label: "Tối", start: "18:00", end: "22:00" },
            ],
            activeTimePeriod: "morning",
            previewing: false,
            pendingLockPayload: null,
            lockConflictPreview: null,
            lockResolutions: {},
        };
    },
    computed: {
        canSubmit() {
            const hasTarget =
                this.form.lock_mode === "whole_day"
                    ? this.selectedCourtIds.length > 0
                    : this.selectedSlots.length > 0;

            return (
                hasTarget &&
                this.form.start_date &&
                this.form.end_date &&
                this.form.reason
            );
        },
        dateRangeLabel() {
            if (this.form.start_date === this.form.end_date) {
                return this.date(this.form.start_date);
            }

            return `${this.date(this.form.start_date)} - ${this.date(this.form.end_date)}`;
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
            return new Set(
                this.selectedSlots.map((slot) => slot.venue_court_id),
            ).size;
        },
        selectionSummary() {
            if (!this.selectedSlots.length) {
                return "Chọn thời gian trên bảng bên phải";
            }

            return `Thời gian: ${this.selectedTimeText} · ${this.selectedCourtCount} sân`;
        },
        lockButtonLabel() {
            if (this.form.lock_mode === "whole_day") {
                return "Khóa cả ngày";
            }

            return "Khóa thời gian đã chọn";
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
        lockGroups() {
            const grouped = this.locks.reduce((result, lock) => {
                const courtId =
                    lock.venue_court_id || lock.venue_court?.id || "unknown";
                if (!result[courtId]) {
                    result[courtId] = {
                        courtId,
                        courtName: lock.venue_court?.name || "Sân chưa rõ",
                        items: [],
                    };
                }
                result[courtId].items.push(lock);
                return result;
            }, {});

            return Object.values(grouped)
                .map((group) => ({
                    ...group,
                    items: group.items.sort((a, b) =>
                        a.start_time.localeCompare(b.start_time),
                    ),
                }))
                .sort((a, b) => a.courtName.localeCompare(b.courtName));
        },
        dateRangeDates() {
            const start = new Date(`${this.form.start_date}T00:00:00`);
            const end = new Date(`${this.form.end_date}T00:00:00`);
            if (
                Number.isNaN(start.getTime()) ||
                Number.isNaN(end.getTime()) ||
                end < start
            ) {
                return [];
            }

            const dates = [];
            for (
                let date = new Date(start);
                date <= end && dates.length <= 45;
                date.setDate(date.getDate() + 1)
            ) {
                dates.push(this.isoDate(date));
            }

            return dates;
        },
        lockTargetRanges() {
            return this.form.lock_mode === "whole_day"
                ? this.buildWholeDayRanges().map((range) => ({
                      ...range,
                      court_name:
                          this.scheduleCourts.find(
                              (court) => court.id === range.venue_court_id,
                          )?.name || "Sân",
                  }))
                : this.buildSelectedRanges().map((range) => ({
                      ...range,
                      court_name:
                          this.selectedSlots.find(
                              (slot) =>
                                  slot.venue_court_id === range.venue_court_id,
                          )?.court_name ||
                          this.scheduleCourts.find(
                              (court) => court.id === range.venue_court_id,
                          )?.name ||
                          "Sân",
                  }));
        },
        lockPreviewRows() {
            return this.dateRangeDates.flatMap((date) =>
                this.lockTargetRanges.map((range) => {
                    const isStartDate = date === this.form.start_date;
                    const representativeSlot = {
                        start_time: range.start_time,
                        end_time: range.end_time,
                    };
                    const isBusy = isStartDate
                        ? this.isRangeBusy(
                              range.venue_court_id,
                              representativeSlot,
                          )
                        : false;

                    return {
                        key: `${date}-${range.venue_court_id}-${range.start_time}-${range.end_time}`,
                        date,
                        dateLabel: this.date(date),
                        courtName: range.court_name,
                        timeText: `${this.time(range.start_time)} - ${this.time(range.end_time)}`,
                        isBusy,
                        statusLabel: isBusy
                            ? "Đang có lịch"
                            : isStartDate
                              ? "Trống"
                              : "Sẽ kiểm tra khi khóa",
                    };
                }),
            );
        },
        lockPreviewStats() {
            return {
                total: this.lockPreviewRows.length,
                knownBusy: this.lockPreviewRows.filter((row) => row.isBusy)
                    .length,
            };
        },
        activePeriod() {
            return (
                this.quickRanges.find(
                    (range) => range.key === this.activeTimePeriod,
                ) || this.quickRanges[0]
            );
        },
        activePeriodSlots() {
            return this.scheduleSlots.filter((slot) => {
                const start = this.time(slot.start_time);
                return (
                    start >= this.activePeriod.start &&
                    start < this.activePeriod.end
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
        async handleClusterChanged(event) {
            this.selectedClusterId =
                event.detail?.id ||
                localStorage.getItem("selected_cluster") ||
                "";
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

            const response = await bookingService.getSchedule({
                venue_cluster_id: this.selectedClusterId,
                booking_date: this.form.start_date,
                booking_type: "single",
            });
            this.scheduleSlots = response.time_slots || [];
            this.scheduleCourts = response.courts || [];
            this.scheduleSlotStatuses = response.slot_statuses || [];
        },
        async loadLocks() {
            if (!this.selectedClusterId || !this.form.start_date) return;

            const response = await ownerScheduleLockService.list({
                venue_cluster_id: this.selectedClusterId,
                booking_date: this.form.start_date,
            });
            this.locks = response.data || [];
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
                    this.pendingLockPayload = payload;
                    this.lockConflictPreview = data;
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
            return {
                start_date: this.form.start_date,
                end_date: this.form.end_date,
                reason: this.form.reason,
                slots:
                    this.form.lock_mode === "whole_day"
                        ? this.buildWholeDayRanges()
                        : this.buildSelectedRanges(),
            };
        },
        defaultLockResolutions(items = []) {
            return items.reduce((result, item) => {
                const firstAlternative = item.alternatives?.[0]?.id || "";
                result[item.booking_item_id] = {
                    booking_item_id: item.booking_item_id,
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
        },
        async removeLock(lock) {
            await this.removeLocks(
                [lock],
                `Mở lại ${this.time(lock.start_time)} - ${this.time(lock.end_time)} tại ${lock.venue_court?.name}?`,
            );
        },
        async removeLocks(locks, confirmMessage) {
            const targets = (locks || []).filter(Boolean);
            if (!targets.length || !window.confirm(confirmMessage)) return;

            this.deletingId =
                targets.length === 1 ? targets[0].id : `bulk-${Date.now()}`;
            this.error = "";
            this.notice = "";
            try {
                await Promise.all(
                    targets.map((lock) =>
                        ownerScheduleLockService.remove(lock.id),
                    ),
                );
                this.notice =
                    targets.length === 1
                        ? "Đã mở lại khung giờ."
                        : `Đã mở lại ${targets.length} khoảng khóa.`;
                await Promise.all([this.loadSchedule(), this.loadLocks()]);
            } catch (error) {
                this.error = error.message || "Không thể mở lại khung giờ.";
            } finally {
                this.deletingId = "";
            }
        },
        statusFor(courtId, slot) {
            return (
                this.scheduleSlotStatuses.find(
                    (status) =>
                        status.venue_court_id === courtId &&
                        status.start_time === slot.start_time,
                ) || null
            );
        },
        isBusy(courtId, slot) {
            return !this.statusFor(courtId, slot)?.is_available;
        },
        isRangeBusy(courtId, range) {
            const start = this.minutes(range.start_time);
            const end = this.minutes(range.end_time);

            return this.scheduleSlotStatuses.some((status) => {
                if (status.venue_court_id !== courtId || status.is_available) {
                    return false;
                }

                const statusStart = this.minutes(status.start_time);
                const statusEnd = this.minutes(status.end_time);

                return statusStart < end && statusEnd > start;
            });
        },
        slotClass(courtId, slot) {
            const status = this.statusFor(courtId, slot);
            if (this.isSelected(courtId, slot)) return "selected";
            if (!status || status.is_available) return "available";
            if (status.busy_source === "booking") return "booking";
            if (status.busy_status === "manual") return "manual";
            return "holding";
        },
        slotTitle(courtId, slot) {
            const status = this.statusFor(courtId, slot);
            if (this.isSelected(courtId, slot))
                return `${this.time(slot.start_time)} - ${this.time(slot.end_time)} · Đã chọn`;
            if (!status || status.is_available)
                return `${this.time(slot.start_time)} - ${this.time(slot.end_time)} · Trống`;
            if (status.busy_source === "booking") return "Đã có booking";
            if (status.busy_status === "manual")
                return `Đã khóa: ${status.lock_reason || "Không có lý do"}`;
            return "Đang được giữ chỗ";
        },
        pickSlot(court, slot) {
            if (this.form.lock_mode !== "slots") return;

            const key = this.slotKey(court.id, slot.start_time);
            const existingIndex = this.selectedSlots.findIndex(
                (item) => item.key === key,
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
        slotKey(courtId, startTime) {
            return `${courtId}-${this.withSeconds(startTime)}`;
        },
        isSelected(courtId, slot) {
            const key = this.slotKey(courtId, slot.start_time);
            return this.selectedSlots.some((item) => item.key === key);
        },
        clearSelection() {
            this.selectedSlots = [];
            this.selectedCourtIds = [];
        },
        setLockMode(mode) {
            this.form.lock_mode = mode;
            this.clearSelection();
        },
        toggleAllCourts() {
            if (this.selectedCourtIds.length === this.scheduleCourts.length) {
                this.selectedCourtIds = [];
                return;
            }

            this.selectedCourtIds = this.scheduleCourts.map(
                (court) => court.id,
            );
        },
        buildWholeDayRanges() {
            return this.selectedCourtIds.map((courtId) => ({
                venue_court_id: courtId,
                start_time: "00:00:00",
                end_time: "24:00:00",
            }));
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
        date(value) {
            if (!value) return "-";
            return new Intl.DateTimeFormat("vi-VN").format(
                new Date(`${value}T00:00:00`),
            );
        },
    },
};
</script>

<style scoped>
.schedule-lock-page {
    display: grid;
    gap: 18px;
    max-width: 1320px;
}
.card-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
}
.card-head h3 {
    margin: 0;
    color: var(--admin-text);
}
.header-actions {
    display: flex;
    align-items: center;
    gap: 16px;
}
.btn-refresh {
    padding: 8px 14px;
    font-size: 13px;
}
.eyebrow {
    margin: 0 0 6px;
    color: var(--admin-muted);
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 0.1em;
}
.content-grid {
    display: grid;
    grid-template-columns: 360px minmax(0, 1fr);
    gap: 18px;
    align-items: start;
}
.side-panel {
    display: grid;
    gap: 14px;
    align-self: start;
}
.form-card,
.schedule-card,
.locks-card {
    border: 1px solid var(--admin-border);
    border-radius: 14px;
    background: var(--admin-surface, #fff);
    box-shadow: 0 8px 28px rgba(15, 23, 42, 0.04);
}
.form-card,
.locks-card {
    padding: 18px;
}
.compact-head {
    align-items: center;
}
.compact-head h3 {
    font-size: 17px;
}
.compact-head span {
    display: inline-flex;
    margin-top: 4px;
    color: var(--admin-muted);
    font-size: 12px;
    font-weight: 750;
}
.form-card form {
    display: grid;
    gap: 13px;
    margin-top: 16px;
}
.mode-switch {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px;
    padding: 4px;
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    background: var(--admin-surface-muted);
}
.mode-switch button {
    min-height: 42px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: var(--admin-faint);
    font: inherit;
    font-weight: 900;
    cursor: pointer;
}
.mode-switch button.active {
    background: var(--admin-primary, #000000);
    color: var(--admin-bg);
    box-shadow: 0 6px 14px rgba(0, 0, 0, 0.08);
}
.date-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}
.form-card label {
    display: grid;
    gap: 7px;
    color: var(--admin-text);
    font-size: 13px;
    font-weight: 850;
}
.form-card input,
.form-card select,
.form-card textarea {
    width: 100%;
    border: 1px solid var(--admin-border);
    border-radius: 9px;
    padding: 10px 11px;
    background: var(--admin-surface, #fff);
    color: var(--admin-text);
    font: inherit;
}
.form-card textarea {
    min-height: 92px;
    resize: vertical;
}
.time-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.form-note {
    padding: 11px;
    border-radius: 9px;
    background: var(--admin-surface-muted);
    color: var(--admin-muted);
    font-size: 12px;
    line-height: 1.5;
}
.court-picker {
    display: grid;
    gap: 12px;
    padding: 12px;
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    background: var(--admin-surface-muted);
}
.picker-head {
    display: grid;
    grid-template-columns: 1fr auto auto;
    align-items: center;
    gap: 10px;
    color: var(--admin-text);
}
.picker-head span {
    padding: 4px 8px;
    border-radius: 999px;
    background: var(--admin-primary-soft, #f3f4f6);
    color: var(--admin-primary-dark, #000000);
    font-size: 12px;
    font-weight: 900;
}
.picker-head button {
    border: 0;
    background: transparent;
    color: var(--admin-primary, #000000);
    font: inherit;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
}
.court-chip-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}
.court-chip-grid label {
    position: relative;
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 68px;
    padding: 11px 34px 11px 12px;
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    background: var(--admin-surface, #fff);
    cursor: pointer;
    transition:
        border-color 0.16s ease,
        background 0.16s ease,
        box-shadow 0.16s ease,
        transform 0.16s ease;
}
.court-chip-grid label::after {
    content: "";
    position: absolute;
    top: 12px;
    right: 12px;
    width: 16px;
    height: 16px;
    border: 1px solid var(--admin-border);
    border-radius: 999px;
    background: var(--admin-surface, #fff);
    box-shadow: inset 0 0 0 3px var(--admin-bg);
}
.court-chip-grid label:hover {
    border-color: var(--admin-border);
    background: var(--admin-hover);
    transform: translateY(-1px);
}
.court-chip-grid label.active {
    border-color: var(--admin-primary);
    background: var(--admin-primary-soft);
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.08);
}
.court-chip-grid label.active::after {
    border-color: var(--admin-primary);
    background: var(--admin-primary);
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
    color: var(--admin-text);
    font-size: 13px;
}
.court-chip-grid small {
    color: var(--admin-muted);
    font-size: 12px;
    font-weight: 700;
}
.primary-btn,
.secondary-btn,
.danger-btn {
    border: 0;
    border-radius: 9px;
    padding: 10px 14px;
    font: inherit;
    font-weight: 850;
    cursor: pointer;
}
.primary-btn {
    background: var(--admin-primary, #000000);
    color: #fff;
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.08);
}
.secondary-btn {
    border: 1px solid var(--admin-border, #e5e7eb);
    background: var(--admin-surface, #fff);
    color: var(--admin-text, #000000);
}
.danger-btn {
    border: 1px solid #fecaca;
    background: #fff5f5;
    color: #b91c1c;
}
.primary-btn:disabled,
.secondary-btn:disabled,
.danger-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
.alert {
    padding: 13px 15px;
    border-radius: 10px;
    font-weight: 800;
}
.alert.error {
    background: #fee2e2;
    color: #991b1b;
}
.alert.success {
    background: #dcfce7;
    color: #166534;
}
.schedule-card {
    overflow: hidden;
}
.schedule-headline {
    padding: 16px 18px;
    border-bottom: 1px solid var(--admin-border);
}
.legend {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 12px;
    color: var(--admin-muted);
    font-size: 11px;
    font-weight: 800;
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
    border: 1px solid var(--admin-border);
}
.legend .available {
    background: var(--admin-surface, #fff);
}
.legend .booking {
    background: #cbd5e1;
}
.legend .holding {
    background: #fde68a;
}
.legend .manual {
    background: #fca5a5;
}
.state {
    padding: 36px;
    text-align: center;
    color: var(--admin-muted);
}
.compact-state {
    padding: 18px 6px 4px;
    text-align: left;
    font-size: 13px;
}
.schedule-wrap {
    max-width: 100%;
    overflow-x: auto;
    border-top: 1px solid var(--admin-border);
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
    border-right: 1px solid var(--admin-border);
    border-bottom: 1px solid var(--admin-border);
}
.grid-head {
    display: grid;
    place-items: center;
    background: var(--admin-surface-muted);
    color: var(--admin-faint);
    font-size: 11px;
    font-weight: 900;
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
    background: var(--admin-surface, #fff);
}
.court-cell strong {
    color: var(--admin-text);
    font-size: 12px;
}
.court-cell span {
    color: var(--admin-muted);
    font-size: 11px;
}
.slot-cell {
    min-width: 0;
    padding: 0;
    border-top: 0;
    border-left: 0;
    background: var(--admin-surface, #fff);
    cursor: pointer;
}
.slot-cell.available:hover {
    background: #d1fae5;
    box-shadow: inset 0 0 0 1px rgba(5, 150, 105, 0.35);
}
.slot-cell.booking {
    background: #cbd5e1;
}
.slot-cell.holding {
    background: #fde68a;
}
.slot-cell.manual {
    background: #fca5a5;
}
.slot-cell:disabled {
    cursor: not-allowed;
}
.locks-card .card-head {
    padding-bottom: 12px;
    border-bottom: 1px solid var(--admin-border);
}
.count-badge {
    min-width: 28px;
    padding: 5px 9px;
    border-radius: 999px;
    background: #ecfdf5;
    color: #047857;
    font-size: 12px;
    font-weight: 850;
    text-align: center;
}
.lock-list {
    display: grid;
    gap: 10px;
}
.lock-group {
    display: grid;
    gap: 10px;
    padding: 12px;
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    background: var(--admin-surface-muted);
}
.lock-group-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.lock-group-head > div {
    display: grid;
    gap: 2px;
}
.lock-group-head strong {
    color: var(--admin-text);
    font-size: 14px;
}
.lock-group-head span {
    color: var(--admin-muted);
    font-size: 12px;
    font-weight: 750;
}
.lock-group-head button,
.text-danger-btn {
    border: 0;
    background: transparent;
    color: #dc2626;
    font: inherit;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
}
.lock-chip-list {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}
.lock-chip-list button {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    border: 1px solid #fecaca;
    border-radius: 999px;
    padding: 7px 9px;
    background: var(--admin-surface, #fff);
    color: #991b1b;
    font: inherit;
    cursor: pointer;
}
.lock-chip-list button strong {
    font-size: 12px;
    font-weight: 900;
}
.lock-chip-list button span {
    color: #dc2626;
    font-size: 11px;
    font-weight: 900;
}
.lock-chip-list button:hover:not(:disabled) {
    border-color: #fca5a5;
    background: #fff5f5;
}
.lock-group-head button:disabled,
.text-danger-btn:disabled,
.lock-chip-list button:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
@media (max-width: 1050px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    .side-panel {
        grid-template-columns: minmax(0, 1fr) minmax(300px, 0.7fr);
        align-items: start;
    }
    .form-card form {
        grid-template-columns: repeat(2, 1fr);
    }
    .form-note,
    .primary-btn,
    .form-card label:last-of-type {
        grid-column: 1/-1;
    }
    .court-chip-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 720px) {
    .schedule-headline {
        display: grid;
        gap: 12px;
    }
    .header-actions {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    .form-card form {
        grid-template-columns: 1fr;
    }
    .side-panel {
        grid-template-columns: 1fr;
    }
    .time-grid {
        grid-template-columns: 1fr;
    }
    .date-grid,
    .mode-switch {
        grid-template-columns: 1fr;
    }
    .form-note,
    .primary-btn,
    .form-card label:last-of-type {
        grid-column: auto;
    }
    .legend {
        justify-content: flex-start;
    }
    .btn-refresh {
        width: 100%;
    }
}
</style>
<style scoped>
.selection-box {
    display: grid;
    gap: 5px;
    padding: 12px;
    border: 1px solid var(--admin-blue);
    border-radius: 9px;
    background: var(--admin-blue-soft);
    color: var(--admin-blue);
}
.selection-box.empty {
    border-color: var(--admin-border);
    background: var(--admin-surface-muted);
    color: var(--admin-faint);
}
.selection-box span {
    font-size: 12px;
    line-height: 1.45;
}
.selection-box button {
    justify-self: start;
    border: 0;
    padding: 0;
    background: transparent;
    color: #dc2626;
    font: inherit;
    font-size: 12px;
    font-weight: 850;
    cursor: pointer;
}
.lock-preview-panel {
    display: grid;
    gap: 10px;
    padding: 12px;
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    background: var(--admin-surface-muted);
}
.preview-headline {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}
.preview-headline > div {
    display: grid;
    gap: 2px;
}
.preview-headline strong {
    color: var(--admin-text);
    font-size: 13px;
    font-weight: 900;
}
.preview-headline span,
.preview-headline em {
    color: var(--admin-muted);
    font-size: 12px;
    font-style: normal;
    font-weight: 750;
}
.preview-headline em {
    color: var(--admin-warning);
}
.lock-preview-list {
    display: grid;
    gap: 7px;
    max-height: 220px;
    overflow: auto;
}
.lock-preview-list article {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 9px;
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    background: var(--admin-surface, #fff);
}
.lock-preview-list article.busy {
    border-color: var(--admin-warning);
    background: var(--admin-warning-soft);
}
.lock-preview-list article > div {
    display: grid;
    gap: 2px;
    min-width: 0;
}
.lock-preview-list strong {
    color: var(--admin-text);
    font-size: 13px;
    font-weight: 900;
}
.lock-preview-list small {
    color: var(--admin-muted);
    font-size: 12px;
    line-height: 1.35;
}
.lock-preview-list article > span {
    flex: 0 0 auto;
    border-radius: 999px;
    padding: 4px 8px;
    background: var(--admin-hover);
    color: var(--admin-text);
    font-size: 11px;
    font-weight: 900;
}
.lock-preview-list article.busy > span {
    background: var(--admin-warning);
    color: var(--admin-bg);
}
.preview-note,
.preview-more {
    margin: 0;
    color: var(--admin-faint);
    font-size: 12px;
    line-height: 1.45;
}
.slot-cell.selected {
    background: var(--admin-primary, #000000);
    box-shadow: inset 0 0 0 2px var(--admin-primary-light, #1f2937);
}
.slot-cell.selected:hover {
    background: var(--admin-primary-dark, #000000);
}
.quick-ranges {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    flex-wrap: wrap;
    gap: 8px;
    padding: 14px 20px;
    border-bottom: 1px solid var(--admin-border);
    background: var(--admin-surface, #fff);
}
.quick-ranges button {
    appearance: none;
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    gap: 4px;
    min-height: 38px;
    margin: 0;
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    padding: 8px 12px;
    background: var(--admin-surface, #fff);
    color: var(--admin-faint);
    font-family: inherit;
    font-weight: 850;
    line-height: 1;
    white-space: nowrap;
    cursor: pointer;
    box-shadow: none;
    transition:
        background 0.16s ease,
        border-color 0.16s ease,
        color 0.16s ease,
        transform 0.16s ease,
        box-shadow 0.16s ease;
}
.quick-ranges button strong {
    color: inherit;
    font-size: 15px;
    font-weight: 850;
    line-height: 1;
}
.quick-ranges button small {
    color: inherit;
    display: inline-flex;
    align-items: center;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.15;
    opacity: 0.78;
    transform: translateY(1px);
}
.quick-ranges button:hover:not(:disabled):not(.active) {
    border-color: var(--admin-border);
    background: var(--admin-hover);
    color: var(--admin-text);
    transform: translateY(-1px);
    box-shadow: 0 4px 9px rgba(0, 0, 0, 0.04);
}
.quick-ranges button:hover:not(:disabled):not(.active) small {
    opacity: 0.84;
}
.quick-ranges button.active {
    border-color: var(--admin-primary);
    background: var(--admin-primary);
    color: var(--admin-bg);
    box-shadow: 0 5px 12px rgba(0, 0, 0, 0.08);
}
.quick-ranges button.active strong,
.quick-ranges button.active small {
    color: var(--admin-bg) !important;
}
.quick-ranges button.active small {
    opacity: 0.88;
}
.quick-ranges button:focus-visible {
    outline: 3px solid var(--admin-primary-ring);
    outline-offset: 2px;
}
.quick-ranges button:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 120;
    display: grid;
    place-items: center;
    padding: 24px;
    background: rgba(15, 23, 42, 0.55);
}
.conflict-modal {
    width: min(900px, 100%);
    max-height: min(760px, calc(100vh - 48px));
    display: grid;
    grid-template-rows: auto auto minmax(0, 1fr) auto;
    gap: 14px;
    overflow: hidden;
    border-radius: 12px;
    border: 1px solid var(--admin-border);
    background: var(--admin-surface, #fff);
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
    color: var(--admin-text);
    font-size: 22px;
}
.icon-close {
    width: 36px;
    height: 36px;
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    background: var(--admin-surface, #fff);
    color: var(--admin-text);
    font-size: 24px;
    line-height: 1;
    cursor: pointer;
}
.conflict-help {
    margin: 0 20px;
    padding: 12px 14px;
    border-radius: 8px;
    background: #f0fdf4;
    color: var(--admin-faint);
    line-height: 1.45;
}
.conflict-list {
    display: grid;
    gap: 10px;
    min-height: 0;
    overflow: auto;
    padding: 0 20px;
}
.conflict-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(280px, 360px);
    gap: 14px;
    align-items: center;
    padding: 14px;
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    background: var(--admin-surface-muted);
}
.conflict-main {
    display: grid;
    gap: 4px;
    min-width: 0;
}
.conflict-main strong {
    color: var(--admin-text);
    font-size: 15px;
}
.conflict-main span,
.conflict-main small {
    color: var(--admin-faint);
    line-height: 1.35;
}
.conflict-actions {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    gap: 8px;
    align-items: center;
}
.conflict-actions select {
    min-width: 0;
    height: 38px;
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    padding: 0 10px;
    background: var(--admin-surface, #fff);
    color: #1f2937;
    font-weight: 750;
}
.radio-line {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--admin-text);
    font-weight: 850;
    white-space: nowrap;
}
.radio-line.danger {
    color: #b91c1c;
}
.radio-line.cash {
    color: #b45309;
}
@media (max-width: 720px) {
    .quick-ranges {
        gap: 8px;
        padding: 14px;
    }
    .quick-ranges button {
        flex: 1 1 132px;
        padding: 0 12px;
    }
    .quick-ranges button strong {
        font-size: 15px;
    }
    .quick-ranges button small {
        font-size: 11px;
    }
    .conflict-row,
    .conflict-actions {
        grid-template-columns: 1fr;
    }
    .conflict-modal header,
    .conflict-modal footer {
        align-items: stretch;
    }
}
</style>
