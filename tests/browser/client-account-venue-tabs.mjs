import fs from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import puppeteer from 'puppeteer';

const baseUrl = process.env.APP_URL || 'http://127.0.0.1:8000';
const username = process.env.CLIENT_TEST_USER || 'user';
const password = process.env.CLIENT_TEST_PASSWORD || '12345678';
const venueId = process.env.CLIENT_TEST_VENUE_ID || '1';
const artifactDir = path.resolve('storage/app/test-artifacts/client-account-venue-tabs');

await fs.mkdir(artifactDir, { recursive: true });

const browser = await puppeteer.launch({
  executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe',
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});

const page = await browser.newPage();
const failures = [];
const consoleErrors = [];
const pageErrors = [];
const failedApiResponses = [];
let activeStep = 'bootstrap';

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

async function inspect(label, selector) {
  await page.waitForSelector(selector, { visible: true, timeout: 20_000 });
  await settle();
  const result = await page.evaluate((targetSelector) => {
    const target = document.querySelector(targetSelector);
    const rect = target?.getBoundingClientRect();
    const style = target ? getComputedStyle(target) : null;
    return {
      path: location.pathname,
      query: location.search,
      heading: document.querySelector('h1, h2')?.textContent?.trim() || '',
      textLength: (document.body.innerText || '').trim().length,
      horizontalOverflow: Math.max(document.documentElement.scrollWidth, document.body.scrollWidth) - innerWidth,
      visible: Boolean(rect?.width && rect?.height && style?.display !== 'none' && style?.visibility !== 'hidden'),
      brokenImages: [...document.images]
        .filter((image) => image.complete && image.naturalWidth === 0)
        .map((image) => image.currentSrc || image.src || image.alt),
      mojibake: /(?:Ă¢â‚¬|Ă¯Â¿Â½|ï¿½|Ă„â€˜|Ă†Â°|Ă†Â¡|Ă¡Â»|Ă¡Âº)/.test(document.body.innerText || ''),
    };
  }, selector);

  if (!result.visible) throw new Error(`${label}: vùng chính không hiển thị.`);
  if (result.horizontalOverflow > 2) throw new Error(`${label}: tràn ngang ${result.horizontalOverflow}px.`);
  if (result.brokenImages.length) throw new Error(`${label}: ${result.brokenImages.length} ảnh lỗi.`);
  if (result.mojibake) throw new Error(`${label}: phát hiện chuỗi sai mã hóa.`);

  await page.screenshot({ path: path.join(artifactDir, `${label}.png`), fullPage: true });
  return result;
}

async function attempt(label, callback) {
  activeStep = label;
  try {
    return await callback();
  } catch (error) {
    failures.push({ step: label, message: error.message });
    return null;
  }
}

const results = { login: null, desktop: {}, mobile: {}, actions: {} };

