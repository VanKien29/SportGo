import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const source = await readFile(new URL('../resources/js/app.js', import.meta.url), 'utf8');

assert.match(
  source,
  /import\s*\{\s*loadSystemProfile\s*\}\s*from\s*['"]\.\/stores\/systemProfile\.js['"];/,
  'app bootstrap must import loadSystemProfile before calling it',
);
assert.match(source, /loadSystemProfile\(\);/, 'app bootstrap must load the system profile');

console.log('System profile bootstrap checks passed.');
