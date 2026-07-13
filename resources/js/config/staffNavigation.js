export const staffNavigationSections = [
  {
    label: 'Công việc',
    items: [
      { label: 'Tổng quan', icon: 'dashboard', to: '/staff/dashboard', activeNames: ['staff-dashboard'] },
      { label: 'Lịch trực của tôi', icon: 'calendar', to: '/staff/schedules', activeNames: ['staff-schedules'] },
    ],
  },
];

export const staffRouteTitles = {
  'staff-dashboard': 'Tổng quan ca trực',
  'staff-schedules': 'Lịch trực của tôi',
  'staff-profile': 'Thông tin cá nhân',
};

export const staffRouteSections = {
  'staff-dashboard': 'Công việc',
  'staff-schedules': 'Công việc',
  'staff-profile': 'Tài khoản',
};