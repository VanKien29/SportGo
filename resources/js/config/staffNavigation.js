export const staffNavigationSections = [
  {
    label: 'Vận hành',
    items: [
      { label: 'Bàn làm việc quầy (POS)', icon: 'layoutGrid', to: '/staff/bookings', activeNames: ['staff-bookings'] },
      { label: 'Lịch trực & Chấm công', icon: 'clock', to: '/staff/schedules', activeNames: ['staff-schedules'] },
      { label: 'Đặt lịch cố định', icon: 'calendar', to: '/staff/counter-booking', activeNames: ['staff-counter-booking'] },
    ],
  },
  {
    label: 'Tin nhắn',
    items: [
      { label: 'Trò chuyện', icon: 'messageSquare', to: '/staff/chat', activeNames: ['staff-chat'], menuKey: 'chat' },
    ],
  },
  {
    label: 'Hệ thống',
    items: [
      { label: 'Cài đặt giao diện', icon: 'palette', to: '/staff/settings', activeNames: ['staff-settings'], menuKey: 'settings' },
    ],
  },
];

export const staffRouteTitles = {
  'staff-dashboard': 'Bàn làm việc quầy',
  'staff-schedules': 'Lịch trực & Chấm công',
  'staff-bookings': 'Bàn làm việc quầy (POS)',
  'staff-counter-booking': 'Đặt lịch cố định nâng cao',
  'staff-vouchers': 'Quản lý voucher sân',
  'staff-settings': 'Cài đặt giao diện',
  'staff-chat': 'Trò chuyện',
  'staff-profile': 'Thông tin cá nhân',
};

export const staffRouteSections = {
  'staff-dashboard': 'Vận hành',
  'staff-schedules': 'Vận hành',
  'staff-bookings': 'Vận hành',
  'staff-counter-booking': 'Vận hành',
  'staff-vouchers': 'Vận hành',
  'staff-settings': 'Hệ thống',
  'staff-chat': 'Tin nhắn',
  'staff-profile': 'Tài khoản',
};

