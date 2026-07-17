import fs from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import puppeteer from 'puppeteer';

const baseUrl = process.env.APP_URL || 'http://127.0.0.1:8000';
const password = process.env.TEST_PASSWORD || '12345678';
const adminUsername = process.env.ADMIN_TEST_USER || 'superadmin';
const ownerUsername = process.env.OWNER_TEST_USER || 'owner';
const onlyRole = process.env.ADMIN_OWNER_SMOKE_ROLE || '';
const onlyRoute = process.env.ADMIN_OWNER_SMOKE_ONLY || '';
const runTag = `ADMIN_OWNER_SMOKE_${new Date().toISOString().replace(/\D/g, '').slice(0, 14)}`;
const artifactDir = path.resolve('storage/app/test-artifacts/admin-owner-ui-smoke', runTag);

await fs.mkdir(artifactDir, { recursive: true });

const browser = await puppeteer.launch({
  executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe',
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});
const page = await browser.newPage();

const results = [];
const failures = [];
const consoleErrors = [];
const pageErrors = [];
const failedApiResponses = [];
const invalidApiResponses = [];
const pendingApiRequests = new Map();
let activeRoute = 'bootstrap';

page.on('console', (message) => {
  if (message.type() === 'error') consoleErrors.push({ route: activeRoute, message: message.text() });
});
page.on('pageerror', (error) => pageErrors.push({ route: activeRoute, message: error.message }));
page.on('request', (request) => {
  if (request.url().startsWith(`${baseUrl}/api/`)) {
    pendingApiRequests.set(request.url(), { route: activeRoute, method: request.method(), url: request.url() });
  }
});
page.on('response', (response) => {
  pendingApiRequests.delete(response.url());
  if (response.url().startsWith(`${baseUrl}/api/`)) {
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
    if (response.status() >= 400) failedApiResponses.push(row);
    if (response.status() !== 204 && response.status() < 400 && !isExpectedFileResponse && !/application\/([\w.+-]*\+)?json/i.test(row.content_type)) {
      invalidApiResponses.push(row);
    }
  }
});
page.on('requestfailed', (request) => pendingApiRequests.delete(request.url()));

const sleep = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

async function settle() {
  await page.waitForNetworkIdle({ idleTime: 300, timeout: 1_500 }).catch(() => {});
  await sleep(300);
}

