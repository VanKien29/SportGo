import fs from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import puppeteer from 'puppeteer';

const baseUrl = process.env.APP_URL || 'http://127.0.0.1:8000';
const username = process.env.STAFF_TEST_USER || 'venuestaff';
const password = process.env.STAFF_TEST_PASSWORD || '12345678';
const onlyFlow = process.env.STAFF_E2E_ONLY || '';
const runTag = `STAFF_E2E_${new Date().toISOString().replace(/\D/g, '').slice(0, 14)}`;
const artifactDir = path.resolve('storage/app/test-artifacts/staff-e2e-actions', runTag);

await fs.mkdir(artifactDir, { recursive: true });

const browser = await puppeteer.launch({
  executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe',
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});
const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 960, deviceScaleFactor: 1 });

const results = [];
const pageErrors = [];
const consoleErrors = [];
const failedResponses = [];
let activeFlow = 'bootstrap';

page.on('pageerror', (error) => pageErrors.push({ flow: activeFlow, message: error.message }));
page.on('console', (message) => {
  if (message.type() === 'error') consoleErrors.push({ flow: activeFlow, message: message.text() });
});
page.on('response', (response) => {
  if (response.url().startsWith(`${baseUrl}/api/`) && response.status() >= 400) {
    failedResponses.push({
      flow: activeFlow,
      method: response.request().method(),
      status: response.status(),
      url: response.url(),
    });
  }
});

const sleep = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

async function settle() {
  await page.waitForNetworkIdle({ idleTime: 350, timeout: 3_000 }).catch(() => {});
  await sleep(400);
}

