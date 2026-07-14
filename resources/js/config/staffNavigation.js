export const staffNavigationSections = [
  {
    label: "Tổng quan",
    items: [
      { label: "Bảng điều hành", icon: "dashboard", to: "/staff/dashboard", activeNames: ["staff-dashboard"] },
    ],
  },
  {
    label: "Vận hành sân",
    items: [
      { label: "Danh sách booking", icon: "calendar", to: "/staff/booking-list", activeNames: ["staff-booking-list"] },
      { label: "Booking tại quầy", icon: "plus", to: "/staff/counter-booking", activeNames: ["staff-counter-booking"] },
    ],
  },
  {
    label: "Ca trực",
    items: [
      { label: "Ca của tôi", icon: "clock", to: "/staff/schedules", activeNames: ["staff-schedules", "staff-staff-shifts"] },
    ],
  },
  {
    label: 'Tin nhắn',
    items: [
      { label: 'Trò chuyện', icon: 'messageSquare', to: '/staff/chat', activeNames: ['staff-chat'] },
    ],
  },
  {
    label: 'Hệ thống',
    items: [
      { label: 'Cài đặt giao diện', icon: 'palette', to: '/staff/settings', activeNames: ['staff-settings'] },
    ],
  },
];

export const staffRouteTitles = {
  "staff-dashboard": "Bảng điều hành",
  "staff-booking-list": "Danh sách booking",
  "staff-counter-booking": "Booking tại quầy",
  "staff-schedules": "Ca của tôi",
  "staff-staff-shifts": "Ca của tôi",
  'staff-settings': 'Cài đặt giao diện',
  'staff-chat': 'Trò chuyện',
  'staff-profile': 'Thông tin cá nhân',
};

export const staffRouteSections = {
  "staff-dashboard": "Tổng quan",
  "staff-booking-list": "Vận hành sân",
  "staff-counter-booking": "Vận hành sân",
  "staff-schedules": "Ca trực",
  "staff-staff-shifts": "Ca trực",
  'staff-settings': 'Hệ thống',
  'staff-chat': 'Tin nhắn',
  'staff-profile': 'Tài khoản',
};