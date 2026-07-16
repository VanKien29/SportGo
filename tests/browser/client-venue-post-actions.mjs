import { spawnSync } from 'node:child_process';
import fs from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import puppeteer from 'puppeteer';

const baseUrl = process.env.APP_URL || 'http://127.0.0.1:8000';
const username = process.env.CLIENT_TEST_USER || 'user';
const password = process.env.CLIENT_TEST_PASSWORD || '12345678';
const venueId = process.env.CLIENT_TEST_VENUE_ID || '1';
const runTag = `BROWSER_VENUE_POST_${new Date().toISOString().replace(/\D/g, '').slice(0, 14)}`;
const artifactDir = path.resolve('storage/app/test-artifacts/client-venue-post-actions', runTag);

await fs.mkdir(artifactDir, { recursive: true });

const browser = await puppeteer.launch({
  executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe',
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});
const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 960, deviceScaleFactor: 1 });

const createdCommentIds = [];
const createdReportIds = [];
const touchedPostIds = [];
const consoleErrors = [];
const pageErrors = [];
const failedApiResponses = [];
let activeStep = 'bootstrap';
let userId = null;
let result = {};
let failure = '';

page.on('console', (message) => {
  if (message.type() === 'error') consoleErrors.push({ step: activeStep, message: message.text() });
});
page.on('pageerror', (error) => pageErrors.push({ step: activeStep, message: error.message }));
page.on('response', (response) => {
  if (response.url().startsWith(`${baseUrl}/api/`) && response.status() >= 400) {
    failedApiResponses.push({ step: activeStep, status: response.status(), url: response.url() });
  }
});

async function settle() {
  await page.waitForNetworkIdle({ idleTime: 250, timeout: 2_000 }).catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 250));
}