async function goto(url, selector) {
  await page.goto(`${baseUrl}${url}`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  if (selector) await page.waitForSelector(selector, { visible: true, timeout: 20_000 });
  await settle();
}

async function clickText(selector, text) {
  const clicked = await page.evaluate((targetSelector, targetText) => {
    const element = [...document.querySelectorAll(targetSelector)]
      .find((candidate) => candidate.textContent?.trim().includes(targetText));
    if (!element) return false;
    element.click();
    return true;
  }, selector, text);
  if (!clicked) throw new Error(`Không tìm thấy ${selector} có nội dung "${text}".`);
}

async function screenshot(name) {
  await page.screenshot({ path: path.join(artifactDir, `${name}.png`), fullPage: true });
}

async function acceptPolicyGate() {
  const gate = await page.$('.policy-backdrop');
  if (!gate) return false;
  await page.evaluate(() => {
    const list = document.querySelector('.policy-list');
    if (!list) return;
    list.scrollTop = list.scrollHeight;
    list.dispatchEvent(new Event('scroll', { bubbles: true }));
  });
  await page.waitForFunction(() => !document.querySelector('.agree-row input')?.disabled, { timeout: 8_000 });
  await page.click('.agree-row input');
  await page.click('.accept-btn');
  await page.waitForSelector('.policy-backdrop', { hidden: true, timeout: 15_000 });
  return true;
}

async function assertNoVisibleFailure() {
  const visibleFailure = await page.evaluate(() => {
    const visible = (element) => {
      const style = getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return rect.width > 0 && rect.height > 0 && style.display !== 'none' && style.visibility !== 'hidden';
    };
    return [...document.querySelectorAll('.alert.error, .error-state, .staff-alert-error, [role="alert"]')]
      .filter(visible)
      .map((element) => element.textContent?.trim())
      .filter(Boolean);
  });
  if (visibleFailure.length) throw new Error(`Màn hình hiển thị lỗi: ${visibleFailure.join(' | ')}`);
}

async function runFlow(name, action) {
  if (onlyFlow && name !== 'login-and-dashboard' && name !== onlyFlow) return;
  activeFlow = name;
  const startedAt = Date.now();
  const responseStart = failedResponses.length;
  const pageErrorStart = pageErrors.length;
  const consoleErrorStart = consoleErrors.length;
  try {
    const details = await action();
    await assertNoVisibleFailure();
    const responseFailures = failedResponses.slice(responseStart);
    const newPageErrors = pageErrors.slice(pageErrorStart);
    const newConsoleErrors = consoleErrors.slice(consoleErrorStart);
    if (responseFailures.length) throw new Error(`API lỗi: ${JSON.stringify(responseFailures)}`);
    if (newPageErrors.length) throw new Error(`JavaScript lỗi: ${JSON.stringify(newPageErrors)}`);
    if (newConsoleErrors.length) throw new Error(`Console lỗi: ${JSON.stringify(newConsoleErrors)}`);
    results.push({ name, status: 'pass', duration_ms: Date.now() - startedAt, details });
    console.log(`PASS ${name}`);
  } catch (error) {
    await screenshot(`failure-${name}`).catch(() => {});
    results.push({ name, status: 'fail', duration_ms: Date.now() - startedAt, error: error.message });
    console.error(`FAIL ${name}: ${error.message}`);
  }
}

await runFlow('login-and-dashboard', async () => {
  await goto('/login', '#login');
  await page.type('#login', username);
  await page.type('input[type="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForFunction(
    () => localStorage.getItem('auth_role_group') === 'staff' && Boolean(localStorage.getItem('auth_token')),
    { timeout: 20_000 },
  );
  await page.evaluate(() => localStorage.setItem('selected_cluster', '1'));
  await goto('/staff/dashboard', '.staff-dashboard-page');
  const policyAccepted = await acceptPolicyGate();
  await page.waitForFunction(() => !document.querySelector('.loading-skeleton-layout'), { timeout: 20_000 });
  await screenshot('dashboard');
  return { role: 'staff', dashboard: true, policy_accepted: policyAccepted };
});

await runFlow('schedule-week-day-navigation', async () => {
  await goto('/staff/schedules', '.staff-schedules-page');
  await page.waitForFunction(() => !document.body.innerText.includes('Đang tải lịch trực'), { timeout: 20_000 });
  const initialWeek = await page.$eval('.staff-week-label', (element) => element.textContent.trim());
  await page.click('button[title="Tuần sau"]');
  await sleep(300);
  const shiftedWeek = await page.$eval('.staff-week-label', (element) => element.textContent.trim());
  if (initialWeek === shiftedWeek) throw new Error('Nút tuần sau không đổi khoảng ngày.');
  await clickText('.staff-view-switcher button', 'Xem theo ngày');
  await page.waitForSelector('.staff-day-view-container', { visible: true, timeout: 8_000 });
  const initialDay = await page.$eval('.staff-week-label', (element) => element.textContent.trim());
  await page.click('button[title="Ngày sau"]');
  await sleep(300);
  const shiftedDay = await page.$eval('.staff-week-label', (element) => element.textContent.trim());
  if (initialDay === shiftedDay) throw new Error('Nút ngày sau không đổi ngày.');
  await screenshot('schedules-day');
  return { week_navigation: true, day_navigation: true };
});

await runFlow('booking-filters-and-timeline', async () => {
  await goto('/staff/bookings', '.bookings-page');
  await page.waitForFunction(() => !document.body.innerText.includes('Đang tải lịch sân'), { timeout: 20_000 });
  const periodButtons = await page.$$('.period-row button');
  if (periodButtons.length < 2) throw new Error('Không đủ bộ lọc buổi trong ngày.');
  await periodButtons[1].click();
  const activePeriod = await page.$eval('.period-row button.active', (element) => element.textContent.trim());
  const statusTabs = await page.$$('.filter-tabs .tab-btn');
  if (statusTabs.length > 1) {
    await statusTabs[1].click();
    await settle();
  }
  await screenshot('bookings-filtered');
  return { active_period: activePeriod, status_filter_clicked: statusTabs.length > 1 };
});

await runFlow('counter-booking-load-and-tabs', async () => {
  await goto('/staff/counter-booking', '.owner-counter-page');
  await page.waitForFunction(() => !document.body.innerText.includes('Đang tải'), { timeout: 20_000 }).catch(() => {});
  await clickText('.tabs-and-actions button', 'Booking tại quầy');
  await clickText('.tabs-and-actions button', 'Danh sách booking');
  await settle();
  await screenshot('counter-booking');
  return { tabs_interactive: true };
});

await runFlow('settings-apply', async () => {
  await goto('/staff/settings', '.settings-container');
  const choices = await page.$$('.radius-selector-group .radius-btn');
  if (!choices.length) throw new Error('Không có tùy chọn giao diện.');
  await choices[0].click();
  const updateResponse = page.waitForResponse(
    (response) => response.url().includes('/api/owner/ui-settings') && ['PUT', 'PATCH', 'POST'].includes(response.request().method()),
    { timeout: 12_000 },
  );
  await clickText('.settings-card-footer button', 'Áp dụng cấu hình');
  const response = await updateResponse;
  await settle();
  const successVisible = await page.$eval('.alert.success', (element) => element.textContent.trim()).catch(() => '');
  const errorVisible = await page.$eval('.alert.error', (element) => element.textContent.trim()).catch(() => '');
  if (!response.ok()) {
    if (successVisible) throw new Error(`Lưu giao diện trả HTTP ${response.status()} nhưng UI vẫn báo thành công: ${successVisible}`);
    if (!errorVisible) throw new Error(`Lưu giao diện trả HTTP ${response.status()} nhưng UI không hiển thị lỗi.`);
    throw new Error(`Lưu giao diện bị chặn HTTP ${response.status()} và UI đã báo đúng: ${errorVisible}`);
  }
  if (!successVisible) throw new Error('Lưu thành công nhưng không có phản hồi cho người dùng.');
  await screenshot('settings-saved');
  return { saved: true };
});

await runFlow('chat-select-and-send', async () => {
  await goto('/staff/chat');
  const chatPath = new URL(page.url()).pathname;
  if (chatPath !== '/staff/chat') throw new Error(`Điều hướng chat bị chuyển sang ${chatPath}.`);
  try {
    await page.waitForFunction(
      () => Boolean(document.querySelector('.chat-page')) || location.pathname !== '/staff/chat',
      { timeout: 15_000 },
    );
  } catch {
    const diagnostics = await page.evaluate(() => ({
      path: location.pathname,
      heading: document.querySelector('h1, h2')?.textContent?.trim() || '',
      resources: performance.getEntriesByType('resource').slice(-12).map((entry) => entry.name),
    }));
    throw new Error(`Chat không mount sau 15 giây: ${JSON.stringify(diagnostics)}.`);
  }
  const chatState = await page.evaluate(() => ({
    path: location.pathname,
    hasChat: Boolean(document.querySelector('.chat-page')),
    hasToken: Boolean(localStorage.getItem('auth_token')),
    roleGroup: localStorage.getItem('auth_role_group'),
  }));
  if (!chatState.hasChat) throw new Error(`Chat không mount: ${JSON.stringify(chatState)}.`);
  await page.waitForFunction(() => !document.body.innerText.includes('Đang tải hộp thư'), { timeout: 20_000 });
  const conversations = await page.$$('.tg-conv-item');
  if (!conversations.length) return { state: 'no-conversation' };
  await conversations[0].click();
  await page.waitForSelector('input[placeholder="Nhập tin nhắn..."]', { visible: true, timeout: 15_000 });
  const message = `${runTag} Tin nhắn kiểm thử nhân viên sân`;
  await page.type('input[placeholder="Nhập tin nhắn..."]', message);
  const sendHitTarget = await page.$eval('.zalo-chat-box button[type="submit"]', (button) => {
    const rect = button.getBoundingClientRect();
    const hit = document.elementFromPoint(rect.left + rect.width / 2, rect.top + rect.height / 2);
    return {
      buttonDisabled: button.disabled,
      rect: { left: rect.left, top: rect.top, width: rect.width, height: rect.height },
      hitTag: hit?.tagName || '',
      hitClass: hit?.getAttribute?.('class') || '',
      hitInsideButton: Boolean(hit && button.contains(hit)),
    };
  });
  await screenshot('chat-ready');
  if (sendHitTarget.buttonDisabled || !sendHitTarget.hitInsideButton) {
    throw new Error(`Nút gửi bị che hoặc bị khóa: ${JSON.stringify(sendHitTarget)}.`);
  }
  const requestPromise = page.waitForRequest(
    (request) => request.url().includes('/api/chat/conversations/')
      && request.url().endsWith('/messages')
      && request.method() === 'POST',
    { timeout: 8_000 },
  );
  const responsePromise = page.waitForResponse(
    (response) => response.url().includes('/api/chat/conversations/')
      && response.url().endsWith('/messages')
      && response.request().method() === 'POST',
    { timeout: 15_000 },
  );
  await page.click('.zalo-chat-box button[type="submit"]');
  let request;
  try {
    request = await requestPromise;
  } catch {
    const diagnostics = await page.evaluate(() => ({
      path: location.pathname,
      hasChat: Boolean(document.querySelector('.chat-page')),
      inputValue: document.querySelector('input[placeholder="Nhập tin nhắn..."]')?.value || '',
      submitDisabled: document.querySelector('.zalo-chat-box button[type="submit"]')?.disabled ?? null,
      bodyHeading: document.querySelector('h1, h2')?.textContent?.trim() || '',
    }));
    throw new Error(`Nút gửi không phát sinh request: ${JSON.stringify(diagnostics)}.`);
  }
  const response = await responsePromise;
  if (!response.ok()) throw new Error(`Gửi tin nhắn trả HTTP ${response.status()}.`);
  await page.waitForFunction((text) => document.body.innerText.includes(text), { timeout: 15_000 }, message);
  await screenshot('chat-message');
  return { sent: true, message };
});

await runFlow('profile-content', async () => {
  await goto('/staff/profile', '.profile-wrapper .pcard');
  const profile = await page.evaluate(() => ({
    name: document.querySelector('.hero-name')?.textContent?.trim() || '',
    role: document.querySelector('.role-badge')?.textContent?.trim() || '',
    username: document.querySelector('.info-value')?.textContent?.trim() || '',
  }));
  if (!profile.name || !profile.username || !/nhân viên/i.test(profile.role)) {
    throw new Error(`Thông tin hồ sơ nhân viên không đầy đủ: ${JSON.stringify(profile)}`);
  }
  await screenshot('profile');
  return profile;
});

await browser.close();

const failures = results.filter((result) => result.status === 'fail');
const report = {
  status: failures.length === 0 ? 'pass' : 'fail',
  run_tag: runTag,
  results,
  page_errors: pageErrors,
  console_errors: consoleErrors,
  failed_responses: failedResponses,
  artifact_dir: artifactDir,
};

await fs.writeFile(path.join(artifactDir, 'report.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
if (report.status !== 'pass') process.exitCode = 1;
