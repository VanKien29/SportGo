<template>
    <div class="min-h-screen bg-sportgo-bg">
        <PublicNavbar />

        <main class="pt-16">
            <!-- Loading -->
            <div v-if="loading" class="flex justify-center items-center py-32">
                <div
                    class="w-12 h-12 border-4 border-sportgo-accent border-t-transparent rounded-full animate-spin"
                ></div>
            </div>

            <!-- Error -->
            <div
                v-else-if="error"
                class="max-w-4xl mx-auto px-4 py-16 text-center"
            >
                <div
                    class="bg-red-50 border border-red-200 rounded-2xl p-12 text-red-700 font-bold"
                >
                    {{ error }}
                </div>
            </div>

            <template v-else-if="venue">
                <!-- Hero Section -->
                <div class="relative min-h-[340px] overflow-hidden bg-gray-900">
                    <div
                        class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-emerald-900 to-blue-900 text-8xl font-black text-white/20"
                    >
                        {{ venue.name.slice(0, 2).toUpperCase() }}
                    </div>
                    <img
                        v-if="imageUrl(activeImage)"
                        :src="imageUrl(activeImage)"
                        :alt="venue.name"
                        class="absolute inset-0 w-full h-full object-cover"
                        @error="hideBrokenImage"
                    />
                    <div
                        class="absolute inset-0 bg-gradient-to-t from-gray-900/90 via-gray-900/40 to-transparent"
                    ></div>

                    <div
                        class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full min-h-[340px] flex flex-col justify-end pb-8"
                    >
                        <router-link
                            to="/venues"
                            class="inline-flex items-center gap-1.5 text-emerald-300 text-sm font-bold mb-6 hover:text-white transition-colors w-fit"
                        >
                            ← Tìm sân
                        </router-link>
                        <div class="flex flex-wrap gap-2 mb-4">
                            <span
                                v-for="type in venue.court_types"
                                :key="type.id"
                                class="bg-white/90 backdrop-blur-md text-gray-900 text-xs font-black px-3 py-1.5 rounded-lg"
                                >{{ type.name }}</span
                            >
                        </div>
                        <h1
                            class="text-4xl sm:text-5xl font-black text-white mb-3 leading-tight"
                        >
                            {{ venue.name }}
                        </h1>
                        <p
                            class="text-gray-300 font-medium mb-6 flex items-center gap-1.5"
                        >
                            <svg
                                class="w-4 h-4 text-gray-400"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                            </svg>
                            {{ venue.address }}
                        </p>
                        <div class="flex flex-wrap gap-3">
                            <a
                                v-if="venue.map_url"
                                :href="venue.map_url"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-2 h-10 px-5 rounded-xl bg-white/90 text-gray-900 text-sm font-black hover:bg-white transition-colors"
                                >📍 Chỉ đường</a
                            >
                            <a
                                v-if="venue.phone_contact"
                                :href="`tel:${venue.phone_contact}`"
                                class="inline-flex items-center gap-2 h-10 px-5 rounded-xl bg-sportgo-accent text-white text-sm font-black hover:bg-sportgo-dark transition-colors"
                                >📞 Liên hệ sân</a
                            >
                        </div>
                    </div>
                </div>

                <!-- Tab + Content Section -->
                <div
                    class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-4 relative z-10 pb-16"
                >
                    <!-- Tab Nav -->
                    <div
                        class="bg-white rounded-t-3xl shadow-sm border border-gray-100 border-b-0 px-6 sm:px-8 pt-5 flex space-x-6 overflow-x-auto"
                    >
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            class="pb-5 border-b-[3px] font-black text-sm sm:text-[15px] transition-colors whitespace-nowrap"
                            :class="
                                activeTab === tab.key
                                    ? 'border-sportgo-accent text-sportgo-accent'
                                    : 'border-transparent text-gray-400 hover:text-gray-900 hover:border-gray-200'
                            "
                            @click="activeTab = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <!-- Tab Content -->
                    <div
                        class="bg-white rounded-b-3xl shadow-sm border border-gray-100 p-6 sm:p-8 min-h-[500px]"
                    >
                        <!-- Overview -->
                        <section
                            v-if="activeTab === 'overview'"
                            class="grid lg:grid-cols-[1fr_320px] gap-6"
                        >
                            <div class="space-y-6">
                                <div
                                    class="p-5 border border-gray-100 rounded-2xl bg-gray-50"
                                >
                                    <h2
                                        class="text-xl font-black text-gray-900 mb-3"
                                    >
                                        Tổng quan
                                    </h2>
                                    <p class="text-gray-600 leading-relaxed whitespace-pre-line">
                                        {{
                                            venue.description ||
                                            "Cụm sân chưa cập nhật mô tả."
                                        }}
                                    </p>
                                    <dl class="mt-5 space-y-3">
                                        <div
                                            class="flex justify-between py-2 border-b border-gray-100"
                                        >
                                            <dt
                                                class="text-gray-500 font-medium"
                                            >
                                                Rating
                                            </dt>
                                            <dd
                                                class="font-black text-gray-900"
                                            >
                                                ⭐
                                                {{
                                                    venue.rating_avg || "0.0"
                                                }}
                                                /
                                                {{
                                                    venue.rating_count || 0
                                                }}
                                                đánh giá
                                            </dd>
                                        </div>
                                        <div
                                            class="flex justify-between py-2 border-b border-gray-100"
                                        >
                                            <dt
                                                class="text-gray-500 font-medium"
                                            >
                                                Số sân đang mở
                                            </dt>
                                            <dd
                                                class="font-black text-gray-900"
                                            >
                                                {{ venue.court_count }} sân
                                            </dd>
                                        </div>
                                        <div class="flex justify-between py-2">
                                            <dt
                                                class="text-gray-500 font-medium"
                                            >
                                                Điện thoại
                                            </dt>
                                            <dd
                                                class="font-black text-gray-900"
                                            >
                                                {{
                                                    venue.phone_contact ||
                                                    "Chưa cập nhật"
                                                }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                            <div
                                class="p-5 border border-gray-100 rounded-2xl bg-gray-50"
                            >
                                <h2
                                    class="text-xl font-black text-gray-900 mb-4"
                                >
                                    Tiện ích
                                </h2>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="item in amenities"
                                        :key="item"
                                        class="px-3 py-1.5 bg-white border border-gray-200 rounded-full text-sm font-bold text-gray-700"
                                        >{{ item }}</span
                                    >
                                    <span
                                        v-if="amenities.length === 0"
                                        class="text-gray-400 font-medium"
                                        >Chưa cập nhật tiện ích.</span
                                    >
                                </div>
                            </div>
                        </section>

                        <!-- Booking / Schedule -->
                        <section
                            v-else-if="activeTab === 'booking'"
                            class="grid lg:grid-cols-[1fr_300px] gap-6"
                        >
                            <div class="min-w-0">
                                <!-- Controls -->
                                <div
                                    class="grid sm:grid-cols-3 gap-4 mb-5 p-4 bg-gray-50 rounded-2xl border border-gray-100"
                                >
                                    <label class="flex flex-col gap-1.5">
                                        <span
                                            class="text-xs font-black text-gray-600 uppercase tracking-wider"
                                            >Ngày đặt sân</span
                                        >
                                        <input
                                            v-model="bookingDate"
                                            type="date"
                                            :min="minDate"
                                            class="h-10 px-3 border border-gray-300 rounded-xl text-sm font-medium focus:ring-2 focus:ring-sportgo-accent focus:border-sportgo-accent bg-white"
                                            @change="loadSchedule"
                                        />
                                    </label>
                                    <label class="flex flex-col gap-1.5">
                                        <span
                                            class="text-xs font-black text-gray-600 uppercase tracking-wider"
                                            >Loại sân</span
                                        >
                                        <select
                                            v-model="selectedCourtTypeId"
                                            class="h-10 px-3 border border-gray-300 rounded-xl text-sm font-medium focus:ring-2 focus:ring-sportgo-accent focus:border-sportgo-accent bg-white"
                                            @change="loadSchedule"
                                        >
                                            <option value="">
                                                Tất cả loại sân
                                            </option>
                                            <option
                                                v-for="type in venue.court_types"
                                                :key="type.id"
                                                :value="type.id"
                                            >
                                                {{ type.name }}
                                            </option>
                                        </select>
                                    </label>
                                    <div
                                        class="flex flex-col gap-1.5 justify-end"
                                    >
                                        <span
                                            class="text-xs font-medium text-gray-500"
                                            >Chọn các khung giờ liên tiếp trên
                                            cùng 1 sân.</span
                                        >
                                    </div>
                                </div>

                                <!-- Legend & Toggle -->
                                <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                                    <div class="flex flex-wrap gap-4 text-xs font-bold text-gray-600">
                                        <span class="flex items-center gap-1.5"
                                            ><i
                                                class="w-3.5 h-3.5 rounded border border-gray-300 bg-white inline-block"
                                            ></i>
                                            Trống</span
                                        >
                                        <span class="flex items-center gap-1.5"
                                            ><i
                                                class="w-3.5 h-3.5 rounded bg-gray-200 inline-block"
                                            ></i>
                                            Đã đặt</span
                                        >
                                        <span class="flex items-center gap-1.5"
                                            ><i
                                                class="w-3.5 h-3.5 rounded bg-green-500 inline-block"
                                            ></i>
                                            Đang chọn</span
                                        >
                                    </div>
                                    <div v-if="hasVisualLayout" class="flex gap-2">
                                        <button 
                                            type="button" 
                                            class="px-3 py-1.5 rounded-lg text-xs font-black border transition-all"
                                            :class="showMapMode ? 'bg-sportgo-accent text-white border-sportgo-accent' : 'bg-white text-gray-700 border-gray-200'"
                                            @click="showMapMode = !showMapMode"
                                        >
                                            🗺️ {{ showMapMode ? 'Ẩn sơ đồ' : 'Xem sơ đồ sân' }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Interactive Court Map Selector -->
                                <div 
                                    v-if="hasVisualLayout && showMapMode && !scheduleLoading" 
                                    class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-2xl"
                                >
                                    <h4 class="text-sm font-black text-gray-800 mb-1">Vị trí các sân con ngoài thực tế:</h4>
                                    <p class="text-xs text-gray-500 mb-4">📍 Click chọn sân con trên sơ đồ để định vị nhanh dòng đặt lịch bên dưới</p>
                                    <div class="relative w-full aspect-[1000/600] border border-gray-200 rounded-xl bg-slate-100 overflow-hidden shadow-inner">
                                        <!-- Grid markings background -->
                                        <div class="absolute inset-0 bg-[linear-gradient(to_right,rgba(15,23,42,0.02)_1px,transparent_1px),linear-gradient(to_bottom,rgba(15,23,42,0.02)_1px,transparent_1px)] bg-[size:30px_30px] pointer-events-none"></div>
                                        
                                        <!-- Placed Courts on client view -->
                                        <div
                                            v-for="court in placedCourts"
                                            :key="court.id"
                                            class="absolute transition-all duration-150"
                                            :style="getClientCourtStyle(court)"
                                            @click="selectCourtFromMap(court)"
                                        >
                                            <CourtVisual
                                                :name="court.name"
                                                :court-type-name="court.court_type?.name"
                                                :status="getCourtMapStatus(court)"
                                                :rotation="court.layout_rotation || 0"
                                                :interactive="true"
                                                :show-type="true"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <p
                                    v-if="selectionError"
                                    class="mb-3 p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm font-bold"
                                >
                                    {{ selectionError }}
                                </p>

                                <div
                                    v-if="scheduleLoading"
                                    class="py-16 text-center text-gray-400 font-medium"
                                >
                                    Đang tải lịch...
                                </div>
                                <div
                                    v-else-if="scheduleError"
                                    class="py-8 text-center text-red-600 font-bold"
                                >
                                    {{ scheduleError }}
                                </div>
                                <div
                                    v-else
                                    class="overflow-auto border border-gray-200 rounded-2xl bg-white"
                                >
                                    <div
                                        class="schedule-grid min-w-max"
                                        :style="scheduleGridStyle"
                                    >
                                        <div class="schedule-head sticky-col">
                                            Sân \ Giờ
                                        </div>
                                        <div
                                            v-for="slot in scheduleSlots"
                                            :key="slot.start_time"
                                            class="schedule-head time-head"
                                        >
                                            {{ slot.label }}
                                        </div>
                                        <template
                                            v-for="court in scheduleCourts"
                                            :key="court.id"
                                        >
                                            <div
                                                class="schedule-court sticky-col"
                                                :id="`court-row-${court.id}`"
                                                :class="{ 'active-highlight': selectedGridCourtId === court.id }"
                                            >
                                                <strong
                                                    class="text-xs font-black text-gray-900 block"
                                                    >{{ court.name }}</strong
                                                >
                                                <span
                                                    class="text-[10px] text-gray-500"
                                                    >{{
                                                        court.court_type?.name
                                                    }}</span
                                                >
                                            </div>
                                            <button
                                                v-for="(
                                                    slot, index
                                                ) in scheduleSlots"
                                                :key="`${court.id}-${slot.start_time}`"
                                                type="button"
                                                class="schedule-cell"
                                                :class="{
                                                    busy: isSlotBusy(
                                                        court.id,
                                                        slot,
                                                    ),
                                                    selected: isSlotSelected(
                                                        court.id,
                                                        index,
                                                    ),
                                                }"
                                                :disabled="
                                                    isSlotBusy(court.id, slot)
                                                "
                                                @click="
                                                    selectSlot(court, index)
                                                "
                                            ></button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Booking Summary -->
                            <aside
                                class="bg-gray-50 border border-gray-200 rounded-2xl p-5 h-fit sticky top-20"
                            >
                                <h2
                                    class="text-lg font-black text-gray-900 mb-4"
                                >
                                    Xác nhận đặt sân
                                </h2>
                                <dl class="space-y-3 mb-6">
                                    <div
                                        v-for="[label, val] in summaryRows"
                                        :key="label"
                                        class="flex justify-between gap-3 text-sm border-b border-gray-100 pb-2"
                                    >
                                        <dt class="text-gray-500 font-medium">
                                            {{ label }}
                                        </dt>
                                        <dd
                                            class="font-black text-gray-900 text-right"
                                        >
                                            {{ val }}
                                        </dd>
                                    </div>
                                </dl>
                                <button
                                    type="button"
                                    :disabled="!canGoBooking"
                                    class="w-full h-12 rounded-xl bg-sportgo-accent text-white font-black text-sm disabled:opacity-40 disabled:cursor-not-allowed hover:bg-sportgo-dark transition-colors"
                                    @click="goBooking"
                                >
                                    Đặt sân →
                                </button>
                                <p
                                    class="mt-3 text-xs text-gray-400 text-center leading-relaxed"
                                >
                                    Giá cuối tính lại theo bảng giá ngày đặc
                                    biệt và khung giờ khi xác nhận.
                                </p>
                            </aside>
                        </section>

                        <!-- Pricing -->
                        <section v-else-if="activeTab === 'pricing'">
                            <div
                                class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-sm font-medium"
                            >
                                💡 Giá ngày đặc biệt được ưu tiên trước giá theo
                                khung giờ. Hệ thống tính từng block 30 phút.
                            </div>

                            <h2 class="text-xl font-black text-gray-900 mb-4">
                                Bảng giá theo khung giờ
                            </h2>
                            <div
                                class="overflow-auto border border-gray-200 rounded-2xl bg-white mb-8"
                            >
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr
                                            class="bg-gray-50 border-b border-gray-200"
                                        >
                                            <th
                                                class="text-left px-4 py-3 font-black text-gray-700"
                                            >
                                                Loại sân
                                            </th>
                                            <th
                                                class="text-left px-4 py-3 font-black text-gray-700"
                                            >
                                                Ngày áp dụng
                                            </th>
                                            <th
                                                class="text-left px-4 py-3 font-black text-gray-700"
                                            >
                                                Loại đặt
                                            </th>
                                            <th
                                                class="text-left px-4 py-3 font-black text-gray-700"
                                            >
                                                Khung giờ
                                            </th>
                                            <th
                                                class="text-right px-4 py-3 font-black text-gray-700"
                                            >
                                                Giá / giờ
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="slot in venue.price_slots"
                                            :key="slot.id"
                                            class="border-b border-gray-100 hover:bg-gray-50"
                                        >
                                            <td
                                                class="px-4 py-3 font-medium text-gray-900"
                                            >
                                                {{
                                                    slot.court_type?.name || "-"
                                                }}
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">
                                                {{
                                                    formatDays(
                                                        slot.apply_to_days,
                                                    )
                                                }}
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">
                                                {{
                                                    bookingTypeLabel(
                                                        slot.booking_type,
                                                    )
                                                }}
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">
                                                {{
                                                    formatTime(slot.start_time)
                                                }}
                                                -
                                                {{ formatTime(slot.end_time) }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-right font-black text-sportgo-accent"
                                            >
                                                {{ formatCurrency(slot.price) }}
                                            </td>
                                        </tr>
                                        <tr v-if="!venue.price_slots?.length">
                                            <td
                                                colspan="5"
                                                class="px-4 py-8 text-center text-gray-400 font-medium"
                                            >
                                                Chưa có bảng giá thường.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <h2 class="text-xl font-black text-gray-900 mb-4">
                                Giá ngày lễ / ngày đặc biệt
                            </h2>
                            <div
                                class="overflow-auto border border-gray-200 rounded-2xl bg-white"
                            >
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr
                                            class="bg-gray-50 border-b border-gray-200"
                                        >
                                            <th
                                                class="text-left px-4 py-3 font-black text-gray-700"
                                            >
                                                Ngày
                                            </th>
                                            <th
                                                class="text-left px-4 py-3 font-black text-gray-700"
                                            >
                                                Loại ngày
                                            </th>
                                            <th
                                                class="text-left px-4 py-3 font-black text-gray-700"
                                            >
                                                Loại sân
                                            </th>
                                            <th
                                                class="text-left px-4 py-3 font-black text-gray-700"
                                            >
                                                Loại đặt
                                            </th>
                                            <th
                                                class="text-left px-4 py-3 font-black text-gray-700"
                                            >
                                                Khung giờ
                                            </th>
                                            <th
                                                class="text-right px-4 py-3 font-black text-gray-700"
                                            >
                                                Giá / giờ
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="item in venue.holiday_prices"
                                            :key="item.id"
                                            class="border-b border-gray-100 hover:bg-gray-50"
                                        >
                                            <td
                                                class="px-4 py-3 font-medium text-gray-900"
                                            >
                                                {{
                                                    formatDate(
                                                        item.holiday_date,
                                                    )
                                                }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <span
                                                    class="px-2 py-1 rounded-full text-xs font-black bg-orange-100 text-orange-700"
                                                    >{{
                                                        item.date_type ===
                                                        "holiday"
                                                            ? "Ngày lễ"
                                                            : "Ngày đặc biệt"
                                                    }}</span
                                                >
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">
                                                {{
                                                    item.court_type?.name || "-"
                                                }}
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">
                                                {{
                                                    bookingTypeLabel(
                                                        item.booking_type,
                                                    )
                                                }}
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">
                                                {{
                                                    formatTime(item.start_time)
                                                }}
                                                -
                                                {{ formatTime(item.end_time) }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-right font-black text-orange-600"
                                            >
                                                {{ formatCurrency(item.price) }}
                                            </td>
                                        </tr>
                                        <tr
                                            v-if="!venue.holiday_prices?.length"
                                        >
                                            <td
                                                colspan="6"
                                                class="px-4 py-8 text-center text-gray-400 font-medium"
                                            >
                                                Chưa có giá ngày đặc biệt.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <!-- Other tabs placeholder -->
                        <section
                            v-else
                            class="flex flex-col items-center justify-center py-20 text-gray-400"
                        >
                            <div class="text-5xl mb-4">🏗️</div>
                            <p class="font-medium">
                                Chức năng này đang được phát triển.
                            </p>
                        </section>
                    </div>
                </div>
            </template>
        </main>
    </div>
</template>

<script>
import PublicNavbar from "../../components/PublicNavbar.vue";
import CourtVisual from "../../components/CourtVisual.vue";
import { getAuth } from "../../stores/auth.js";
import { venueService } from "../../services/venues.js";

export default {
    name: "VenueDetail",
    components: { PublicNavbar, CourtVisual },
    data() {
        return {
            venue: null,
            loading: true,
            error: "",
            activeImage: "",
            activeTab: "booking",
            bookingDate: new Date().toISOString().split("T")[0],
            selectedCourtTypeId: "",
            showMapMode: true,
            scheduleLoading: false,
            scheduleError: "",
            scheduleSlots: [],
            scheduleCourts: [],
            scheduleSlotStatuses: [],
            selectedGridCourtId: "",
            selectedSlotIndexes: [],
            selectionError: "",
            tabs: [
                { key: "overview", label: "Tổng quan" },
                { key: "booking", label: "Lịch & Đặt sân" },
                { key: "pricing", label: "Bảng giá" },
                { key: "community", label: "Giao lưu" },
                { key: "posts", label: "Bài đăng sân" },
                { key: "reviews", label: "Đánh giá" },
                { key: "policies", label: "Chính sách" },
            ],
        };
    },
    computed: {
        minDate() {
            return new Date().toISOString().split("T")[0];
        },
        hasVisualLayout() {
            return this.scheduleCourts.some(
                (c) => c.layout_x !== null && c.layout_y !== null,
            );
        },
        placedCourts() {
            return this.scheduleCourts.filter(
                (c) => c.layout_x !== null && c.layout_y !== null,
            );
        },
        amenities() {
            return Array.isArray(this.venue?.amenities)
                ? this.venue.amenities
                : [];
        },
        selectedCourt() {
            return (
                this.scheduleCourts.find(
                    (c) => c.id === this.selectedGridCourtId,
                ) || null
            );
        },
        selectedTimeText() {
            if (!this.selectedSlotIndexes.length) return "-";
            const first = Math.min(...this.selectedSlotIndexes);
            const last = Math.max(...this.selectedSlotIndexes);
            return `${this.formatTime(this.scheduleSlots[first]?.start_time)} - ${this.formatTime(this.scheduleSlots[last]?.end_time)}`;
        },
        selectedPriceText() {
            if (!this.selectedSlotIndexes.length || !this.selectedGridCourtId) {
                return this.venue?.min_price
                    ? `Từ ${this.formatCurrency(this.venue.min_price)}/giờ`
                    : "Chưa có giá";
            }
            const total = this.selectedSlotIndexes.reduce((sum, index) => {
                const status = this.slotStatus(
                    this.selectedGridCourtId,
                    this.scheduleSlots[index],
                );
                return sum + Number(status?.price || 0);
            }, 0);
            return this.formatCurrency(total);
        },
        canGoBooking() {
            return (
                this.selectedGridCourtId && this.selectedSlotIndexes.length > 0
            );
        },
        scheduleGridStyle() {
            return {
                gridTemplateColumns: `132px repeat(${this.scheduleSlots.length}, 36px)`,
            };
        },
        summaryRows() {
            return [
                ["Cụm sân", this.venue?.name],
                ["Sân con", this.selectedCourt?.name || "-"],
                ["Ngày chơi", this.formatDate(this.bookingDate)],
                ["Khung giờ", this.selectedTimeText],
                ["Tạm tính", this.selectedPriceText],
            ];
        },
    },
    async mounted() {
        await this.loadVenue();
    },
    methods: {
        async loadVenue() {
            this.loading = true;
            this.error = "";
            try {
                const response = await venueService.show(this.$route.params.id);
                this.venue = response.data;
                this.activeImage =
                    this.venue.gallery?.[0] || this.venue.image_path;
                await this.loadSchedule();
            } catch (error) {
                this.error = error.message || "Không thể tải chi tiết cụm sân.";
            } finally {
                this.loading = false;
            }
        },
        async loadSchedule() {
            if (!this.venue || !this.bookingDate) return;
            this.scheduleLoading = true;
            this.scheduleError = "";
            this.clearSelection();
            try {
                const params = { booking_date: this.bookingDate };
                if (this.selectedCourtTypeId)
                    params.court_type_id = this.selectedCourtTypeId;
                const response = await venueService.schedule(
                    this.$route.params.id,
                    params,
                );
                this.scheduleSlots = response.time_slots || [];
                this.scheduleCourts = response.courts || [];
                this.scheduleSlotStatuses = response.slot_statuses || [];
            } catch (error) {
                this.scheduleError =
                    error.message || "Không thể tải lịch trống.";
            } finally {
                this.scheduleLoading = false;
            }
        },
        selectSlot(court, index) {
            const slot = this.scheduleSlots[index];
            if (!slot || this.isSlotBusy(court.id, slot)) return;
            if (
                this.selectedGridCourtId &&
                this.selectedGridCourtId !== court.id
            )
                this.clearSelection();
            let nextIndexes = [index];
            if (
                this.selectedGridCourtId === court.id &&
                this.selectedSlotIndexes.length > 0
            ) {
                const min = Math.min(...this.selectedSlotIndexes);
                const max = Math.max(...this.selectedSlotIndexes);
                if (index === max + 1) nextIndexes = this.range(min, index);
                else if (index === min - 1)
                    nextIndexes = this.range(index, max);
                else if (index >= min && index <= max)
                    nextIndexes = this.selectedSlotIndexes.filter(
                        (i) => i !== index,
                    );
                else {
                    this.selectionError =
                        "Vui lòng chọn các khung giờ liên tiếp.";
                    return;
                }
            }
            if (
                nextIndexes.length &&
                !this.isRangeFree(court.id, nextIndexes)
            ) {
                this.selectionError = "Khoảng giờ có ô không còn trống.";
                return;
            }
            this.selectionError = "";
            this.selectedGridCourtId = court.id;
            this.selectedSlotIndexes = nextIndexes;
        },
        goBooking() {
            const auth = getAuth();
            if (!auth) {
                this.$router.push({
                    name: "login",
                    query: { redirect: this.$route.fullPath },
                });
                return;
            }
            const first = Math.min(...this.selectedSlotIndexes);
            const last = Math.max(...this.selectedSlotIndexes);
            this.$router.push({
                name: "booking-create",
                query: {
                    venue_cluster_id: this.venue.id,
                    venue_court_id: this.selectedGridCourtId,
                    booking_date: this.bookingDate,
                    start_time: this.scheduleSlots[first].start_time,
                    end_time: this.scheduleSlots[last].end_time,
                },
            });
        },
        slotStatus(courtId, slot) {
            return this.scheduleSlotStatuses.find(
                (s) =>
                    s.venue_court_id === courtId &&
                    s.start_time === slot.start_time,
            );
        },
        isSlotBusy(courtId, slot) {
            const s = this.slotStatus(courtId, slot);
            return s ? !s.is_available : false;
        },
        isSlotSelected(courtId, index) {
            return (
                this.selectedGridCourtId === courtId &&
                this.selectedSlotIndexes.includes(index)
            );
        },
        isRangeFree(courtId, indexes) {
            return indexes.every((i) => {
                const slot = this.scheduleSlots[i];
                return slot && !this.isSlotBusy(courtId, slot);
            });
        },
        clearSelection() {
            this.selectedGridCourtId = "";
            this.selectedSlotIndexes = [];
            this.selectionError = "";
        },
        range(start, end) {
            return Array.from({ length: end - start + 1 }, (_, o) => start + o);
        },
        imageUrl(path) {
            if (!path) return "";
            if (/^https?:\/\//.test(path)) return path;
            return `/storage/${path}`;
        },
        hideBrokenImage(e) {
            e.target.style.display = "none";
        },
        formatTime(t) {
            return (t || "").slice(0, 5);
        },
        formatDate(d) {
            if (!d) return "-";
            if (String(d).includes("T"))
                return new Intl.DateTimeFormat("vi-VN").format(new Date(d));
            const [y, m, day] = String(d).slice(0, 10).split("-");
            return `${day}/${m}/${y}`;
        },
        formatCurrency(amount) {
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
                maximumFractionDigits: 0,
            }).format(Number(amount || 0));
        },
        formatDays(days) {
            const labels = [
                "Thứ 2",
                "Thứ 3",
                "Thứ 4",
                "Thứ 5",
                "Thứ 6",
                "Thứ 7",
                "Chủ nhật",
            ];
            const values = [...new Set((days || []).map(Number))].sort(
                (a, b) => a - b,
            );
            if (values.length === 7) return "Thứ 2 - Chủ nhật";
            return values
                .map((d) => labels[d])
                .filter(Boolean)
                .join(", ");
        },
        bookingTypeLabel(type) {
            return (
                { all: "Tất cả", single: "Đặt lẻ", recurring: "Đặt cố định" }[
                    type
                ] || type
            );
        },
        getClientCourtStyle(court) {
            return {
                left: `${court.layout_x / 10}%`,
                top: `${court.layout_y / 6}%`,
                width: `${court.layout_w / 10}%`,
                height: `${court.layout_h / 6}%`,
            };
        },
        getCourtMapStatus(court) {
            if (court.status === 'maintenance') return 'maintenance';
            if (court.status === 'inactive') return 'inactive';
            if (this.selectedGridCourtId === court.id) return 'selected';
            
            // If all slots are busy, show as busy
            const courtSlots = this.scheduleSlotStatuses.filter(s => s.venue_court_id === court.id);
            if (courtSlots.length > 0 && courtSlots.every(s => !s.is_available)) {
                return 'busy';
            }
            return 'active';
        },
        selectCourtFromMap(court) {
            if (court.status === 'maintenance' || court.status === 'inactive') {
                return;
            }
            this.selectedGridCourtId = court.id;
            
            // Smoothly scroll the selected court row into view in the timetable
            this.$nextTick(() => {
                const el = document.getElementById(`court-row-${court.id}`);
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        }
    },
};
</script>

<style scoped>
/* Schedule grid CSS (không thể viết bằng Tailwind inline vì dynamic) */
.schedule-grid {
    display: grid;
    min-width: max-content;
}
.schedule-head,
.schedule-court,
.schedule-cell {
    min-height: 32px;
    border-right: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
}
.schedule-head {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    color: #334155;
    font-size: 10px;
    font-weight: 900;
}
.time-head {
    font-size: 9px;
}
.sticky-col {
    position: sticky;
    left: 0;
    z-index: 2;
}
.schedule-court {
    display: grid;
    align-content: center;
    gap: 2px;
    padding: 5px 7px;
    background: #fff;
    min-width: 132px;
    transition: background-color 0.2s, border-left 0.2s;
    border-left: 4px solid transparent;
}
.schedule-court.active-highlight {
    background-color: #f0fdf4 !important; /* light emerald */
    border-left: 4px solid #16a34a !important; /* active green border */
}
.schedule-cell {
    width: 36px;
    min-width: 36px;
    background: #fff;
    cursor: pointer;
    transition: background 0.1s;
}
.schedule-cell:not(:disabled):hover {
    background: #dcfce7;
}
.schedule-cell.busy {
    background: #e5e7eb;
    cursor: not-allowed;
}
.schedule-cell.selected {
    background: #16a34a;
}
</style>