try {
  await page.setViewport({ width: 1440, height: 960, deviceScaleFactor: 1 });
  activeStep = 'login';
  await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  await page.waitForSelector('#login', { visible: true });
  await page.type('#login', username);
  await page.type('input[type="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForFunction(() => location.pathname !== '/login', { timeout: 20_000 });
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

  results.login = await page.evaluate(() => ({
    authenticated: Boolean(localStorage.getItem('auth_token')),
    roleGroup: localStorage.getItem('auth_role_group'),
  }));
  if (!results.login.authenticated || results.login.roleGroup !== 'user') {
    throw new Error(`Đăng nhập không đúng role user: ${JSON.stringify(results.login)}`);
  }

  for (const viewport of [
    { name: 'desktop', width: 1440, height: 960 },
    { name: 'mobile', width: 390, height: 844 },
  ]) {
    await page.setViewport({ width: viewport.width, height: viewport.height, deviceScaleFactor: 1 });

    await attempt(`${viewport.name}-account-profile`, async () => {
      await page.goto(`${baseUrl}/profile`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
      results[viewport.name].profile = await inspect(`${viewport.name}-account-profile`, '.profile-account-shell .client-account-nav');
      const navLabels = await page.$$eval('.client-account-nav strong', (items) => items.map((item) => item.textContent.trim()));
      if (!['Tài khoản', 'Lịch đặt', 'Số dư hoàn tiền'].every((label) => navLabels.includes(label))) {
        throw new Error(`Thiếu mục điều hướng tài khoản: ${navLabels.join(', ')}`);
      }
    });

    await attempt(`${viewport.name}-refund-balance`, async () => {
      await page.goto(`${baseUrl}/profile?tab=refunds`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
      await page.waitForFunction(
        () => document.querySelectorAll('.refund-summary-card').length === 3 || Boolean(document.querySelector('.refund-error')),
        { timeout: 20_000 },
      );
      results[viewport.name].refunds = await inspect(`${viewport.name}-refund-balance`, '.refund-balance-panel');
      const summaryCount = await page.$$eval('.refund-summary-card', (items) => items.length);
      if (summaryCount !== 3) throw new Error(`Tổng quan số dư có ${summaryCount}/3 thẻ.`);
    });

    await attempt(`${viewport.name}-booking-history`, async () => {
      await page.goto(`${baseUrl}/bookings`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
      await page.waitForFunction(
        () => Boolean(document.querySelector('.booking-card, .history-panel .state.empty, .history-panel .state.error')),
        { timeout: 20_000 },
      );
      results[viewport.name].bookings = await inspect(`${viewport.name}-booking-history`, '.booking-history-page .client-account-nav');
    });

    await attempt(`${viewport.name}-venue-posts`, async () => {
      await page.goto(`${baseUrl}/venues/${venueId}?tab=posts`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
      await page.waitForFunction(() => !document.body.innerText.includes('Đang tải thông tin sân'), { timeout: 20_000 });
      await page.waitForFunction(
        () => Boolean(document.querySelector('.venue-post-card, .venue-post-state--error'))
          || document.body.innerText.includes('Sân chưa có bài đăng công khai'),
        { timeout: 20_000 },
      );
      results[viewport.name].venuePosts = await inspect(`${viewport.name}-venue-posts`, '.venue-posts-tab');
      const state = await page.evaluate(() => ({
        activeTab: document.querySelector('.venue-tabs button.active')?.textContent?.trim() || '',
        postCount: document.querySelectorAll('.venue-post-card').length,
        hasLoadError: document.body.innerText.includes('Không thể tải bài đăng'),
      }));
      if (!state.activeTab.includes('Bài đăng')) throw new Error(`Tab active sai: ${state.activeTab}`);
      if (state.hasLoadError) throw new Error('API bài đăng trả lỗi trên giao diện.');
      results[viewport.name].venuePosts.state = state;
    });
  }

  await page.setViewport({ width: 1440, height: 960, deviceScaleFactor: 1 });
  await page.goto(`${baseUrl}/venues/${venueId}?tab=posts`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  await page.waitForSelector('.venue-post-card', { visible: true, timeout: 20_000 });
  await settle();

  await attempt('action-tab-switching', async () => {
    for (const label of ['Tổng quan', 'Sân & giá', 'Đánh giá', 'Vị trí', 'Bài đăng']) {
      await page.evaluate((wanted) => {
        const button = [...document.querySelectorAll('.venue-tabs button')]
          .find((item) => item.textContent.includes(wanted));
        button?.click();
      }, label);
      await page.waitForFunction((wanted) => document.querySelector('.venue-tabs button.active')?.textContent.includes(wanted), {}, label);
    }
    await page.waitForSelector('.venue-post-card', { visible: true, timeout: 20_000 });
    results.actions.tabSwitching = '5/5';
  });

  await attempt('action-post-report-popup', async () => {
    await page.click('.venue-post-card .venue-post-menu-wrap > button');
    await page.waitForSelector('.venue-post-menu', { visible: true });
    await page.click('.venue-post-menu button');
    await page.waitForSelector('[role="dialog"]', { visible: true, timeout: 10_000 });
    results.actions.reportPopupOpened = true;
    await page.keyboard.press('Escape');
    await settle();
  });

  await attempt('action-post-comments', async () => {
    await page.click('.venue-post-card .venue-post-actions button:nth-child(2)');
    await page.waitForSelector('.venue-comments', { visible: true, timeout: 10_000 });
    await page.waitForFunction(() => !document.body.innerText.includes('Đang tải bình luận'), { timeout: 15_000 });
    results.actions.commentsReadLoaded = true;
  });

  await attempt('action-post-like', async () => {
    const before = await page.evaluate(() => {
      const card = document.querySelector('.venue-post-card');
      return {
        active: card?.querySelector('.venue-post-actions button:first-child')?.classList.contains('active') || false,
        count: card?.querySelector('.venue-post-stats span:first-child')?.textContent?.trim() || '',
      };
    });
    await page.click('.venue-post-card .venue-post-actions button:first-child');
    await page.waitForFunction((previous) => {
      const card = document.querySelector('.venue-post-card');
      const active = card?.querySelector('.venue-post-actions button:first-child')?.classList.contains('active') || false;
      const count = card?.querySelector('.venue-post-stats span:first-child')?.textContent?.trim() || '';
      const toast = document.querySelector('.Vue-Toastification__toast')?.textContent || '';
      return active !== previous.active
        || count !== previous.count
        || toast.includes('chờ hệ thống cập nhật dữ liệu');
    }, { timeout: 8_000 }, before);
    const after = await page.evaluate(() => {
      const card = document.querySelector('.venue-post-card');
      return {
        active: card?.querySelector('.venue-post-actions button:first-child')?.classList.contains('active') || false,
        count: card?.querySelector('.venue-post-stats span:first-child')?.textContent?.trim() || '',
        toast: document.querySelector('.Vue-Toastification__toast')?.textContent || '',
      };
    });

    if (after.active !== before.active || after.count !== before.count) {
      await page.click('.venue-post-card .venue-post-actions button:first-child');
      await page.waitForFunction((original) => {
        const card = document.querySelector('.venue-post-card');
        const active = card?.querySelector('.venue-post-actions button:first-child')?.classList.contains('active') || false;
        const count = card?.querySelector('.venue-post-stats span:first-child')?.textContent?.trim() || '';
        return active === original.active && count === original.count;
      }, { timeout: 8_000 }, before);
      results.actions.like = { mode: 'live-toggle', restored: true };
      return;
    }

    if (after.toast.includes('chờ hệ thống cập nhật dữ liệu')) {
      results.actions.like = { mode: 'unavailable-migration-pending', graceful: true };
      return;
    }

    throw new Error('Nút thích không đổi trạng thái và cũng không báo tạm ngưng rõ ràng.');
  });

  await attempt('action-post-lightbox', async () => {
    const mediaButton = await page.$('.venue-post-card .venue-post-media button');
    if (!mediaButton) {
      results.actions.lightbox = 'no-seeded-media';
      return;
    }
    await mediaButton.click();
    await page.waitForSelector('.venue-lightbox', { visible: true, timeout: 10_000 });
    results.actions.lightbox = true;
    await page.keyboard.press('Escape');
  });
} catch (error) {
  failures.push({ step: activeStep, message: error.message });
} finally {
  await browser.close();
}

const report = {
  generatedAt: new Date().toISOString(),
  results,
  failures,
  consoleErrors,
  pageErrors,
  failedApiResponses,
};

await fs.writeFile(path.join(artifactDir, 'report.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));

if (failures.length || consoleErrors.length || pageErrors.length || failedApiResponses.length) {
  process.exitCode = 1;
}
