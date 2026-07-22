<template>
  <section class="page">
    <AppTabs v-model="currentTab" :tabs="navigationTabs" />

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <!-- ========================================== -->
    <!-- TAB 1: TODAY / CA TRỰC HÔM NAY -->
    <!-- ========================================== -->
    <div v-if="currentTab === 'today'" class="tab-content">
      <!-- 1.1 Dành cho Nhân viên (Staff View) -->
      <div v-if="isStaff" class="staff-today-container">
        <div v-if="loading" class="state">Đang tải ca trực...</div>
        <div v-else-if="myTodaySchedules.length === 0" class="state empty-state">
          <AppIcon name="calendar" size="48" class="empty-icon" />
          <p>Hôm nay bạn không có ca trực nào được phân công.</p>
        </div>
        <div v-else class="my-shifts-grid">
          <div v-for="sch in myTodaySchedules" :key="sch.id" class="my-shift-card" :class="sch.status">
            <div class="card-header">
              <span class="badge" :class="sch.status">{{ statusLabel(sch.status) }}</span>
              <span class="cluster-name">{{ sch.venue_cluster?.name }}</span>
            </div>
            <div class="card-body">
              <h4 class="shift-name">{{ sch.shift?.name || 'Ca đặc biệt' }}</h4>
              <div class="time-range">
                <AppIcon name="clock" size="16" />
                <span>{{ formatTime(sch.start_time) }} - {{ formatTime(sch.end_time) }}</span>
              </div>

              <!-- Live timer for checked_in shift -->
              <div v-if="sch.status === 'checked_in'" class="live-timer">
                <span class="timer-label">Thời gian đã trực:</span>
                <span class="timer-value">{{ liveDuration(sch.check_in_at) }}</span>
              </div>

              <div class="attendance-time">
                <p v-if="sch.check_in_at">Check-in: <strong>{{ formatDateTime(sch.check_in_at) }}</strong></p>
                <p v-if="sch.check_out_at">Check-out: <strong>{{ formatDateTime(sch.check_out_at) }}</strong></p>
              </div>

              <p v-if="sch.notes" class="notes">Ghi chú: {{ sch.notes }}</p>
            </div>
            <div class="card-footer">
              <button v-if="sch.status === 'scheduled'" class="btn primary btn-block"
                :disabled="!canCheckIn(sch) || actionLoading === sch.id" @click="doCheckIn(sch.id)">
                {{ actionLoading === sch.id ? 'Đang check-in...' : 'CHECK-IN' }}
              </button>
              <button v-if="sch.status === 'checked_in'" class="btn danger btn-block"
                :disabled="actionLoading === sch.id" @click="doCheckOut(sch.id)">
                {{ actionLoading === sch.id ? 'Đang check-out...' : 'CHECK-OUT' }}
              </button>
              <span v-if="sch.status === 'checked_out'" class="footer-done-text">
                <AppIcon name="checkCircle" size="16" /> Ca trực đã hoàn thành
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- 1.2 Dành cho Chủ sân (Owner View) -->
      <div v-else class="owner-today-container">
        <section class="table-card">
          <div class="table-header flex-header">
            <h4>Lịch trực hôm nay ({{ todayDateString }})</h4>
            <div class="view-mode-toggle">
              <button type="button" class="toggle-btn" :class="{ active: todayViewMode === 'table' }"
                @click="todayViewMode = 'table'">
                <AppIcon name="fileText" size="14" />
                <span>Dạng bảng</span>
              </button>
              <button type="button" class="toggle-btn" :class="{ active: todayViewMode === 'timeline' }"
                @click="todayViewMode = 'timeline'">
                <AppIcon name="clock" size="14" />
                <span>Timeline 24h</span>
              </button>
            </div>
          </div>
          <div v-if="loading" class="state">Đang tải lịch trực hôm nay...</div>
          <div v-else-if="todaySchedules.length === 0" class="state">
            Hôm nay chưa có lịch trực nào được phân công.
          </div>
          <template v-else>
            <!-- 1.2.1 Dạng Bảng -->
            <table v-if="todayViewMode === 'table'">
              <thead>
                <tr>
                  <th>Nhân viên</th>
                  <th>Ca trực</th>
                  <th>Thời gian dự kiến</th>
                  <th>Check-in thực tế</th>
                  <th>Check-out thực tế</th>
                  <th>Trạng thái</th>
                  <th>Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="sch in todaySchedules" :key="sch.id">
                  <td>
                    <strong>{{ sch.user?.full_name }}</strong>
                    <div class="text-sub">{{ sch.user?.username }}</div>
                  </td>
                  <td>{{ sch.shift?.name || 'Ca đặc biệt' }}</td>
                  <td>{{ formatTime(sch.start_time) }} - {{ formatTime(sch.end_time) }}</td>
                  <td>{{ sch.check_in_at ? formatDateTime(sch.check_in_at) : '-' }}</td>
                  <td>{{ sch.check_out_at ? formatDateTime(sch.check_out_at) : '-' }}</td>
                  <td>
                    <span class="badge" :class="sch.status">{{ statusLabel(sch.status) }}</span>
                  </td>
                  <td>
                    <TableActionGroup>
                      <ActionIconButton icon="pencil" label="Sửa trạng thái" @click="openEditSchedule(sch)" />
                      <ActionIconButton icon="trash" label="Hủy lịch trực" variant="danger"
                        @click="deleteSchedule(sch.id)" />
                    </TableActionGroup>
                  </td>
                </tr>
              </tbody>
            </table>
            <div v-if="todayViewMode === 'table'" class="mobile-today-list">
              <article
                v-for="sch in todaySchedules"
                :key="`mobile-today-${sch.id}`"
                class="mobile-shift-card"
                :class="sch.status"
              >
                <div class="mobile-shift-card__top">
                  <div>
                    <strong>{{ sch.user?.full_name }}</strong>
                    <span>{{ sch.user?.username }}</span>
                  </div>
                  <span class="badge" :class="sch.status">{{ statusLabel(sch.status) }}</span>
                </div>
                <div class="mobile-shift-card__body">
                  <div>
                    <span>Ca trực</span>
                    <strong>{{ sch.shift?.name || 'Ca đặc biệt' }}</strong>
                  </div>
                  <div>
                    <span>Dự kiến</span>
                    <strong>{{ formatTime(sch.start_time) }} - {{ formatTime(sch.end_time) }}</strong>
                  </div>
                  <div>
                    <span>Check-in</span>
                    <strong>{{ sch.check_in_at ? formatDateTime(sch.check_in_at) : '-' }}</strong>
                  </div>
                  <div>
                    <span>Check-out</span>
                    <strong>{{ sch.check_out_at ? formatDateTime(sch.check_out_at) : '-' }}</strong>
                  </div>
                </div>
                <div class="mobile-card-actions">
                  <button type="button" class="mobile-action-btn" @click="openEditSchedule(sch)">
                    Sửa
                  </button>
                  <button type="button" class="mobile-action-btn danger" @click="deleteSchedule(sch.id)">
                    Hủy
                  </button>
                </div>
              </article>
            </div>
            <!-- 1.2.2 Dạng Timeline 24h -->
            <div v-else class="shift-timeline-layout">
              <div class="timeline-board">
                <div class="timeline-scroller">
                  <div class="timeline-axis">
                    <div class="axis-staff">Nhân viên</div>
                    <div class="axis-track">
                      <span v-for="tick in timelineTicks" :key="tick.label" class="axis-tick"
                        :style="{ left: `${tick.left}%` }">
                        {{ tick.label }}
                      </span>
                    </div>
                  </div>

                  <article v-for="row in todayTimelineRows" :key="row.user.id" class="timeline-row">
                    <div class="staff-meta">
                      <strong>{{ row.user.full_name }}</strong>
                      <span>{{ row.user.username }}</span>
                    </div>
                    <div class="timeline-track">
                      <span v-for="tick in timelineTicks" :key="`grid-${row.user.id}-${tick.label}`"
                        class="track-gridline" :style="{ left: `${tick.left}%` }"></span>
                      <button v-for="block in row.blocks" :key="block.id" type="button" class="timeline-block"
                        :class="block.statusClass" :style="block.style"
                        :title="`Bấm để chỉnh sửa. Trạng thái: ${block.statusLabel}. Ghi chú: ${block.schedule.notes || 'Không có'}`"
                        @click="openEditSchedule(block.schedule)">
                        <strong>{{ block.title }}</strong>
                        <span class="block-time">{{ block.timeLabel }}</span>
                      </button>
                    </div>
                  </article>
                </div>
              </div>
            </div>
          </template>
        </section>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- TAB 2: SCHEDULES / LẬP LỊCH TUẦN -->
    <!-- ========================================== -->
    <div v-if="currentTab === 'schedules'" class="tab-content">

      <div class="schedule-filters schedule-planner-toolbar">
        <div class="filter-group">
          <button class="btn icon-only" @click="shiftWeek(-1)">
            <AppIcon name="chevronLeft" size="16" />
          </button>
          <span class="week-label">Tuần: {{ formatWeekRange() }}</span>
          <button class="btn icon-only" @click="shiftWeek(1)">
            <AppIcon name="chevronRight" size="16" />
          </button>
          <button class="btn secondary" @click="goCurrentWeek">Tuần này</button>
        </div>

        <div class="schedule-view-switch" role="group" aria-label="Chọn kiểu xem lịch trực">
          <button type="button" class="schedule-view-btn" :class="{ active: scheduleViewMode === 'week' }" @click="setScheduleViewMode('week')">
            Tuần
          </button>
          <button type="button" class="schedule-view-btn" :class="{ active: scheduleViewMode === 'day' }" @click="setScheduleViewMode('day')">
            Ngày
          </button>
        </div>
      </div>

      <section v-if="scheduleViewMode === 'day'" class="day-schedule-board">
        <div class="day-schedule-head">
          <div>
            <p class="day-schedule-kicker">Lịch trực trong ngày</p>
            <h3>{{ selectedScheduleDayLabel }}</h3>
          </div>
          <div class="day-schedule-controls">
            <button class="btn icon-only" type="button" @click="shiftScheduleDay(-1)">
              <AppIcon name="chevronLeft" size="16" />
            </button>
            <input v-model="selectedScheduleDate" class="day-date-input" type="date" @change="syncWeekToSelectedDate" />
            <button class="btn icon-only" type="button" @click="shiftScheduleDay(1)">
              <AppIcon name="chevronRight" size="16" />
            </button>
            <button class="btn secondary" type="button" @click="goTodayScheduleDay">Hôm nay</button>
          </div>
        </div>

        <div v-if="loading" class="state">Đang tải lịch trực...</div>
        <div v-else class="day-schedule-content">
          <div class="day-summary-row">
            <div class="day-summary-item"><span>Tổng ca</span><strong>{{ selectedDaySchedules.length }}</strong></div>
            <div class="day-summary-item"><span>Nhân viên có lịch</span><strong>{{ selectedDayStaffCount }}</strong></div>
            <div class="day-summary-item"><span>Chưa phân ca</span><strong>{{ unscheduledStaffForSelectedDay.length }}</strong></div>
          </div>

          <div v-if="dayScheduleGroups.length === 0" class="day-empty-state">
            <strong>Chưa có ca trực trong ngày này</strong>
            <span>Chọn nhân viên bên dưới hoặc dùng nút thêm để phân ca nhanh.</span>
          </div>

          <div v-else class="day-shift-groups">
            <article v-for="group in dayScheduleGroups" :key="group.key" class="day-shift-group">
              <header class="day-shift-group-head">
                <div><strong>{{ group.timeLabel }}</strong><span>{{ group.shiftName }}</span></div>
                <em>{{ group.schedules.length }} nhân viên</em>
              </header>
              <div class="day-shift-staff-list">
                <button v-for="sch in group.schedules" :key="`day-sch-${sch.id}`" type="button" class="day-staff-shift" :class="sch.status" @click="openEditSchedule(sch)">
                  <span class="day-staff-avatar">{{ getInitials(getScheduleStaffName(sch)) }}</span>
                  <span class="day-staff-main"><strong>{{ getScheduleStaffName(sch) }}</strong><em>{{ getStatusLabelCompact(sch.status) }}</em></span>
                </button>
              </div>
            </article>
          </div>

          <section v-if="!isPastDate(selectedScheduleDate) && unscheduledStaffForSelectedDay.length > 0" class="day-unassigned-panel">
            <header><strong>Nhân viên chưa có lịch</strong><span>{{ unscheduledStaffForSelectedDay.length }} người</span></header>
            <div class="day-unassigned-list">
              <button v-for="staffMember in unscheduledStaffForSelectedDay" :key="`day-unassigned-${staffMember.id}`" type="button" class="day-unassigned-chip" @click="openScheduleForStaffDay(staffMember.id, selectedScheduleDate)">
                <span>{{ getInitials(staffMember.full_name) }}</span><strong>{{ staffMember.full_name }}</strong>
              </button>
            </div>
          </section>
        </div>
      </section>

      <section v-if="scheduleViewMode === 'week'" class="table-card table-schedule-grid">
        <div v-if="loading" class="state">Đang tải lịch trực...</div>
        <table v-else>
          <thead>
            <tr>
              <th class="col-staff">Nhân viên</th>
              <th v-for="day in weekDays" :key="day.dateString" class="col-day">
                {{ day.label }}
                <div class="day-date">{{ day.dateDisplay }}</div>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="staffMember in staffList" :key="staffMember.id">
              <td class="col-staff">
                <strong>{{ staffMember.full_name }}</strong>
                <div class="text-sub">{{ staffMember.username }}</div>
              </td>
              <td v-for="day in weekDays" :key="day.dateString" class="col-day-cell">
                <div class="cell-schedules-container" :class="{ 'clickable-cell': !isPastDate(day.dateString) }"
                  @click="!isPastDate(day.dateString) && onCellClick(staffMember.id, day.dateString, $event)"
                  :title="!isPastDate(day.dateString) ? 'Bấm vào vùng trống để phân ca nhanh cho nhân viên này' : ''">
                  <div v-for="sch in getSchedulesForCell(staffMember.id, day.dateString)" :key="sch.id"
                    class="cell-schedule-text" :class="sch.status" @click.stop="openEditSchedule(sch)"
                    :title="`Bấm để chỉnh sửa. Trạng thái: ${getStatusLabelCompact(sch.status)}. Ghi chú: ${sch.notes || 'Không có'}`">
                    <div class="text-time">{{ formatTime(sch.start_time) }} - {{ formatTime(sch.end_time) }}</div>
                    <div class="text-name">
                      {{ sch.shift?.name || 'Ca riêng' }}
                      <span class="status-suffix">({{ getStatusLabelCompact(sch.status) }})</span>
                    </div>
                  </div>
                  <button
                    v-if="!isPastDate(day.dateString)"
                    type="button"
                    class="cell-add-shift-btn"
                    title="Thêm ca khác trong ngày này"
                    @click.stop="openScheduleForStaffDay(staffMember.id, day.dateString)"
                  >
                    + Thêm ca
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="staffList.length === 0">
              <td colspan="8" class="state">Chưa có nhân viên nào trong cụm sân này để xếp lịch. Hãy thêm nhân viên
                trước.
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="!loading" class="mobile-week-list">
          <div v-if="staffList.length === 0" class="state">
            Chưa có nhân viên nào trong cụm sân này để xếp lịch. Hãy thêm nhân viên trước.
          </div>
          <article
            v-for="row in mobileStaffScheduleRows"
            v-else
            :key="`mobile-week-${row.staff.id}`"
            class="mobile-staff-week"
          >
            <header class="mobile-staff-week__header">
              <div>
                <strong>{{ row.staff.full_name }}</strong>
                <span>{{ row.staff.username }}</span>
              </div>
              <button
                type="button"
                class="mobile-add-small"
                @click="openScheduleForStaff(row.staff.id)"
              >
                Phân ca
              </button>
            </header>

            <div v-if="row.days.length === 0" class="mobile-empty-week">
              Chưa có ca trong tuần này.
            </div>
            <div v-else class="mobile-day-stack">
              <section
                v-for="day in row.days"
                :key="`mobile-day-${row.staff.id}-${day.dateString}`"
                class="mobile-day-card"
              >
                <div class="mobile-day-card__head">
                  <div>
                    <strong>{{ day.label }}</strong>
                    <span>{{ day.dateDisplay }}</span>
                  </div>
                  <button
                    v-if="!isPastDate(day.dateString)"
                    type="button"
                    class="mobile-add-day"
                    @click="openScheduleForStaffDay(row.staff.id, day.dateString)"
                  >
                    Thêm
                  </button>
                </div>
                <button
                  v-for="sch in day.schedules"
                  :key="`mobile-sch-${sch.id}`"
                  type="button"
                  class="mobile-schedule-pill"
                  :class="sch.status"
                  @click="openEditSchedule(sch)"
                >
                  <span>{{ formatTime(sch.start_time) }} - {{ formatTime(sch.end_time) }}</span>
                  <strong>{{ sch.shift?.name || 'Ca riêng' }}</strong>
                  <em>{{ getStatusLabelCompact(sch.status) }}</em>
                </button>
              </section>
            </div>
          </article>
        </div>
      </section>
    </div>

    <!-- ========================================== -->
    <!-- TAB 3: SHIFT TEMPLATES / CA MẪU -->
    <!-- ========================================== -->
    <div v-if="currentTab === 'templates'" class="tab-content">
      <section class="table-card">
        <div v-if="loading" class="state">Đang tải danh sách ca mẫu...</div>
        <div v-else-if="shifts.length === 0" class="state">
          Chưa có ca trực mẫu nào. Hãy tạo ca trực mẫu để thuận tiện lập lịch trực.
        </div>
        <table v-else>
          <thead>
            <tr>
              <th>Tên ca trực</th>
              <th>Giờ bắt đầu</th>
              <th>Giờ kết thúc</th>
              <th>Mô tả</th>
              <th>Trạng thái hoạt động</th>
              <th>Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="shift in shifts" :key="shift.id">
              <td><strong>{{ shift.name }}</strong></td>
              <td>{{ formatTime(shift.start_time) }}</td>
              <td>{{ formatTime(shift.end_time) }}</td>
              <td>{{ shift.description || '-' }}</td>
              <td>
                <span class="badge" :class="shift.is_active ? 'active' : 'inactive'">
                  {{ shift.is_active ? 'Đang hoạt động' : 'Tạm ngưng' }}
                </span>
              </td>
              <td>
                <TableActionGroup>
                  <ActionIconButton icon="pencil" label="Sửa ca mẫu" @click="openEditShift(shift)" />
                  <ActionIconButton icon="trash" label="Xóa ca mẫu" variant="danger" @click="deleteShift(shift.id)" />
                </TableActionGroup>
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>

    <!-- ========================================== -->
    <!-- TAB 4: ATTENDANCE REPORT / BÁO CÁO -->
    <!-- ========================================== -->
    <div v-if="currentTab === 'report'" class="tab-content">
      <div class="schedule-filters">
        <div class="filter-group">
          <label>Từ ngày: <input type="date" v-model="reportStartDate" @change="loadReport" /></label>
          <label>Đến ngày: <input type="date" v-model="reportEndDate" @change="loadReport" /></label>
          <button class="btn secondary" @click="setReportThisMonth">Tháng này</button>
        </div>
      </div>

      <section class="table-card">
        <div v-if="loading" class="state">Đang tải báo cáo thống kê...</div>
        <div v-else-if="reportData.length === 0" class="state">Không có dữ liệu ca trực trong khoảng thời gian này.
        </div>
        <table v-else>
          <thead>
            <tr>
              <th>Nhân viên</th>
              <th>Tổng số ca phân công</th>
              <th>Đã trực (Hoàn thành)</th>
              <th>Đi muộn / Về sớm</th>
              <th>Vắng mặt</th>
              <th>Bị hủy</th>
              <th>Tỷ lệ chuyên cần</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="rep in reportData" :key="rep.user_id">
              <td>
                <strong>{{ rep.full_name }}</strong>
                <div class="text-sub">{{ rep.username }}</div>
              </td>
              <td>{{ rep.total_shifts }}</td>
              <td>{{ rep.checked_in }}</td>
              <td>
                <span :class="{ 'text-danger': rep.late > 0 }">{{ rep.late }} ca</span>
              </td>
              <td>{{ rep.absent }}</td>
              <td>{{ rep.cancelled }}</td>
              <td>
                <strong>{{ calculateAttendanceRate(rep) }}%</strong>
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 1: THÊM / SỬA CA MẪU -->
    <!-- ========================================== -->
    <div v-if="showShiftModal" class="modal-backdrop" @click.self="closeShiftModal">
      <form class="modal" @submit.prevent="saveShift">
        <h3>{{ shiftForm.id ? 'Sửa ca trực mẫu' : 'Thêm ca trực mẫu' }}</h3>
        <div class="grid">
          <label>Tên ca mẫu <input v-model.trim="shiftForm.name" placeholder="Ví dụ: Ca sáng" required /></label>
          <label>Giờ bắt đầu <input type="time" v-model="shiftForm.start_time" required /></label>
          <label>Giờ kết thúc <input type="time" v-model="shiftForm.end_time" required /></label>
          <label class="full-width">Mô tả <textarea v-model.trim="shiftForm.description" rows="2"
              placeholder="Nhập ghi chú hoặc mô tả về ca trực"></textarea></label>
          <label v-if="shiftForm.id" class="check">
            <input type="checkbox" v-model="shiftForm.is_active" /> Ca trực đang hoạt động
          </label>
        </div>
        <footer>
          <button class="btn secondary" type="button" @click="closeShiftModal">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving">Lưu</button>
        </footer>
      </form>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 2: PHÂN CA TRỰC (SCHEDULE MODAL) -->
    <!-- ========================================== -->
    <div v-if="showScheduleModal" class="modal-backdrop" @click.self="closeScheduleModal">
      <form class="modal schedule-modal" @submit.prevent="saveSchedules">
        <header class="schedule-modal-head">
          <h3>Phân công ca trực cho nhân viên</h3>
        </header>

        <div class="sch-grid">
          <section class="schedule-panel schedule-panel-left">
            <div class="schedule-panel-title">
              <span>Nhân viên</span>
              <strong>{{ scheduleForm.user_ids.length }}</strong>
            </div>
            <div class="staff-chip-grid">
              <button
                v-for="staffMember in staffList"
                :key="staffMember.id"
                type="button"
                class="staff-chip"
                :class="{ active: scheduleForm.user_ids.includes(staffMember.id) }"
                @click="toggleStaff(staffMember.id)"
              >
                <span class="chip-avatar">{{ staffMember.full_name.charAt(0) }}</span>
                <span class="chip-name">{{ staffMember.full_name }}</span>
                <span v-if="scheduleForm.user_ids.includes(staffMember.id)" class="chip-check">✓</span>
              </button>
            </div>

            <div class="schedule-panel-title schedule-panel-title-spaced">
              <span>Ngày trực</span>
              <strong>{{ scheduleForm.dates.length }}</strong>
            </div>
            <div class="custom-date-picker">
              <div class="date-tags-bar">
                <div v-if="scheduleForm.dates.length > 0" class="date-tags-list">
                  <span v-for="date in scheduleForm.dates" :key="date" class="date-tag">
                    {{ formatDateDisplay(date) }}
                    <button type="button" class="tag-remove" aria-label="Bỏ ngày" @click="removeScheduleDate(date)">&#215;</button>
                  </span>
                </div>
                <div v-else class="date-placeholder">Chưa chọn ngày nào</div>
                <button type="button" class="cal-toggle-btn" @click="calendarOpen = !calendarOpen">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                  </svg>
                  Thêm ngày
                </button>
              </div>
              <div v-if="calendarOpen" class="cal-panel">
                <div class="cal-nav">
                  <button type="button" class="cal-nav-btn" @click="calPrevMonth">‹</button>
                  <span class="cal-month-label">{{ calMonthLabel }}</span>
                  <button type="button" class="cal-nav-btn" @click="calNextMonth">›</button>
                </div>
                <div class="cal-weekdays">
                  <span v-for="d in ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN']" :key="d">{{ d }}</span>
                </div>
                <div class="cal-days-grid">
                  <button
                    v-for="cell in calDays"
                    :key="cell.key"
                    type="button"
                    class="cal-day"
                    :class="{
                      'other-month': !cell.currentMonth,
                      'is-today': cell.dateStr === todayDateString,
                      'is-selected': scheduleForm.dates.includes(cell.dateStr),
                      'is-past': cell.dateStr < todayDateString,
                    }"
                    :disabled="cell.dateStr < todayDateString"
                    @click="calSelectDay(cell.dateStr)"
                  >{{ cell.day }}</button>
                </div>
              </div>
            </div>
          </section>

          <section class="schedule-panel schedule-panel-right">
            <div class="schedule-field">
              <div class="field-label">Ca mẫu có sẵn</div>
              <div v-if="shiftDropOpen" class="dropdown-backdrop" @click="shiftDropOpen = false"></div>
              <div class="custom-dropdown" :class="{ open: shiftDropOpen }">
                <button type="button" class="dropdown-trigger" @click="shiftDropOpen = !shiftDropOpen">
                  <span v-if="scheduleForm.venue_staff_shift_id">
                    {{ activeShifts.find(s => s.id === scheduleForm.venue_staff_shift_id)?.name }}
                    ({{ formatTime(activeShifts.find(s => s.id === scheduleForm.venue_staff_shift_id)?.start_time) }} –
                    {{ formatTime(activeShifts.find(s => s.id === scheduleForm.venue_staff_shift_id)?.end_time) }})
                  </span>
                  <span v-else class="dropdown-placeholder">Tự định nghĩa giờ trực</span>
                  <span class="dropdown-caret" :class="{ rotated: shiftDropOpen }">&#9662;</span>
                </button>
                <div v-if="shiftDropOpen" class="dropdown-menu">
                  <button
                    type="button"
                    class="dropdown-item"
                    :class="{ active: !scheduleForm.venue_staff_shift_id }"
                    @click="selectShiftTemplate(null)"
                  >
                    <span>Tự định nghĩa giờ trực</span>
                  </button>
                  <button
                    v-for="shift in activeShifts"
                    :key="shift.id"
                    type="button"
                    class="dropdown-item"
                    :class="{ active: scheduleForm.venue_staff_shift_id === shift.id }"
                    @click="selectShiftTemplate(shift.id)"
                  >
                    <strong>{{ shift.name }}</strong>
                    <span class="shift-time-badge">{{ formatTime(shift.start_time) }} – {{ formatTime(shift.end_time) }}</span>
                  </button>
                </div>
              </div>
            </div>

            <div v-if="!scheduleForm.venue_staff_shift_id" class="time-input-row">
              <label class="time-input-card">
                <span>Giờ bắt đầu</span>
                <input
                  v-model="scheduleForm.start_time"
                  class="time-input-control"
                  type="time"
                  required
                />
              </label>
              <label class="time-input-card">
                <span>Giờ kết thúc</span>
                <input
                  v-model="scheduleForm.end_time"
                  class="time-input-control"
                  type="time"
                  required
                />
              </label>
            </div>
            <div class="schedule-field">
              <div class="field-label">Ghi chú công việc</div>
              <input
                class="styled-input"
                v-model.trim="scheduleForm.notes"
                placeholder="Nhập nhiệm vụ hoặc lưu ý đặc biệt"
              />
            </div>
          </section>
        </div>

        <footer class="schedule-modal-footer">
          <button class="btn secondary" type="button" @click="closeScheduleModal">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving">Phân công</button>
        </footer>
      </form>
    </div>
    <div v-if="showEditScheduleModal" class="modal-backdrop" @click.self="closeEditScheduleModal">
      <form class="modal" @submit.prevent="saveEditSchedule">
        <h3>Điều chỉnh ca trực nhân viên</h3>
        <div class="grid">
          <label>Nhân viên <input :value="editingSchedule?.user?.full_name" disabled /></label>
          <label>Ngày trực <input type="date" v-model="editScheduleForm.date" required /></label>
          <label>Giờ bắt đầu <input type="time" v-model="editScheduleForm.start_time" required /></label>
          <label>Giờ kết thúc <input type="time" v-model="editScheduleForm.end_time" required /></label>
          <label>Trạng thái trực
            <select v-model="editScheduleForm.status">
              <option value="scheduled">Đã phân lịch (Chờ trực)</option>
              <option value="checked_in">Đang trực (Đã Check-in)</option>
              <option value="checked_out">Hoàn thành (Đã Check-out)</option>
              <option value="absent">Vắng mặt (Không đi làm)</option>
              <option value="cancelled">Hủy ca trực</option>
            </select>
          </label>
          <label class="full-width">Ghi chú <input v-model.trim="editScheduleForm.notes" /></label>
        </div>
        <footer>
          <button class="btn secondary" type="button" @click="closeEditScheduleModal">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving">Lưu thay đổi</button>
        </footer>
      </form>
    </div>

    <!-- Floating Action Button for Owner -->
    <div v-if="!isStaff && (currentTab === 'schedules' || currentTab === 'templates')" class="floating-add-container">
      <button v-if="currentTab === 'templates'" class="btn-float-add" @click="openCreateShift">
        <AppIcon name="plus" size="20" />
        <span class="btn-float-text">Thêm ca mẫu</span>
      </button>
      <button v-if="currentTab === 'schedules'" class="btn-float-add" @click="openScheduleModal">
        <AppIcon name="plus" size="20" />
        <span class="btn-float-text">Phân ca trực</span>
      </button>
    </div>
  </section>
