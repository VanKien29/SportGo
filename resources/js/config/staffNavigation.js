export const staffNavigationSections = [
  {
    label: 'Công việc',
    items: [
      { label: 'Lịch trực của tôi', icon: 'calendar', to: '/staff/schedules', activeNames: ['staff-schedules'] },
      { label: 'Lịch đặt sân', icon: 'calendar', to: '/staff/bookings', activeNames: ['staff-bookings'] },
      { label: 'Đặt sân tại quầy', icon: 'plus', to: '/staff/counter-booking', activeNames: ['staff-counter-booking'] },
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
  'staff-dashboard': 'Tổng quan ca trực',
  'staff-schedules': 'Lịch trực của tôi',
  'staff-bookings': 'Lịch đặt sân',
  'staff-counter-booking': 'Booking tại quầy',
  'staff-settings': 'Cài đặt giao diện',
  'staff-chat': 'Trò chuyện',
  'staff-profile': 'Thông tin cá nhân',
};

export const staffRouteSections = {
  'staff-dashboard': 'Công việc',
  'staff-schedules': 'Công việc',
  'staff-bookings': 'Công việc',
  'staff-counter-booking': 'Công việc',
  'staff-settings': 'Hệ thống',
  'staff-chat': 'Tin nhắn',
  'staff-profile': 'Tài khoản',
};