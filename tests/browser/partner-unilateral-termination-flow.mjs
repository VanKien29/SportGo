import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import puppeteer from 'puppeteer';

const root = path.resolve(import.meta.dirname, '..', '..');
const baseUrl = process.env.APP_URL || 'http://127.0.0.1:8000';
const screenshotDir = path.join(root, 'storage', 'app', 'testing', 'browser');
const password = process.env.TEST_PASSWORD || '12345678';
const otp = '123456';
mkdirSync(screenshotDir, { recursive: true });

function logStep(message, data = null) {
  process.stdout.write(`${message}${data ? ` ${JSON.stringify(data)}` : ''}\n`);
}

async function clickButton(page, text) {
  await page.waitForFunction((label) => [...document.querySelectorAll('button')]
    .some((button) => !button.disabled && button.textContent.trim().includes(label)), {}, text);
  await page.evaluate((label) => {
    const button = [...document.querySelectorAll('button')]
      .find((item) => !item.disabled && item.textContent.trim().includes(label));
    button.click();
  }, text);
}

async function waitForText(page, text) {
  await page.waitForFunction((value) => document.body.innerText.includes(value), {}, text);
}

async function login(page, email) {
  const isAdmin = email === 'admin@sportgo.vn';
  await page.goto(`${baseUrl}${isAdmin ? '/admin/login' : '/login'}`, { waitUntil: 'domcontentloaded' });
  const loginSelector = isAdmin ? '#admin-login' : '#login';
  await page.waitForSelector(loginSelector, { visible: true });
  await page.evaluate((selector, loginValue, passwordValue) => {
    const loginInput = document.querySelector(selector);
    const passwordInput = document.querySelector('input[type="password"]');
    loginInput.value = loginValue;
    loginInput.dispatchEvent(new Event('input', { bubbles: true }));
    passwordInput.value = passwordValue;
    passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
  }, loginSelector, email, password);
  const endpoint = isAdmin ? '/api/admin/auth/login' : '/api/auth/login';
  const response = page.waitForResponse((item) => item.url().includes(endpoint) && item.request().method() === 'POST');
  await clickButton(page, 'Đăng nhập');
  const result = await response;
  if (!result.ok()) throw new Error(`Login ${email} failed with HTTP ${result.status()} payload=${result.request().postData()}: ${await result.text()}`);
}

function setKnownOtp(signingRequestId) {
  const code = [
    `$request = \\App\\Models\\DocumentSigningRequest::with('verificationCode')->findOrFail(${Number(signingRequestId)});`,
    `$request->verificationCode->forceFill([`,
    `'code' => \\Illuminate\\Support\\Facades\\Hash::make('${otp}'),`,
    `'attempt_count' => 0, 'is_used' => false, 'expires_at' => now()->addMinutes(10),`,
    `])->save();`,
  ].join(' ');
  execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { cwd: root, stdio: 'pipe' });
}

async function drawSignature(page, selector) {
  const canvas = await page.waitForSelector(selector, { visible: true });
  const box = await canvas.boundingBox();
  if (!box) throw new Error(`Signature canvas ${selector} has no bounding box`);
  await page.mouse.move(box.x + 30, box.y + box.height * 0.65);
  await page.mouse.down();
  for (let index = 0; index < 12; index += 1) {
    const x = box.x + 30 + index * ((box.width - 60) / 11);
    const y = box.y + box.height * (0.5 + Math.sin(index * 1.3) * 0.2);
    await page.mouse.move(x, y, { steps: 2 });
  }
  await page.mouse.up();
}

async function closeDocumentModal(page) {
  await page.evaluate(() => {
    const backdrop = [...document.querySelectorAll('div')]
      .find((item) => item.className.includes('bg-gray-900/70') && item.className.includes('fixed'));
    backdrop?.click();
  });
  await page.waitForFunction(() => !document.body.innerText.includes('Thông tin chữ ký'));
}

async function fillInlineAction(page, buttonLabel, note, submitLabel) {
  await clickButton(page, buttonLabel);
  const form = await page.waitForSelector('.termination-inline-form', { visible: true });
  const textarea = await form.$('textarea');
  await textarea.type(note);
  await clickButton(page, submitLabel);
}

const browser = await puppeteer.launch({
  executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});

const browserErrors = [];
let terminationId = null;

