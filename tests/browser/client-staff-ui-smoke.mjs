import fs from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import puppeteer from 'puppeteer';

const baseUrl = process.env.APP_URL || 'http://127.0.0.1:8000';
const password = process.env.TEST_PASSWORD || '12345678';
const onlyGroup = process.env.CLIENT_STAFF_SMOKE_GROUP || '';
const onlyRoute = process.env.CLIENT_STAFF_SMOKE_ONLY || '';
const runTag = `CLIENT_STAFF_SMOKE_${new Date().toISOString().replace(/\D/g, '').slice(0, 14)}`;
const artifactDir = path.resolve('storage/app/test-artifacts/client-staff-ui-smoke', runTag);

await fs.mkdir(artifactDir, { recursive: true });

const browser = await puppeteer.launch({
  executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe',
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});
const page = await browser.newPage();

const results = [];
const failures = [];
const pageErrors = [];
const consoleErrors = [];
const failedApiResponses = [];
const invalidApiResponses = [];
const pendingApiRequests = new Map();
let activeRoute = 'bootstrap';

page.on('pageerror', (error) => pageErrors.push({ route: activeRoute, message: error.message }));
page.on('console', (message) => {
  if (message.type() === 'error') consoleErrors.push({ route: activeRoute, message: message.text() });
});
page.on('request', (request) => {
  if (request.url().startsWith(`${baseUrl}/api/`)) {
    pendingApiRequests.set(request.url(), {
      route: activeRoute,
      method: request.method(),
      url: request.url(),
    });
  }
});
page.on('requestfailed', (request) => pendingApiRequests.delete(request.url()));
page.on('response', (response) => {
  pendingApiRequests.delete(response.url());
  if (!response.url().startsWith(`${baseUrl}/api/`)) return;

  const row = {
    route: activeRoute,
    method: response.request().method(),
    status: response.status(),
    content_type: response.headers()['content-type'] || '',
    url: response.url(),
  };
  const contentDisposition = response.headers()['content-disposition'] || '';
  const isExpectedFileResponse = /\/download(?:\?|$)/i.test(row.url)
    || /attachment|inline/i.test(contentDisposition)
    || /^(image|audio|video)\//i.test(row.content_type)
    || /application\/(pdf|octet-stream|msword|vnd\.)/i.test(row.content_type);
  if (row.status >= 400) failedApiResponses.push(row);
  if (row.status !== 204 && row.status < 400 && !isExpectedFileResponse && !/application\/([\w.+-]*\+)?json/i.test(row.content_type)) {
    invalidApiResponses.push(row);
  }
});

const sleep = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

async function retryTransientPageRead(action) {
  for (let attempt = 1; attempt <= 3; attempt += 1) {
    try {
      return await action();
    } catch (error) {
      const transient = /Execution context was destroyed|Cannot find context with specified id/i.test(error.message);
      if (!transient || attempt === 3) throw error;
      await page.waitForSelector('#app', { visible: true, timeout: 5_000 }).catch(() => {});
      await sleep(300);
    }
  }

  throw new Error('Khong the doc trang sau khi thu lai.');
}

async function settle() {
  await page.waitForNetworkIdle({ idleTime: 400, timeout: 2_500 }).catch(() => {});
  await sleep(500);
}

