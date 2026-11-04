import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';

// Only use the disposable integration site and its fictional example.test fixtures.
const base = process.env.UT_URL;
const user = process.env.UT_ADMIN_USER;
const password = process.env.UT_ADMIN_PASS;
if (!base || !user || !password) throw new Error('UT_URL, UT_ADMIN_USER and UT_ADMIN_PASS are required');
const output = resolve(process.env.UT_SCREENSHOTS || 'work/navigation-screenshots');
await mkdir(output, { recursive: true });
const browser = await chromium.launch();
try {
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, colorScheme: 'light' });
  // Joomla may auto-start its welcome tour on the dashboard after signing in; its sessionStorage state
  // would send the next page back to the dashboard. Clear it before any page script runs.
  await page.addInitScript(() => {
    try {
      for (const key of ['tourId', 'tourToken', 'autoTourId', 'currentStepId', 'previousStepUrl']) sessionStorage.removeItem(key);
    } catch {
      // Storage may be unavailable; the capture then behaves as before.
    }
  });
  await page.goto(base + '/administrator/index.php');
  await page.locator('input[name="username"]').fill(user);
  await page.locator('input[name="passwd"]').fill(password);
  await page.locator('button[type="submit"]').first().click();
  await page.locator('input[name="passwd"]').waitFor({ state: 'detached' });
  const home = base + '/administrator/index.php?option=com_usertools&view=home';
  const capture = async name => {
    await page.evaluate(() => document.fonts.ready);
    return page.screenshot({ path: resolve(output, name), type: 'jpeg', quality: 78, fullPage: true });
  };
  await page.goto(home);
  await page.locator('[data-ut-category=""]').waitFor();
  await capture('01-portada.jpg');
  for (const [category, name] of [
    ['accounts', '31-menu-cuentas.jpg'], ['groups', '32-menu-grupos.jpg'],
    ['automation', '33-menu-automatizacion.jpg'], ['delete', '34-menu-eliminacion.jpg'],
  ]) {
    await page.goto(home + '&section=' + category);
    await page.locator('[data-ut-category="' + category + '"]').waitFor();
    const current = page.locator('a[aria-current="page"][href*="section=' + category + '"]');
    if (!(await current.isVisible())) throw new Error('Category is not active and visible: ' + category);
    await capture(name);
  }
  await page.goto(home + '&section=accounts');
  const accounts = page.locator('a[aria-current="page"][href*="section=accounts"]');
  await accounts.focus();
  await page.keyboard.press('Tab');
  const keyboardNext = await page.evaluate(() => document.activeElement?.getAttribute('href'));
  if (!keyboardNext?.includes('section=groups')) throw new Error('Unexpected native menu keyboard order');
  await page.screenshot({ path: resolve(output, 'navigation-keyboard.png'), fullPage: true });
  const parent = page.getByRole('link', { name: 'User Tools', exact: true });
  await parent.click();
  await accounts.waitFor({ state: 'hidden' });
  await parent.focus();
  await page.keyboard.press('Enter');
  await accounts.waitFor({ state: 'visible' });

  await page.setViewportSize({ width: 390, height: 844 });
  const layout = await page.evaluate(() => {
    const app = document.querySelector('.ut-app');
    return {
      viewport: innerWidth, documentWidth: document.documentElement.scrollWidth,
      outsideViewport: [...app.querySelectorAll('*')].map(el => {
        const box = el.getBoundingClientRect();
        return { tag: el.tagName, left: box.left, right: box.right };
      }).filter(box => box.left < -1 || box.right > innerWidth + 1).slice(0, 20),
    };
  });
  await page.screenshot({ path: resolve(output, 'navigation-mobile.png'), fullPage: true });
  if (layout.outsideViewport.length) throw new Error('Category content overflows the narrow viewport');
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
  await page.screenshot({ path: resolve(output, 'navigation-large-text.png'), fullPage: true });
  await page.evaluate(() => { document.documentElement.style.fontSize = ''; });
  await page.emulateMedia({ colorScheme: 'dark' });
  await page.screenshot({ path: resolve(output, 'navigation-dark-preference.png'), fullPage: true });
  await page.emulateMedia({ colorScheme: 'light' });
  await page.goto(base + '/administrator/index.php?option=com_cpanel&view=cpanel&dashboard=users');
  try {
    await page.locator('[data-ut-dashboard-links-category="accounts"]').waitFor();
  } catch (error) {
    await page.screenshot({ path: resolve(output, 'navigation-dashboard-error.png'), fullPage: true });
    console.log(JSON.stringify({ dashboardUrl: page.url(), title: await page.title(),
      modules: await page.locator('.mod-usertools').allTextContents() }));
    throw error;
  }
  await capture('13-panel-usuarios.jpg');
  // Exercise the actual Joomla asset as well as the HTTP-only integration client.
  await page.goto(base + '/administrator/index.php?option=com_usertools&view=names');
  await page.locator('[data-ut-names-form]').waitFor();
  await capture('43-nombres-lista.jpg');
  await page.locator('[name="lines"]').fill('names_one;Updated First Name\nnames_two;Updated Second Name\nnames_manager;Protected Row\nnames_missing;Unknown Row\nmalformed row');
  await page.locator('button[value="names.preview"]').click();
  await page.locator('[data-ut-names-review]').waitFor();
  const applyNames = page.locator('[data-ut-names-submit]');
  if (!(await applyNames.isEnabled())) throw new Error('Name selection script did not initialise');
  await page.locator('[data-ut-name-choice][value="1"]').uncheck();
  if (await page.locator('[data-ut-names-selected]').inputValue() !== '[0]') throw new Error('Deselected name was still submitted');
  await capture('44-nombres-revision.jpg');
  await page.locator('[data-ut-name-choice][value="0"]').uncheck();
  if (!(await applyNames.isDisabled())) throw new Error('Empty name selection must disable apply');
  const noScript = await browser.newContext({ storageState: await page.context().storageState(), javaScriptEnabled: false });
  try {
    const fallback = await noScript.newPage();
    await fallback.goto(base + '/administrator/index.php?option=com_usertools&view=names&layout=review');
    if (!(await fallback.locator('[data-ut-names-submit]').isDisabled())
        || await fallback.locator('[data-ut-names-selected]').inputValue() !== '[]'
        || await fallback.locator('[data-ut-names-ready]').inputValue() !== '0') {
      throw new Error('Name update must remain disabled without JavaScript');
    }
  } finally {
    await noScript.close();
  }

  // Browser verification of the standalone, create-only bulk registration review.
  // No submit/apply: synthetic fixtures only, with no account changes.
  const bulkUrl = base + '/administrator/index.php?option=com_usertools&view=bulkcreate';
  await page.goto(bulkUrl);
  await page.locator('textarea[name="lines"]').fill(
    'ut_browser_a\nut_browser_b;Example Person;manual@example.test\nut_browser_c;Third Person',
  );
  await capture('40-alta-masiva-entrada.jpg');
  await page.locator('button[value="bulkcreate.preview"]').click();
  const bulk = page.locator('[data-ut-bulkcreate-review]');
  await bulk.waitFor();
  await capture('41-alta-masiva-revision.jpg');
  if (await bulk.locator('[data-ut-add]:checked').count() !== 0 ||
      await bulk.locator('[data-ut-bulkcreate-submit]').isEnabled()) {
    throw new Error('Bulk creation must start with no accounts selected');
  }
  await bulk.locator('[data-ut-email-auto-on]').check();
  await bulk.locator('[data-ut-email-domain]').fill('example.test');
  const emails = await bulk.locator('[data-ut-user] [data-ut-email]').evaluateAll(
    fields => fields.map(field => field.value),
  );
  if (JSON.stringify(emails) !== JSON.stringify([
    'ut_browser_a@example.test', 'manual@example.test', 'ut_browser_c@example.test',
  ])) throw new Error('Bulk creation email generation must preserve manual addresses');
  await bulk.locator('[data-ut-add-all]').check();
  if (await bulk.locator('[data-ut-add]:checked').count() !== 3 ||
      await bulk.locator('[data-ut-bulkcreate-selected]').textContent() !== '3') {
    throw new Error('Bulk create select-all and counter must reflect three accounts');
  }
  const typed = bulk.locator('[data-ut-typed]');
  if (!(await typed.isVisible())) throw new Error('Bulk create threshold must require a typed count');
  await capture('42-alta-masiva-correos-generados.jpg');
  await bulk.locator('[data-ut-add]').last().uncheck();
  const threshold = Number(await bulk.locator('[data-ut-confirm]').getAttribute('data-ut-threshold'));
  if ((await typed.isVisible()) !== (threshold > 0 && 2 >= threshold)) {
    throw new Error('Typed confirmation must follow the configured threshold and selected count');
  }

  await writeFile(resolve(output, 'navigation-checks.json'), JSON.stringify({
    menuKeyboardOrder: true, keyboardExpandCollapse: true, activeCategories: true,
    narrowCategoryFits: true, layout, language: 'en-GB',
    scope: 'Joomla disposable CI site; own category content, not a WCAG conformity assessment',
    captures: ['home', 'four categories', 'Users dashboard', '390px', '200% root font', 'dark colour preference'],
  }, null, 2));
  console.log('Category navigation captures and keyboard/layout checks completed');
} finally {
  await browser.close();
}
