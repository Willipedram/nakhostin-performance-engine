// Run against an authenticated staging admin. Supply an external Playwright storage state.
const assert = require('node:assert/strict');
const { mkdirSync } = require('node:fs');
const { join } = require('node:path');
const { chromium } = require('playwright-core');

const base = process.env.NPE_UI_ADMIN_URL;
const state = process.env.NPE_UI_STORAGE_STATE;
const rtlState = process.env.NPE_UI_RTL_STORAGE_STATE;
const rtlQuery = process.env.NPE_UI_RTL_QUERY;
const output = process.env.NPE_UI_SCREENSHOTS;
const axeScript = process.env.NPE_UI_AXE_SCRIPT;
assert(base && state, 'Set NPE_UI_ADMIN_URL and NPE_UI_STORAGE_STATE');

function url(path, rtl) {
  const result = new URL(path, base);
  if (rtl && rtlQuery) result.searchParams.set(rtlQuery, '1');
  return result.href;
}

async function main() {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH });
  let checked = 0;
  try {
    for (const display of [
      { name: 'desktop-light', width: 1440, height: 1000, dark: false },
      { name: 'tablet-dark', width: 820, height: 1000, dark: true },
      { name: 'mobile-light', width: 390, height: 844, dark: false },
    ]) {
      for (const rtl of rtlState || rtlQuery ? [false, true] : [false]) {
        const context = await browser.newContext({
          storageState: rtl && rtlState ? rtlState : state,
          colorScheme: display.dark ? 'dark' : 'light',
          viewport: { width: display.width, height: display.height },
        });
        try {
          const page = await context.newPage();
          const errors = [];
          page.on('pageerror', error => errors.push(error.message));
          const dir = rtl ? 'rtl' : 'ltr';
          const first = url('admin.php?page=nakhostin-performance-engine', rtl);
          assert.equal((await page.goto(first)).status(), 200);
          const root = page.locator('.npe-shell');
          assert.equal(await root.count(), 1);
          assert.equal(await root.evaluate(el => getComputedStyle(el).colorScheme), display.dark ? 'dark' : 'normal');
          await root.locator('.npe-shell-skip').focus();
          await page.keyboard.press('Enter');
          assert.equal(await page.evaluate(() => document.activeElement.id), 'npe-main');
          const links = await root.locator('.npe-shell-sidebar nav a').evaluateAll(links => links.map(link => link.href));
          assert.equal(links.length, new Set(links).size);
          assert(links.length >= 14, 'Expected all existing NPE sections');
          for (const link of links) {
            const response = await page.goto(url(link, rtl));
            const slug = new URL(link).searchParams.get('page');
            assert.equal(response.status(), 200, slug);
            assert.equal(await root.count(), 1, slug);
            assert.equal(await root.getAttribute('dir'), dir, slug);
            assert.equal(await root.locator('.npe-shell-sidebar a[aria-current=page]').count(), 1, slug);
            assert.equal(new URL(await root.locator('.npe-shell-sidebar a[aria-current=page]').getAttribute('href')).searchParams.get('page'), slug);
            assert.equal(await root.locator('h1:visible').count(), 1, slug);
            assert.equal(await root.evaluate(el => el.scrollWidth <= el.clientWidth + 2), true, slug + ' overflows');
            assert.equal(await page.locator('#npe-admin-system-css').count(), 1, slug);
            assert.deepEqual(errors, [], slug);
            if (axeScript) {
              await page.addScriptTag({ path: axeScript });
              const violations = await page.evaluate(async () => {
                const result = await window.axe.run('.npe-shell', {
                  runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'] },
                });
                return result.violations.map(violation => violation.id);
              });
              assert.deepEqual(violations, [], `${slug} accessibility`);
            }
            if (output && ['nakhostin-performance-engine', 'npe-settings', 'npe-performance-overview'].includes(slug)) {
              mkdirSync(output, { recursive: true });
              await page.screenshot({ path: join(output, `${display.name}-${dir}-${slug}.png`) });
            }
            checked++;
          }
          await page.goto(url('admin.php?page=npe-settings&section=cache', rtl));
          const sidebar = root.locator('.npe-shell-sidebar');
          if (display.width <= 960) {
            assert.equal(await sidebar.getAttribute('open'), null);
            await sidebar.locator('summary').focus();
            await page.keyboard.press('Enter');
            assert.equal(await sidebar.locator('a').first().isVisible(), true);
            await page.keyboard.press('Enter');
          }
          const summary = page.locator('#npe-field-cache-early_cache').locator('..').locator('summary');
          await summary.focus();
          await page.keyboard.press('Enter');
          assert.match(await page.locator('#npe-field-cache-early_cache-help').innerText(), /WP_CACHE/);
          await page.keyboard.press('Escape');
          assert.equal(await summary.locator('..').getAttribute('open'), null);
          await page.goto(url('index.php', false));
          assert.equal(await page.locator('#npe-admin-system-css, #npe-admin-shell-js').count(), 0);
          await page.goto(new URL('../', base).href);
          assert.equal(await page.locator('.npe-shell, #npe-admin-system-css, #npe-admin-shell-js').count(), 0);
        } finally {
          await context.close();
        }
      }
    }
    console.log(`Verified ${checked} NPE screen/profile combinations${rtlState || rtlQuery ? ' in LTR and RTL' : ' in LTR; RTL not configured'}`);
  } finally {
    await browser.close();
  }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