async function clearSession() {
  await page.goto(`${baseUrl}/`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  await page.evaluate(() => localStorage.clear());
  const cookies = await page.cookies();
  if (cookies.length) await page.deleteCookie(...cookies);
}

async function login(role) {
  const isAdmin = role === 'admin';
  await clearSession();
  await page.goto(`${baseUrl}${isAdmin ? '/admin/login' : '/login'}`, {
    waitUntil: 'domcontentloaded',
    timeout: 30_000,
  });
  const loginSelector = isAdmin ? '#admin-login' : '#login';
  await page.waitForSelector(loginSelector, { visible: true, timeout: 15_000 });
  await page.$eval(loginSelector, (input) => {
    input.value = '';
    input.dispatchEvent(new Event('input', { bubbles: true }));
  });
  await page.type(loginSelector, isAdmin ? adminUsername : ownerUsername);
  await page.$eval('input[type="password"]', (input) => {
    input.value = '';
    input.dispatchEvent(new Event('input', { bubbles: true }));
  });
  await page.type('input[type="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForFunction(
    (admin) => Boolean(localStorage.getItem('auth_token')) && (admin
      ? window.location.pathname.startsWith('/admin/') && window.location.pathname !== '/admin/login'
      : window.location.pathname.startsWith('/owner/')),
    { timeout: 20_000 },
    isAdmin,
  );
  await settle();
  if (!isAdmin) {
    await page.evaluate(() => localStorage.setItem('selected_cluster', '1'));
  }
  const session = await page.evaluate(() => ({
    path: window.location.pathname,
    hasToken: Boolean(localStorage.getItem('auth_token')),
    roleGroup: localStorage.getItem('auth_role_group'),
  }));
  if (!session.hasToken || session.roleGroup !== role) {
    throw new Error(`Đăng nhập ${role} không hợp lệ: ${JSON.stringify(session)}`);
  }
  results.push({ role, viewport: 'login', name: 'login', status: 'pass', ...session });
}

async function inspectRoute(role, viewport, testCase) {
  activeRoute = `${role}:${viewport}:${testCase.name}`;
  const startedAt = Date.now();
  const apiFailureStart = failedApiResponses.length;
  const invalidApiStart = invalidApiResponses.length;
  const pageErrorStart = pageErrors.length;
  const consoleErrorStart = consoleErrors.length;
  console.log(`SMOKE ${activeRoute}`);
  try {
    await page.goto(`${baseUrl}${testCase.url}`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
    await page.waitForSelector('.sg-shell-admin .content-area', { visible: true, timeout: 20_000 });
    await settle();
    await page.waitForFunction((routeName) => {
      if (routeName === 'services') {
        const proxy = document.querySelector('.owner-services-page')?.__vueParentComponent?.proxy;
        return Boolean(proxy?.selectedCluster?.id) && proxy.loading === false;
      }
      const text = document.querySelector('.sg-shell-admin .content-area')?.innerText || '';
      return !text.includes('Đang tải');
    }, { timeout: 20_000 }, testCase.name).catch(() => {});
    await settle();
    const inspection = await page.evaluate((expectedRole) => {
      const bodyText = document.body.innerText || '';
      const area = document.querySelector('.sg-shell-admin .content-area');
      const rect = area?.getBoundingClientRect();
      return {
        path: window.location.pathname,
        heading: area?.querySelector('h1, h2')?.textContent?.trim() || '',
        textLength: area?.innerText?.trim().length || 0,
        visible: Boolean(area && rect?.width && rect?.height),
        inViewport: Boolean(rect
          && rect.bottom > 0
          && rect.right > 0
          && rect.top < window.innerHeight
          && rect.left < window.innerWidth),
        geometry: rect ? {
          left: Math.round(rect.left),
          top: Math.round(rect.top),
          right: Math.round(rect.right),
          bottom: Math.round(rect.bottom),
          width: Math.round(rect.width),
        } : null,
        horizontalOverflow: Math.max(document.documentElement.scrollWidth, document.body.scrollWidth) - window.innerWidth,
        redirectedToLogin: expectedRole === 'admin'
          ? window.location.pathname === '/admin/login'
          : window.location.pathname === '/login',
        fatalText: /Whoops|Internal Server Error|Undefined constant|SQLSTATE\[|Cannot read properties of undefined/i.test(bodyText),
        stuckLoading: (area?.innerText || '').includes('Đang tải'),
        loadingDiagnostics: (() => {
          const root = document.querySelector('.owner-services-page');
          const proxy = root?.__vueParentComponent?.proxy;
          return root ? {
            loading: proxy?.loading,
            selectedClusterId: proxy?.selectedCluster?.id,
            servicesCount: proxy?.services?.length,
            resources: performance.getEntriesByType('resource')
              .filter((entry) => entry.name.includes('/api/'))
              .slice(-6)
              .map((entry) => ({ name: entry.name, duration: Math.round(entry.duration) })),
          } : null;
        })(),
        brokenImages: [...document.images]
          .filter((image) => image.complete && image.naturalWidth === 0)
          .map((image) => image.currentSrc || image.getAttribute('src') || image.alt),
      };
    }, role);

    const newApiFailures = failedApiResponses.slice(apiFailureStart);
    const newInvalidApi = invalidApiResponses.slice(invalidApiStart);
    const newPageErrors = pageErrors.slice(pageErrorStart);
    const newConsoleErrors = consoleErrors.slice(consoleErrorStart);

    await page.screenshot({
      path: path.join(artifactDir, `${role}-${viewport}-${testCase.name}.png`),
      fullPage: true,
    });

    if (inspection.redirectedToLogin) throw new Error('Bị chuyển về trang đăng nhập.');
    if (!inspection.path.startsWith(`/${role}/`)) throw new Error(`Điều hướng sai role: ${inspection.path}`);
    if (!inspection.visible || !inspection.inViewport || inspection.textLength < 20) {
      throw new Error(`Nội dung chính không render trong viewport (${inspection.textLength} ký tự, ${JSON.stringify(inspection.geometry)}).`);
    }
    if (inspection.horizontalOverflow > 3) throw new Error(`Trang tràn ngang ${inspection.horizontalOverflow}px.`);
    if (inspection.fatalText) throw new Error('Trang hiển thị dấu hiệu lỗi hệ thống nghiêm trọng.');
    if (inspection.stuckLoading) throw new Error(`Trang vẫn còn trạng thái Đang tải sau 20 giây: ${JSON.stringify(inspection.loadingDiagnostics)}`);
    if (inspection.brokenImages.length) throw new Error(`Có ${inspection.brokenImages.length} ảnh hỏng.`);
    if (newPageErrors.length) throw new Error(`JavaScript lỗi: ${newPageErrors.map((item) => item.message).join('; ')}`);
    if (newConsoleErrors.length) throw new Error(`Console lỗi: ${newConsoleErrors.map((item) => item.message).join('; ')}`);
    if (newApiFailures.length) throw new Error(`API lỗi: ${JSON.stringify(newApiFailures)}`);
    if (newInvalidApi.length) throw new Error(`API không trả JSON: ${JSON.stringify(newInvalidApi)}`);

    results.push({
      role,
      viewport,
      name: testCase.name,
      status: 'pass',
      duration_ms: Date.now() - startedAt,
      ...inspection,
    });
  } catch (error) {
    await page.screenshot({
      path: path.join(artifactDir, `failure-${role}-${viewport}-${testCase.name}.png`),
      fullPage: true,
    }).catch(() => {});
    failures.push({ role, viewport, name: testCase.name, message: error.message, path: new URL(page.url()).pathname });
  }
}

const adminCases = [
  ['dashboard', '/admin/dashboard'],
  ['profile', '/admin/profile'],
  ['users', '/admin/users'],
  ['staffs', '/admin/staffs'],
  ['user-detail', '/admin/users/12'],
  ['staff-detail', '/admin/staffs/3'],
  ['vouchers', '/admin/vouchers'],
  ['voucher-detail', '/admin/vouchers/1'],
  ['membership-packages', '/admin/membership-packages'],
  ['payments', '/admin/payments'],
  ['finance-operations', '/admin/finance-operations'],
  ['partner-applications', '/admin/partner-applications'],
  ['partner-application-detail', '/admin/partner-applications/1'],
  ['partner-application-document', '/admin/partner-applications/1/documents/1'],
  ['partner-detail', '/admin/partners/1'],
  ['banners', '/admin/banners'],
  ['moderation', '/admin/moderation'],
  ['system-posts', '/admin/system-posts'],
  ['policies', '/admin/policies'],
  ['platform-fee-policies', '/admin/platform-fee-policies'],
  ['policy-detail', '/admin/policies/1'],
  ['reports-complaints', '/admin/reports-complaints'],
  ['reports-alias', '/admin/reports'],
  ['complaints-alias', '/admin/complaints'],
  ['roles', '/admin/roles'],
  ['role-detail', '/admin/roles/1'],
  ['court-types', '/admin/court-types'],
  ['amenities', '/admin/amenities'],
  ['service-categories', '/admin/service-categories'],
  ['venue-clusters', '/admin/venue-clusters'],
  ['venue-cluster-detail', '/admin/venue-clusters/1'],
  ['platform-fee-tiers', '/admin/platform-fee-tiers'],
  ['platform-fee-ledgers', '/admin/platform-fee-ledgers'],
  ['platform-fee-ledger-detail', '/admin/platform-fee-ledgers/1'],
  ['venue-platform-fees', '/admin/venues/1/platform-fees'],
  ['platform-fee-settings', '/admin/settings/platform-fee'],
  ['system-profile', '/admin/system-profile'],
  ['settings', '/admin/settings'],
  ['post-detail', '/admin/posts/1'],
  ['chat', '/admin/chat'],
].map(([name, url]) => ({ name, url }));

const ownerCases = [
  ['dashboard', '/owner/dashboard'],
  ['venue-clusters', '/owner/venue-clusters'],
  ['partner-termination', '/owner/venue-clusters/1/termination'],
  ['termination-request', '/owner/termination-requests/2'],
  ['affiliate', '/owner/affiliate'],
  ['services', '/owner/services'],
  ['venue-courts', '/owner/venue-courts'],
  ['bookings-alias', '/owner/bookings'],
  ['counter-booking', '/owner/counter-booking'],
  ['booking-list', '/owner/booking-list'],
  ['pricing', '/owner/pricing'],
  ['booking-settings', '/owner/booking-settings'],
  ['settings', '/owner/settings'],
  ['platform-fees', '/owner/platform-fees'],
  ['schedule-locks', '/owner/schedule-locks'],
  ['venue-posts', '/owner/venue-posts'],
  ['staff', '/owner/staff'],
  ['staff-shifts', '/owner/staff-shifts'],
  ['vouchers', '/owner/vouchers'],
  ['wallet-alias', '/owner/wallet'],
  ['policies', '/owner/policies'],
  ['matchmaking', '/owner/matchmaking'],
  ['complaints', '/owner/complaints'],
  ['complaint-detail', '/owner/complaints/2'],
  ['profile', '/owner/profile'],
  ['partner-profile', '/owner/partner-profile'],
  ['chat', '/owner/chat'],
  ['partner-document', '/owner/partner-documents/1/1'],
  ['finance', '/owner/finance'],
  ['refunds', '/owner/refunds'],
].map(([name, url]) => ({ name, url }));

const mobileNames = {
  admin: new Set(adminCases.map((testCase) => testCase.name)),
  owner: new Set(ownerCases.map((testCase) => testCase.name)),
};

try {
  for (const [role, cases] of [['admin', adminCases], ['owner', ownerCases]]) {
    if (onlyRole && onlyRole !== role) continue;
    await page.setViewport({ width: 1440, height: 960, deviceScaleFactor: 1 });
    await login(role);
    const selectedCases = onlyRoute ? cases.filter((testCase) => testCase.name === onlyRoute) : cases;
    for (const testCase of selectedCases) await inspectRoute(role, 'desktop', testCase);

    await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
    for (const testCase of selectedCases.filter((testCase) => mobileNames[role].has(testCase.name))) {
      await inspectRoute(role, 'mobile', testCase);
    }
  }
} catch (error) {
  failures.push({ role: onlyRole || 'bootstrap', viewport: 'bootstrap', name: activeRoute, message: error.message });
} finally {
  await browser.close();
}

const failedRouteKeys = new Set(failures.map((failure) => `${failure.role}:${failure.viewport}:${failure.name}`));
const relevantApiFailures = failedApiResponses.filter((failure) => !failedRouteKeys.has(failure.route));
const relevantInvalidApiResponses = invalidApiResponses.filter((failure) => !failedRouteKeys.has(failure.route));
const report = {
  status: failures.length === 0
    && pageErrors.length === 0
    && consoleErrors.length === 0
    && relevantApiFailures.length === 0
    && relevantInvalidApiResponses.length === 0 ? 'pass' : 'fail',
  run_tag: runTag,
  coverage: {
    admin_desktop: results.filter((item) => item.role === 'admin' && item.viewport === 'desktop').length,
    admin_mobile: results.filter((item) => item.role === 'admin' && item.viewport === 'mobile').length,
    owner_desktop: results.filter((item) => item.role === 'owner' && item.viewport === 'desktop').length,
    owner_mobile: results.filter((item) => item.role === 'owner' && item.viewport === 'mobile').length,
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
