import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = readFileSync('resources/js/views/owner/OwnerVenuePosts.vue', 'utf8');

const uploadZoneIndex = source.indexOf('class="upload-zone"');
assert.notEqual(uploadZoneIndex, -1, 'Owner post thumbnail upload zone should exist');

const fileInputIndex = source.indexOf('ref="fileInputRef"', uploadZoneIndex);
assert.notEqual(fileInputIndex, -1, 'Owner post thumbnail file input should exist after upload zone');

const nearestOpeningLabel = source.lastIndexOf('<label', uploadZoneIndex);
const nearestClosingLabel = source.lastIndexOf('</label>', uploadZoneIndex);

assert.ok(
  nearestOpeningLabel === -1 || nearestClosingLabel > nearestOpeningLabel,
  'Thumbnail upload zone must not be inside a label while also manually clicking the file input; that can open the browser file picker twice.',
);

console.log('owner venue post upload markup test passed');
