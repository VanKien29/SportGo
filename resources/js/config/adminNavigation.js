export const adminNavigationSections = [
  {
    label: 'Tổng quan',
    items: [
      {
        label: 'Bảng điều hành',
        icon: 'dashboard',
        to: '/admin/dashboard',
        activeNames: ['admin-dashboard'],
      },
    ],
  },
  {
    label: 'Vận hành sân',
    items: [
      {
        label: 'Cụm sân',
        icon: 'building',
        to: '/admin/venue-clusters',
        activeNames: [
          'admin-venue-clusters',
          'admin-venue-cluster-detail',
          'admin-venue-platform-fees',
        ],
      },
      {
        label: 'Phí nền tảng',
        icon: 'layers',
        to: '/admin/platform-fee-tiers',
        activeNames: [
          'admin-platform-fee-tiers',
          'admin-platform-fee-ledgers',
          'admin-platform-fee-ledger-detail',
          'admin-platform-fee-policies',
          'admin-platform-fee-settings',
        ],
      },
      {
        label: 'Hồ sơ đối tác',
        icon: 'fileText',
        to: '/admin/partner-applications',
        activeNames: ['admin-partner-applications'],
      },
    ],
  },
  {
    label: 'Người dùng & quyền',
    items: [
      {
        label: 'Nhân sự',
        icon: 'users',
        to: '/admin/staffs',
        activeNames: ['admin-staffs'],
      },
      {
        label: 'Tài khoản',
        icon: 'users',
        to: '/admin/users',
        activeNames: ['admin-users', 'admin-user-detail'],
      },
      {
        label: 'Nhóm quyền',
        icon: 'shieldCheck',
        to: '/admin/roles',
        activeNames: ['admin-roles', 'admin-role-detail'],
      },
    ],
  },
  {
    label: 'Tài chính',
    items: [
      {
        label: 'Thanh toán booking',
        icon: 'creditCard',
        to: '/admin/payments',
        activeNames: ['admin-payments'],
      },
      {
        label: 'Hoàn tiền & rút tiền',
        icon: 'banknote',
        to: '/admin/finance-operations',
        activeNames: ['admin-finance-operations'],
      },
      {
        label: 'Voucher hệ thống',
        icon: 'tag',
        to: '/admin/vouchers',
        activeNames: ['admin-vouchers', 'admin-voucher-detail'],
      },
      {
        label: 'Gói VIP hệ thống',
        icon: 'star',
        to: '/admin/membership-packages',
        activeNames: ['admin-membership-packages'],
      },
    ],
  },
  {
    label: 'Nội dung & cấu hình',
    items: [
      {
        label: 'Chính sách',
        icon: 'fileText',
        to: '/admin/policies',
        activeNames: ['admin-policies', 'admin-policy-detail'],
      },
      {
        label: 'Loại sân',
        icon: 'layers',
        to: '/admin/court-types',
        activeNames: ['admin-court-types'],
      },
      {
        label: 'Tiện ích',
        icon: 'star',
        to: '/admin/amenities',
        activeNames: ['admin-amenities'],
      },
      {
        label: 'Banner',
        icon: 'image',
        to: '/admin/banners',
        activeNames: ['admin-banners'],
      },
      {
        label: 'Cài đặt giao diện',
        icon: 'palette',
        to: '/admin/settings',
        activeNames: ['admin-settings'],
      },
    ],
  },
  {
    label: 'Kiểm duyệt & hỗ trợ',
    items: [
      {
        label: 'Kiểm duyệt bài viết',
        icon: 'fileText',
        to: '/admin/venue-posts',
        activeNames: ['admin-venue-posts'],
      },
      {
        label: 'Kiểm duyệt nội dung',
        icon: 'eye',
        to: '/admin/moderation',
        activeNames: ['admin-moderation'],
      },
      {
        label: 'Báo cáo',
        icon: 'messageWarning',
        to: '/admin/reports',
        activeNames: ['admin-reports'],
      },
      {
        label: 'Khiếu nại',
        icon: 'shieldCheck',
        to: '/admin/complaints',
        activeNames: ['admin-complaints'],
      },
    ],
  },
];

export const adminRouteTitles = {
  'admin-dashboard': 'Bảng điều hành',
  'admin-profile': 'Thông tin cá nhân',
  'admin-staffs': 'Quản lý nhân sự',
  'admin-users': 'Quản lý tài khoản',
  'admin-user-detail': 'Chi tiết tài khoản',
  'admin-payments': 'Theo dõi thanh toán booking',
  'admin-finance-operations': 'Hoàn tiền và rút tiền',
  'admin-vouchers': 'Voucher hệ thống',
  'admin-membership-packages': 'Gói VIP hệ thống',
  'admin-partner-applications': 'Quản lý hồ sơ đối tác',
  'admin-banners': 'Quản lý banner',
  'admin-moderation': 'Kiểm duyệt nội dung',
  'admin-venue-posts': 'Kiểm duyệt bài viết',
  'admin-roles': 'Quản lý nhóm quyền',
  'admin-role-detail': 'Chi tiết nhóm quyền',
  'admin-policies': 'Quản lý chính sách',
  'admin-platform-fee-policies': 'Chính sách phí nền tảng',
  'admin-policy-detail': 'Chi tiết chính sách',
  'admin-reports': 'Xử lý báo cáo',
  'admin-complaints': 'Xử lý khiếu nại',
  'admin-court-types': 'Quản lý loại sân',
  'admin-amenities': 'Quản lý tiện ích',
  'admin-venue-clusters': 'Quản lý cụm sân',
  'admin-venue-cluster-detail': 'Chi tiết cụm sân',
  'admin-platform-fee-tiers': 'Cấu hình bậc phí nền tảng',
  'admin-platform-fee-ledgers': 'Quản lý phí duy trì hệ thống',
  'admin-platform-fee-ledger-detail': 'Chi tiết kỳ phí duy trì',
  'admin-venue-platform-fees': 'Phí duy trì cụm sân',
  'admin-platform-fee-settings': 'Cài đặt phí duy trì',
  'admin-settings': 'Cấu hình giao diện',
};

export function findAdminNavigationSection(routeName) {
  return adminNavigationSections.find((section) =>
    section.items.some((item) => item.activeNames.includes(routeName)),
  );
}
