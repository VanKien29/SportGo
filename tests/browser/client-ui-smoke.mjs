import fs from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import puppeteer from 'puppeteer';

const baseUrl = process.env.APP_URL || 'http://127.0.0.1:8000';
const username = process.env.CLIENT_TEST_USER || 'user';
const password = process.env.CLIENT_TEST_PASSWORD || '12345678';
const venueId = process.env.CLIENT_TEST_VENUE_ID || '1';
const bookingId = process.env.CLIENT_TEST_BOOKING_ID || '3';
const profileId = process.env.CLIENT_TEST_PROFILE_ID || '13';
const communitySlug = process.env.CLIENT_TEST_COMMUNITY_SLUG || 'green-sport-ba-dinh-dat-san-gio-cao-diem';
const newsSlug = process.env.CLIENT_TEST_NEWS_SLUG || 'chao-mung-den-voi-sportgo';
const playerPostId = process.env.CLIENT_TEST_PLAYER_POST_ID || '1';
const partnerApplicationId = process.env.CLIENT_TEST_PARTNER_APPLICATION_ID || '';
const partnerDocumentId = process.env.CLIENT_TEST_PARTNER_DOCUMENT_ID || '';
const onlyRoute = process.env.CLIENT_SMOKE_ONLY || '';
const artifactDir = path.resolve('storage/app/test-artifacts/client-ui-smoke');

await fs.mkdir(artifactDir, { recursive: true });

const browser = await puppeteer.launch({
  executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe',
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});

const page = await browser.newPage();
const consoleErrors = [];
const pageErrors = [];
const failedApiResponses = [];
let activeRoute = 'bootstrap';

page.on('console', (message) => {
  if (message.type() === 'error') {
    consoleErrors.push({ route: activeRoute, message: message.text() });
  }
});
page.on('pageerror', (error) => pageErrors.push({ route: activeRoute, message: error.message }));
page.on('response', (response) => {
  const url = response.url();
  if (url.startsWith(`${baseUrl}/api/`) && response.status() >= 400) {
    failedApiResponses.push({ route: activeRoute, status: response.status(), url });
  }
});

async function settle() {
  await page.waitForNetworkIdle({ idleTime: 300, timeout: 2_000 }).catch(() => {});
  await new Promise((resolve) => setTimeout(resolve, 250));
}

