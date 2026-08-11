import { expect, test } from '@playwright/test';
import { expectNoSeriousA11yViolations } from './axe';

/**
 * The public browse path, driven end to end. These pages are the ones an
 * anonymous visitor and a search engine see, so they must work with the built
 * assets in place — not merely return 200 from a controller test.
 */

test('home page renders and exposes the search form', async ({ page }) => {
    await page.goto('/');

    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.getByRole('search').first()).toBeVisible();
    await expectNoSeriousA11yViolations(page, 'home');
});

test('the catalog is reachable and filterable from the home page', async ({ page }) => {
    await page.goto('/');

    await page.getByRole('link', { name: 'Всички обяви' }).click();
    await expect(page).toHaveURL(/\/listings/);

    await expect(page.getByLabel('Категория')).toBeVisible();
    await expect(page.getByLabel('Област')).toBeVisible();

    await expectNoSeriousA11yViolations(page, 'listings index');
});

test('a keyword search keeps its term in the URL and the field', async ({ page }) => {
    await page.goto('/listings');

    const search = page.getByRole('search').first().getByRole('searchbox');
    await search.fill('пекарна');
    await search.press('Enter');

    await expect(page).toHaveURL(/[?&]q=/);
});

test('sorting is applied through the query string', async ({ page }) => {
    await page.goto('/listings');

    await page.getByLabel('Подреждане').selectOption('title');
    await page.getByRole('button', { name: 'Приложи' }).click();

    await expect(page).toHaveURL(/[?&]sort=title/);
});

test('the sitemap is served as XML', async ({ request }) => {
    const response = await request.get('/sitemap.xml');

    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('xml');
    expect(await response.text()).toContain('<urlset');
});

test('a skip link takes a keyboard user straight to the content', async ({ page }) => {
    await page.goto('/');

    await page.keyboard.press('Tab');

    const skip = page.getByRole('link', { name: 'Към основното съдържание' });
    await expect(skip).toBeFocused();

    await skip.press('Enter');
    await expect(page).toHaveURL(/#main$/);
});
