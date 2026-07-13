import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import puppeteer from 'puppeteer';

const root = path.resolve(import.meta.dirname, '..', '..');
const baseUrl = process.env.APP_URL || 'http://127.0.0.1:8000';
const screenshotDir = path.join(root, 'storage', 'app', 'testing', 'browser');
const otp = '123456';
const labels = {
  login: '\u0110\u0103ng nh\u1eadp',
  formTitle: 'Th\u00f4ng tin y\u00eau c\u1ea7u',
  preview: 'Xem tr\u01b0\u1edbc \u0111\u01a1n',
  signTitle: 'K\u00fd \u0111\u01a1n y\u00eau c\u1ea7u',
  sendOtp: 'G\u1eedi OTP',
  signAndSubmit: 'K\u00fd v\u00e0 g\u1eedi y\u00eau c\u1ea7u',
  waitingAdmin: 'Ch\u1edd admin x\u00e1c nh\u1eadn',
  sendCancelOtp: 'G\u1eedi OTP h\u1ee7y y\u00eau c\u1ea7u',
  confirmCancel: 'X\u00e1c nh\u1eadn h\u1ee7y y\u00eau c\u1ea7u',
  cancelled: '\u0110\u00e3 h\u1ee7y y\u00eau c\u1ea7u',
};
mkdirSync(screenshotDir, { recursive: true });

function logStep(message, data = null) {
  process.stdout.write(`${message}${data ? ` ${JSON.stringify(data)}` : ''}\n`);
}

