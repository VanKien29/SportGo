export const staffNavigationSections = [
  {
    label: 'Công việc',
    items: [
      { label: 'Tổng quan', icon: 'dashboard', to: '/staff/dashboard', activeNames: ['staff-dashboard'] },
      { label: 'Lịch trực của tôi', icon: 'calendar', to: '/staff/schedules', activeNames: ['staff-schedules'] },
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
  'staff-settings': 'Cài đặt giao diện',
  'staff-chat': 'Trò chuyện',
  'staff-profile': 'Thông tin cá nhân',
};

export const staffRouteSections = {
  'staff-dashboard': 'Công việc',
  'staff-schedules': 'Công việc',
  'staff-settings': 'Hệ thống',
  'staff-chat': 'Tin nhắn',
  'staff-profile': 'Tài khoản',
};