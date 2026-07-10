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
  openFinal: 'M\u1edf file v\u00e0 k\u00fd bi\u00ean b\u1ea3n',
  adminSignTitle: 'K\u00fd bi\u00ean b\u1ea3n ch\u1ea5m d\u1ee9t cu\u1ed1i',
  sendFinalOtp: 'G\u1eedi OTP k\u00fd bi\u00ean b\u1ea3n',
  signFinal: 'K\u00fd bi\u00ean b\u1ea3n',
  ownerAction: 'Ch\u1ee7 s\u00e2n k\u00fd bi\u00ean b\u1ea3n cu\u1ed1i',
  ownerOpen: 'Xem file v\u00e0 k\u00fd bi\u00ean b\u1ea3n',
  ownerSignTitle: 'K\u00fd bi\u00ean b\u1ea3n cu\u1ed1i',
  sendOtp: 'G\u1eedi OTP',
  transition: 'Trong th\u1eddi gian xem h\u1ed3 s\u01a1',
};
mkdirSync(screenshotDir, { recursive: true });

function logStep(message, data = null) {
  process.stdout.write(`${message}${data ? ` ${JSON.stringify(data)}` : ''}\n`);
}

function runTinker(code) {
  return execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], {
    cwd: root,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  });
}

function markerPayload(output, marker) {
  const line = output.split(/\r?\n/).find((item) => item.startsWith(marker));
  if (!line) throw new Error(`Missing ${marker} in tinker output: ${output}`);
  return JSON.parse(line.slice(marker.length));
}

function createFixture() {
  const code = [
    `$admin = \\App\\Models\\User::findOrFail(2);`,
    `$owner = \\App\\Models\\User::findOrFail(9);`,
    `$sourceCluster = \\App\\Models\\VenueCluster::findOrFail(1);`,
    `$sourceApplication = \\App\\Models\\PartnerApplication::findOrFail(1);`,
    `$sourceContract = \\App\\Models\\PartnerContract::findOrFail(1);`,
    `$sequence = ((int) \\App\\Models\\VenueCluster::max('id')) + 1;`,
    `$cluster = $sourceCluster->replicate();`,
    `$cluster->forceFill([`,
    `'owner_id' => $owner->id,`,
    `'name' => 'QA Final Signing Venue ' . $sequence,`,
    `'slug' => 'qa-final-signing-' . $sequence,`,
    `'status' => 'locked',`,
    `'status_reason' => 'QA browser test: cum san dang trong quy trinh cham dut hop tac.',`,
    `'locked_at' => now(),`,
    `'locked_by' => $admin->id,`,
    `])->save();`,
    `$application = $sourceApplication->replicate();`,
    `$application->forceFill([`,
    `'user_id' => $owner->id,`,
    `'venue_name' => $cluster->name,`,
    `'business_name' => 'QA Final Signing Business ' . $sequence,`,
    `'approved_venue_cluster_id' => $cluster->id,`,
    `'current_contract_id' => null,`,
    `'status' => 'completed',`,
    `'status_reason' => 'QA browser test fixture.',`,
    `])->save();`,
    `$contract = $sourceContract->replicate();`,
    `$contract->forceFill([`,
    `'contract_code' => 'CONTRACT_QA_FINAL_' . $sequence,`,
    `'partner_application_id' => $application->id,`,
    `'owner_id' => $owner->id,`,
    `'venue_cluster_id' => $cluster->id,`,
    `'contract_title' => 'QA Final Signing Contract ' . $sequence,`,
    `'status' => 'signed_active',`,
    `'generated_document_id' => null,`,
    `'generated_file_media_id' => null,`,
    `'signed_file_media_id' => null,`,
    `'final_file_media_id' => null,`,
    `'deleted_at' => null,`,
    `])->save();`,
    `$application->forceFill(['current_contract_id' => $contract->id])->save();`,
    `$termination = \\App\\Models\\PartnerTerminationRequest::create([`,
    `'termination_code' => 'TERM-QA-FINAL-' . $sequence,`,
    `'partner_contract_id' => $contract->id,`,
    `'partner_application_id' => $application->id,`,
    `'owner_id' => $owner->id,`,
    `'venue_cluster_id' => $cluster->id,`,
    `'termination_type' => 'unilateral_by_owner',`,
    `'requested_by' => $owner->id,`,
    `'requested_at' => now(),`,
    `'requested_effective_date' => now()->addDays(30)->toDateString(),`,
    `'reason' => 'QA browser test for final termination document signing.',`,
    `'status' => 'settlement_processing',`,
    `'approved_by' => $admin->id,`,
    `'approved_at' => now(),`,
    `]);`,
    `$termination = app(\\App\\Services\\Partner\\PartnerTerminationFlowService::class)`,
    `->markReadyForFinalDocument($termination, $admin, 'QA browser fixture ready for final document.');`,
    `echo 'QA_FIXTURE:' . json_encode([`,
    `'applicationId' => $application->id,`,
    `'clusterId' => $cluster->id,`,
    `'contractId' => $contract->id,`,
    `'terminationId' => $termination->id,`,
    `'venueName' => $cluster->name,`,
    `]);`,
  ].join(' ');

  return markerPayload(runTinker(code), 'QA_FIXTURE:');
}