try {
  const adminContext = await browser.createBrowserContext();
  const adminPage = await adminContext.newPage();
  await adminPage.setViewport({ width: 1440, height: 1000, deviceScaleFactor: 1 });
  adminPage.on('pageerror', (error) => browserErrors.push(`admin pageerror: ${error.message}`));
  adminPage.on('console', (message) => {
    if (message.type() === 'error') browserErrors.push(`admin console: ${message.text()}`);
  });

  await login(adminPage, 'admin@sportgo.vn');
  await adminPage.goto(`${baseUrl}/admin/partner-applications/1?tab=settlement`, { waitUntil: 'domcontentloaded' });
  await waitForText(adminPage, 'Quyết toán và chấm dứt');
  await clickButton(adminPage, 'Đơn phương chấm dứt');
  const modal = await adminPage.waitForSelector('.modal-panel', { visible: true });
  await modal.evaluate((form) => {
    const [reason, detail] = form.querySelectorAll('textarea');
    reason.value = 'Kiểm thử công văn chấm dứt qua Chrome 20260710';
    reason.dispatchEvent(new Event('input', { bubbles: true }));
    detail.value = 'Kiểm tra quy trình tạo file, ký OTP, khóa sân, xác nhận tiếp nhận, xem xét lại và thu hồi công văn.';
    detail.dispatchEvent(new Event('input', { bubbles: true }));
  });

  const createResponsePromise = adminPage.waitForResponse((response) => (
    response.url().endsWith('/api/admin/partner-profiles/1/terminate')
      && response.request().method() === 'POST'
  ));
  await clickButton(adminPage, 'Tạo bản xem trước');
  const createResponse = await createResponsePromise;
  const createPayload = await createResponse.json();
  if (!createResponse.ok()) throw new Error(`Create notice failed: ${JSON.stringify(createPayload)}`);
  terminationId = createPayload.data.id;
  await waitForText(adminPage, 'Ký và phát hành công văn');
  await waitForText(adminPage, 'Kiểm tra quy trình tạo file');
  await adminPage.screenshot({ path: path.join(screenshotDir, 'unilateral-admin-preview-desktop.png'), fullPage: true });
  logStep('PASS admin creates DOCX preview', { terminationId });

  await drawSignature(adminPage, '.unilateral-sign-panel canvas');
  await adminPage.click('.unilateral-sign-panel input[type="checkbox"]');
  const otpResponsePromise = adminPage.waitForResponse((response) => response.url().includes(`/partner-termination-requests/${terminationId}/unilateral-notice/sign/send-otp`));
  await clickButton(adminPage, 'Gửi OTP ký công văn');
  const otpResponse = await otpResponsePromise;
  const otpPayload = await otpResponse.json();
  if (!otpResponse.ok()) throw new Error(`Send OTP failed: ${JSON.stringify(otpPayload)}`);
  setKnownOtp(otpPayload.data.signing_request_id);
  await adminPage.type('.unilateral-otp-box input', otp);

  const issueResponsePromise = adminPage.waitForResponse((response) => (
    response.url().endsWith(`/partner-termination-requests/${terminationId}/unilateral-notice/sign`)
      && response.request().method() === 'POST'
  ));
  await clickButton(adminPage, 'Ký và gửi công văn');
  const issueResponse = await issueResponsePromise;
  const issuePayload = await issueResponse.json();
  if (!issueResponse.ok()) throw new Error(`Issue notice failed: ${JSON.stringify(issuePayload)}`);
  await waitForText(adminPage, 'Chờ chủ sân xác nhận đã nhận');
  logStep('PASS admin signs and issues notice', { status: issuePayload.data.status });

  const ownerContext = await browser.createBrowserContext();
  const ownerPage = await ownerContext.newPage();
  await ownerPage.setViewport({ width: 1440, height: 1000, deviceScaleFactor: 1 });
  ownerPage.on('pageerror', (error) => browserErrors.push(`owner pageerror: ${error.message}`));
  ownerPage.on('console', (message) => {
    if (message.type() === 'error') browserErrors.push(`owner console: ${message.text()}`);
  });

  await login(ownerPage, 'owner@sportgo.vn');
  await ownerPage.goto(`${baseUrl}/owner/termination-requests/${terminationId}`, { waitUntil: 'domcontentloaded' });
  await waitForText(ownerPage, 'Đọc và xác nhận đã nhận công văn');
  await ownerPage.screenshot({ path: path.join(screenshotDir, 'unilateral-owner-ack-desktop.png'), fullPage: true });
  await clickButton(ownerPage, 'Mở công văn');
  await waitForText(ownerPage, 'Thông tin chữ ký');
  await waitForText(ownerPage, 'Kiểm tra quy trình tạo file');
  await ownerPage.screenshot({ path: path.join(screenshotDir, 'unilateral-owner-document-preview.png'), fullPage: true });
  await closeDocumentModal(ownerPage);

  await ownerPage.click('.acknowledgement-band input[type="checkbox"]');
  const acknowledgeResponsePromise = ownerPage.waitForResponse((response) => response.url().includes(`/termination-requests/${terminationId}/unilateral-notice/acknowledge`));
  await clickButton(ownerPage, 'Xác nhận đã nhận');
  const acknowledgeResponse = await acknowledgeResponsePromise;
  const acknowledgePayload = await acknowledgeResponse.json();
  if (!acknowledgeResponse.ok()) throw new Error(`Acknowledge failed: ${JSON.stringify(acknowledgePayload)}`);
  await waitForText(ownerPage, 'Yêu cầu SportGo xem xét lại');
  logStep('PASS owner acknowledges notice', { status: acknowledgePayload.data.status });

  await clickButton(ownerPage, 'Yêu cầu SportGo xem xét lại');
  await ownerPage.type('.reconsideration-band textarea', 'Đề nghị SportGo kiểm tra lại căn cứ và thời điểm hiệu lực của công văn trước khi tiếp tục.');
  const reconsiderResponsePromise = ownerPage.waitForResponse((response) => response.url().endsWith(`/termination-requests/${terminationId}/unilateral-notice/reconsideration`));
  await clickButton(ownerPage, 'Gửi xem xét lại');
  const reconsiderResponse = await reconsiderResponsePromise;
  if (!reconsiderResponse.ok()) throw new Error(`Reconsideration failed: ${await reconsiderResponse.text()}`);
  await waitForText(ownerPage, 'SportGo đang xem xét phản hồi');
  logStep('PASS owner requests reconsideration');

  await adminPage.reload({ waitUntil: 'domcontentloaded' });
  await waitForText(adminPage, 'Chủ sân yêu cầu xem xét lại');
  const keepResponsePromise = adminPage.waitForResponse((response) => response.url().includes('/reconsideration/resolve'));
  await fillInlineAction(adminPage, 'Giữ nguyên sau xem xét', 'SportGo đã kiểm tra dữ liệu và giữ nguyên công văn để tiếp tục xử lý nghĩa vụ.', 'Gửi phản hồi');
  const keepResponse = await keepResponsePromise;
  if (!keepResponse.ok()) throw new Error(`Keep notice failed: ${await keepResponse.text()}`);
  logStep('PASS admin resolves reconsideration');

  await ownerPage.reload({ waitUntil: 'domcontentloaded' });
  await clickButton(ownerPage, 'Yêu cầu SportGo xem xét lại');
  await ownerPage.type('.reconsideration-band textarea', 'Đề nghị thu hồi công văn kiểm thử sau khi đã xác nhận toàn bộ luồng ký và tiếp nhận hoạt động đúng.');
  const reconsiderAgainPromise = ownerPage.waitForResponse((response) => response.url().endsWith(`/termination-requests/${terminationId}/unilateral-notice/reconsideration`));
  await clickButton(ownerPage, 'Gửi xem xét lại');
  const reconsiderAgain = await reconsiderAgainPromise;
  if (!reconsiderAgain.ok()) throw new Error(`Second reconsideration failed: ${await reconsiderAgain.text()}`);

  await adminPage.reload({ waitUntil: 'domcontentloaded' });
  await waitForText(adminPage, 'Đề nghị thu hồi công văn kiểm thử');
  const withdrawResponsePromise = adminPage.waitForResponse((response) => response.url().endsWith(`/partner-termination-requests/${terminationId}/unilateral-notice/withdraw`));
  await fillInlineAction(adminPage, 'Thu hồi công văn', 'Thu hồi công văn sau khi hoàn thành bài kiểm thử trình duyệt; giữ nguyên file và nhật ký ký.', 'Xác nhận thu hồi');
  const withdrawResponse = await withdrawResponsePromise;
  const withdrawPayload = await withdrawResponse.json();
  if (!withdrawResponse.ok()) throw new Error(`Withdraw failed: ${JSON.stringify(withdrawPayload)}`);
  await waitForText(adminPage, 'Không có hồ sơ chấm dứt đang xử lý');
  await adminPage.evaluate(() => {
    const button = [...document.querySelectorAll('.head-actions button')]
      .find((item) => item.textContent.includes('Lịch sử ('));
    button?.click();
  });
  await waitForText(adminPage, 'SportGo đã thu hồi công văn');
  logStep('PASS admin withdraws notice', { status: withdrawPayload.data.status });

  await ownerPage.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
  await ownerPage.reload({ waitUntil: 'domcontentloaded' });
  await waitForText(ownerPage, 'SportGo đã thu hồi công văn');
  const mobileMetrics = await ownerPage.evaluate(() => ({
    innerWidth: window.innerWidth,
    scrollWidth: document.documentElement.scrollWidth,
    bodyScrollWidth: document.body.scrollWidth,
  }));
  await ownerPage.screenshot({ path: path.join(screenshotDir, 'unilateral-owner-mobile.png'), fullPage: true });
  if (mobileMetrics.scrollWidth > mobileMetrics.innerWidth || mobileMetrics.bodyScrollWidth > mobileMetrics.innerWidth) {
    throw new Error(`Mobile horizontal overflow: ${JSON.stringify(mobileMetrics)}`);
  }
  logStep('PASS owner mobile layout has no horizontal overflow', mobileMetrics);

  await adminContext.close();
  await ownerContext.close();

  const relevantErrors = browserErrors.filter((message) => !message.includes('favicon'));
  if (relevantErrors.length) throw new Error(`Browser console/page errors: ${relevantErrors.join(' | ')}`);
  logStep('PASS no browser console/page errors');
} finally {
  await browser.close();
}

logStep('RESULT browser flow passed', { terminationId, screenshots: screenshotDir });
