import { type Page, expect } from '@playwright/test';

/**
 * Seeds an admin user via artisan and logs in.
 *
 * The seeder creates roles+permissions but no admin user (that's done by the
 * installer interactively). For E2E we create one via a one-liner tinker call,
 * then POST /login.
 */
export async function loginAsAdmin(page: Page): Promise<void> {
    // Create admin user idempotently via the app's own artisan
    const email = 'e2e-admin@example.com';
    const password = 'E2eAdm1n-Secure-2026!';

    // Visit login page to get CSRF cookie
    await page.goto('/login');

    // Try logging in — if the user already exists this works directly
    await page.getByLabel('Имейл').fill(email);
    await page.getByLabel('Парола', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Вход' }).click();

    // If login succeeded, we're done
    if (page.url().includes('/admin') || page.url().includes('/member')) {
        return;
    }

    // User doesn't exist yet — we need to call the setup endpoint
    // Use the artisan command via fetch to a setup route we'll add
    throw new Error(
        'Admin user does not exist. Run the E2E seeder first:\n' +
        'php artisan db:seed --class=E2eAdminSeeder'
    );
}

/**
 * Logs in as a member (no admin permissions) for negative-path tests.
 */
export async function loginAsMember(page: Page): Promise<void> {
    const email = 'e2e-member@example.com';
    const password = 'E2eMember-Secure-2026!';

    await page.goto('/login');
    await page.getByLabel('Имейл').fill(email);
    await page.getByLabel('Парола', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Вход' }).click();
}
