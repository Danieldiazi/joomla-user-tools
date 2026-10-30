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
  await page.goto(base + '/administrator/index.php');
  await page.locator('input[name="username"]').fill(user);
  await page.locator('input[name="passwd"]').fill(password);
  await page.locator('button[type="submit"]').first().click();
  await page.locator('input[name="passwd"]').waitFor({ state: 'detached' });
  const home = base + '/administrator/index.php?option=com_usertools&view=home';
  const capture = async name => page.screenshot({ path: resolve(output, name), type: 'jpeg', quality: 78, fullPage: true });
  await page.goto(home);
  await page.locator('[data-ut-category=""]').waitFor();
  await capture('01-portada.jpg');
  for (const [category, name] of [
    ['accounts', '30-menu-cuentas.jpg'], ['groups', '31-menu-grupos.jpg'],
    ['automation', '32-menu-automatizacion.jpg'], ['delete', '33-menu-eliminacion.jpg'],
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
