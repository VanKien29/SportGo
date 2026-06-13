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
        label: 'Hồ sơ & Hợp đồng',
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
        label: 'Lịch sân',
        icon: 'calendar',
        to: '/owner/bookings',
        activeNames: ['owner-bookings'],
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
    ],
  },
  {
    label: 'Kinh doanh',
    items: [
      {
        label: 'Ví tài chính',
        icon: 'dollarSign',
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
  'owner-partner-profile': 'Hồ sơ & Hợp đồng',
  'owner-profile': 'Thông tin cá nhân',
  'owner-venue-clusters': 'Quản lý cụm sân',
  'owner-venue-courts': 'Quản lý sân con',
  'owner-bookings': 'Lịch sân',
  'owner-counter-booking': 'Booking tại quầy',
  'owner-pricing': 'Cấu hình giá',
  'owner-finance': 'Ví tài chính',
  'owner-staff': 'Nhân viên sân',
  'owner-vouchers': 'Voucher của sân',
  'owner-policies': 'Chính sách sân',
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
