import fs from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import puppeteer from 'puppeteer';

const baseUrl = process.env.APP_URL || 'http://127.0.0.1:8000';
const password = process.env.TEST_PASSWORD || '12345678';
const runTag = `ADMIN_OWNER_ACTIONS_${new Date().toISOString().replace(/\D/g, '').slice(0, 14)}`;
const onlyActions = new Set((process.env.ADMIN_OWNER_ACTIONS_ONLY || '').split(',').map((value) => value.trim()).filter(Boolean));
const artifactDir = path.resolve('storage/app/test-artifacts/admin-owner-ui-actions', runTag);
await fs.mkdir(artifactDir, { recursive: true });

const browser = await puppeteer.launch({
  executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe',
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});
const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 960, deviceScaleFactor: 1 });

const results = [];
const failures = [];
const pageErrors = [];
const apiFailures = [];
const invalidApiResponses = [];
const mutations = [];
let activeAction = 'bootstrap';

page.on('pageerror', (error) => pageErrors.push({ action: activeAction, message: error.message }));
page.on('response', (response) => {
  const request = response.request();
  if (!response.url().startsWith(`${baseUrl}/api/`)) return;
  const row = {
    action: activeAction,
    method: request.method(),
    status: response.status(),
    content_type: response.headers()['content-type'] || '',
    url: response.url(),
  };
  if (response.status() >= 400) apiFailures.push(row);
  if (response.status() !== 204 && response.status() < 400 && !/application\/([\w.+-]*\+)?json/i.test(row.content_type)) {
    invalidApiResponses.push(row);
  }
  if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method())) mutations.push(row);
});

const sleep = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));
const assert = (condition, message) => { if (!condition) throw new Error(message); };

async function clearInput(selector) {
  await page.$eval(selector, (input) => {
    input.value = '';
    input.dispatchEvent(new Event('input', { bubbles: true }));
  });
}