async function inspectRoute(testCase, viewportName) {
  activeRoute = `${viewportName}:${testCase.name}`;
  console.log(`SMOKE ${activeRoute}`);
  await page.goto(`${baseUrl}${testCase.url}`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  await page.waitForSelector(testCase.selector, { timeout: 20_000 });
  if (testCase.loadingText) {
    await page.waitForFunction(
      (loadingText) => !document.body.innerText.includes(loadingText),
      { timeout: 20_000 },
      testCase.loadingText,
    );
  }
  await settle();
  await page.waitForFunction(
    () => [...document.images].every((image) => image.complete),
    { timeout: 5_000 },
  ).catch(() => {});

  const inspection = await page.evaluate((selector) => {
    const text = document.body.innerText || '';
    const target = document.querySelector(selector);
    const targetStyle = target ? window.getComputedStyle(target) : null;
    const targetRect = target?.getBoundingClientRect();
    return {
      path: window.location.pathname,
      title: document.title,
      heading: document.querySelector('h1, h2')?.textContent?.trim() || '',
      textLength: text.trim().length,
      horizontalOverflow: Math.max(document.documentElement.scrollWidth, document.body.scrollWidth) - window.innerWidth,
      mojibake: /(?:â€|ï¿½|�|Ä‘|Æ°|Æ¡|á»|áº)/.test(text),
      brokenImages: [...document.images]
        .filter((image) => image.complete && image.naturalWidth === 0)
        .map((image) => image.currentSrc || image.getAttribute('src') || image.alt),
      targetVisible: Boolean(target && targetStyle?.visibility !== 'hidden' && targetStyle?.display !== 'none' && targetRect?.width && targetRect?.height),
    };
  }, testCase.selector);

  await page.screenshot({
    path: path.join(artifactDir, `${viewportName}-${testCase.name}.png`),
    fullPage: true,
  });

  if (inspection.textLength < 20) {
    throw new Error(`${testCase.name}: nội dung render quá ngắn (${inspection.textLength} ký tự).`);
  }
  if (inspection.mojibake) {
    throw new Error(`${testCase.name}: phát hiện chuỗi có dấu hiệu sai mã hóa.`);
  }
  if (inspection.horizontalOverflow > 2) {
    throw new Error(`${testCase.name}: tràn ngang ${inspection.horizontalOverflow}px ở ${viewportName}.`);
  }
  if (inspection.brokenImages.length) {
    throw new Error(`${testCase.name}: có ${inspection.brokenImages.length} ảnh không tải được.`);
  }
  if (!inspection.targetVisible) {
    throw new Error(`${testCase.name}: vùng nội dung chính tồn tại nhưng không hiển thị.`);
  }

  return { name: testCase.name, ...inspection };
}

const results = { login: null, guest: [], desktop: [], mobile: [], modalChecks: [] };
const failures = [];

try {
  await page.setViewport({ width: 1440, height: 960, deviceScaleFactor: 1 });
  for (const testCase of [
    { name: 'register', url: '/register', selector: '.auth-layout h1' },
    { name: 'forgot-password', url: '/forgot-password', selector: '.auth-layout h1' },
  ]) {
    try {
      results.guest.push(await inspectRoute(testCase, 'guest'));
    } catch (error) {
      failures.push({ route: `guest:${testCase.name}`, message: error.message });
    }
  }

  activeRoute = 'desktop:login';
  await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  await page.waitForSelector('#login', { visible: true });
  await page.type('#login', username);
  await page.type('input[type="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForFunction(() => window.location.pathname !== '/login', { timeout: 20_000 });
  await settle();
  results.login = await page.evaluate(() => ({
    path: window.location.pathname,
    authenticated: Boolean(localStorage.getItem('auth_token')),
    roleGroup: localStorage.getItem('auth_role_group'),
  }));

  if (!results.login.authenticated || results.login.roleGroup !== 'user') {
    throw new Error(`Đăng nhập client không đúng role: ${JSON.stringify(results.login)}`);
  }

  const policyGate = await page.$('.policy-backdrop');
  if (policyGate) {
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
    results.policyAccepted = true;
  }

  const desktopCases = [
    { name: 'home', url: '/', selector: '.home-page' },
    { name: 'venues', url: '/venues', selector: '.venue-market-page', loadingText: 'Đang tải danh sách sân' },
    { name: 'venue-detail', url: `/venues/${venueId}`, selector: '.venue-detail-page .hero-copy h1' },
    { name: 'booking-create', url: `/booking?venue_cluster_id=${venueId}`, selector: '.client-booking .schedule-workspace' },
    { name: 'booking-history', url: '/bookings', selector: '.booking-history-page' },
    { name: 'booking-detail', url: `/booking/${bookingId}`, selector: '.detail-container .detail-content' },
    { name: 'community', url: '/community', selector: '.community-page', loadingText: 'Đang tải bài viết' },
    { name: 'community-detail', url: `/community/${communitySlug}`, selector: '.news-detail-page .fb-modal-container', loadingText: 'Đang tải bài viết' },
    { name: 'user-profile', url: `/user/${profileId}`, selector: '.user-profile-page .profile-hero h1' },
    { name: 'news', url: '/news', selector: '.news-page-container', loadingText: 'Đang tải bài viết' },
    { name: 'news-detail', url: `/news/${newsSlug}`, selector: '.news-detail-container .news-detail-content', loadingText: 'Đang tải bài viết' },
    { name: 'matchmaking-manage', url: `/matchmaking-posts/${playerPostId}/manage`, selector: '.matchmaking-manage-page .page-header h1' },
    { name: 'chat', url: '/chat', selector: '.chat-page' },
    { name: 'account', url: '/profile', selector: '.profile-wrapper' },
    { name: 'vip-membership', url: '/vip-membership', selector: 'main, .vip-page, .vip-membership' },
    { name: 'partner-application', url: '/partner-application', selector: '.partner-portal-page .portal-title', loadingText: 'Đang tải hồ sơ' },
  ];

  if (partnerApplicationId) {
    desktopCases.push({
      name: 'partner-application-detail',
      url: `/partner-application/${partnerApplicationId}`,
      selector: '.partner-review-page .partner-page-title h1',
      loadingText: 'Đang tải hồ sơ',
    });
  }
  if (partnerApplicationId && partnerDocumentId) {
    desktopCases.push({
      name: 'partner-application-document',
      url: `/partner-application/${partnerApplicationId}/documents/${partnerDocumentId}`,
      selector: '.partner-document-page .partner-page-title h1',
      loadingText: 'Đang tải văn bản',
    });
  }

  const selectedDesktopCases = onlyRoute
    ? desktopCases.filter((testCase) => testCase.name === onlyRoute)
    : desktopCases;

  for (const testCase of selectedDesktopCases) {
    try {
      results.desktop.push(await inspectRoute(testCase, 'desktop'));
    } catch (error) {
      failures.push({ route: `desktop:${testCase.name}`, message: error.message });
    }
  }

  if (!onlyRoute || onlyRoute === 'venue-detail') {
    activeRoute = 'desktop:venue-support-modals';
    await page.goto(`${baseUrl}/venues/${venueId}`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
    await page.waitForSelector('.support-actions', { visible: true, timeout: 8_000 });
    await settle();
    for (const check of [
      { buttonText: 'Khiếu nại sân', selector: '.complaint-modal' },
      { buttonText: 'Báo cáo sân', selector: '.moderation-modal' },
    ]) {
      const clicked = await page.evaluate((buttonText) => {
        const button = [...document.querySelectorAll('button')]
          .find((candidate) => candidate.textContent?.includes(buttonText));
        button?.click();
        return Boolean(button);
      }, check.buttonText);
      if (!clicked) {
        failures.push({ route: activeRoute, message: `Không tìm thấy nút ${check.buttonText}.` });
        continue;
      }
      try {
        await page.waitForSelector(check.selector, { visible: true, timeout: 5_000 });
        results.modalChecks.push(check.buttonText);
        await page.evaluate((selector) => {
          document.querySelector(`${selector} button[aria-label="Đóng"]`)?.click();
        }, check.selector);
        await page.waitForFunction((selector) => !document.querySelector(selector), { timeout: 5_000 }, check.selector);
      } catch (error) {
        failures.push({ route: activeRoute, message: `${check.buttonText}: ${error.message}` });
      }
    }
  }

  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
  const mobileCases = [
    'user-profile',
    'home',
    'venues',
    'venue-detail',
    'booking-create',
    'booking-history',
    'booking-detail',
    'community',
    'community-detail',
    'news-detail',
    'matchmaking-manage',
    'partner-application',
    ...(partnerApplicationId ? ['partner-application-detail'] : []),
    ...(partnerApplicationId && partnerDocumentId ? ['partner-application-document'] : []),
  ].map((name) => desktopCases.find((testCase) => testCase.name === name));

  const selectedMobileCases = onlyRoute
    ? mobileCases.filter((testCase) => testCase.name === onlyRoute)
    : mobileCases;

  for (const testCase of selectedMobileCases) {
    try {
      results.mobile.push(await inspectRoute(testCase, 'mobile'));
    } catch (error) {
      failures.push({ route: `mobile:${testCase.name}`, message: error.message });
    }
  }
} catch (error) {
  failures.push({ route: activeRoute, message: error.message });
} finally {
  try {
    const token = !page.isClosed()
      ? await page.evaluate(() => localStorage.getItem('auth_token'))
      : null;
    if (token) {
      await fetch(`${baseUrl}/api/auth/logout`, {
        method: 'POST',
        headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
      });
    }
  } catch {
    // Cleanup should not hide the actual smoke-test result.
  }
  await browser.close();
}

const report = {
  status: failures.length === 0 && pageErrors.length === 0 && failedApiResponses.length === 0 ? 'pass' : 'fail',
  results,
  failures,
  pageErrors,
  consoleErrors,
  failedApiResponses,
  artifactDir,
};

const output = process.env.CLIENT_SMOKE_SUMMARY
  ? {
      status: report.status,
      coverage: {
        guest: results.guest.length,
        desktop: results.desktop.length,
        mobile: results.mobile.length,
        modalChecks: results.modalChecks.length,
        policyAccepted: results.policyAccepted,
      },
      failures,
      pageErrors,
      consoleErrors,
      failedApiResponses,
      artifactDir,
    }
  : report;

console.log(JSON.stringify(output, null, 2));
if (report.status !== 'pass') process.exitCode = 1;
