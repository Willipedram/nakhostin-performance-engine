// Run against two published WordPress pages using Playwright Core and Chromium.
// Pass the URL and visible text for each page as four positional arguments.
// Set CHROMIUM_PATH when using a system Chromium.
const assert = require('node:assert/strict');
const { chromium } = require('playwright-core');

async function main() {
  const [firstUrl, firstText, secondUrl, secondText] = process.argv.slice(2);
  assert(firstUrl && firstText && secondUrl && secondText, 'Expected two URL/text pairs');
  const routes = [[firstUrl, firstText], [secondUrl, secondText]];
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH });
  let completed = 0;
  try {
    for (const mobile of [false, true]) {
      for (const cacheDisabled of [false, true]) {
        const context = await browser.newContext({
          viewport: mobile ? { width: 390, height: 844 } : { width: 1366, height: 900 },
          isMobile: mobile,
          hasTouch: mobile,
        });
        try {
          const page = await context.newPage();
          const session = await context.newCDPSession(page);
          const errors = [];
          let offline = false;
          page.on('pageerror', error => errors.push(error.message));
          page.on('console', message => {
            if (message.type() !== 'error') return;
            if (offline && message.text().includes('net::ERR_INTERNET_DISCONNECTED')) return;
            errors.push(message.text());
          });
          page.on('response', response => {
            if (response.status() >= 500) errors.push(`${response.status()} ${response.url()}`);
          });
          await session.send('Network.enable');
          await session.send('Network.setCacheDisabled', { cacheDisabled });
          if (mobile && cacheDisabled) {
            await session.send('Network.emulateNetworkConditions', {
              offline: false,
              latency: 150,
              downloadThroughput: 1_000_000,
              uploadThroughput: 250_000,
            });
          }
          for (let attempt = 0; attempt < 25; attempt++) {
            const [url, marker] = routes[attempt % routes.length];
            const response = await page.goto(url, { waitUntil: 'load' });
            assert.equal(response.status(), 200, url);
            await page.getByText(marker, { exact: true }).waitFor({ state: 'visible' });
            assert.deepEqual(errors, [], url);
            completed++;
          }
          if (mobile && cacheDisabled) {
            offline = true;
            await session.send('Network.emulateNetworkConditions', {
              offline: true,
              latency: 0,
              downloadThroughput: 0,
              uploadThroughput: 0,
            });
            await assert.rejects(page.goto(firstUrl), /ERR_INTERNET_DISCONNECTED/);
            await session.send('Network.emulateNetworkConditions', {
              offline: false,
              latency: 0,
              downloadThroughput: -1,
              uploadThroughput: -1,
            });
            offline = false;
            const response = await page.goto(firstUrl, { waitUntil: 'load' });
            assert.equal(response.status(), 200, firstUrl);
            await page.getByText(firstText, { exact: true }).waitFor({ state: 'visible' });
            assert.deepEqual(errors, [], firstUrl);
            console.log('Fresh navigation recovered after reconnecting');
          }
        } finally {
          await context.close();
        }
      }
    }
  } finally {
    await browser.close();
    console.log(`Completed ${completed}/100 visible page loads`);
  }
}

main().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