</template>

<script>
import ActionIconButton from '../../components/ActionIconButton.vue';
import AppIcon from '../../components/AppIcon.vue';
import AppTabs from '../../components/common/AppTabs.vue';
import TableActionGroup from '../../components/TableActionGroup.vue';
import { getAuth } from '../../stores/auth.js';
import { ownerStaffShiftService } from '../../services/ownerStaffShiftService.js';
import { ownerStaffService } from '../../services/ownerStaffService.js';

export default {
  name: 'OwnerStaffShifts',
  components: { ActionIconButton, AppIcon, AppTabs, TableActionGroup },
  data() {
    return {
      currentTab: 'today',
      shifts: [],
      schedules: [],
      staffList: [],
      reportData: [],
      loading: false,
      saving: false,
      actionLoading: null,
      error: '',
      success: '',

      // Current week states for Tab 2
      currentWeekStart: new Date(),
      scheduleViewMode: 'week',
      selectedScheduleDate: this.formatDateIso(new Date()),

      // Shift template modal states
      showShiftModal: false,
      shiftForm: this.emptyShiftForm(),

      // Scheduling modal states
      showScheduleModal: false,
      scheduleForm: this.emptyScheduleForm(),

      // Edit schedule modal states
      showEditScheduleModal: false,
      editingSchedule: null,
      editScheduleForm: {
        date: '',
        start_time: '',
        end_time: '',
        status: 'scheduled',
        notes: '',
      },

      // Report Tab states
      reportStartDate: '',
      reportEndDate: '',

      // Live timer tick helper
      nowTime: new Date(),
      timerInterval: null,

      // View mode for Today's schedules (table or timeline)
      todayViewMode: 'table',

      // Custom calendar state
      calendarOpen: false,
      calViewYear: new Date().getFullYear(),
      calViewMonth: new Date().getMonth(),

      // Custom shift dropdown
      shiftDropOpen: false,

      // Custom time picker state (HH, MM integers)
      startHour: 6,
      startMin: 0,
      endHour: 12,
      endMin: 0,
    };
  },
  computed: {
    navigationTabs() {
      if (this.isStaff) {
        return [
          { key: 'today', label: 'Ca trực của tôi', icon: 'calendar' }
        ];
      }
      return [
        { key: 'today', label: 'Lịch trực hôm nay', icon: 'calendar' },
        { key: 'schedules', label: 'Lập lịch trực', icon: 'users' },
        { key: 'templates', label: 'Cấu hình ca mẫu', icon: 'settings' },
        { key: 'report', label: 'Thống kê & Báo cáo', icon: 'fileText' },
      ];
    },
    isStaff() {
      const auth = getAuth() || {};
      return auth.roles?.includes('venue_staff');
    },
    activeShifts() {
      return this.shifts.filter(s => s.is_active);
    },
    todayDateString() {
      const d = new Date();
      return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    },
    startH() { return String(this.startHour).padStart(2, '0'); },
    startM() { return String(this.startMin).padStart(2, '0'); },
    endH() { return String(this.endHour).padStart(2, '0'); },
    endM() { return String(this.endMin).padStart(2, '0'); },
    calMonthLabel() {
      const months = ['Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6', 'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'];
      return `${months[this.calViewMonth]} ${this.calViewYear}`;
    },
    calDays() {
      const year = this.calViewYear;
      const month = this.calViewMonth;
      const firstDay = new Date(year, month, 1);
      // Monday-first: 0=Mon...6=Sun
      let startDow = firstDay.getDay(); // 0=Sun
      startDow = startDow === 0 ? 6 : startDow - 1;
      const daysInMonth = new Date(year, month + 1, 0).getDate();
      const cells = [];
      // Prev month filler
      const prevMonthDays = new Date(year, month, 0).getDate();
      for (let i = startDow - 1; i >= 0; i--) {
        const d = prevMonthDays - i;
        const m = month === 0 ? 12 : month;
        const y = month === 0 ? year - 1 : year;
        const dateStr = `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        cells.push({ key: `prev-${d}`, day: d, dateStr, currentMonth: false });
      }
      // Current month
      for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        cells.push({ key: `cur-${d}`, day: d, dateStr, currentMonth: true });
      }
      // Next month filler
      const remaining = 42 - cells.length;
      for (let d = 1; d <= remaining; d++) {
        const m = month === 11 ? 1 : month + 2;
        const y = month === 11 ? year + 1 : year;
        const dateStr = `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        cells.push({ key: `next-${d}`, day: d, dateStr, currentMonth: false });
      }
      return cells;
    },
    myTodaySchedules() {
      if (!this.isStaff) return [];
      const today = this.todayDateString;
      return this.schedules.filter(s => s.date === today);
    },
    todaySchedules() {
      if (this.isStaff) return [];
      const today = this.todayDateString;
      return this.schedules.filter(s => s.date === today);
    },
    timelineTicks() {
      const ticks = [];
      for (let h = 0; h <= 24; h += 2) {
        ticks.push({
          label: `${String(h).padStart(2, '0')}:00`,
          left: (h / 24) * 100,
        });
      }
      return ticks;
    },
    todayTimelineRows() {
      if (this.isStaff) return [];

      const grouped = {};
      this.todaySchedules.forEach(sch => {
        const userId = sch.user_id;
        if (!grouped[userId]) {
          grouped[userId] = {
            user: sch.user,
            blocks: [],
          };
        }

        // Parse time to minutes from midnight
        const parseTimeMins = (timeStr) => {
          if (!timeStr) return 0;
          const parts = timeStr.split(':');
          const h = parseInt(parts[0]) || 0;
          const m = parseInt(parts[1]) || 0;
          return h * 60 + m;
        };

        const startMins = parseTimeMins(sch.start_time);
        const endMins = parseTimeMins(sch.end_time);
        const totalMins = 24 * 60; // 24 hours

        const left = (startMins / totalMins) * 100;
        const width = ((endMins - startMins) / totalMins) * 100;

        grouped[userId].blocks.push({
          id: sch.id,
          schedule: sch,
          title: sch.shift?.name || 'Ca đặc biệt',
          timeLabel: `${this.formatTime(sch.start_time)} - ${this.formatTime(sch.end_time)}`,
          statusLabel: this.statusLabel(sch.status),
          statusClass: sch.status,
          style: {
            left: `${left}%`,
            width: `${width}%`,
          },
        });
      });

      return Object.values(grouped);
    },
    weekDays() {
      const days = [];
      const labels = ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ nhật'];
      const start = this.getMonday(new Date(this.currentWeekStart));

      for (let i = 0; i < 7; i++) {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        const dateString = `${yyyy}-${mm}-${dd}`;

        days.push({
          label: labels[i],
          dateString,
          dateDisplay: `${dd}/${mm}`,
        });
      }
      return days;
    },
    selectedScheduleDayMeta() {
      return this.buildDayMeta(this.selectedScheduleDate);
    },
    selectedScheduleDayLabel() {
      const day = this.selectedScheduleDayMeta;
      return day ? `${day.label}, ${day.dateDisplay}` : this.selectedScheduleDate;
    },
    selectedDaySchedules() {
      return this.schedules
        .filter((sch) => sch.date === this.selectedScheduleDate)
        .sort((a, b) => this.timeToMinutes(a.start_time) - this.timeToMinutes(b.start_time));
    },
    selectedDayStaffCount() {
      return new Set(this.selectedDaySchedules.map((sch) => String(sch.user_id))).size;
    },
    dayScheduleGroups() {
      const groups = new Map();
      this.selectedDaySchedules.forEach((sch) => {
        const shiftName = sch.shift?.name || 'Ca riêng';
        const key = `${this.formatTime(sch.start_time)}-${this.formatTime(sch.end_time)}-${shiftName}`;
        if (!groups.has(key)) {
          groups.set(key, {
            key,
            startMinutes: this.timeToMinutes(sch.start_time),
            timeLabel: `${this.formatTime(sch.start_time)} - ${this.formatTime(sch.end_time)}`,
            shiftName,
            schedules: [],
          });
        }
        groups.get(key).schedules.push(sch);
      });

      return Array.from(groups.values())
        .map((group) => ({
          ...group,
          schedules: group.schedules.sort((a, b) => this.getScheduleStaffName(a).localeCompare(this.getScheduleStaffName(b), 'vi')),
        }))
        .sort((a, b) => a.startMinutes - b.startMinutes);
    },
    unscheduledStaffForSelectedDay() {
      const scheduledIds = new Set(this.selectedDaySchedules.map((sch) => String(sch.user_id)));
      return this.staffList.filter((staff) => !scheduledIds.has(String(staff.id)));
    },
    mobileStaffScheduleRows() {
      return this.staffList.map((staff) => ({
        staff,
        days: this.weekDays
          .map((day) => ({
            ...day,
            schedules: this.getSchedulesForCell(staff.id, day.dateString),
          }))
          .filter((day) => day.schedules.length > 0),
      }));
    },
  },
  watch: {
    currentTab(newTab) {
      this.error = '';
      this.success = '';
      this.loadTab(newTab);
    },
  },
  mounted() {
    window.addEventListener('owner-cluster-changed', this.onClusterChanged);
    this.timerInterval = setInterval(() => {
      this.nowTime = new Date();
    }, 1000);
    this.loadTab(this.currentTab);
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.onClusterChanged);
    if (this.timerInterval) clearInterval(this.timerInterval);
  },
  methods: {
    getStatusLabelCompact(status) {
      return {
        scheduled: 'Lên lịch',
        checked_in: 'Đang trực',
        checked_out: 'Đã xong',
        absent: 'Vắng',
        cancelled: 'Hủy'
      }[status] || status;
    },
    emptyShiftForm() {
      return {
        id: null,
        name: '',
        start_time: '',
        end_time: '',
        description: '',
        is_active: true,
      };
    },
    emptyScheduleForm() {
      return {
        user_ids: [],
        dates: [],
        venue_staff_shift_id: null,
        start_time: '06:00',
        end_time: '12:00',
        notes: '',
      };
    },
    onClusterChanged() {
      this.loadTab(this.currentTab);
    },
    loadTab(tab) {
      if (tab === 'today') {
        this.loadTodayShifts();
      } else if (tab === 'schedules') {
        this.loadSchedulesForWeek();
      } else if (tab === 'templates') {
        this.loadShifts();
      } else if (tab === 'report') {
        this.setReportThisMonth();
      }
    },
    async loadShifts() {
      this.loading = true;
      try {
        const res = await ownerStaffShiftService.listShifts();
        this.shifts = res.data || [];
      } catch (err) {
        this.error = err.message || 'Không thể tải ca trực mẫu.';
      } finally {
        this.loading = false;
      }
    },
    async loadTodayShifts() {
      this.loading = true;
      try {
        const today = this.todayDateString;
        if (this.isStaff) {
          const res = await ownerStaffShiftService.mySchedules({ start_date: today, end_date: today });
          this.schedules = res.data || [];
        } else {
          const res = await ownerStaffShiftService.listSchedules({ start_date: today, end_date: today });
          this.schedules = res.data || [];
        }
      } catch (err) {
        this.error = err.message || 'Không thể tải lịch trực hôm nay.';
      } finally {
        this.loading = false;
      }
    },
    async loadSchedulesForWeek() {
      this.loading = true;
      try {
        const start = this.getMonday(new Date(this.currentWeekStart));
        const end = new Date(start);
        end.setDate(start.getDate() + 6);

        const yyyyStart = start.getFullYear();
        const mmStart = String(start.getMonth() + 1).padStart(2, '0');
        const ddStart = String(start.getDate()).padStart(2, '0');

        const yyyyEnd = end.getFullYear();
        const mmEnd = String(end.getMonth() + 1).padStart(2, '0');
        const ddEnd = String(end.getDate()).padStart(2, '0');

        const startStr = `${yyyyStart}-${mmStart}-${ddStart}`;
        const endStr = `${yyyyEnd}-${mmEnd}-${ddEnd}`;

        // 1. Fetch schedules
        const resSch = await ownerStaffShiftService.listSchedules({ start_date: startStr, end_date: endStr });
        this.schedules = resSch.data || [];

        // 2. Fetch staff list & templates
        const resStaff = await ownerStaffService.list();
        this.staffList = resStaff.data || [];

        const resShifts = await ownerStaffShiftService.listShifts();
        this.shifts = resShifts.data || [];
      } catch (err) {
        this.error = err.message || 'Không thể tải lịch trực tuần.';
      } finally {
        this.loading = false;
      }
    },
    async loadReport() {
      if (!this.reportStartDate || !this.reportEndDate) return;
      this.loading = true;
      try {
        const res = await ownerStaffShiftService.attendanceReport({
          start_date: this.reportStartDate,
          end_date: this.reportEndDate,
        });
        this.reportData = res.data || [];
      } catch (err) {
        this.error = err.message || 'Không thể tải báo cáo thống kê.';
      } finally {
        this.loading = false;
      }
    },

    // Staff action handlers
    async doCheckIn(scheduleId) {
      this.actionLoading = scheduleId;
      this.error = '';
      this.success = '';
      try {
        await ownerStaffShiftService.checkIn(scheduleId);
        this.success = 'Check-in thành công!';
        await this.loadTodayShifts();
      } catch (err) {
        this.error = err.message || 'Check-in thất bại.';
      } finally {
        this.actionLoading = null;
      }
    },
    async doCheckOut(scheduleId) {
      this.actionLoading = scheduleId;
      this.error = '';
      this.success = '';
      try {
        await ownerStaffShiftService.checkOut(scheduleId);
        this.success = 'Check-out thành công!';
        await this.loadTodayShifts();
      } catch (err) {
        this.error = err.message || 'Check-out thất bại.';
      } finally {
        this.actionLoading = null;
      }
    },

    // Shift templates CRUDS
    openCreateShift() {
      this.shiftForm = this.emptyShiftForm();
      this.showShiftModal = true;
    },
    openEditShift(shift) {
      this.shiftForm = {
        id: shift.id,
        name: shift.name,
        start_time: shift.start_time.substring(0, 5),
        end_time: shift.end_time.substring(0, 5),
        description: shift.description || '',
        is_active: !!shift.is_active,
      };
      this.showShiftModal = true;
    },
    async saveShift() {
      this.saving = true;
      this.error = '';
      this.success = '';
      try {
        if (this.shiftForm.id) {
          await ownerStaffShiftService.updateShift(this.shiftForm.id, this.shiftForm);
          this.success = 'Đã cập nhật ca trực mẫu.';
        } else {
          await ownerStaffShiftService.createShift(this.shiftForm);
          this.success = 'Đã tạo ca trực mẫu mới.';
        }
        this.showShiftModal = false;
        await this.loadShifts();
      } catch (err) {
        this.error = err.message || 'Lỗi khi lưu ca trực.';
      } finally {
        this.saving = false;
      }
    },
    async deleteShift(id) {
      if (!confirm('Bạn có chắc chắn muốn xóa ca trực mẫu này?')) return;
      this.error = '';
      this.success = '';
      try {
        await ownerStaffShiftService.deleteShift(id);
        this.success = 'Đã xóa ca trực mẫu.';
        await this.loadShifts();
      } catch (err) {
        this.error = err.message || 'Không thể xóa ca trực mẫu.';
      }
    },

    // Schedule management
    openScheduleModal() {
      this.scheduleForm = this.emptyScheduleForm();
      this.showScheduleModal = true;
    },
    toggleStaff(staffId) {
      const idx = this.scheduleForm.user_ids.indexOf(staffId);
      if (idx === -1) {
        this.scheduleForm.user_ids.push(staffId);
      } else {
        this.scheduleForm.user_ids.splice(idx, 1);
      }
    },
    formatDateDisplay(dateString) {
      if (!dateString) return '';
      const [yyyy, mm, dd] = dateString.split('-');
      return `${dd}/${mm}/${yyyy}`;
    },
    calPrevMonth() {
      if (this.calViewMonth === 0) { this.calViewMonth = 11; this.calViewYear--; }
      else { this.calViewMonth--; }
    },
    calNextMonth() {
      if (this.calViewMonth === 11) { this.calViewMonth = 0; this.calViewYear++; }
      else { this.calViewMonth++; }
    },
    calSelectDay(dateStr) {
      if (dateStr < this.todayDateString) return;
      if (!this.scheduleForm.dates.includes(dateStr)) {
        this.scheduleForm.dates.push(dateStr);
        this.scheduleForm.dates.sort();
      } else {
        this.scheduleForm.dates = this.scheduleForm.dates.filter(d => d !== dateStr);
      }
    },
    selectShiftTemplate(id) {
      this.scheduleForm.venue_staff_shift_id = id;
      this.shiftDropOpen = false;
      if (id) {
        const found = this.shifts.find(s => s.id === id);
        if (found) {
          this.scheduleForm.start_time = found.start_time.substring(0, 5);
          this.scheduleForm.end_time = found.end_time.substring(0, 5);
        }
      }
    },
    adjustTime(field, part, delta) {
      if (field === 'start') {
        if (part === 'h') {
          this.startHour = (this.startHour + delta + 24) % 24;
        } else {
          this.startMin = (this.startMin + delta * 5 + 60) % 60;
        }
        this.scheduleForm.start_time = `${this.startH}:${this.startM}`;
      } else {
        if (part === 'h') {
          this.endHour = (this.endHour + delta + 24) % 24;
        } else {
          this.endMin = (this.endMin + delta * 5 + 60) % 60;
        }
        this.scheduleForm.end_time = `${this.endH}:${this.endM}`;
      }
    },
    onCellClick(staffId, dateString, event) {
      if (event.target.closest('.cell-schedule-text')) {
        return;
      }
      this.scheduleForm = {
        user_ids: [staffId],
        dates: [dateString],
        venue_staff_shift_id: null,
        start_time: '06:00',
        end_time: '12:00',
        notes: '',
      };
      this.showScheduleModal = true;
    },
    openScheduleForStaff(staffId) {
      this.scheduleForm = {
        user_ids: [staffId],
        dates: [],
        venue_staff_shift_id: null,
        start_time: '06:00',
        end_time: '12:00',
        notes: '',
      };
      this.showScheduleModal = true;
    },
    openScheduleForStaffDay(staffId, dateString) {
      this.scheduleForm = {
        user_ids: [staffId],
        dates: [dateString],
        venue_staff_shift_id: null,
        start_time: '06:00',
        end_time: '12:00',
        notes: '',
      };
      this.showScheduleModal = true;
    },
    isPastDate(dateString) {
      return dateString < this.todayDateString;
    },
    onSelectTemplateShift() {
      if (this.scheduleForm.venue_staff_shift_id) {
        const found = this.shifts.find(s => s.id === this.scheduleForm.venue_staff_shift_id);
        if (found) {
          this.scheduleForm.start_time = found.start_time.substring(0, 5);
          this.scheduleForm.end_time = found.end_time.substring(0, 5);
        }
      }
    },
    addScheduleDate(e) {
      const val = e.target.value;
      if (val && !this.scheduleForm.dates.includes(val)) {
        this.scheduleForm.dates.push(val);
        this.scheduleForm.dates.sort();
      }
      e.target.value = '';
    },
    removeScheduleDate(date) {
      this.scheduleForm.dates = this.scheduleForm.dates.filter(d => d !== date);
    },
    async saveSchedules() {
      if (this.scheduleForm.user_ids.length === 0) {
        this.error = 'Vui lòng chọn ít nhất một nhân viên.';
        return;
      }
      if (this.scheduleForm.dates.length === 0) {
        this.error = 'Vui lòng chọn ít nhất một ngày trực.';
        return;
      }

      this.saving = true;
      this.error = '';
      this.success = '';
      try {
        await ownerStaffShiftService.createSchedules(this.scheduleForm);
        this.success = 'Đã phân ca trực thành công.';
        this.showScheduleModal = false;
        await this.loadSchedulesForWeek();
      } catch (err) {
        this.error = err.message || 'Lỗi khi xếp ca trực.';
      } finally {
        this.saving = false;
      }
    },
    openEditSchedule(sch) {
      this.editingSchedule = sch;
      this.editScheduleForm = {
        date: sch.date,
        start_time: sch.start_time.substring(0, 5),
        end_time: sch.end_time.substring(0, 5),
        status: sch.status,
        notes: sch.notes || '',
      };
      this.showEditScheduleModal = true;
    },
    async saveEditSchedule() {
      this.saving = true;
      this.error = '';
      this.success = '';
      try {
        await ownerStaffShiftService.updateSchedule(this.editingSchedule.id, this.editScheduleForm);
        this.success = 'Đã cập nhật lịch trực thành công.';
        this.showEditScheduleModal = false;
        if (this.currentTab === 'today') {
          await this.loadTodayShifts();
        } else {
          await this.loadSchedulesForWeek();
        }
      } catch (err) {
        this.error = err.message || 'Lỗi khi cập nhật lịch trực.';
      } finally {
        this.saving = false;
      }
    },
    async deleteSchedule(id) {
      if (!confirm('Bạn có chắc chắn muốn hủy ca trực đã xếp này?')) return;
      this.error = '';
      this.success = '';
      try {
        await ownerStaffShiftService.deleteSchedule(id);
        this.success = 'Đã xóa lịch trực.';
        if (this.currentTab === 'today') {
          await this.loadTodayShifts();
        } else {
          await this.loadSchedulesForWeek();
        }
      } catch (err) {
        this.error = err.message || 'Không thể xóa lịch trực.';
      }
    },

    // Week navigation helpers
    getMonday(d) {
      d = new Date(d);
      const day = d.getDay();
      const diff = d.getDate() - day + (day === 0 ? -6 : 1); // adjust when day is sunday
      return new Date(d.setDate(diff));
    },
    shiftWeek(weeks) {
      const d = new Date(this.currentWeekStart);
      d.setDate(d.getDate() + weeks * 7);
      this.currentWeekStart = d;
      if (this.scheduleViewMode === 'day') {
        const selected = new Date((this.selectedScheduleDate || this.todayDateString) + 'T00:00:00');
        selected.setDate(selected.getDate() + weeks * 7);
        this.selectedScheduleDate = this.formatDateIso(selected);
      }
      this.loadSchedulesForWeek();
    },
    goCurrentWeek() {
      this.currentWeekStart = new Date();
      if (this.scheduleViewMode === 'day') {
        this.selectedScheduleDate = this.formatDateIso(new Date());
      }
      this.loadSchedulesForWeek();
    },
    formatWeekRange() {
      const start = this.getMonday(new Date(this.currentWeekStart));
      const end = new Date(start);
      end.setDate(start.getDate() + 6);
      return `${start.getDate()}/${start.getMonth() + 1} - ${end.getDate()}/${end.getMonth() + 1}`;
    },
    getSchedulesForCell(staffId, dateString) {
      return this.schedules.filter(
        sch => String(sch.user_id) === String(staffId) && sch.date === dateString
      );
    },

    // Attendance helper report dates
    setReportThisMonth() {
      const now = new Date();
      const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
      const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

      this.reportStartDate = this.formatDateIso(firstDay);
      this.reportEndDate = this.formatDateIso(lastDay);
      this.loadReport();
    },
    formatDateIso(d) {
      return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    },

    buildDayMeta(dateString) {
      if (!dateString) return null;
      const labels = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
      const date = new Date(`${dateString}T00:00:00`);
      if (Number.isNaN(date.getTime())) return null;
      const dd = String(date.getDate()).padStart(2, '0');
      const mm = String(date.getMonth() + 1).padStart(2, '0');
      return {
        label: labels[date.getDay()],
        dateString,
        dateDisplay: `${dd}/${mm}`,
      };
    },
    timeToMinutes(timeStr) {
      if (!timeStr) return 0;
      const [hour = '0', minute = '0'] = String(timeStr).split(':');
      return (parseInt(hour, 10) || 0) * 60 + (parseInt(minute, 10) || 0);
    },
    getScheduleStaffName(schedule) {
      return schedule?.user?.full_name
        || this.staffList.find((staff) => String(staff.id) === String(schedule?.user_id))?.full_name
        || 'Nhân viên';
    },
    getInitials(name) {
      const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
      if (parts.length === 0) return '?';
      return parts.slice(-2).map((part) => part.charAt(0).toUpperCase()).join('');
    },
    setScheduleViewMode(mode) {
      this.scheduleViewMode = mode;
      if (mode === 'day') this.syncWeekToSelectedDate();
    },
    syncWeekToSelectedDate() {
      if (!this.selectedScheduleDate) {
        this.selectedScheduleDate = this.formatDateIso(new Date());
      }
      this.currentWeekStart = new Date(`${this.selectedScheduleDate}T00:00:00`);
      this.loadSchedulesForWeek();
    },
    shiftScheduleDay(days) {
      const current = new Date(`${this.selectedScheduleDate || this.todayDateString}T00:00:00`);
      current.setDate(current.getDate() + days);
      this.selectedScheduleDate = this.formatDateIso(current);
      this.syncWeekToSelectedDate();
    },
    goTodayScheduleDay() {
      this.selectedScheduleDate = this.formatDateIso(new Date());
      this.syncWeekToSelectedDate();
    },
    // Display helpers
    statusLabel(status) {
      return {
        scheduled: 'Chờ trực',
        checked_in: 'Đang trực',
        checked_out: 'Đã hoàn thành',
        absent: 'Vắng mặt',
        cancelled: 'Đã hủy',
      }[status] || 'Không rõ';
    },
    formatTime(t) {
      if (!t) return '';
      return t.substring(0, 5);
    },
    formatDateTime(dt) {
      if (!dt) return '';
      const date = new Date(dt);
      return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')} ${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}`;
    },
    canCheckIn(sch) {
      if (sch.status !== 'scheduled') return false;
      const today = this.todayDateString;
      if (sch.date !== today) return false;

      // Allow 30 min early check-in
      const shiftStart = new Date(sch.date + 'T' + sch.start_time);
      const earliest = new Date(shiftStart.getTime() - 30 * 60 * 1000);
      return this.nowTime >= earliest;
    },
    liveDuration(checkInAt) {
      if (!checkInAt) return '00:00:00';
      const checkInTime = new Date(checkInAt);
      const diffMs = this.nowTime - checkInTime;
      if (diffMs < 0) return '00:00:00';

      const diffSecs = Math.floor(diffMs / 1000);
      const hours = Math.floor(diffSecs / 3600);
      const minutes = Math.floor((diffSecs % 3600) / 60);
      const seconds = diffSecs % 60;

      return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    },
    calculateAttendanceRate(rep) {
      const actualShifts = rep.total_shifts - rep.cancelled;
      if (actualShifts <= 0) return 0;
      return Math.round((rep.checked_in / actualShifts) * 100);
    },

    // Modals control
    closeShiftModal() { this.showShiftModal = false; },
    closeScheduleModal() { this.showScheduleModal = false; },
    closeEditScheduleModal() { this.showEditScheduleModal = false; },
  },
};
</script>

