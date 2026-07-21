import fs from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import puppeteer from 'puppeteer';

const baseUrl = process.env.APP_URL || 'http://127.0.0.1:8000';
const username = process.env.CLIENT_TEST_USER || 'user';
const password = process.env.CLIENT_TEST_PASSWORD || '12345678';
const venueId = process.env.CLIENT_TEST_VENUE_ID || '1';
const communitySlug = process.env.CLIENT_TEST_COMMUNITY_SLUG || 'green-sport-ba-dinh-dat-san-gio-cao-diem';
const onlyFlow = process.env.CLIENT_E2E_ONLY || '';
const runTag = `BROWSER_E2E_${new Date().toISOString().replace(/\D/g, '').slice(0, 14)}`;
const artifactDir = path.resolve('storage/app/test-artifacts/client-e2e-actions', runTag);

await fs.mkdir(artifactDir, { recursive: true });

const browser = await puppeteer.launch({
  executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe',
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});

const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 960, deviceScaleFactor: 1 });

const results = [];
const consoleErrors = [];
const pageErrors = [];
const failedResponses = [];
let activeFlow = 'bootstrap';
let createdBookingId = '';
const createdBookingIds = [];
const createdCommentIds = [];
const createdMessageIds = [];

page.on('console', (message) => {
  if (message.type() === 'error') consoleErrors.push({ flow: activeFlow, message: message.text() });
});
page.on('pageerror', (error) => pageErrors.push({ flow: activeFlow, message: error.message }));
page.on('response', (response) => {
  if (response.url().startsWith(`${baseUrl}/api/`) && response.status() >= 400) {
    failedResponses.push({ flow: activeFlow, status: response.status(), url: response.url() });
  }
});

const sleep = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

async function settle() {
  await page.waitForNetworkIdle({ idleTime: 300, timeout: 3_000 }).catch(() => {});
  await sleep(300);
}

