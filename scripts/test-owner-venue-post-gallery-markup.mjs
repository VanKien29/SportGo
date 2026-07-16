import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const ownerSource = await readFile('resources/js/views/owner/OwnerVenuePosts.vue', 'utf8');
const detailSource = await readFile('resources/js/views/clients/NewsDetail.vue', 'utf8');
const viteConfig = await readFile('vite.config.js', 'utf8');

assert.match(ownerSource, /ref="galleryInputRef"[^>]*multiple/);
assert.match(ownerSource, /const MAX_GALLERY_IMAGES = 10/);
assert.match(ownerSource, /formData\.append\('gallery\[\]', image\)/);
assert.match(ownerSource, /formData\.append\('removed_gallery_media_ids\[\]', mediaId\)/);
assert.match(detailSource, /collection === ['"]gallery['"]/);
assert.match(detailSource, /v-for="\(image, index\) in galleryImages"/);
assert.match(
    detailSource,
    /v-if="post"\s+class="article-footer"/,
    'The detail footer must not render before the post has loaded.',
);
assert.match(
    detailSource,
    /normalizeMediaUrl\(media\)\s*\{\s*return normalizeMediaUrl\(media\);\s*\}/,
    'Gallery rendering must expose the media URL normalizer to the template.',
);
assert.match(
    viteConfig,
    /hmr:\s*\{[\s\S]*?host:\s*['"]localhost['"]/,
    'The dev server must publish a browser-reachable HMR host.',
);

console.log('Venue post gallery markup checks passed.');