function setKnownOtp(signingRequestId) {
  const code = [
    `$request = \\App\\Models\\DocumentSigningRequest::with('verificationCode')->findOrFail(${Number(signingRequestId)});`,
    `$request->verificationCode->forceFill([`,
    `'code' => \\Illuminate\\Support\\Facades\\Hash::make('${otp}'),`,
    `'attempt_count' => 0, 'is_used' => false, 'expires_at' => now()->addMinutes(10),`,
    `])->save();`,
  ].join(' ');
  runTinker(code);
}

function finalDatabaseState(terminationId) {
  const code = [
    `$termination = \\App\\Models\\PartnerTerminationRequest::findOrFail(${Number(terminationId)});`,
    `$row = $termination->documents()->with('generatedDocument.signatures')->whereIn('document_type', ['settlement_minutes', 'final_termination_file'])->latest()->firstOrFail();`,
    `echo 'QA_STATE:' . json_encode([`,
    `'status' => $termination->status,`,
    `'documentStatus' => $row->generatedDocument->status,`,
    `'signedSides' => $row->generatedDocument->signatures->where('status', 'signed')->pluck('signer_side')->sort()->values()->all(),`,
    `'contractStatus' => $termination->contract()->value('status'),`,
    `]);`,
  ].join(' ');
  return markerPayload(runTinker(code), 'QA_STATE:');
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
  const selector = isAdmin ? '#admin-login' : '#login';
  await page.waitForSelector(selector, { visible: true });
  await page.evaluate((loginSelector, loginValue) => {
    const loginInput = document.querySelector(loginSelector);
    const passwordInput = document.querySelector('input[type="password"]');
    loginInput.value = loginValue;
    loginInput.dispatchEvent(new Event('input', { bubbles: true }));
    passwordInput.value = '12345678';
    passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
  }, selector, email);
  const endpoint = isAdmin ? '/api/admin/auth/login' : '/api/auth/login';
  const responsePromise = page.waitForResponse((response) => response.url().includes(endpoint));
  await clickButton(page, labels.login);
  const response = await responsePromise;
  if (!response.ok()) throw new Error(`Login failed for ${email}: ${await response.text()}`);
}

async function drawSignature(page, selector) {
  const canvas = await page.waitForSelector(selector, { visible: true });
  const box = await canvas.boundingBox();
  if (!box) throw new Error(`Signature canvas ${selector} is not visible`);
  await page.mouse.move(box.x + 24, box.y + box.height * 0.65);
  await page.mouse.down();
  for (let index = 0; index < 12; index += 1) {
    await page.mouse.move(
      box.x + 24 + index * ((box.width - 48) / 11),
      box.y + box.height * (0.5 + Math.sin(index * 1.25) * 0.2),
      { steps: 2 },
    );
  }
  await page.mouse.up();
}

