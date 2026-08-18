export const AVATAR_PALETTE = [
  '#f97316', // Orange
  '#10b981', // Emerald green
  '#3b82f6', // Blue
  '#8b5cf6', // Purple
  '#ec4899', // Pink
  '#06b6d4', // Cyan
  '#f43f5e', // Rose
  '#6366f1', // Indigo
];

/**
 * Sinh mã màu nền cho avatar dựa theo tên (đồng bộ với Chat Admin/Owner).
 * @param {string} name
 * @returns {string} Mã màu hex
 */
export function getAvatarColorHex(name) {
  if (!name) return '#10b981';
  const cleanName = String(name)
    .replace(/^Người dùng\s+/i, '')
    .replace(/^Nhân viên sân\s+/i, '')
    .trim();

  let hash = 0;
  for (let i = 0; i < cleanName.length; i++) {
    hash = cleanName.charCodeAt(i) + ((hash << 5) - hash);
  }
  const index = Math.abs(hash) % AVATAR_PALETTE.length;
  return AVATAR_PALETTE[index];
}

/**
 * Lấy ký tự đầu viết hoa cho avatar.
 * @param {string} name
 * @returns {string}
 */
export function getAvatarInitial(name) {
  if (!name) return 'U';
  return String(name).trim().charAt(0).toUpperCase();
}
