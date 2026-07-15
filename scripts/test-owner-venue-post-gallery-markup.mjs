import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const ownerSource = await readFile('resources/js/views/owner/OwnerVenuePosts.vue', 'utf8');
const detailSource = await readFile('resources/js/views/clients/NewsDetail.vue', 'utf8');

assert.match(ownerSource, /ref="galleryInputRef"[^>]*multiple/);
assert.match(ownerSource, /const MAX_GALLERY_IMAGES = 10/);
assert.match(ownerSource, /formData\.append\('gallery\[\]', image\)/);
assert.match(ownerSource, /formData\.append\('removed_gallery_media_ids\[\]', mediaId\)/);
assert.match(detailSource, /collection === ['"]gallery['"]/);
assert.match(detailSource, /v-for="\(image, index\) in galleryImages"/);

console.log('Venue post gallery markup checks passed.');