async function clearSession() {
  await page.goto(`${baseUrl}/`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  await page.evaluate(() => localStorage.clear());
  const cookies = await page.cookies();
  if (cookies.length) await page.deleteCookie(...cookies);
}

async function setInput(selector, value) {
  await page.waitForSelector(selector, { visible: true, timeout: 15_000 });
  await page.$eval(selector, (input) => {
    input.value = '';
    input.dispatchEvent(new Event('input', { bubbles: true }));
  });
  await page.type(selector, value);
}

async function login(username, roleGroup) {
  activeRoute = `login:${roleGroup}`;
  await clearSession();
  await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  await setInput('#login', username);
  await setInput('input[type="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForFunction(
    (expectedRole) => Boolean(localStorage.getItem('auth_token'))
      && localStorage.getItem('auth_role_group') === expectedRole,
    { timeout: 20_000 },
    roleGroup,
  );
  if (roleGroup === 'staff') await page.evaluate(() => localStorage.setItem('selected_cluster', '1'));
  await settle();
  results.push({ group: roleGroup, viewport: 'login', name: 'login', status: 'pass', path: new URL(page.url()).pathname });
}

async function visibleLoadingTexts() {
  return retryTransientPageRead(() => page.evaluate(() => {
    const visible = (element) => {
      const rect = element.getBoundingClientRect();
      const style = getComputedStyle(element);
      return rect.width > 0 && rect.height > 0 && style.display !== 'none' && style.visibility !== 'hidden';
    };
    const textLoaders = [...document.querySelectorAll('body *')]
      .filter((element) => visible(element) && /^Đang (tải|chuẩn bị|xử lý)/i.test((element.textContent || '').trim()))
      .filter((element) => ![...element.children].some((child) => /^Đang (tải|chuẩn bị|xử lý)/i.test((child.textContent || '').trim())))
      .map((element) => (element.textContent || '').trim().slice(0, 160));
    const skeletonLoaders = [...document.querySelectorAll('.loading-skeleton-layout, [aria-busy="true"]')]
      .filter(visible)
      .map((element) => element.getAttribute('aria-label') || 'Đang tải giao diện');
    return [...textLoaders, ...skeletonLoaders];
  }));
}

async function inspectRoute(group, viewport, testCase) {
  activeRoute = `${group}:${viewport}:${testCase.name}`;
  const startedAt = Date.now();
  const apiFailureStart = failedApiResponses.length;
  const invalidApiStart = invalidApiResponses.length;
  const pageErrorStart = pageErrors.length;
  const consoleErrorStart = consoleErrors.length;
  console.log(`SMOKE ${activeRoute}`);

  try {
    await page.goto(`${baseUrl}${testCase.url}`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
    await page.waitForSelector('#app', { visible: true, timeout: 20_000 });
    await settle();
    if (testCase.name === 'home') {
      await page.$eval('#community', (section) => section.scrollIntoView({ block: 'center' })).catch(() => {});
      await settle();
    }

    const deadline = Date.now() + 20_000;
    let loaders = await visibleLoadingTexts();
    while (loaders.length && Date.now() < deadline) {
      await sleep(500);
      loaders = await visibleLoadingTexts();
    }
    await settle();

    const inspection = await retryTransientPageRead(() => page.evaluate(() => {
      const visible = (element) => {
        if (!element) return false;
        const rect = element.getBoundingClientRect();
        const style = getComputedStyle(element);
        return rect.width > 0 && rect.height > 0 && style.display !== 'none' && style.visibility !== 'hidden';
      };
      const root = document.querySelector('.sg-shell-admin .content-area')
        || document.querySelector('main')
        || document.querySelector('#app');
      const bodyText = document.body?.innerText || '';
      const errorSelectors = [
        '.alert.error', '.error-state', '.state-panel.error', '.news-state--error',
        '.error-message', '[role="alert"].error', '.loading-error',
      ];
      const visibleErrors = errorSelectors.flatMap((selector) => [...document.querySelectorAll(selector)])
        .filter(visible)
        .map((element) => (element.innerText || element.textContent || '').trim())
        .filter(Boolean);

      return {
        path: location.pathname,
        query: location.search,
        title: document.title,
        textLength: (root?.innerText || '').trim().length,
        rootVisible: visible(root),
        heading: root?.querySelector('h1, h2')?.textContent?.trim() || '',
        horizontalOverflow: Math.max(document.documentElement.scrollWidth, document.body.scrollWidth) - innerWidth,
        fatalText: /Whoops|Internal Server Error|SQLSTATE\[|Undefined constant|Cannot read properties of undefined/i.test(bodyText),
        visibleErrors,
        brokenImages: [...document.images]
          .filter((image) => image.complete && image.naturalWidth === 0)
          .map((image) => image.currentSrc || image.getAttribute('src') || image.alt),
      };
    }));

    const newApiFailures = failedApiResponses.slice(apiFailureStart);
    const newInvalidApi = invalidApiResponses.slice(invalidApiStart);
    const newPageErrors = pageErrors.slice(pageErrorStart);
    const newConsoleErrors = consoleErrors.slice(consoleErrorStart);

    await page.screenshot({
      path: path.join(artifactDir, `${group}-${viewport}-${testCase.name}.png`),
      fullPage: true,
    });

    if (inspection.path !== testCase.expectedPath) throw new Error(`Điều hướng sai: ${inspection.path}, cần ${testCase.expectedPath}.`);
    if (!inspection.rootVisible || inspection.textLength < 20) throw new Error(`Nội dung chính không render đủ (${inspection.textLength} ký tự).`);
    if (inspection.horizontalOverflow > 3) throw new Error(`Trang tràn ngang ${inspection.horizontalOverflow}px.`);
    if (inspection.fatalText) throw new Error('Trang có dấu hiệu lỗi hệ thống nghiêm trọng.');
    if (loaders.length) throw new Error(`Trang còn loader sau 20 giây: ${JSON.stringify(loaders)}`);
    if (inspection.visibleErrors.length) throw new Error(`Trang hiển thị lỗi: ${inspection.visibleErrors.join(' | ')}`);
    if (inspection.brokenImages.length) throw new Error(`Có ${inspection.brokenImages.length} ảnh hỏng: ${inspection.brokenImages.slice(0, 3).join(', ')}`);
    if (newPageErrors.length) throw new Error(`JavaScript lỗi: ${newPageErrors.map((item) => item.message).join('; ')}`);
    if (newConsoleErrors.length) throw new Error(`Console lỗi: ${newConsoleErrors.map((item) => item.message).join('; ')}`);
    if (newApiFailures.length) throw new Error(`API lỗi: ${JSON.stringify(newApiFailures)}`);
    if (newInvalidApi.length) throw new Error(`API không trả JSON: ${JSON.stringify(newInvalidApi)}`);

    results.push({
      group,
      viewport,
      name: testCase.name,
      status: 'pass',
      duration_ms: Date.now() - startedAt,
      ...inspection,
    });
  } catch (error) {
    await page.screenshot({
      path: path.join(artifactDir, `failure-${group}-${viewport}-${testCase.name}.png`),
      fullPage: true,
    }).catch(() => {});
    failures.push({ group, viewport, name: testCase.name, message: error.message, path: new URL(page.url()).pathname });
  }
}

const publicCases = [
  ['home', '/', '/'],
  ['venues', '/venues', '/venues'],
  ['venue-detail', '/venues/1', '/venues/1'],
  ['community', '/community', '/community'],
  ['community-detail', '/community/green-sport-ba-dinh-dat-san-gio-cao-diem', '/community/green-sport-ba-dinh-dat-san-gio-cao-diem'],
  ['news', '/news', '/news'],
  ['news-detail', '/news/chao-mung-den-voi-sportgo', '/news/chao-mung-den-voi-sportgo'],
  ['user-profile', '/user/12', '/user/12'],
  ['login', '/login', '/login'],
  ['register', '/register', '/register'],
  ['forgot-password', '/forgot-password', '/forgot-password'],
  ['admin-login', '/admin/login', '/admin/login'],
  ['admin-forgot-password', '/admin/forgot-password', '/admin/forgot-password'],
].map(([name, url, expectedPath]) => ({ name, url, expectedPath }));

const clientCases = [
  ['profile', '/profile', '/profile'],
  ['matchmaking-manage', '/matchmaking-posts/1/manage', '/matchmaking-posts/1/manage'],
  ['chat', '/chat', '/chat'],
  ['partner-application', '/partner-application', '/partner-application'],
  ['booking-create', '/booking?venue_cluster_id=1', '/booking'],
  ['booking-detail', '/booking/1', '/booking/1'],
  ['booking-history', '/bookings', '/bookings'],
  ['vip-membership', '/vip-membership', '/vip-membership'],
  ['become-partner-alias', '/become-partner', '/partner-application'],
].map(([name, url, expectedPath]) => ({ name, url, expectedPath }));

const partnerCases = [
  ['partner-application-detail', '/partner-application/3', '/partner-application/3'],
  ['partner-application-document', '/partner-application/1/documents/105', '/partner-application/1/documents/105'],
].map(([name, url, expectedPath]) => ({ name, url, expectedPath }));

const staffCases = [
  ['dashboard', '/staff/dashboard', '/staff/dashboard'],
  ['schedules', '/staff/schedules', '/staff/schedules'],
  ['bookings', '/staff/bookings', '/staff/bookings'],
  ['counter-booking', '/staff/counter-booking', '/staff/counter-booking'],
  ['settings', '/staff/settings', '/staff/settings'],
  ['chat', '/staff/chat', '/staff/chat'],
  ['profile', '/staff/profile', '/staff/profile'],
].map(([name, url, expectedPath]) => ({ name, url, expectedPath }));

const mobileNames = {
  public: new Set(publicCases.map((testCase) => testCase.name)),
  client: new Set(clientCases.map((testCase) => testCase.name)),
  partner: new Set(partnerCases.map((testCase) => testCase.name)),
  staff: new Set(staffCases.map((testCase) => testCase.name)),
};

async function runGroup(group, cases, loginConfig = null) {
  if (onlyGroup && onlyGroup !== group) return;
  await page.setViewport({ width: 1440, height: 960, deviceScaleFactor: 1 });
  if (loginConfig) await login(loginConfig.username, loginConfig.roleGroup);
  else await clearSession();
  const selectedCases = onlyRoute ? cases.filter((testCase) => testCase.name === onlyRoute) : cases;
  for (const testCase of selectedCases) await inspectRoute(group, 'desktop', testCase);

  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
  for (const testCase of selectedCases.filter((testCase) => mobileNames[group].has(testCase.name))) {
    await inspectRoute(group, 'mobile', testCase);
  }
}

try {
  await runGroup('public', publicCases);
  await runGroup('client', clientCases, { username: 'user', roleGroup: 'user' });
  await runGroup('partner', partnerCases, { username: 'owner', roleGroup: 'owner' });
  await runGroup('staff', staffCases, { username: 'venuestaff', roleGroup: 'staff' });
} catch (error) {
  failures.push({ group: onlyGroup || 'bootstrap', viewport: 'bootstrap', name: activeRoute, message: error.message });
} finally {
  await browser.close();
}

const report = {
  status: failures.length === 0 && pageErrors.length === 0 ? 'pass' : 'fail',
  run_tag: runTag,
  coverage: {
    public_desktop: results.filter((item) => item.group === 'public' && item.viewport === 'desktop').length,
    public_mobile: results.filter((item) => item.group === 'public' && item.viewport === 'mobile').length,
    client_desktop: results.filter((item) => item.group === 'client' && item.viewport === 'desktop').length,
    client_mobile: results.filter((item) => item.group === 'client' && item.viewport === 'mobile').length,
    partner_desktop: results.filter((item) => item.group === 'partner' && item.viewport === 'desktop').length,
    partner_mobile: results.filter((item) => item.group === 'partner' && item.viewport === 'mobile').length,
    staff_desktop: results.filter((item) => item.group === 'staff' && item.viewport === 'desktop').length,
    staff_mobile: results.filter((item) => item.group === 'staff' && item.viewport === 'mobile').length,
  },
  failures,
  page_errors: pageErrors,
  console_errors: consoleErrors,
  failed_api_responses: failedApiResponses,
  invalid_api_responses: invalidApiResponses,
  pending_api_requests: [...pendingApiRequests.values()],
  artifact_dir: artifactDir,
};

await fs.writeFile(path.join(artifactDir, 'report.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
if (report.status !== 'pass') process.exitCode = 1;
