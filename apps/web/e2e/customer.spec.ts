import { expect, test } from '@playwright/test';

test.describe('customer website guest journeys', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      localStorage.setItem('noor_locale', 'en');
    });
  });

  test('home page renders the hero and customer navigation', async ({ page }) => {
    const pageErrors: string[] = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));

    const response = await page.goto('/');

    expect(response?.status()).toBe(200);
    await expect(page).toHaveTitle(/NOOR/i);
    await expect(page.locator('h1').first()).toBeVisible();
    await expect(page.locator('a[href="/login"]').first()).toBeVisible();
    await expect(page.locator('a[href="/pricing"]').first()).toBeVisible();
    expect(pageErrors).toEqual([]);
  });

  test('search submits a customer query through the search page', async ({ page }) => {
    await page.goto('/search');
    const search = page.getByPlaceholder(/search titles, actors, genres/i);
    await search.fill('quran');
    await search.press('Enter');

    await expect(page).toHaveURL(/\/search\?q=quran/);
    await expect(page.locator('body')).not.toContainText(/Internal Server Error|Application error/i);
  });

  test('sign-in and registration are reachable and render usable forms', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByRole('heading', { name: /welcome back/i })).toBeVisible();
    await expect(page.locator('input[type="email"]')).toBeVisible();
    await expect(page.locator('input[type="password"]')).toBeVisible();
    await expect(page.getByRole('link', { name: /sign up now/i })).toHaveAttribute('href', '/register');

    await page.goto('/register');
    await expect(page.locator('form.space-y-4')).toBeVisible();
    await expect(page.locator('input[type="email"]')).toBeVisible();
    await expect(page.locator('input[type="password"]')).toBeVisible();
  });

  test('public catalog and membership routes render successfully', async ({ page }) => {
    for (const route of ['/movies', '/tv-shows', '/live-tv', '/pricing']) {
      const response = await page.goto(route);
      expect(response?.status(), `${route} should return HTTP 200`).toBe(200);
      await expect(page.locator('h1').first(), `${route} should show a page heading`).toBeVisible();
      await expect(page.locator('body')).not.toContainText(/Internal Server Error|Application error/i);
    }
  });

  test('guest is sent to sign-in before opening their account', async ({ page }) => {
    await page.goto('/account');
    await expect(page).toHaveURL(/\/login(?:\?|$)/);
    await expect(page.getByRole('heading', { name: /welcome back/i })).toBeVisible();
  });
});