async function login() {
  activeStep = 'login';
  await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  await page.type('#login', username);
  await page.type('input[type="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForFunction(() => window.location.pathname !== '/login', { timeout: 20_000 });
  await settle();

  if (await page.$('.policy-backdrop')) {
    await page.evaluate(() => {
      const list = document.querySelector('.policy-list');
      if (list) {
        list.scrollTop = list.scrollHeight;
        list.dispatchEvent(new Event('scroll', { bubbles: true }));
      }
    });
    await page.waitForFunction(() => !document.querySelector('.agree-row input')?.disabled, { timeout: 5_000 });
    await page.click('.agree-row input');
    await page.click('.accept-btn');
    await page.waitForSelector('.policy-backdrop', { hidden: true, timeout: 15_000 });
  }

  const auth = await page.evaluate(() => JSON.parse(localStorage.getItem('auth_user') || 'null'));
  userId = Number(auth?.id || 0) || null;
  if (!userId || localStorageRole(auth) !== 'user') {
    throw new Error(`Phiên đăng nhập Client không hợp lệ: ${JSON.stringify(auth)}`);
  }
}

function localStorageRole(auth) {
  return auth?.role_group || auth?.role || 'user';
}

function cleanupCreatedRecords() {
  if (!userId || (!createdCommentIds.length && !createdReportIds.length)) {
    return { attempted: false, success: true };
  }

  const commentIds = createdCommentIds.map(Number).filter(Number.isInteger);
  const reportIds = createdReportIds.map(Number).filter(Number.isInteger);
  const postIds = touchedPostIds.map(Number).filter(Number.isInteger);
  const php = [
    `\$userId = ${Number(userId)};`,
    `\$commentIds = ${JSON.stringify(commentIds)};`,
    `\$reportIds = ${JSON.stringify(reportIds)};`,
    `\$postIds = ${JSON.stringify(postIds)};`,
    'DB::transaction(function () use ($userId, $commentIds, $reportIds, $postIds) {',
    "  if ($commentIds) DB::table('venue_post_comments')->where('user_id', $userId)->whereIn('id', $commentIds)->delete();",
    "  foreach ($postIds as $postId) { $count = DB::table('venue_post_comments')->where('venue_post_id', $postId)->where('status', 'published')->count(); DB::table('venue_posts')->where('id', $postId)->update(['comment_count' => $count]); }",
    "  if ($reportIds) DB::table('reports')->where('reporter_id', $userId)->whereIn('id', $reportIds)->delete();",
    '});',
  ].join(' ');
  const cleanup = spawnSync('php', ['artisan', 'tinker', `--execute=${php}`], {
    cwd: process.cwd(),
    encoding: 'utf8',
    windowsHide: true,
  });

  return {
    attempted: true,
    success: cleanup.status === 0,
    status: cleanup.status,
    stderr: cleanup.stderr?.trim() || '',
  };
}

try {
  await login();
  activeStep = 'open-venue-posts';
  await page.goto(`${baseUrl}/venues/${venueId}?tab=posts`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  await page.waitForSelector('.venue-post-card', { visible: true, timeout: 20_000 });
  await settle();

  activeStep = 'submit-comment';
  await page.click('.venue-post-card .venue-post-actions button:nth-child(2)');
  await page.waitForSelector('.venue-post-card .venue-comment-form input', { visible: true, timeout: 15_000 });
  const commentText = `${runTag} Bình luận kiểm thử Chrome.`;
  await page.type('.venue-post-card .venue-comment-form input', commentText);
  const commentResponsePromise = page.waitForResponse(
    (response) => response.url().includes('/api/venue-posts/')
      && response.url().endsWith('/comments')
      && response.request().method() === 'POST',
    { timeout: 15_000 },
  );
  await page.$eval('.venue-post-card .venue-comment-form', (form) => form.requestSubmit());
  const commentResponse = await commentResponsePromise;
  const commentPayload = await commentResponse.json();
  if (!commentResponse.ok()) throw new Error(`Gửi bình luận trả HTTP ${commentResponse.status()}.`);
  const commentId = Number(commentPayload?.data?.id || 0);
  if (!commentId) throw new Error('API bình luận không trả ID để kiểm tra và dọn dữ liệu.');
  createdCommentIds.push(commentId);
  const postId = Number(commentResponse.url().match(/venue-posts\/(\d+)\/comments/)?.[1] || 0);
  if (postId) touchedPostIds.push(postId);
  await page.waitForFunction(
    (text) => document.querySelector('.venue-post-card .venue-comment-list')?.textContent?.includes(text),
    { timeout: 15_000 },
    commentText,
  );
  result.comment = { submitted: true, rendered: true, id: commentId, post_id: postId };

  activeStep = 'submit-report';
  await page.click('.venue-post-card .venue-post-menu-wrap > button');
  await page.waitForSelector('.venue-post-menu', { visible: true, timeout: 8_000 });
  await page.click('.venue-post-menu button');
  await page.waitForSelector('.moderation-modal', { visible: true, timeout: 8_000 });
  await page.click('.moderation-modal input[value="spam"]');
  await page.type('.moderation-modal textarea', `${runTag} Báo cáo kiểm thử Chrome.`);
  const reportResponsePromise = page.waitForResponse(
    (response) => response.url().endsWith('/api/reports') && response.request().method() === 'POST',
    { timeout: 15_000 },
  );
  await page.$eval('.moderation-modal form', (form) => form.requestSubmit());
  const reportResponse = await reportResponsePromise;
  const reportPayload = await reportResponse.json();
  if (!reportResponse.ok()) throw new Error(`Gửi báo cáo trả HTTP ${reportResponse.status()}.`);
  const reportId = Number(reportPayload?.data?.id || 0);
  if (!reportId) throw new Error('API báo cáo không trả ID để kiểm tra và dọn dữ liệu.');
  createdReportIds.push(reportId);
  await page.waitForSelector('.moderation-modal', { hidden: true, timeout: 15_000 });
  await page.waitForSelector('.Vue-Toastification__toast--success', { visible: true, timeout: 8_000 });
  result.report = { submitted: true, success_toast: true, id: reportId };

  await page.screenshot({ path: path.join(artifactDir, 'venue-post-actions.png'), fullPage: true });
} catch (error) {
  failure = `${activeStep}: ${error.message}`;
  await page.screenshot({ path: path.join(artifactDir, 'failure.png'), fullPage: true }).catch(() => {});
} finally {
  await browser.close();
}

const cleanup = cleanupCreatedRecords();
const report = {
  status: !failure
    && !consoleErrors.length
    && !pageErrors.length
    && !failedApiResponses.length
    && cleanup.success
    ? 'pass'
    : 'fail',
  run_tag: runTag,
  result,
  cleanup,
  failure,
  console_errors: consoleErrors,
  page_errors: pageErrors,
  failed_api_responses: failedApiResponses,
  artifact_dir: artifactDir,
};

await fs.writeFile(path.join(artifactDir, 'report.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
if (report.status !== 'pass') process.exitCode = 1;
