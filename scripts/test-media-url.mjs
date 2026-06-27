import assert from 'node:assert/strict';
import { normalizeMediaUrl } from '../resources/js/utils/mediaUrl.js';

assert.equal(normalizeMediaUrl({ file_path: 'venue_posts/sample.png' }), '/storage/venue_posts/sample.png');
assert.equal(normalizeMediaUrl({ file_path: 'storage/venue_posts/sample.png' }), '/storage/venue_posts/sample.png');
assert.equal(normalizeMediaUrl({ file_path: '/storage/venue_posts/sample.png' }), '/storage/venue_posts/sample.png');
assert.equal(normalizeMediaUrl({ file_path: 'public/venue_posts/sample.png' }), '/storage/venue_posts/sample.png');
assert.equal(normalizeMediaUrl({ file_path: 'venue_posts\\sample.png' }), '/storage/venue_posts/sample.png');
assert.equal(normalizeMediaUrl({ url: 'https://cdn.example.com/sample.png', file_path: 'venue_posts/sample.png' }), 'https://cdn.example.com/sample.png');
assert.equal(normalizeMediaUrl({ file_url: '/storage/from-file-url.png' }), '/storage/from-file-url.png');
assert.equal(normalizeMediaUrl({ full_url: 'data:image/png;base64,abc' }), 'data:image/png;base64,abc');
assert.equal(normalizeMediaUrl({ path: 'venue_posts/from-path.png' }), '/storage/venue_posts/from-path.png');
assert.equal(normalizeMediaUrl({}), '');
assert.equal(normalizeMediaUrl(null), '');

console.log('media URL normalization tests passed');