async function goto(url, selector) {
  await page.goto(`${baseUrl}${url}`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  if (selector) await page.waitForSelector(selector, { visible: true, timeout: 20_000 });
  await settle();
}

async function waitForText(text, selector = 'body', timeout = 15_000) {
  await page.waitForFunction(
    (targetSelector, targetText) => document.querySelector(targetSelector)?.innerText?.includes(targetText),
    { timeout },
    selector,
    text,
  );
}

async function waitForSuccessToast(timeout = 8_000) {
  await page.waitForSelector('.Vue-Toastification__toast--success', { visible: true, timeout });
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

async function runFlow(name, action) {
  if (onlyFlow && name !== 'login-validation-and-success' && name !== onlyFlow) return;
  activeFlow = name;
  const startedAt = Date.now();
  try {
    const details = await action();
    results.push({ name, status: 'pass', duration_ms: Date.now() - startedAt, details });
    console.log(`PASS ${name}`);
  } catch (error) {
    await screenshot(`failure-${name}`).catch(() => {});
    results.push({ name, status: 'fail', duration_ms: Date.now() - startedAt, error: error.message });
    console.error(`FAIL ${name}: ${error.message}`);
  }
}

async function login() {
  await goto('/login', '#login');
  await page.type('#login', username);
  await page.type('input[type="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForFunction(() => window.location.pathname !== '/login', { timeout: 20_000 });
  await settle();

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
  }
}

async function selectBookableRange() {
  const futureDate = new Date();
  futureDate.setDate(futureDate.getDate() + 2);
  const bookingDate = futureDate.toISOString().slice(0, 10);

  await page.$eval('input[type="date"]', (input, value) => {
    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }, bookingDate);
  await page.waitForFunction(() => !document.querySelector('.schedule-skeleton'), { timeout: 20_000 });
  await settle();

  const periodCount = await page.$$eval('.period-tabs button', (buttons) => buttons.length);
  for (let periodIndex = 0; periodIndex < periodCount; periodIndex += 1) {
    if (periodIndex > 0) {
      await page.evaluate((index) => document.querySelectorAll('.period-tabs button')[index]?.click(), periodIndex);
      await sleep(250);
    }

    const slotIndexes = await page.evaluate(() => {
      const slotCount = document.querySelectorAll('.time-heading').length;
      const slots = [...document.querySelectorAll('.schedule-board .slot')];
      if (!slotCount) return [];

      for (let rowStart = 0; rowStart < slots.length; rowStart += slotCount) {
        const row = slots.slice(rowStart, rowStart + slotCount);
        let run = [];
        for (let index = 0; index < row.length; index += 1) {
          if (!row[index].disabled) {
            run.push(rowStart + index);
            if (run.length >= 4) return run;
          } else {
            run = [];
          }
        }
        if (run.length >= 2) return run;
      }
      return [];
    });

    if (!slotIndexes.length) continue;
    for (const slotIndex of slotIndexes) {
      await page.evaluate(
        (index) => document.querySelectorAll('.schedule-board .slot')[index]?.click(),
        slotIndex,
      );
      await sleep(450);
      const enabled = await page.$eval('.booking-summary footer > button', (button) => !button.disabled);
      if (enabled) return bookingDate;
    }
  }

  throw new Error('Không tìm được khoảng giờ liên tục đủ điều kiện để tạo booking.');
}

await runFlow('guest-register-and-forgot-validation', async () => {
  await goto('/register', 'form');
  await page.click('button[type="submit"]');
  await waitForText('Vui lòng nhập tên đăng nhập');

  await goto('/forgot-password', 'form');
  await page.click('button[type="submit"]');
  await waitForText('Vui lòng nhập email, số điện thoại hoặc tên đăng nhập.');
  return { register: 'client-validation-visible', forgot_password: 'client-validation-visible' };
});

await runFlow('login-validation-and-success', async () => {
  await goto('/login', '#login');
  await page.click('button[type="submit"]');
  await waitForText('Vui lòng nhập email, số điện thoại hoặc tên đăng nhập.');
  await login();
  const auth = await page.evaluate(() => ({
    hasToken: Boolean(localStorage.getItem('auth_token')),
    role: localStorage.getItem('auth_role_group'),
  }));
  if (!auth.hasToken || auth.role !== 'user') throw new Error(`Phiên đăng nhập không hợp lệ: ${JSON.stringify(auth)}`);
  return auth;
});

await runFlow('home-search-and-featured-content', async () => {
  await goto('/', '.home-page');
  await page.waitForFunction(() => !document.body.innerText.includes('Đang tải cụm sân'), { timeout: 20_000 });
  if (!(await page.$('.venue-card'))) throw new Error('Trang chủ không hiển thị cụm sân nổi bật từ API.');
  await page.type('.search-panel input[type="text"]', 'Ba Đình');
  await page.$eval('.search-panel', (form) => form.requestSubmit());
  await page.waitForFunction(() => window.location.pathname === '/venues', { timeout: 10_000 });
  const url = new URL(page.url());
  if (url.searchParams.get('area') !== 'Ba Đình') throw new Error(`Bộ lọc khu vực bị mất khi điều hướng: ${url.search}`);
  return { featured_venue: true, destination: `${url.pathname}${url.search}` };
});

await runFlow('news-list-search-and-detail', async () => {
  await goto('/news', '.sg-news-page');
  await page.waitForFunction(() => !document.body.innerText.includes('Đang tải tin tức'), { timeout: 20_000 });
  const cards = await page.$$('.sg-news-card');
  if (!cards.length) throw new Error('Danh sách tin tức không có dữ liệu để kiểm tra chi tiết.');
  await page.type('#news-search', 'SportGo');
  await page.keyboard.press('Enter');
  await settle();
  const filteredCards = await page.$$('.sg-news-card');
  if (!filteredCards.length) {
    await page.$eval('#news-search', (input) => {
      input.value = '';
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.closest('form')?.requestSubmit();
    });
    await settle();
  }
  const firstCardAction = await page.$('.sg-news-card button');
  if (!firstCardAction) throw new Error('Không khôi phục được danh sách tin tức sau khi xóa từ khóa.');
  await firstCardAction.click();
  await page.waitForFunction(() => /^\/news\/.+/.test(window.location.pathname), { timeout: 10_000 });
  await page.waitForSelector('.sg-news-article', { visible: true, timeout: 15_000 });
  return { detail_path: new URL(page.url()).pathname };
});

await runFlow('profile-and-vip-purchase-preview', async () => {
  await goto('/profile', '.profile-wrapper');
  const vipLink = await page.$('.btn-vip');
  if (!vipLink) throw new Error('Hồ sơ người dùng không có lối vào màn gói VIP.');
  await vipLink.click();
  await page.waitForFunction(() => window.location.pathname === '/vip-membership', { timeout: 10_000 });
  await page.waitForSelector('.vip-page', { visible: true, timeout: 15_000 });
  await page.waitForFunction(() => !document.body.innerText.includes('Đang tải gói VIP'), { timeout: 20_000 });
  if (!(await page.$('.plan-card'))) throw new Error('Màn VIP không hiển thị gói từ API.');
  const enabledCycle = await page.$('.cycle-list button:not([disabled])');
  if (!enabledCycle) return { packages_loaded: true, state: 'active-subscription-prevents-extra-purchase' };
  await enabledCycle.click();
  await page.waitForSelector('.confirm-modal', { visible: true, timeout: 8_000 });
  await page.click('.confirm-modal .ghost-btn');
  await page.waitForSelector('.confirm-modal', { hidden: true, timeout: 8_000 });
  return { packages_loaded: true, purchase_preview: true, purchase_not_submitted: true };
});

await runFlow('venue-search-map-and-detail', async () => {
  await goto('/venues', '.venue-market-page');
  await page.waitForFunction(() => !document.body.innerText.includes('Đang tải danh sách sân'), { timeout: 20_000 });
  await page.click('.booking-datetime__trigger');
  await clickText('.booking-datetime__quick button', 'Ngày mai');
  await clickText('.booking-datetime__footer button', 'Áp dụng');
  const search = await page.$('input[type="search"], input[placeholder*="Tìm"]');
  if (!search) throw new Error('Không tìm thấy ô tìm kiếm sân.');
  await search.type('Green');
  await page.keyboard.press('Enter');
  await settle();
  if (!(await page.$('.venue-row'))) throw new Error('Không có card sân sau khi tìm kiếm.');

  const mapToggle = await page.$('button[aria-label*="bản đồ" i], button[title*="bản đồ" i]');
  if (!mapToggle) throw new Error('Không tìm thấy nút chuyển sang bản đồ.');
  await mapToggle.click();
  await page.waitForSelector('.venue-results-map, .leaflet-container', { visible: true, timeout: 10_000 });
  await screenshot('venue-map');
  await goto(`/venues/${venueId}`, '.venue-detail-page');
  await page.waitForFunction(
    () => !document.body.innerText.includes('Đang tải thông tin sân...'),
    { timeout: 30_000 },
  );
  const cta = await page.$('#btn-view-schedule:not([disabled])');
  if (!cta) throw new Error('Chi tiết sân không có CTA xem lịch khả dụng.');
  await cta.click();
  await page.waitForFunction((expectedVenueId) => {
    const url = new URL(window.location.href);
    return url.pathname === '/booking' && url.searchParams.get('venue_cluster_id') === expectedVenueId;
  }, { timeout: 10_000 }, String(venueId));
  const destination = new URL(page.url());
  return { detail_path: `/venues/${venueId}`, booking_destination: `${destination.pathname}${destination.search}` };
});

await runFlow('booking-create-preview-and-cancel', async () => {
  await goto(`/booking?venue_cluster_id=${venueId}`, '.client-booking .schedule-workspace');
  const bookingDate = await selectBookableRange();
  await waitForText('Chi tiết giá', '.booking-summary');
  await screenshot('booking-preview');
  await page.click('.booking-summary footer > button');
  await page.waitForFunction(() => /^\/booking\/\d+$/.test(window.location.pathname), { timeout: 25_000 });
  await settle();
  createdBookingId = new URL(page.url()).pathname.split('/').pop();
  createdBookingIds.push(createdBookingId);
  await waitForText('Chi tiết đơn đặt');

  await goto('/bookings', '.booking-history-page');
  await page.waitForFunction(() => !document.body.innerText.includes('Đang tải lịch sử booking'), { timeout: 20_000 });
  const opened = await page.evaluate((bookingId) => {
    const link = document.querySelector(`a[href="/booking/${bookingId}"]`);
    const card = link?.closest('.booking-card');
    const button = card?.querySelector('.danger-action');
    button?.click();
    return Boolean(button);
  }, createdBookingId);
  if (!opened) throw new Error(`Booking ${createdBookingId} không có hành động hủy hợp lệ.`);
  await page.waitForSelector('.confirm-modal', { visible: true, timeout: 8_000 });
  await clickText('.confirm-modal button', 'Xác nhận hủy');
  await page.waitForSelector('.confirm-modal', { hidden: true, timeout: 20_000 });
  await page.waitForFunction((bookingId) => {
    const link = document.querySelector(`a[href="/booking/${bookingId}"]`);
    return link?.closest('.booking-card')?.innerText?.includes('Đã hủy');
  }, { timeout: 20_000 }, createdBookingId);
  await screenshot('booking-cancelled');
  return { booking_id: createdBookingId, booking_date: bookingDate, final_status: 'cancelled' };
});

await runFlow('booking-online-payment-qr-and-cancel', async () => {
  await goto(`/booking?venue_cluster_id=${venueId}`, '.client-booking .schedule-workspace');
  const bookingDate = await selectBookableRange();
  if (!(await page.$('.payment-section input[value="full_payment"]'))) {
    throw new Error('Sân không cung cấp hình thức thanh toán toàn bộ qua QR.');
  }
  await page.click('.payment-section input[value="full_payment"]');
  await page.waitForFunction(
    () => !document.querySelector('.booking-summary footer > button')?.disabled,
    { timeout: 10_000 },
  );
  const createResponsePromise = page.waitForResponse(
    (response) => response.url().endsWith('/api/bookings') && response.request().method() === 'POST',
    { timeout: 20_000 },
  );
  await page.$eval('.booking-summary footer > button', (button) => button.click());
  const createResponse = await createResponsePromise;
  if (!createResponse.ok()) {
    throw new Error(`Tạo booking thanh toán online trả HTTP ${createResponse.status()}: ${await createResponse.text()}`);
  }
  await page.waitForFunction(() => /^\/booking\/\d+$/.test(window.location.pathname), { timeout: 25_000 });
  await settle();
  const bookingId = new URL(page.url()).pathname.split('/').pop();
  createdBookingIds.push(bookingId);
  await page.waitForSelector('.btn-sepay', { visible: true, timeout: 15_000 });
  await page.click('.btn-sepay');
  await page.waitForSelector('.sepay-panel', { visible: true, timeout: 20_000 });
  const paymentPreview = await page.evaluate(() => ({
    qr: document.querySelector('.sepay-panel .qr-wrap img')?.getAttribute('src') || '',
    transferRows: document.querySelectorAll('.sepay-panel .transfer-row').length,
  }));
  if (!paymentPreview.qr || paymentPreview.transferRows < 4) {
    throw new Error(`Thông tin QR chưa đầy đủ: ${JSON.stringify(paymentPreview)}`);
  }
  await screenshot('booking-payment-qr');

  const cancelButtons = await page.$$('.sepay-panel .btn-cancel-payment');
  if (!cancelButtons.length) throw new Error('Không có nút hủy thanh toán sau khi tạo QR.');
  await cancelButtons[0].click();
  await page.waitForSelector('.confirm-modal', { visible: true, timeout: 8_000 });
  await clickText('.confirm-modal button', 'Hủy thanh toán');
  await page.waitForSelector('.confirm-modal', { hidden: true, timeout: 20_000 });
  await waitForText('Đơn Đã Bị Hủy', '.status-banner', 20_000);
  return { booking_id: bookingId, booking_date: bookingDate, qr_rendered: true, final_status: 'cancelled' };
});

await runFlow('client-complaints-system-and-venue', async () => {
  await goto(`/venues/${venueId}`, '.support-actions');
  await clickText('.support-actions button', 'Khiếu nại sân');
  await page.waitForSelector('.complaint-modal', { visible: true, timeout: 8_000 });
  await page.type('.complaint-modal textarea', `${runTag} Khiếu nại sân từ kiểm thử trình duyệt.`);
  await clickText('.complaint-modal button', 'Gửi khiếu nại');
  await page.waitForSelector('.complaint-modal', { hidden: true, timeout: 20_000 });
  await waitForSuccessToast();

  await clickText('.support-actions button', 'Khiếu nại sân');
  await page.waitForSelector('.complaint-modal', { visible: true, timeout: 8_000 });
  await page.click('.complaint-modal input[value="system"]');
  await page.type('.complaint-modal textarea', `${runTag} Khiếu nại hệ thống từ kiểm thử trình duyệt.`);
  await clickText('.complaint-modal button', 'Gửi khiếu nại');
  await page.waitForSelector('.complaint-modal', { hidden: true, timeout: 20_000 });
  await waitForSuccessToast();
  return { tag: runTag, submitted: ['venue', 'system'] };
});

await runFlow('venue-report-submit', async () => {
  await goto(`/venues/${venueId}`, '.support-actions');
  await clickText('.support-actions button', 'Báo cáo sân');
  await page.waitForSelector('.moderation-modal', { visible: true, timeout: 8_000 });
  await page.click('.moderation-modal input[value="spam"]');
  await page.type('.moderation-modal textarea', `${runTag} Báo cáo sân từ kiểm thử trình duyệt.`);
  await clickText('.moderation-modal button', 'Gửi báo cáo');
  await page.waitForSelector('.moderation-modal', { hidden: true, timeout: 20_000 });
  await waitForSuccessToast();
  return { tag: runTag, target: `venue:${venueId}` };
});

await runFlow('community-comment-submit', async () => {
  await goto(`/community/${communitySlug}`, '.sg-community-detail-page');
  await page.waitForFunction(() => !document.body.innerText.includes('Đang tải bài viết'), { timeout: 20_000 });
  await clickText('.sg-community-actions button', 'Bình luận');
  await page.waitForSelector('.sg-community-comment-composer textarea', { visible: true, timeout: 8_000 });
  await page.type('.sg-community-comment-composer textarea', `${runTag} Bình luận kiểm thử bằng Chrome.`);
  const commentState = await page.$eval('.sg-community-comment-composer button[type="submit"]', (button) => ({
    disabled: button.disabled,
    textarea: button.closest('form')?.querySelector('textarea')?.value || '',
  }));
  if (commentState.disabled) throw new Error(`Nút gửi bình luận bị khóa sau khi nhập: ${JSON.stringify(commentState)}`);
  const commentResponsePromise = page.waitForResponse(
    (response) => response.url().includes('/comments') && response.request().method() === 'POST',
    { timeout: 15_000 },
  );
  await page.$eval('.sg-community-comment-composer form', (form) => form.requestSubmit());
  const commentResponse = await commentResponsePromise;
  if (!commentResponse.ok()) throw new Error(`Gửi bình luận trả HTTP ${commentResponse.status()}: ${await commentResponse.text()}`);
  const commentPayload = await commentResponse.json();
  const commentId = commentPayload?.data?.id ?? commentPayload?.comment?.id ?? commentPayload?.id;
  if (commentId) createdCommentIds.push(commentId);
  await waitForText(`${runTag} Bình luận kiểm thử bằng Chrome.`, '.sg-community-comment-list', 20_000);
  await screenshot('community-comment');
  return { tag: runTag, post: communitySlug };
});

await runFlow('chat-send-message', async () => {
  await goto('/chat', '.chat-page');
  await page.waitForFunction(() => !document.body.innerText.includes('Đang tải hộp thư'), { timeout: 20_000 });
  const conversations = await page.$$('.tg-conv-item');
  if (!conversations.length) throw new Error('Tài khoản kiểm thử không có hội thoại để gửi tin.');
  await conversations[0].click();
  await page.waitForSelector('input[placeholder="Nhập tin nhắn..."]', { visible: true, timeout: 15_000 });
  const message = `${runTag} Tin nhắn kiểm thử Chrome`;
  await page.type('input[placeholder="Nhập tin nhắn..."]', message);
  const messageResponsePromise = page.waitForResponse(
    (response) => response.url().includes('/api/chat/conversations/')
      && response.url().endsWith('/messages')
      && response.request().method() === 'POST',
    { timeout: 15_000 },
  );
  await page.click('.zalo-chat-box button[type="submit"]');
  const messageResponse = await messageResponsePromise;
  if (!messageResponse.ok()) throw new Error(`Gửi tin nhắn trả HTTP ${messageResponse.status()}: ${await messageResponse.text()}`);
  const messagePayload = await messageResponse.json();
  const messageId = messagePayload?.data?.id ?? messagePayload?.message?.id ?? messagePayload?.id;
  if (messageId) createdMessageIds.push(messageId);
  await waitForText(message, 'body', 20_000);
  await screenshot('chat-message');
  return { tag: runTag, message };
});

await runFlow('meetup-empty-precondition-ux', async () => {
  await goto('/community', '.community-page');
  await page.click('.meetup-sidebar button[aria-label="Tạo bài giao lưu"]');
  await page.waitForSelector('.meetup-modal', { visible: true, timeout: 8_000 });
  await page.waitForFunction(() => !document.body.innerText.includes('Đang tải lịch sân đủ điều kiện'), { timeout: 20_000 });
  await waitForText('Chưa có lịch sân phù hợp', '.meetup-modal');
  await screenshot('meetup-empty-state');
  return { state: 'no-confirmed-future-booking' };
});

await runFlow('partner-application-client-validation', async () => {
  await goto('/partner-application', '.partner-portal-page');
  const canStart = await page.evaluate(() => [...document.querySelectorAll('button')]
    .some((button) => button.textContent?.includes('Đăng ký hồ sơ mới')));
  if (!canStart) return { state: 'registration-not-available-for-current-account' };
  await clickText('button', 'Đăng ký hồ sơ mới');
  await page.waitForSelector('.wizard-container form', { visible: true, timeout: 10_000 });
  await clickText('.wizard-container form button', 'Gửi hồ sơ đăng ký');
  await page.waitForFunction(
    () => document.querySelectorAll('.wizard-container .error-text').length > 0,
    { timeout: 10_000 },
  );
  await screenshot('partner-validation');
  const errorCount = await page.$$eval('.wizard-container .error-text', (errors) => errors.length);
  return { validation: 'required-fields-visible', error_count: errorCount };
});

try {
  const token = await page.evaluate(() => localStorage.getItem('auth_token'));
  if (token) {
    await fetch(`${baseUrl}/api/auth/logout`, {
      method: 'POST',
      headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
    });
  }
} catch {
  // Cleanup must not hide browser-flow failures.
}

await browser.close();

const unexpectedResponses = failedResponses.filter((failure) => !(
  failure.flow === 'login-validation-and-success' && failure.status === 422
));
const failures = results.filter((result) => result.status === 'fail');
const report = {
  status: failures.length === 0 && pageErrors.length === 0 && unexpectedResponses.length === 0 ? 'pass' : 'fail',
  run_tag: runTag,
  created_booking_ids: createdBookingIds,
  created_comment_ids: createdCommentIds,
  created_message_ids: createdMessageIds,
  results,
  console_errors: consoleErrors,
  page_errors: pageErrors,
  failed_responses: unexpectedResponses,
  artifact_dir: artifactDir,
};

console.log(JSON.stringify(report, null, 2));
if (report.status !== 'pass') process.exitCode = 1;