const fixture = createFixture();
logStep('PASS created isolated final-signing fixture', fixture);

const browser = await puppeteer.launch({
  executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});
const browserErrors = [];

try {
  const adminContext = await browser.createBrowserContext();
  const adminPage = await adminContext.newPage();
  await adminPage.setViewport({ width: 1440, height: 1000, deviceScaleFactor: 1 });
  adminPage.on('pageerror', (error) => browserErrors.push(`admin pageerror: ${error.message}`));
  adminPage.on('console', (message) => {
    if (message.type() === 'error') browserErrors.push(`admin console: ${message.text()}`);
  });

  await login(adminPage, 'admin@sportgo.vn');
  await adminPage.goto(`${baseUrl}/admin/partner-applications/${fixture.applicationId}?tab=settlement`, { waitUntil: 'domcontentloaded' });
  await waitForText(adminPage, labels.openFinal);
  const previewResponsePromise = adminPage.waitForResponse((response) => response.url().endsWith(`/partner-termination-requests/${fixture.terminationId}/final-document/preview`));
  await clickButton(adminPage, labels.openFinal);
  const previewResponse = await previewResponsePromise;
  if (!previewResponse.ok()) throw new Error(`Admin final preview failed: ${await previewResponse.text()}`);
  await waitForText(adminPage, labels.adminSignTitle);
  await waitForText(adminPage, fixture.venueName);
  const adminPreviewText = await adminPage.$eval('.document-preview-docx', (element) => element.innerText);
  if (adminPreviewText.includes('Ch\u01b0a cung c\u1ea5p') || adminPreviewText.includes('[Kh\u00f4ng/C\u00f3')) {
    throw new Error('Final DOCX still contains unresolved template placeholders.');
  }
  await adminPage.screenshot({ path: path.join(screenshotDir, 'termination-final-admin-sign-desktop.png'), fullPage: true });
  logStep('PASS admin sees final file and signing panel together');

  await drawSignature(adminPage, '.unilateral-sign-panel canvas');
  await adminPage.click('.unilateral-sign-panel input[type="checkbox"]');
  const adminOtpResponsePromise = adminPage.waitForResponse((response) => response.url().endsWith(`/partner-termination-requests/${fixture.terminationId}/final-document/sign/send-otp`));
  await clickButton(adminPage, labels.sendFinalOtp);
  const adminOtpResponse = await adminOtpResponsePromise;
  const adminOtpPayload = await adminOtpResponse.json();
  if (!adminOtpResponse.ok()) throw new Error(`Admin final OTP failed: ${JSON.stringify(adminOtpPayload)}`);
  setKnownOtp(adminOtpPayload.data.signing_request_id);
  await adminPage.type('.unilateral-sign-panel input[autocomplete="one-time-code"]', otp);

  const adminSignResponsePromise = adminPage.waitForResponse((response) => response.url().endsWith(`/partner-termination-requests/${fixture.terminationId}/final-document/sign`));
  await clickButton(adminPage, labels.signFinal);
  const adminSignResponse = await adminSignResponsePromise;
  if (!adminSignResponse.ok()) throw new Error(`Admin final sign failed: ${await adminSignResponse.text()}`);
  logStep('PASS admin signs final document with OTP');

  const ownerContext = await browser.createBrowserContext();
  const ownerPage = await ownerContext.newPage();
  await ownerPage.setViewport({ width: 1440, height: 1000, deviceScaleFactor: 1 });
  ownerPage.on('pageerror', (error) => browserErrors.push(`owner pageerror: ${error.message}`));
  ownerPage.on('console', (message) => {
    if (message.type() === 'error') browserErrors.push(`owner console: ${message.text()}`);
  });

  await login(ownerPage, 'owner@sportgo.vn');
  await ownerPage.goto(`${baseUrl}/owner/termination-requests/${fixture.terminationId}`, { waitUntil: 'domcontentloaded' });
  await waitForText(ownerPage, labels.ownerAction);
  await clickButton(ownerPage, labels.ownerOpen);
  await waitForText(ownerPage, 'SportGo \u0111\u00e3 k\u00fd, ch\u1ee7 s\u00e2n c\u1ea7n x\u00e1c nh\u1eadn');
  await waitForText(ownerPage, fixture.venueName);
  const ownerPreviewText = await ownerPage.$eval('.document-preview-docx', (element) => element.innerText);
  const signerNameOccurrences = ownerPreviewText.split('Admin V\u1eadn H\u00e0nh SportGo').length - 1;
  if (signerNameOccurrences > 2) {
    throw new Error(`SportGo signer name is duplicated ${signerNameOccurrences} times in signed DOCX.`);
  }
  await ownerPage.screenshot({ path: path.join(screenshotDir, 'termination-final-owner-sign-desktop.png'), fullPage: true });
  logStep('PASS owner is unlocked only after SportGo signature and sees file beside signing panel');

  await drawSignature(ownerPage, '.preview-signing-panel canvas');
  await ownerPage.click('.preview-signing-panel input[type="checkbox"]');
  const ownerOtpResponsePromise = ownerPage.waitForResponse((response) => response.url().endsWith(`/api/owner/termination-requests/${fixture.terminationId}/final-document/sign/send-otp`));
  await clickButton(ownerPage, labels.sendOtp);
  const ownerOtpResponse = await ownerOtpResponsePromise;
  const ownerOtpPayload = await ownerOtpResponse.json();
  if (!ownerOtpResponse.ok()) throw new Error(`Owner final OTP failed: ${JSON.stringify(ownerOtpPayload)}`);
  setKnownOtp(ownerOtpPayload.data.signing_request_id);
  await ownerPage.type('.preview-signing-panel input[autocomplete="one-time-code"]', otp);

  const ownerSignResponsePromise = ownerPage.waitForResponse((response) => response.url().endsWith(`/api/owner/termination-requests/${fixture.terminationId}/final-document/sign`));
  await clickButton(ownerPage, labels.ownerSignTitle);
  const ownerSignResponse = await ownerSignResponsePromise;
  const ownerSignPayload = await ownerSignResponse.json();
  if (!ownerSignResponse.ok()) throw new Error(`Owner final sign failed: ${JSON.stringify(ownerSignPayload)}`);
  await waitForText(ownerPage, labels.transition);
  logStep('PASS owner signs final document with OTP', { status: ownerSignPayload.data.status });

  await ownerPage.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
  await ownerPage.reload({ waitUntil: 'domcontentloaded' });
  await waitForText(ownerPage, labels.transition);
  const mobileMetrics = await ownerPage.evaluate(() => ({
    innerWidth: window.innerWidth,
    scrollWidth: document.documentElement.scrollWidth,
    bodyScrollWidth: document.body.scrollWidth,
  }));
  await ownerPage.screenshot({ path: path.join(screenshotDir, 'termination-final-owner-mobile.png'), fullPage: true });
  if (mobileMetrics.scrollWidth > mobileMetrics.innerWidth || mobileMetrics.bodyScrollWidth > mobileMetrics.innerWidth) {
    throw new Error(`Final signing mobile overflow: ${JSON.stringify(mobileMetrics)}`);
  }
  logStep('PASS final signing mobile layout', mobileMetrics);

  const state = finalDatabaseState(fixture.terminationId);
  if (state.documentStatus !== 'completed' || state.signedSides.join(',') !== 'owner,sportgo') {
    throw new Error(`Final document proof is incomplete: ${JSON.stringify(state)}`);
  }
  logStep('PASS database stores both final signatures and document completion', state);

  const relevantErrors = browserErrors.filter((message) => !message.includes('favicon'));
  if (relevantErrors.length) throw new Error(`Browser errors: ${relevantErrors.join(' | ')}`);
  logStep('PASS final signing browser flow has no console errors');

  await adminContext.close();
  await ownerContext.close();
} finally {
  await browser.close();
}

logStep('RESULT final termination signing flow passed', fixture);
