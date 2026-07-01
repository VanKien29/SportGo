export const ownerNavigationSections = [
  {
    label: 'Tổng quan',
    items: [
      {
        label: 'Bảng điều hành',
        icon: 'dashboard',
        to: '/owner/dashboard',
        activeNames: ['owner-dashboard'],
      },
      {
        label: 'Hồ sơ đối tác',
        icon: 'fileText',
        to: '/owner/partner-profile',
        activeNames: ['owner-partner-profile'],
      },
    ],
  },
  {
    label: 'Vận hành sân',
    items: [
      {
        label: 'Cụm sân',
        icon: 'building',
        to: '/owner/venue-clusters',
        activeNames: ['owner-venue-clusters'],
      },
      {
        label: 'Sân con',
        icon: 'court',
        to: '/owner/venue-courts',
        activeNames: ['owner-venue-courts'],
      },
      {
        label: 'Khóa lịch sân',
        icon: 'lock',
        to: '/owner/schedule-locks',
        activeNames: ['owner-schedule-locks'],
      },
      {
        label: 'Booking tại quầy',
        icon: 'plus',
        to: '/owner/counter-booking',
        activeNames: ['owner-counter-booking'],
      },
      {
        label: 'Cấu hình giá',
        icon: 'settings',
        to: '/owner/pricing',
        activeNames: ['owner-pricing'],
      },
      {
        label: 'Cấu hình đặt sân',
        icon: 'calendar',
        to: '/owner/booking-settings',
        activeNames: ['owner-booking-settings'],
      },
      {
        label: 'Giao lưu tại sân',
        icon: 'users',
        to: '/owner/matchmaking',
        activeNames: ['owner-matchmaking'],
      },
    ],
  },
  {
    label: 'Kinh doanh',
    items: [
      {
        label: 'Phí nền tảng',
        icon: 'layers',
        to: '/owner/platform-fees',
        activeNames: ['owner-platform-fees'],
      },
      {
        label: 'Yêu cầu hoàn/hủy',
        icon: 'refresh',
        to: '/owner/refunds',
        activeNames: ['owner-refunds'],
      },
      {
        label: 'Ví tài chính',
        icon: 'banknote',
        to: '/owner/finance',
        activeNames: ['owner-finance'],
      },
      {
        label: 'Voucher sân',
        icon: 'tag',
        to: '/owner/vouchers',
        activeNames: ['owner-vouchers'],
      },
      {
        label: 'Chính sách sân',
        icon: 'fileText',
        to: '/owner/policies',
        activeNames: ['owner-policies'],
      },
      {
        label: 'Bài viết & Tin tức',
        icon: 'fileText',
        to: '/owner/venue-posts',
        activeNames: ['owner-venue-posts'],
      },
      {
        label: 'Tiếp thị liên kết',
        icon: 'share',
        to: '/owner/affiliate',
        activeNames: ['owner-affiliate'],
      },
    ],
  },
  {
    label: 'Nhân sự',
    items: [
      {
        label: 'Nhân viên sân',
        icon: 'users',
        to: '/owner/staff',
        activeNames: ['owner-staff'],
      },
    ],
  },
];


export const ownerRouteTitles = {
  'owner-dashboard': 'Bảng điều hành',
  'owner-partner-profile': 'Hồ sơ đối tác',
  'owner-profile': 'Thông tin cá nhân',
  'owner-venue-clusters': 'Quản lý cụm sân',
  'owner-venue-courts': 'Quản lý sân con',
  'owner-bookings': 'Lịch sân',
  'owner-counter-booking': 'Booking tại quầy',
  'owner-pricing': 'Cấu hình giá',
  'owner-booking-settings': 'Cấu hình đặt sân',
  'owner-schedule-locks': 'Khóa lịch theo khung giờ',
  'owner-platform-fees': 'Phí nền tảng',
  'owner-finance': 'Ví tài chính',
  'owner-refunds': 'Yêu cầu hoàn/hủy',
  'owner-staff': 'Nhân viên sân',
  'owner-vouchers': 'Voucher của sân',
  'owner-policies': 'Chính sách sân',
  'owner-venue-posts': 'Quản lý bài viết',
  'owner-matchmaking': 'Giao lưu tại sân',
  'owner-affiliate': 'Tiếp thị liên kết',
};

export const ownerRouteSectionLabels = {
  'owner-profile': 'Tài khoản',
};

export function findOwnerNavigationSection(routeName) {
  return ownerNavigationSections.find((section) =>
    section.items.some((item) => item.activeNames.includes(routeName)),
  );
}

export function getOwnerRouteSectionLabel(routeName) {
  return findOwnerNavigationSection(routeName)?.label || ownerRouteSectionLabels[routeName] || 'Tổng quan';
}