async function waitForText(page, value) {
  await page.waitForFunction((text) => document.body.innerText.includes(text), {}, value);
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

async function loginOwner(page) {
  await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#login', { visible: true });
  await page.evaluate(() => {
    const login = document.querySelector('#login');
    const password = document.querySelector('input[type="password"]');
    login.value = 'owner@sportgo.vn';
    login.dispatchEvent(new Event('input', { bubbles: true }));
    password.value = '12345678';
    password.dispatchEvent(new Event('input', { bubbles: true }));
  });
  const responsePromise = page.waitForResponse((response) => (
    response.url().includes('/api/auth/login') && response.request().method() === 'POST'
  ));
  await clickButton(page, labels.login);
  const response = await responsePromise;
  if (!response.ok()) throw new Error(`Owner login failed: ${await response.text()}`);
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
  if (!box) throw new Error(`Canvas ${selector} is not visible`);
  await page.mouse.move(box.x + 25, box.y + box.height * 0.65);
  await page.mouse.down();
  for (let index = 0; index < 12; index += 1) {
    await page.mouse.move(
      box.x + 25 + index * ((box.width - 50) / 11),
      box.y + box.height * (0.5 + Math.sin(index * 1.2) * 0.2),
      { steps: 2 },
    );
  }
  await page.mouse.up();
}

const browser = await puppeteer.launch({
  executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});

let terminationId = null;
const browserErrors = [];

try {
  const context = await browser.createBrowserContext();
  const page = await context.newPage();
  await page.setViewport({ width: 1440, height: 1000, deviceScaleFactor: 1 });
  page.on('pageerror', (error) => browserErrors.push(`pageerror: ${error.message}`));
  page.on('console', (message) => {
    if (message.type() === 'error') browserErrors.push(`console: ${message.text()}`);
  });

  await loginOwner(page);
  await page.goto(`${baseUrl}/owner/venue-clusters/1/termination`, { waitUntil: 'domcontentloaded' });
  await waitForText(page, labels.formTitle);
  await page.evaluate(() => {
    const textareas = document.querySelectorAll('.form-panel textarea');
    textareas[0].value = 'Owner Chrome test termination request 20260710';
    textareas[0].dispatchEvent(new Event('input', { bubbles: true }));
    textareas[1].value = 'Verify DOCX data, OTP signature, venue lock, cancellation and retained audit history.';
    textareas[1].dispatchEvent(new Event('input', { bubbles: true }));
    const policy = document.querySelector('.form-panel select');
    policy.value = 'manual_per_booking';
    policy.dispatchEvent(new Event('change', { bubbles: true }));
    document.querySelector('.form-panel input[type="checkbox"]').click();
  });

  const previewResponsePromise = page.waitForResponse((response) => (
    response.url().endsWith('/api/owner/venue-clusters/1/termination/preview')
  ));
  await clickButton(page, labels.preview);
  const previewResponse = await previewResponsePromise;
  const previewPayload = await previewResponse.json();
  if (!previewResponse.ok()) throw new Error(`Owner preview failed: ${JSON.stringify(previewPayload)}`);
  terminationId = previewPayload.data.id;
  await waitForText(page, labels.signTitle);
  await waitForText(page, 'Verify DOCX data, OTP signature');
  await page.screenshot({ path: path.join(screenshotDir, 'owner-termination-preview-desktop.png'), fullPage: true });
  logStep('PASS owner creates and sees DOCX preview', { terminationId });

  await drawSignature(page, '.preview-signing-panel canvas');
  await page.click('.preview-signing-panel input[type="checkbox"]');
  const sendOtpResponsePromise = page.waitForResponse((response) => (
    response.url().endsWith('/api/owner/venue-clusters/1/termination/send-otp')
  ));
  await clickButton(page, labels.sendOtp);
  const sendOtpResponse = await sendOtpResponsePromise;
  const sendOtpPayload = await sendOtpResponse.json();
  if (!sendOtpResponse.ok()) throw new Error(`Owner send OTP failed: ${JSON.stringify(sendOtpPayload)}`);
  setKnownOtp(sendOtpPayload.data.signing_request_id);
  await page.type('.preview-signing-panel input[autocomplete="one-time-code"]', otp);

  const submitResponsePromise = page.waitForResponse((response) => (
    response.url().endsWith('/api/owner/venue-clusters/1/termination/submit')
  ));
  await clickButton(page, labels.signAndSubmit);
  const submitResponse = await submitResponsePromise;
  const submitPayload = await submitResponse.json();
  if (!submitResponse.ok()) throw new Error(`Owner submit failed: ${JSON.stringify(submitPayload)}`);
  await waitForText(page, labels.waitingAdmin);
  logStep('PASS owner signs/submits and cluster is locked', { status: submitPayload.data.status });

  await page.click('details.cancel-panel summary');
  await page.type('.cancel-panel textarea', 'Cancel the browser test request after validating the signed submission workflow.');
  await drawSignature(page, '.cancel-panel canvas');
  await page.click('.cancel-panel input[type="checkbox"]');
  const cancelOtpResponsePromise = page.waitForResponse((response) => (
    response.url().endsWith(`/api/owner/termination-requests/${terminationId}/cancel/send-otp`)
  ));
  await clickButton(page, labels.sendCancelOtp);
  const cancelOtpResponse = await cancelOtpResponsePromise;
  const cancelOtpPayload = await cancelOtpResponse.json();
  if (!cancelOtpResponse.ok()) throw new Error(`Cancel OTP failed: ${JSON.stringify(cancelOtpPayload)}`);
  setKnownOtp(cancelOtpPayload.data.signing_request_id);
  await page.type('.cancel-panel .otp-box input', otp);

  const cancelResponsePromise = page.waitForResponse((response) => (
    response.url().endsWith(`/api/owner/termination-requests/${terminationId}/cancel`)
  ));
  await clickButton(page, labels.confirmCancel);
  const cancelResponse = await cancelResponsePromise;
  const cancelPayload = await cancelResponse.json();
  if (!cancelResponse.ok()) throw new Error(`Cancel request failed: ${JSON.stringify(cancelPayload)}`);
  await waitForText(page, labels.cancelled);
  logStep('PASS owner cancels signed request with OTP', { status: cancelPayload.data.status });

  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
  await page.reload({ waitUntil: 'domcontentloaded' });
  await waitForText(page, labels.cancelled);
  const mobileMetrics = await page.evaluate(() => ({
    innerWidth: window.innerWidth,
    scrollWidth: document.documentElement.scrollWidth,
    bodyScrollWidth: document.body.scrollWidth,
  }));
  await page.screenshot({ path: path.join(screenshotDir, 'owner-termination-cancelled-mobile.png'), fullPage: true });
  if (mobileMetrics.scrollWidth > mobileMetrics.innerWidth || mobileMetrics.bodyScrollWidth > mobileMetrics.innerWidth) {
    throw new Error(`Owner cancellation mobile overflow: ${JSON.stringify(mobileMetrics)}`);
  }
  logStep('PASS owner cancellation mobile layout', mobileMetrics);

  const relevantErrors = browserErrors.filter((message) => !message.includes('favicon'));
  if (relevantErrors.length) throw new Error(`Browser errors: ${relevantErrors.join(' | ')}`);
  logStep('PASS owner request/cancel browser flow has no console errors');
  await context.close();
} finally {
  await browser.close();
}

logStep('RESULT owner request/cancel flow passed', { terminationId, screenshots: screenshotDir });
