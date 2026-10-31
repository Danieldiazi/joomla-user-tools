import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';

// Run against the disposable Joomla site after tests/integration/run.php.
const base = process.env.UT_URL;
const user = process.env.UT_ADMIN_USER;
const password = process.env.UT_ADMIN_PASS;
const output = resolve(process.env.UT_SCREENSHOTS || 'work/profile-screenshots');
if (!base || !user || !password) throw new Error('UT_URL, UT_ADMIN_USER and UT_ADMIN_PASS are required');
await mkdir(output, { recursive: true });
const browser = await chromium.launch();
try {
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, colorScheme: 'light' });
  // Joomla may auto-start its own guided tour on the dashboard after signing in. The tour keeps its
  // state in sessionStorage and, on the next page, sends the browser back to the tour's screen, so the
  // capture would wait for User Tools on the dashboard. Clear that state before any page script runs.
  await page.addInitScript(() => {
    try {
      for (const key of ['tourId', 'tourToken', 'autoTourId', 'currentStepId', 'previousStepUrl']) sessionStorage.removeItem(key);
    } catch {
      // Storage may be unavailable; the capture then behaves as before.
    }
  });
  await page.goto(`${base}/administrator/index.php`);
  await page.locator('input[name="username"]').fill(user);
  await page.locator('input[name="passwd"]').fill(password);
  await Promise.all([page.waitForURL(/administrator\/index\.php/), page.locator('button[type="submit"]').first().click()]);
  await page.locator('input[name="passwd"]').waitFor({ state: 'detached' });
  await page.goto(`${base}/administrator/index.php?option=com_usertools`);
  await page.locator('[data-ut-home-card="profile"]').waitFor();
  await page.screenshot({ path: resolve(output, '01-portada.jpg'), type: 'jpeg', quality: 82, fullPage: true });
  const profileUrl = `${base}/administrator/index.php?option=com_usertools&view=profile&login=profile_demo`;
  await page.goto(profileUrl);
  await page.locator('[data-ut-profile-account]').waitFor();
  await page.locator('#ut-profile-login').focus();
  await page.keyboard.press('Tab');
  if (!(await page.getByRole('button', { name: 'Open profile', exact: true }).evaluate(el => el === document.activeElement))) {
    throw new Error('Profile search has an unexpected keyboard focus order');
  }
  const blockLink = await page.locator('[data-ut-profile-action="block"]').getAttribute('href');
  if (!blockLink) throw new Error('The demo account needs a block-review link');
  await page.screenshot({ path: resolve(output, '24-ficha-persona.jpg'), type: 'jpeg', quality: 82, fullPage: true });
  await page.setViewportSize({ width: 390, height: 844 });
  const layout = await page.evaluate(() => {
    const app = document.querySelector('.ut-app');
    return { viewport: innerWidth, documentWidth: document.documentElement.scrollWidth,
      appWidth: app.clientWidth, appScrollWidth: app.scrollWidth,
      outsideViewport: [...app.querySelectorAll('*')].filter(el => !el.closest('.ut-table-scroll')).map(el => {
        const box = el.getBoundingClientRect();
        return { tag: el.tagName, className: el.className, left: box.left, right: box.right };
      }).filter(box => box.left < -1 || box.right > innerWidth + 1).slice(0,20),
      headerOverflow: [...document.querySelectorAll('body *')].filter(el => !el.closest('.ut-app') && !el.closest('.ut-table-scroll'))
        .map(el => ({ tag: el.tagName, className: String(el.className).slice(0, 60), right: Math.round(el.getBoundingClientRect().right) }))
        .filter(box => box.right > innerWidth + 1).slice(0, 10) };
  });
  // Only User Tools' own content is checked: no element of the profile (outside its scrollable tables)
  // may reach beyond the viewport. Joomla's administrator header (page title and toolbar) belongs to the
  // template and may be wider than a phone; it is recorded in layout.json, not failed. The app's
  // scrollWidth is not used: Bootstrap row gutters add a few pixels without anything being cut off.
  const fits = layout.outsideViewport.length === 0;
  await writeFile(resolve(output, 'layout.json'), JSON.stringify(layout, null, 2));
  await page.screenshot({ path: resolve(output, 'profile-mobile.png'), fullPage: true });
  if (!fits) throw new Error('The profile overflows the narrow viewport');
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
  await page.screenshot({ path: resolve(output, 'profile-large-text.png'), fullPage: true });
  await page.evaluate(() => { document.documentElement.style.fontSize = ''; });
  await page.emulateMedia({ colorScheme: 'dark' });
  await page.screenshot({ path: resolve(output, 'profile-dark-preference.png'), fullPage: true });
  await page.emulateMedia({ colorScheme: 'light' });
  await page.goto(new URL(blockLink, `${base}/administrator/`).href);
  await page.locator('[data-ut-profile-block-review]').waitFor();
  await page.screenshot({ path: resolve(output, '25-ficha-revision-bloqueo.jpg'), type: 'jpeg', quality: 82, fullPage: true });
  // Never press the confirmation button: this remains a read-only visual exercise.
  await page.goto(profileUrl);
  if (!(await page.locator('[data-ut-profile-status]').innerText()).includes('Active')) {
    throw new Error('Opening a block review unexpectedly changed the demo account');
  }
  await writeFile(resolve(output, 'checks.json'), JSON.stringify({
    keyboardSearchFocus: true, narrowProfileFits: fits, templateHeaderWidth: layout.documentWidth, openingReviewLeavesAccountActive: true,
    captures: ['desktop', '390px viewport', '200% root font size', 'dark colour preference', 'block review'],
    language: 'en-GB', fixture: 'profile_demo',
  }, null, 2));
  console.log('Profile screenshots and layout checks completed');
} finally {
  await browser.close();
}