<style scoped>
.page {
  padding: 24px;
}

.table-card {
  background: var(--admin-surface, #fff);
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 12px;
  overflow: auto;
  margin-bottom: 24px;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th,
td {
  padding: 14px 16px;
  border-bottom: 1px solid var(--admin-border-soft, #e2e8f0);
  text-align: left;
  color: var(--admin-text, #1e293b);
}

th {
  font-weight: 700;
  font-size: 13px;
  color: var(--admin-faint, #64748b);
  background: rgba(0, 0, 0, 0.02);
}

.state {
  padding: 24px;
  color: var(--admin-faint, #64748b);
  text-align: center;
}

.avc-filters {
  padding: 0 0 12px 0 !important;
}

.filter-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.filter-tabs {
  display: flex;
  gap: 6px;
}

.filter-tabs button.tab-btn {
  height: 38px !important;
  min-height: 38px !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 8px !important;
  padding: 0 16px !important;
  border-radius: 8px !important;
  border: 1px solid var(--admin-border, #cbd5e1) !important;
  background: var(--admin-surface, #ffffff) !important;
  color: var(--admin-text, #475569) !important;
  font-size: 13px !important;
  font-weight: 600 !important;
  cursor: pointer !important;
  transition: all 0.18s !important;
}

.filter-tabs button.tab-btn.active {
  background: var(--admin-primary, #18181b) !important;
  color: var(--admin-primary-text, #ffffff) !important;
  border-color: var(--admin-primary, #18181b) !important;
}

.action-header {
  display: flex;
  gap: 12px;
}

.tab-content {
  margin-top: 0;
}

/* My shifts cards - Staff attendance styling */
.my-shifts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 20px;
}

.my-shift-card {
  background: var(--admin-surface, #fff);
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 16px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
  padding: 20px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.my-shift-card.never-hover-class-placeholder {
  transform: translateY(-4px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.08);
}

.my-shift-card.checked_in {
  border-left: 6px solid #22c55e;
}

.my-shift-card.checked_out {
  border-left: 6px solid #64748b;
  opacity: 0.85;
}

.my-shift-card.scheduled {
  border-left: 6px solid #3b82f6;
}

.my-shift-card.absent {
  border-left: 6px solid #ef4444;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.cluster-name {
  font-size: 12px;
  color: var(--admin-faint, #64748b);
  font-weight: 700;
  text-transform: uppercase;
}

.shift-name {
  font-size: 18px;
  font-weight: 800;
  color: var(--admin-text, #1e293b);
  margin-bottom: 8px;
}

.time-range {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 16px;
  font-weight: 700;
  color: var(--admin-text, #1e293b);
  margin-bottom: 12px;
}

.live-timer {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  padding: 10px;
  margin: 12px 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% {
    transform: scale(1);
  }

  50% {
    transform: scale(1.02);
  }

  100% {
    transform: scale(1);
  }
}

.timer-label {
  font-size: 12px;
  color: #166534;
  font-weight: 600;
}

.timer-value {
  font-size: 16px;
  font-family: monospace;
  font-weight: 800;
  color: #15803d;
}

.attendance-time {
  font-size: 13px;
  color: var(--admin-faint, #64748b);
  margin-top: 8px;
}

.notes {
  font-size: 13px;
  color: var(--admin-faint, #64748b);
  background: var(--admin-bg-soft, #f7f9fc);
  padding: 8px;
  border-radius: 6px;
  margin-top: 12px;
}

.footer-done-text {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  color: var(--admin-faint, #64748b);
  font-weight: 600;
  font-size: 14px;
  width: 100%;
  text-align: center;
}

/* Today styles for Owner */
.table-header {
  padding: 12px 16px;
  border-bottom: 1px solid var(--admin-border-soft, #e2e8f0);
}

.table-header h4 {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
}

.text-sub {
  font-size: 11px;
  color: var(--admin-faint, #64748b);
}

.col-staff {
  width: 180px;
  text-align: left;
  padding-left: 16px;
}

/* Schedules Grid Tab Styles */
.schedule-filters {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  background: var(--admin-surface, #fff);
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 12px;
  padding: 12px 16px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
}

.filter-group {
  display: flex;
  align-items: center;
  gap: 16px;
}

.filter-group label {
  flex-direction: row;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 700;
  white-space: nowrap;
}

.filter-group input[type="date"] {
  width: auto;
  min-height: 38px;
}

.week-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--admin-text, #1e293b);
  min-width: 120px;
  text-align: center;
}


.schedule-planner-toolbar {
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.schedule-view-switch {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  min-height: 38px;
  padding: 3px;
  border: 1px solid var(--admin-border, #27272a);
  border-radius: 8px;
  background: var(--admin-surface, #09090b);
}

.schedule-view-btn {
  min-width: 68px;
  min-height: 30px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: var(--admin-muted, #a1a1aa);
  font: inherit;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}

.schedule-view-btn.active {
  background: var(--admin-primary, #18181b);
  color: var(--admin-primary-text, #fff);
}

.day-schedule-board {
  display: grid;
  gap: 14px;
  padding: 2px 0 0;
}

.day-schedule-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--admin-border-soft, rgba(255,255,255,0.08));
}

.day-schedule-kicker {
  margin: 0 0 4px;
  color: var(--admin-faint, #71717a);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.day-schedule-head h3 {
  margin: 0;
  color: var(--admin-text, #f4f4f5);
  font-size: 16px;
  font-weight: 600;
}

.day-schedule-controls {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.day-date-input {
  width: 150px !important;
  min-height: 38px;
}

.day-schedule-content,
.day-shift-groups {
  display: grid;
  gap: 0;
}

.day-summary-row {
  display: flex;
  align-items: center;
  gap: 18px;
  flex-wrap: wrap;
  padding: 2px 0 14px;
  border-bottom: 1px solid var(--admin-border-soft, rgba(255,255,255,0.08));
}

.day-summary-item {
  display: inline-flex;
  align-items: baseline;
  gap: 8px;
  min-width: 0;
}

.day-summary-item span {
  color: var(--admin-faint, #a1a1aa);
  font-size: 12px;
}

.day-summary-item strong {
  color: var(--admin-text, #f4f4f5);
  font-size: 16px;
  font-weight: 600;
}

.day-empty-state {
  display: grid;
  gap: 6px;
  padding: 18px 0;
  color: var(--admin-muted, #a1a1aa);
}

.day-empty-state strong {
  color: var(--admin-text, #f4f4f5);
  font-weight: 600;
}

.day-shift-group {
  display: grid;
  grid-template-columns: minmax(160px, 220px) 1fr;
  gap: 16px;
  padding: 16px 0;
  border-bottom: 1px solid var(--admin-border-soft, rgba(255,255,255,0.08));
}

.day-shift-group-head {
  display: grid;
  gap: 6px;
  align-content: start;
}

.day-shift-group-head div {
  display: grid;
  gap: 4px;
}

.day-shift-group-head strong {
  color: var(--admin-text, #f4f4f5);
  font-size: 14px;
  font-weight: 600;
}

.day-shift-group-head span,
.day-shift-group-head em {
  color: var(--admin-faint, #a1a1aa);
  font-size: 12px;
  font-style: normal;
}

.day-shift-staff-list,
.day-unassigned-list {
  display: grid;
  gap: 0;
}

.day-staff-shift,
.day-unassigned-chip {
  display: grid;
  grid-template-columns: 28px 1fr;
  align-items: center;
  gap: 10px;
  min-height: 44px;
  width: 100%;
  padding: 6px 0;
  border: 0;
  border-radius: 0;
  background: transparent;
  color: var(--admin-text, #f4f4f5);
  cursor: pointer;
  text-align: left;
}

.day-staff-shift + .day-staff-shift,
.day-unassigned-chip + .day-unassigned-chip {
  border-top: 1px solid var(--admin-border-soft, rgba(255,255,255,0.06));
}

.day-staff-avatar,
.day-unassigned-chip span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: var(--admin-surface-muted, #27272a);
  color: var(--admin-text, #f4f4f5);
  font-size: 11px;
  font-weight: 600;
}

.day-staff-main {
  display: grid;
  gap: 2px;
  text-align: left;
}

.day-staff-main strong,
.day-unassigned-chip strong {
  font-size: 13px;
  font-weight: 500;
}

.day-staff-main em {
  color: var(--admin-faint, #a1a1aa);
  font-size: 11px;
  font-style: normal;
}

.day-unassigned-panel {
  display: grid;
  grid-template-columns: minmax(160px, 220px) 1fr;
  gap: 16px;
  padding: 16px 0 0;
}

.day-unassigned-panel header {
  display: grid;
  align-content: start;
  gap: 4px;
  color: var(--admin-text, #f4f4f5);
}

.day-unassigned-panel header strong {
  font-size: 13px;
  font-weight: 500;
}

.day-unassigned-panel header span {
  color: var(--admin-faint, #a1a1aa);
  font-size: 12px;
}
.table-schedule-grid {
  overflow-x: auto;
}

.table-schedule-grid table {
  table-layout: fixed;
  width: 100%;
}

.col-day {
  text-align: center;
  width: 13%;
  font-size: 13px;
  padding: 12px 6px;
}

.day-date {
  font-size: 11px;
  color: var(--admin-faint, #64748b);
  margin-top: 2px;
}

.col-day-cell {
  padding: 6px !important;
  vertical-align: middle;
  border-left: 1px solid var(--admin-border-soft, #e2e8f0);
}

.cell-schedules-container {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 6px;
  min-height: 80px;
}

.cell-add-shift-btn {
  min-height: 32px;
  align-self: center;
  padding: 4px 10px;
  border: 1px dashed var(--admin-border, #cbd5e1);
  border-radius: 999px;
  background: var(--admin-surface, #fff);
  color: var(--admin-faint, #64748b);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  opacity: 0.82;
  transition: border-color 0.16s ease, color 0.16s ease, opacity 0.16s ease;
}

.cell-add-shift-btn:focus-visible {
  border-color: var(--admin-focus-border, #18181b);
  color: var(--admin-primary-dark, #27272a);
  opacity: 1;
}

.cell-add-shift-btn:focus-visible {
  outline: 2px solid var(--admin-focus-ring, rgba(24, 24, 27, 0.22));
  outline-offset: 2px;
}

.cell-schedule-text {
  padding: 4px 6px;
  font-size: 13px;
  cursor: pointer;
  background: transparent !important;
  border: none !important;
  box-shadow: none !important;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 3px;
  margin-bottom: 6px;
}

.cell-schedule-text .text-time {
  font-weight: 500;
  font-size: 13px;
}

.cell-schedule-text .text-name {
  font-size: 13px;
  font-weight: 400;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cell-schedule-text .status-suffix {
  font-size: 13px;
  opacity: 0.8;
  font-weight: 400;
}

/* Scheduled status */
.cell-schedule-text.scheduled .text-time {
  color: var(--admin-text, #1e293b) !important;
}

.cell-schedule-text.scheduled .text-name {
  color: var(--admin-text, #1e293b) !important;
}

/* Checked In status */
.cell-schedule-text.checked_in .text-time {
  color: var(--admin-success, #22c55e) !important;
}

.cell-schedule-text.checked_in .text-name {
  color: var(--admin-text, #1e293b) !important;
}

/* Checked Out status */
.cell-schedule-text.checked_out {
  opacity: 0.65;
}

.cell-schedule-text.checked_out .text-time,
.cell-schedule-text.checked_out .text-name {
  color: var(--admin-faint, #94a3b8) !important;
}

/* Absent status */
.cell-schedule-text.absent .text-time {
  color: var(--admin-danger, #ef4444) !important;
}

.cell-schedule-text.absent .text-name {
  color: var(--admin-text, #1e293b) !important;
}

/* Cancelled status */
.cell-schedule-text.cancelled {
  opacity: 0.5;
  text-decoration: line-through;
}

.cell-schedule-text.cancelled .text-time,
.cell-schedule-text.cancelled .text-name {
  color: var(--admin-faint, #94a3b8) !important;
}

/* Status Badges - Clean Text Style to match system.css */
.badge {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  background: transparent !important;
  padding: 0 !important;
  border: none !important;
  border-radius: 0 !important;
  font-size: 13px !important;
  font-weight: 500 !important;
  line-height: 1.25 !important;
  text-transform: none !important;
  white-space: nowrap !important;
}

.badge.scheduled {
  color: var(--admin-text, #1e293b) !important;
}

.badge.checked_in,
.badge.active {
  color: var(--admin-success-text, #10b981) !important;
}

.badge.checked_out {
  color: var(--admin-faint, #94a3b8) !important;
}

.badge.absent {
  color: var(--admin-danger-text, var(--admin-danger, #ef4444)) !important;
}

.badge.cancelled,
.badge.inactive {
  color: var(--admin-faint, #94a3b8) !important;
}

.text-danger {
  color: #ef4444;
  font-weight: 700;
}

/* Modals styles matching existing owner design */
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal {
  background: var(--admin-surface, #fff);
  border-radius: 16px;
  width: 90%;
  max-width: 500px;
  padding: 24px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.modal h3 {
  margin-top: 0;
  margin-bottom: 20px;
  font-size: 18px;
  font-weight: 800;
}

.schedule-modal {
  width: min(960px, calc(100vw - 48px));
  max-width: 960px;
  box-sizing: border-box;
  padding: 0;
  overflow: visible;
  font-size: 13px;
}

.schedule-modal-head {
  padding: 22px 24px 14px;
}

.schedule-modal-head h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
}

.schedule-modal .sch-grid {
  display: grid;
  grid-template-columns: minmax(240px, 0.85fr) minmax(0, 1.15fr);
  gap: 26px;
  margin: 0;
  padding: 20px 24px 12px;
}

.schedule-panel {
  display: grid;
  align-content: start;
  gap: 14px;
  min-width: 0;
  padding: 0;
  border: 0;
  background: transparent;
}

.schedule-panel-left {
  padding-right: 0;
}

.schedule-panel-right {
  padding-left: 0;
  border-left: 0;
}

.schedule-panel-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  color: var(--admin-faint, #64748b);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.schedule-panel-title strong {
  min-width: 28px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  color: var(--admin-text, #1e293b);
  font-size: 11px;
  letter-spacing: 0;
}

.schedule-panel-title-spaced {
  margin-top: 14px;
}

.schedule-field {
  display: grid;
  gap: 8px;
}

.time-input-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
}

.schedule-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin: 0;
  padding: 16px 24px 22px;
}

.schedule-modal .staff-chip-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 8px;
}

.schedule-modal .staff-chip {
  width: 100%;
  min-width: 0;
  justify-content: flex-start;
  padding: 8px 12px 8px 8px;
}

.schedule-modal .staff-chip.active {
  font-weight: 500;
}

.schedule-modal .chip-avatar {
  font-weight: 500;
}

.schedule-modal .chip-name {
  max-width: none;
  min-width: 0;
}

.schedule-modal .field-label,
.schedule-modal .schedule-panel-title {
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.03em;
}

.schedule-modal .dropdown-trigger,
.schedule-modal .styled-input,
.schedule-modal .staff-chip {
  font-size: 13px !important;
}

.schedule-modal .dropdown-trigger,
.schedule-modal .time-input-control,
.schedule-modal .styled-input,
.schedule-modal textarea {
  max-width: 100%;
  box-sizing: border-box;
}

.grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 20px;
}

.full-width {
  grid-column: span 2;
}

label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
  color: var(--admin-faint, #64748b);
}

input,
select,
textarea {
  border: 1px solid var(--admin-border, #cbd5e1) !important;
  border-radius: 8px !important;
  padding: 8px 12px !important;
  font-size: 14px !important;
  color: var(--admin-text, #1e293b) !important;
  -webkit-text-fill-color: var(--admin-text, #1e293b) !important;
  background: var(--admin-surface, #fff) !important;
  width: 100% !important;
}

input:focus,
select:focus,
textarea:focus {
  outline: none;
  border-color: var(--admin-focus-border, #18181b) !important;
  box-shadow: 0 0 0 2px var(--admin-focus-ring, rgba(24, 24, 27, 0.18)) !important;
}

input:disabled,
select:disabled,
textarea:disabled {
  opacity: 0.6 !important;
  cursor: not-allowed !important;
  background: rgba(255, 255, 255, 0.05) !important;
  color: var(--admin-text, #1e293b) !important;
  -webkit-text-fill-color: var(--admin-text, #1e293b) !important;
}

input[type='date'],
input[type='time'] {
  color: var(--admin-text, #1e293b) !important;
  -webkit-text-fill-color: var(--admin-text, #1e293b) !important;
  color-scheme: light !important;
}

:root[data-theme="dark"] input[type='date'],
[data-theme="dark"] input[type='date'],
:root[data-theme="dark"] input[type='time'],
[data-theme="dark"] input[type='time'] {
  color-scheme: dark !important;
}

.check {
  flex-direction: row;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  user-select: none;
}

.check input {
  width: auto;
}

.staff-checkbox-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 10px;
  border: 1px solid var(--admin-border, #cbd5e1);
  border-radius: 8px;
  padding: 12px;
  max-height: 120px;
  overflow-y: auto;
}

.date-tags {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.tags-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tag {
  background: var(--admin-primary-soft, #eff6ff);
  color: var(--admin-primary, #18181b);
  border: 1px solid var(--admin-primary-ring, rgba(24, 24, 27, 0.18));
  border-radius: 6px;
  padding: 4px 8px;
  font-size: 12px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 6px;
}

.tag button {
  background: none;
  border: none;
  color: var(--admin-primary, #18181b);
  font-weight: bold;
  cursor: pointer;
  padding: 0;
  font-size: 14px;
}

footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 24px;
}

/* Flex header toggle */
.flex-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.view-mode-toggle {
  display: inline-flex;
  background: var(--admin-bg-soft, #f7f9fc);
  border: 1px solid var(--admin-border-soft, #cbd5e1);
  padding: 3px;
  border-radius: 8px;
  gap: 4px;
}

.view-mode-toggle .toggle-btn {
  height: 32px !important;
  min-height: 32px !important;
  border: none !important;
  background: transparent !important;
  color: var(--admin-faint, #64748b) !important;
  padding: 0 12px !important;
  font-size: 13px !important;
  font-weight: 600 !important;
  border-radius: 6px !important;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.2s ease;
}

.view-mode-toggle .toggle-btn.active {
  background: var(--admin-surface, #ffffff) !important;
  color: var(--admin-text, #1e293b) !important;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
}

/* Shift Timeline View Styles */
.shift-timeline-layout {
  padding: 16px;
  background: var(--admin-surface, #ffffff);
  overflow-x: auto;
}

.shift-timeline-layout .timeline-board {
  min-width: 900px;
}

.shift-timeline-layout .timeline-axis {
  display: flex;
  align-items: center;
  border-bottom: 2px solid var(--admin-border-soft, #e2e8f0);
  padding-bottom: 10px;
  margin-bottom: 10px;
}

.shift-timeline-layout .axis-staff {
  width: 180px;
  font-weight: 700;
  color: var(--admin-faint, #64748b);
  font-size: 13px;
}

.shift-timeline-layout .axis-track {
  position: relative;
  flex: 1;
  height: 20px;
}

.shift-timeline-layout .axis-tick {
  position: absolute;
  transform: translateX(-50%);
  font-size: 11px;
  font-weight: 700;
  color: var(--admin-faint, #94a3b8);
}

.shift-timeline-layout .timeline-row {
  display: flex;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px dashed var(--admin-border-soft, #f1f5f9);
}

.shift-timeline-layout .staff-meta {
  width: 180px;
  padding-right: 12px;
}

.shift-timeline-layout .staff-meta strong {
  display: block;
  font-size: 14px;
  color: var(--admin-text, #1e293b);
}

.shift-timeline-layout .staff-meta span {
  font-size: 11px;
  color: var(--admin-faint, #94a3b8);
}

.shift-timeline-layout .timeline-track {
  position: relative;
  flex: 1;
  height: 54px;
  background: var(--admin-bg-soft, #f8fafc);
  border-radius: 8px;
  border: 1px solid var(--admin-border-soft, #e2e8f0);
}

.shift-timeline-layout .track-gridline {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 1px;
  border-left: 1px dashed var(--admin-border-soft, #cbd5e1);
  opacity: 0.5;
}

.shift-timeline-layout .timeline-block {
  position: absolute;
  top: 4px;
  bottom: 4px;
  border-radius: 6px;
  padding: 2px 6px !important;
  cursor: pointer;
  display: flex !important;
  flex-direction: column !important;
  justify-content: center !important;
  align-items: center !important;
  text-align: center;
  overflow: hidden;
  border: 1px solid transparent;
  box-sizing: border-box;
}

.shift-timeline-layout .timeline-block strong {
  font-size: 12px;
  font-weight: 700;
  display: block;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
  line-height: 1.2 !important;
  margin: 0 !important;
}

.shift-timeline-layout .timeline-block .block-time {
  font-size: 11px;
  opacity: 0.85;
  white-space: nowrap;
  line-height: 1.2 !important;
  margin: 0 !important;
  margin-top: 2px !important;
}

/* Status colors matching theme variables dynamically */
.shift-timeline-layout .timeline-block.scheduled {
  background-color: var(--admin-primary-soft, #f4f4f5) !important;
  border-color: color-mix(in srgb, var(--admin-primary, #18181b) 30%, transparent) !important;
  color: var(--admin-primary, #18181b) !important;
}

.shift-timeline-layout .timeline-block.checked_in {
  background-color: color-mix(in srgb, #22c55e 12%, transparent) !important;
  border-color: color-mix(in srgb, #22c55e 35%, transparent) !important;
  color: #15803d !important;
}

.shift-timeline-layout .timeline-block.checked_out {
  background-color: var(--admin-bg, #fafafa) !important;
  border-color: var(--admin-border-soft, #e4e4e7) !important;
  color: var(--admin-muted, #71717a) !important;
}

.shift-timeline-layout .timeline-block.absent {
  background-color: color-mix(in srgb, var(--admin-danger, #ef4444) 12%, transparent) !important;
  border-color: color-mix(in srgb, var(--admin-danger, #ef4444) 35%, transparent) !important;
  color: var(--admin-danger, #dc2626) !important;
}

.shift-timeline-layout .timeline-block.cancelled {
  background-color: transparent !important;
  border-color: var(--admin-border-soft, #e4e4e7) !important;
  color: var(--admin-muted, #71717a) !important;
  text-decoration: line-through;
  opacity: 0.6;
}

.cell-schedules-container.clickable-cell {
  cursor: pointer;
  border-radius: 8px;
  padding: 6px;
}


/* ========================================
   SCHEDULE MODAL CUSTOM FORM STYLES
   ======================================== */

.field-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--admin-faint, #64748b);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 8px;
}

/* Staff chip grid */
.staff-chip-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.staff-chip {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 7px 12px 7px 8px;
  border-radius: 999px;
  border: 1.5px solid var(--admin-border, #e2e8f0);
  background: var(--admin-surface-muted, #f8fafc);
  color: var(--admin-text, #1e293b);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
}

.staff-chip:hover {
  border-color: var(--admin-focus-border, #18181b);
  color: var(--admin-primary-dark, #27272a);
}

.staff-chip.active {
  border-color: var(--admin-focus-border, #18181b);
  color: var(--admin-primary-dark, #27272a);
  font-weight: 600;
}

.chip-avatar {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--admin-border, #cbd5e1);
  color: var(--admin-muted, #475569);
  font-size: 11px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  text-transform: uppercase;
}

.staff-chip.active .chip-avatar {
  background: var(--admin-primary, #18181b);
  color: #fff;
}

.chip-name {
  max-width: 120px;
  overflow: hidden;
  text-overflow: ellipsis;
}

.chip-check {
  font-size: 11px;
  color: var(--admin-primary-dark, #27272a);
  font-weight: 700;
}

/* Date picker row */
.date-picker-row {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.date-tags-list {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  min-width: 0;
}

.date-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  min-height: 30px;
  padding: 0;
  border: 0;
  border-radius: 0;
  background: transparent;
  font-size: 13px;
  font-weight: 500;
  color: var(--admin-text, #f4f4f5);
}

.tag-remove {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  background: transparent;
  border: 0;
  padding: 0;
  cursor: pointer;
  font-size: 14px;
  color: var(--admin-faint, #71717a);
  line-height: 1;
  opacity: 0.85;
  transition: opacity 0.15s, color 0.15s;
}

.tag-remove:hover {
  color: var(--admin-text, #f4f4f5);
  opacity: 1;
}

.date-placeholder {
  font-size: 13px;
  color: var(--admin-faint, #94a3b8);
  font-style: italic;
}

/* Styled select dropdown */
.styled-select-wrap {
  position: relative;
  display: block;
}

.styled-select {
  width: 100%;
  padding: 9px 36px 9px 12px;
  border: 1.5px solid var(--admin-border, #e2e8f0);
  border-radius: 8px;
  background: var(--admin-surface, #fff);
  color: var(--admin-text, #1e293b);
  font-size: 14px;
  font-weight: 500;
  appearance: none;
  -webkit-appearance: none;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
  outline: none;
}

.styled-select:focus {
  border-color: var(--admin-focus-border, #18181b);
  box-shadow: 0 0 0 3px var(--admin-focus-ring, rgba(24, 24, 27, 0.18));
}

.select-arrow {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
  color: var(--admin-faint, #64748b);
  font-size: 12px;
}

/* Styled generic input */
.styled-input {
  width: 100%;
  padding: 9px 12px;
  border: 1.5px solid var(--admin-border, #e2e8f0);
  border-radius: 8px;
  background: var(--admin-surface, #fff);
  color: var(--admin-text, #1e293b);
  font-size: 14px;
  font-weight: 500;
  outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;
  box-sizing: border-box;
}

.styled-input:focus {
  border-color: var(--admin-focus-border, #18181b);
  box-shadow: 0 0 0 3px var(--admin-focus-ring, rgba(24, 24, 27, 0.18));
}

input[type="date"].styled-input::-webkit-calendar-picker-indicator,
input[type="time"].styled-input::-webkit-calendar-picker-indicator {
  filter: var(--admin-icon-filter, none);
  opacity: 0.6;
  cursor: pointer;
}

/* Schedule modal grid */
.sch-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
  margin-top: 4px;
}

.sch-full {
  grid-column: 1 / -1;
}

/* ========================
   Custom Date Picker
   ======================== */
.custom-date-picker {
  display: grid;
  gap: 10px;
}

.date-tags-bar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.cal-toggle-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 34px;
  padding: 0 6px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: var(--admin-muted, #a1a1aa);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
  margin-left: auto;
}

.cal-toggle-btn:hover {
  color: var(--admin-text, #f4f4f5);
  background: transparent;
}

.cal-panel {
  padding: 12px;
  border: 1px solid var(--admin-border, #27272a);
  border-radius: 8px;
  background: var(--admin-surface, #fff);
}

.cal-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.cal-month-label {
  font-size: 14px;
  font-weight: 700;
  color: var(--admin-text, #1e293b);
}

.cal-nav-btn {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  border: 1px solid var(--admin-border, #e2e8f0);
  background: var(--admin-surface-muted, #f8fafc);
  color: var(--admin-text, #1e293b);
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s, border-color 0.15s;
}

.cal-nav-btn:hover {
  background: var(--admin-hover, #f1f5f9);
  border-color: var(--admin-focus-border, #18181b);
}

.cal-weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  margin-bottom: 4px;
  gap: 2px;
}

.cal-weekdays span {
  text-align: center;
  font-size: 11px;
  font-weight: 700;
  color: var(--admin-faint, #64748b);
  padding: 2px 0;
  text-transform: uppercase;
}

.cal-days-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 2px;
}

.cal-day {
  aspect-ratio: 1;
  border-radius: 7px;
  border: none;
  background: transparent;
  color: var(--admin-text, #1e293b);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.12s, color 0.12s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.cal-day:hover:not(:disabled):not(.is-past) {
  color: var(--admin-primary-dark, #27272a);
}

.cal-day.other-month {
  color: var(--admin-faint, #94a3b8);
  opacity: 0.4;
}

.cal-day.is-today {
  border: 1.5px solid var(--admin-primary, #18181b);
  font-weight: 700;
  color: var(--admin-primary-dark, #27272a);
}

.cal-day.is-selected {
  background: var(--admin-primary, #18181b) !important;
  color: #fff !important;
  font-weight: 700;
}

.cal-day.is-past,
.cal-day:disabled {
  color: var(--admin-faint, #94a3b8);
  opacity: 0.35;
  cursor: not-allowed;
}

/* ========================
   Custom Dropdown
   ======================== */
.custom-dropdown {
  position: relative;
}

.dropdown-trigger {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 10px 14px;
  border: 1.5px solid var(--admin-border, #e2e8f0);
  border-radius: 10px;
  background: var(--admin-surface, #fff);
  color: var(--admin-text, #1e293b);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
  text-align: left;
}

.dropdown-trigger:hover,
.custom-dropdown.open .dropdown-trigger {
  border-color: var(--admin-focus-border, #18181b);
  box-shadow: 0 0 0 3px var(--admin-focus-ring, rgba(24, 24, 27, 0.15));
}

.dropdown-placeholder {
  color: var(--admin-faint, #94a3b8);
}

.dropdown-caret {
  font-size: 11px;
  color: var(--admin-faint, #64748b);
  flex-shrink: 0;
  transition: transform 0.2s ease;
}

.dropdown-caret.rotated {
  transform: rotate(180deg);
}

.dropdown-menu {
  position: absolute;
  z-index: 50;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: var(--admin-surface, #fff);
  border: 1.5px solid var(--admin-border, #e2e8f0);
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
  overflow: hidden;
}

.dropdown-item {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 10px 14px;
  background: transparent;
  border: none;
  color: var(--admin-text, #1e293b);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  text-align: left;
  transition: background 0.12s;
  border-bottom: 1px solid var(--admin-border-soft, #f0f0f0);
}

.dropdown-item:last-child {
  border-bottom: none;
}

.dropdown-item:hover {
  background: var(--admin-hover, #f1f5f9);
}

.dropdown-item.active {
  color: var(--admin-primary-dark, #27272a);
}

.dropdown-item.active strong {
  color: inherit;
}

.shift-time-badge {
  font-size: 12px;
  font-weight: 600;
  color: var(--admin-faint, #64748b);
  background: var(--admin-bg-soft, #f7f9fc);
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  padding: 2px 8px;
  border-radius: 999px;
  white-space: nowrap;
}

.dropdown-item.active .shift-time-badge {
  background: color-mix(in srgb, var(--admin-primary, #18181b) 15%, white);
  border-color: color-mix(in srgb, var(--admin-primary, #18181b) 30%, transparent);
  color: var(--admin-primary-dark, #27272a);
}

/* ========================
   Time inputs
   ======================== */
.time-input-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.time-input-card {
  display: grid;
  gap: 8px;
  color: var(--admin-faint, #64748b);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.time-input-control {
  height: 44px;
  border: 1px solid var(--admin-border, #cbd5e1) !important;
  border-radius: 10px !important;
  background: var(--admin-surface, #fff) !important;
  color: var(--admin-text, #1e293b) !important;
  -webkit-text-fill-color: var(--admin-text, #1e293b) !important;
  font-size: 14px !important;
  font-weight: 500 !important;
  padding: 0 12px !important;
}

.time-input-control:focus {
  outline: none;
  border-color: var(--admin-focus-border, #18181b) !important;
  box-shadow: 0 0 0 2px var(--admin-focus-ring, rgba(24, 24, 27, 0.18)) !important;
}

.time-input-control::-webkit-calendar-picker-indicator {
  opacity: 0.7;
}
/* Legacy modal grid (for other modals like shift template modal) */
.modal .grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.modal .grid .full-width {
  grid-column: 1 / -1;
}
.mobile-today-list,
.mobile-week-list {
  display: none;
}

.mobile-shift-card,
.mobile-staff-week,
.mobile-day-card {
  background: var(--admin-surface, #fff);
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 12px;
}

@media (max-width: 920px) {
  .schedule-modal {
    width: min(760px, calc(100vw - 32px));
  }

  .schedule-modal .sch-grid {
    grid-template-columns: 1fr;
    gap: 18px;
  }

  .time-input-row {
    grid-template-columns: 1fr;
    gap: 12px;
  }
}

@media (max-width: 760px) {
  .schedule-planner-toolbar,
  .day-schedule-head {
    align-items: stretch;
    flex-direction: column;
  }

  .schedule-view-switch,
  .day-schedule-controls,
  .day-date-input {
    width: 100% !important;
  }

  .schedule-view-btn,
  .day-schedule-controls .btn,
  .day-date-input {
    min-height: 44px;
  }

  .schedule-view-btn {
    flex: 1;
  }

  .day-summary-row {
    align-items: flex-start;
    gap: 10px 16px;
  }

  .day-shift-group,
  .day-unassigned-panel {
    grid-template-columns: 1fr;
    gap: 10px;
  }

  .day-staff-shift,
  .day-unassigned-chip {
    width: 100%;
  }
  .page {
    padding: 14px 12px 96px;
    overflow-x: hidden;
  }

  .avc-filters {
    padding: 6px 0 10px;
    margin-inline: -2px;
  }

  .filter-row {
    display: block;
  }

  .filter-tabs {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding: 2px 2px 8px;
    scroll-snap-type: x proximity;
    -webkit-overflow-scrolling: touch;
  }

  .filter-tabs::-webkit-scrollbar {
    display: none;
  }

  .filter-tabs button.tab-btn {
    flex: 0 0 auto;
    min-width: max-content;
    min-height: 44px !important;
    height: 44px !important;
    padding: 0 14px !important;
    scroll-snap-align: start;
  }

  .tab-content {
    margin-top: 6px;
  }

  .filter-group {
    display: grid;
    grid-template-columns: 44px minmax(0, 1fr) 44px;
    gap: 8px;
    align-items: center;
    margin-bottom: 12px;
  }

  .filter-group .btn {
    min-height: 44px;
  }

  .filter-group .btn.secondary {
    grid-column: 1 / -1;
    width: 100%;
  }

  .week-label {
    min-width: 0;
    font-size: 14px;
    line-height: 1.25;
  }

  .table-card {
    border-radius: 12px;
    overflow: visible;
    margin-bottom: 16px;
  }

  .owner-today-container .table-card > table,
  .table-schedule-grid > table {
    display: none;
  }

  .mobile-today-list,
  .mobile-week-list {
    display: grid;
    gap: 12px;
    padding: 12px;
  }

  .table-header {
    padding: 14px 14px 10px;
  }

  .table-header h4 {
    width: 100%;
    font-size: 15px;
  }

  .view-mode-toggle {
    width: 100%;
    display: grid;
    grid-template-columns: 1fr 1fr;
  }

  .view-mode-toggle .toggle-btn {
    min-height: 40px !important;
    justify-content: center;
    padding: 0 8px !important;
  }

  .shift-timeline-layout {
    padding: 10px;
    overflow-x: auto;
  }

  .shift-timeline-layout .timeline-board {
    min-width: 760px;
  }

  .mobile-shift-card {
    padding: 14px;
    display: grid;
    gap: 12px;
    border-left: 4px solid var(--admin-primary, #18181b);
  }

  .mobile-shift-card.checked_in,
  .mobile-schedule-pill.checked_in {
    border-left-color: #22c55e;
  }

  .mobile-shift-card.checked_out,
  .mobile-schedule-pill.checked_out {
    border-left-color: #94a3b8;
  }

  .mobile-shift-card.absent,
  .mobile-schedule-pill.absent {
    border-left-color: #ef4444;
  }

  .mobile-shift-card.cancelled,
  .mobile-schedule-pill.cancelled {
    border-left-color: #94a3b8;
    opacity: 0.72;
  }

  .mobile-shift-card__top,
  .mobile-staff-week__header,
  .mobile-day-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }

  .mobile-shift-card__top strong,
  .mobile-staff-week__header strong,
  .mobile-day-card__head strong {
    display: block;
    color: var(--admin-text, #1e293b);
    font-size: 14px;
    line-height: 1.3;
  }

  .mobile-shift-card__top span,
  .mobile-staff-week__header span,
  .mobile-day-card__head span {
    display: block;
    margin-top: 2px;
    color: var(--admin-faint, #64748b);
    font-size: 12px;
  }

  .mobile-shift-card__body {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }

  .mobile-shift-card__body div {
    display: grid;
    gap: 3px;
    min-width: 0;
  }

  .mobile-shift-card__body span {
    color: var(--admin-faint, #64748b);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
  }

  .mobile-shift-card__body strong {
    color: var(--admin-text, #1e293b);
    font-size: 13px;
    line-height: 1.35;
    overflow-wrap: anywhere;
  }

  .mobile-card-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
  }

  .mobile-action-btn,
  .mobile-add-small,
  .mobile-add-day {
    min-height: 44px;
    border: 1px solid var(--admin-border-soft, #e2e8f0);
    border-radius: 8px;
    background: var(--admin-surface, #fff);
    color: var(--admin-text, #1e293b);
    font-weight: 700;
    cursor: pointer;
  }

  .mobile-action-btn.danger {
    color: var(--admin-danger, #dc2626);
  }

  .mobile-staff-week {
    overflow: hidden;
  }

  .mobile-staff-week__header {
    padding: 14px;
    border-bottom: 1px solid var(--admin-border-soft, #e2e8f0);
  }

  .mobile-add-small,
  .mobile-add-day {
    min-width: 76px;
    min-height: 40px;
    padding: 0 12px;
    background: var(--admin-primary-soft, #e2f6e8);
    color: var(--admin-primary-dark, #27272a);
    border-color: color-mix(in srgb, var(--admin-primary, #18181b) 25%, transparent);
  }

  .mobile-empty-week {
    padding: 14px;
    color: var(--admin-faint, #64748b);
    font-size: 13px;
  }

  .mobile-day-stack {
    display: grid;
    gap: 10px;
    padding: 12px;
  }

  .mobile-day-card {
    padding: 12px;
    background: var(--admin-bg-soft, #f7f9fc);
  }

  .mobile-day-card__head {
    margin-bottom: 10px;
  }

  .mobile-schedule-pill {
    width: 100%;
    min-height: 56px;
    display: grid;
    grid-template-columns: minmax(84px, auto) 1fr auto;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
    padding: 10px 10px 10px 12px;
    border: 1px solid var(--admin-border-soft, #e2e8f0);
    border-left: 4px solid var(--admin-primary, #18181b);
    border-radius: 10px;
    background: var(--admin-surface, #fff);
    color: var(--admin-text, #1e293b);
    text-align: left;
    cursor: pointer;
  }

  .mobile-schedule-pill span {
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
  }

  .mobile-schedule-pill strong {
    min-width: 0;
    font-size: 13px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .mobile-schedule-pill em {
    color: var(--admin-faint, #64748b);
    font-size: 11px;
    font-style: normal;
    font-weight: 700;
    white-space: nowrap;
  }

  .floating-add-container {
    right: 16px;
    bottom: 18px;
  }

  .btn-float-add {
    min-height: 52px;
    border-radius: 999px;
  }

  .modal-backdrop {
    align-items: flex-end;
    padding: 10px;
  }

  .modal {
    width: 100%;
    max-width: none;
    max-height: calc(100vh - 24px);
    overflow-y: auto;
    border-radius: 16px 16px 12px 12px;
    padding: 18px;
  }

  .modal .grid,
  .sch-grid,
  .schedule-modal .sch-grid,
  .time-input-row {
    grid-template-columns: 1fr;
    gap: 14px;
  }

  .schedule-modal {
    width: 100%;
    max-width: none;
    overflow-y: auto;
  }

  .schedule-modal-head {
    padding: 18px 18px 12px;
  }

  .schedule-panel {
    padding: 0;
  }

  .schedule-panel-left,
  .schedule-panel-right {
    padding-left: 0;
    padding-right: 0;
    border-left: 0;
  }

  .schedule-modal .staff-chip-grid {
    grid-template-columns: 1fr;
  }

  .schedule-modal-footer {
    position: sticky;
    bottom: -18px;
    margin: 0 -18px -18px;
    padding: 12px 18px 18px;
    background: var(--admin-surface, #fff);
    border-top: none;
  }

  .full-width,
  .modal .grid .full-width,
  .sch-full {
    grid-column: 1 / -1;
  }

  footer {
    position: sticky;
    bottom: -18px;
    margin: 18px -18px -18px;
    padding: 12px 18px 18px;
    background: var(--admin-surface, #fff);
    border-top: 1px solid var(--admin-border-soft, #e2e8f0);
  }

  footer .btn {
    flex: 1;
    min-height: 44px;
  }
}

@media (max-width: 420px) {
  .mobile-shift-card__body,
  .mobile-card-actions {
    grid-template-columns: 1fr;
  }

  .mobile-schedule-pill {
    grid-template-columns: 1fr;
    gap: 4px;
  }

  .mobile-schedule-pill strong,
  .mobile-schedule-pill span,
  .mobile-schedule-pill em {
    white-space: normal;
  }
}
</style>