async function login(role) {
  const admin = role === 'admin';
  await page.goto(`${baseUrl}/`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  await page.evaluate(() => localStorage.clear());
  const cookies = await page.cookies();
  if (cookies.length) await page.deleteCookie(...cookies);
  await page.goto(`${baseUrl}${admin ? '/admin/login' : '/login'}`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  const userSelector = admin ? '#admin-login' : '#login';
  await page.waitForSelector(userSelector, { visible: true, timeout: 15_000 });
  await clearInput(userSelector);
  await page.type(userSelector, admin ? 'superadmin' : 'owner');
  await clearInput('input[type="password"]');
  await page.type('input[type="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForFunction((isAdmin) => {
    const authenticated = Boolean(localStorage.getItem('auth_token'));
    const inRoleArea = isAdmin
      ? location.pathname.startsWith('/admin/') && location.pathname !== '/admin/login'
      : location.pathname.startsWith('/owner/');
    return authenticated && inRoleArea;
  }, { timeout: 20_000 }, admin);
  if (!admin) await page.evaluate(() => localStorage.setItem('selected_cluster', '1'));
  const session = await page.evaluate(() => ({ token: Boolean(localStorage.getItem('auth_token')), role: localStorage.getItem('auth_role_group') }));
  assert(session.token && session.role === role, `Phiên ${role} không hợp lệ.`);
}

async function goto(url) {
  await page.goto(`${baseUrl}${url}`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  await page.waitForSelector('.sg-shell-admin .content-area', { visible: true, timeout: 20_000 });
  await sleep(2_200);
  await page.waitForFunction(() => {
    const text = document.querySelector('.sg-shell-admin .content-area')?.innerText || '';
    return !text.includes('Đang tải');
  }, { timeout: 15_000 }).catch(() => {});
  await sleep(500);
}

async function clickText(selector, text) {
  const clicked = await page.$$eval(selector, (elements, expected) => {
    const target = elements.find((element) => (element.innerText || element.textContent || '').trim().includes(expected));
    if (!target) return false;
    target.click();
    return true;
  }, text);
  assert(clicked, `Không tìm thấy ${selector} có nội dung "${text}".`);
}

async function clickVisible(selector, text = '') {
  const clicked = await page.$$eval(selector, (elements, expected) => {
    const target = elements.find((element) => {
      const rect = element.getBoundingClientRect();
      const style = getComputedStyle(element);
      const matchesText = !expected || (element.innerText || element.textContent || '').trim().includes(expected);
      return matchesText && rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none' && !element.disabled;
    });
    if (!target) return false;
    target.click();
    return true;
  }, text);
  assert(clicked, `Không tìm thấy phần tử visible/enabled: ${selector}${text ? ` (${text})` : ''}.`);
}

async function runAction(role, name, callback, options = {}) {
  if (onlyActions.size && !onlyActions.has(name)) return;
  activeAction = `${role}:${name}`;
  const failureStart = apiFailures.length;
  const invalidApiStart = invalidApiResponses.length;
  const mutationStart = mutations.length;
  const errorStart = pageErrors.length;
  const startedAt = Date.now();
  try {
    await callback();
    await sleep(500);
    const newFailures = apiFailures.slice(failureStart);
    const newInvalidApiResponses = invalidApiResponses.slice(invalidApiStart);
    const newMutations = mutations.slice(mutationStart);
    const newPageErrors = pageErrors.slice(errorStart);
    const unexpectedApi = newFailures.filter((row) => !options.allowApiFailure?.(row));
    const unexpectedMutation = newMutations.filter((row) => !options.allowMutation?.(row));
    assert(newPageErrors.length === 0, `JavaScript lỗi: ${newPageErrors.map((row) => row.message).join('; ')}`);
    assert(unexpectedApi.length === 0, `API lỗi ngoài dự kiến: ${JSON.stringify(unexpectedApi)}`);
    assert(newInvalidApiResponses.length === 0, `API không trả JSON: ${JSON.stringify(newInvalidApiResponses)}`);
    assert(unexpectedMutation.length === 0, `Có request ghi ngoài dự kiến: ${JSON.stringify(unexpectedMutation)}`);
    await page.screenshot({ path: path.join(artifactDir, `${role}-${name}.png`), fullPage: true });
    results.push({ role, name, status: 'pass', duration_ms: Date.now() - startedAt, api_failures: newFailures, mutations: newMutations });
  } catch (error) {
    await page.screenshot({ path: path.join(artifactDir, `failure-${role}-${name}.png`), fullPage: true }).catch(() => {});
    failures.push({ role, name, message: error.message, path: new URL(page.url()).pathname });
  }
}

try {
  await login('admin');

  await runAction('admin', 'moderation-tab-switch', async () => {
    await goto('/admin/reports-complaints');
    await clickText('button.tab-btn', 'Khiếu nại');
    await page.waitForFunction(() => new URLSearchParams(location.search).get('tab') === 'complaints', { timeout: 10_000 });
    assert(await page.$eval('button.tab-btn.active', (button) => button.innerText.includes('Khiếu nại')), 'Tab Khiếu nại không active.');
    await clickText('button.tab-btn', 'Báo cáo');
    await page.waitForFunction(() => new URLSearchParams(location.search).get('tab') === 'reports', { timeout: 10_000 });
  });

  await runAction('admin', 'partner-search-open-detail', async () => {
    await goto('/admin/partner-applications');
    const selector = 'input[type="search"]';
    await clearInput(selector);
    const searchResponsePromise = page.waitForResponse((response) => {
      if (!response.url().includes('/api/admin/partner-profiles?')) return false;
      return new URL(response.url()).searchParams.get('search') === 'Green';
    }, { timeout: 30_000 });
    await page.type(selector, 'Green');
    const searchResponse = await searchResponsePromise;
    assert(searchResponse.ok(), `Tìm hồ sơ trả HTTP ${searchResponse.status()}.`);
    await page.waitForFunction(() => {
      const area = document.querySelector('.content-area');
      return area && !area.innerText.includes('Đang tải hồ sơ');
    }, { timeout: 30_000 });
    await page.waitForFunction(() => [...document.querySelectorAll('.open-record-btn')].some((button) => {
      const rect = button.getBoundingClientRect();
      const style = getComputedStyle(button);
      return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none';
    }), { timeout: 10_000 });
    assert((await page.$$('.open-record-btn')).length > 0, 'Tìm Green không trả hồ sơ có thể mở.');
    await clickVisible('.open-record-btn');
    await page.waitForFunction(() => /\/admin\/partner-applications\/\d+/.test(location.pathname), { timeout: 15_000 });
    await page.waitForFunction(() => {
      const area = document.querySelector('.content-area');
      return area && !area.innerText.includes('Đang tải hồ sơ') && area.innerText.length > 100;
    }, { timeout: 20_000 });
    assert((await page.$eval('.content-area', (area) => area.innerText.length)) > 100, 'Chi tiết hồ sơ không render đủ.');
  });

  await runAction('admin', 'user-open-detail', async () => {
    await goto('/admin/users');
    await page.waitForSelector('a[title="Xem chi tiết"]', { visible: true, timeout: 15_000 });
    await clickVisible('a[title="Xem chi tiết"]');
    await page.waitForFunction(() => /\/admin\/users\/\d+/.test(location.pathname), { timeout: 15_000 });
  });

  await runAction('admin', 'banner-create-validation', async () => {
    await goto('/admin/banners');
    await clickVisible('.btn-float-add');
    await page.waitForSelector('.modal-backdrop form', { visible: true, timeout: 10_000 });
    const valid = await page.$eval('.modal-backdrop form', (form) => form.checkValidity());
    assert(!valid, 'Form banner rỗng lại hợp lệ.');
    await page.click('.modal-header button[title="Đóng"]');
    await page.waitForFunction(() => !document.querySelector('.modal-backdrop'), { timeout: 5_000 });
  });

  await runAction('admin', 'ui-settings-pending-schema', async () => {
    await goto('/admin/settings');
    await clickVisible('.settings-card-footer .btn.primary', 'Áp dụng cấu hình');
    await page.waitForSelector('.alert.error', { visible: true, timeout: 10_000 });
    const errorText = await page.$eval('.alert.error', (alert) => alert.innerText);
    assert(
      errorText.includes('chưa được khởi tạo') || errorText.includes('Chưa thể lưu giao diện'),
      `Thông báo 409 cấu hình giao diện không rõ ràng: ${errorText}`,
    );
    assert(!(await page.$('.alert.success')), 'Giao diện báo lưu thành công dù API trả 409.');
  }, {
    allowApiFailure: (row) => row.status === 409 && row.url.endsWith('/api/admin/ui-settings'),
    allowMutation: (row) => row.method === 'POST' && row.status === 409 && row.url.endsWith('/api/admin/ui-settings'),
  });

  await login('owner');

  await runAction('owner', 'ui-settings-pending-schema', async () => {
    await goto('/owner/settings');
    await page.waitForSelector('.settings-card-footer .btn.primary', { visible: true, timeout: 15_000 });
    await clickVisible('.settings-card-footer .btn.primary');
    await page.waitForSelector('.alert.error', { visible: true, timeout: 10_000 });
    const errorText = await page.$eval('.alert.error', (alert) => alert.innerText);
    assert(
      errorText.includes('chưa được khởi tạo') || errorText.includes('Chưa thể lưu giao diện'),
      `Thông báo 409 cấu hình giao diện chủ sân không rõ ràng: ${errorText}`,
    );
    assert(!(await page.$('.alert.success')), 'Giao diện chủ sân báo lưu thành công dù API trả 409.');
  }, {
    allowApiFailure: (row) => row.status === 409 && row.url.endsWith('/api/owner/ui-settings'),
    allowMutation: (row) => row.method === 'POST' && row.status === 409 && row.url.endsWith('/api/owner/ui-settings'),
  });

  await runAction('owner', 'venue-post-filter-validation', async () => {
    await goto('/owner/venue-posts');
    await clickText('button.tab-btn', 'Chờ duyệt');
    await sleep(700);
    assert(await page.$eval('button.tab-btn.active', (button) => button.innerText.includes('Chờ duyệt')), 'Bộ lọc Chờ duyệt không active.');
    await clickVisible('.btn-float-add');
    await page.waitForFunction(() => document.querySelector('#post_form_modal')?.open, { timeout: 10_000 });
    await clickVisible('#post_form_modal button', 'Đăng bài viết');
    await page.waitForSelector('#post_form_modal .error-msg', { visible: true, timeout: 5_000 });
    assert((await page.$$('#post_form_modal .error-msg')).length >= 3, 'Validation bài viết không hiển thị đủ lỗi bắt buộc.');
    await clickText('#post_form_modal button[type="button"]', 'Hủy bỏ');
  });

  await runAction('owner', 'staff-create-validation', async () => {
    await goto('/owner/staff');
    await clickVisible('.btn-float-add');
    await page.waitForSelector('.modal-backdrop form', { visible: true, timeout: 10_000 });
    assert(!await page.$eval('.modal-backdrop form', (form) => form.checkValidity()), 'Form nhân viên rỗng lại hợp lệ.');
    await clickText('.modal-backdrop button[type="button"]', 'Hủy');
  });

  await runAction('owner', 'voucher-create-validation', async () => {
    await goto('/owner/vouchers');
    await clickVisible('.btn-float-add');
    await page.waitForSelector('.modal-backdrop form', { visible: true, timeout: 10_000 });
    await clickVisible('.modal-backdrop button', 'Lưu');
    await page.waitForSelector('.modal-backdrop .field-error', { visible: true, timeout: 5_000 });
    assert((await page.$$('.modal-backdrop .field-error')).length >= 3, 'Validation voucher không hiển thị đủ lỗi bắt buộc.');
    await clickText('.modal-backdrop button[type="button"]', 'Hủy');
  });

  await runAction('owner', 'staff-shift-pending-schema', async () => {
    await goto('/owner/staff-shifts');
    await clickText('button.tab-btn', 'Cấu hình ca mẫu');
    await page.waitForSelector('.btn-float-add', { visible: true, timeout: 10_000 });
    await clickVisible('.btn-float-add');
    await page.waitForSelector('.modal-backdrop form', { visible: true, timeout: 10_000 });
    await page.$eval('.modal-backdrop input:not([type])', (input) => {
      input.value = 'Ca browser 0800';
      input.dispatchEvent(new Event('input', { bubbles: true }));
    });
    const timeInputs = await page.$$('.modal-backdrop input[type="time"]');
    assert(timeInputs.length === 2, 'Form ca mẫu thiếu trường giờ.');
    await page.$$eval('.modal-backdrop input[type="time"]', (inputs) => {
      ['08:00', '10:00'].forEach((value, index) => {
        inputs[index].value = value;
        inputs[index].dispatchEvent(new Event('input', { bubbles: true }));
      });
    });
    assert(await page.$eval('.modal-backdrop form', (form) => form.checkValidity()), 'Form ca mẫu đã điền vẫn không hợp lệ theo trình duyệt.');
    await clickVisible('.modal-backdrop button', 'Lưu');
    await page.waitForSelector('.alert.error', { visible: true, timeout: 10_000 });
    const errorText = await page.$eval('.alert.error', (alert) => alert.innerText);
    assert(errorText.includes('cơ sở dữ liệu'), `Thông báo 409 không rõ ràng: ${errorText}`);
  }, {
    allowApiFailure: (row) => row.status === 409 && row.url.endsWith('/api/owner/staff-shifts'),
    allowMutation: (row) => row.method === 'POST' && row.status === 409 && row.url.endsWith('/api/owner/staff-shifts'),
  });

  await runAction('owner', 'booking-settings-save-unchanged', async () => {
    await goto('/owner/booking-settings');
    await page.waitForSelector('.settings-form button[type="submit"]', { visible: true, timeout: 15_000 });
    await clickVisible('.settings-form button[type="submit"]');
    await page.waitForFunction(() => (
      document.querySelector('.alert.success')
      || document.querySelector('.alert.error')
      || document.querySelector('.validation-summary')
    ), { timeout: 15_000 });
    const outcome = await page.$eval('.content-area', (area) => ({
      success: area.querySelector('.alert.success')?.innerText || '',
      error: area.querySelector('.alert.error')?.innerText || '',
      validation: area.querySelector('.validation-summary')?.innerText || '',
    }));
    assert(Boolean(outcome.success), `Lưu cấu hình bị chặn: ${outcome.error || outcome.validation || 'không rõ lý do'}`);
  }, {
    allowMutation: (row) => ['POST', 'PUT', 'PATCH'].includes(row.method) && row.status < 400 && row.url.includes('/api/owner/booking-config'),
  });
} catch (error) {
  failures.push({ role: 'bootstrap', name: activeAction, message: error.message, path: new URL(page.url()).pathname });
} finally {
  await browser.close();
}

const report = {
  status: failures.length || pageErrors.length || invalidApiResponses.length ? 'fail' : 'pass',
  run_tag: runTag,
  passed: results.length,
  failures,
  page_errors: pageErrors,
  api_failures: apiFailures,
  invalid_api_responses: invalidApiResponses,
  mutations,
  artifact_dir: artifactDir,
};
await fs.writeFile(path.join(artifactDir, 'report.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
if (report.status !== 'pass') process.exitCode = 1;
