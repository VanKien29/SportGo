export function normalizeMediaUrl(media) {
  const raw = [
    media?.url,
    media?.file_url,
    media?.full_url,
    media?.file_path,
    media?.path,
  ].find((value) => typeof value === 'string' && value.trim() !== '');

  if (!raw) return '';

  const value = raw.trim().replace(/\\/g, '/');

  if (/^(https?:)?\/\//i.test(value) || value.startsWith('data:') || value.startsWith('blob:')) {
    return value;
  }

  if (value.startsWith('/storage/')) {
    return value;
  }

  if (value.startsWith('storage/')) {
    return `/${value}`;
  }

  if (value.startsWith('/')) {
    return value;
  }

  const publicPath = value.startsWith('public/') ? value.slice('public/'.length) : value;

  return `/storage/${publicPath}`;
}
