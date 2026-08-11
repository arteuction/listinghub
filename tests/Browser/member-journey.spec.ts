import { expect, test } from '@playwright/test';
import { expectNoSeriousA11yViolations } from './axe';

/**
 * Registration through to the member area, in a real browser.
 *
 * The value over the Pest suite is the parts Pest cannot reach: that the forms
 * are actually submittable, that validation errors are announced, and that a
 * keyboard user can complete the flow.
 */

/** Unique per run so a re-run never collides with a row left by the last one. */
function uniqueEmail(): string {
    return `e2e-${Date.now()}-${Math.floor(Math.random() * 10_000)}@example.com`;
}

/**
 * Unique per run for a second reason: unlike the Pest suite, this one hits the
 * real password policy, and that includes the HaveIBeenPwned breach check. A
 * memorable fixed password could plausibly be in a breach corpus and would
 * then fail registration for reasons that have nothing to do with the page.
 */
function unbreachedPassword(): string {
    return `Ee2e-${Date.now()}-${Math.floor(Math.random() * 1_000_000)}-Zx9`;
}

test('a visitor can register and lands on the verification notice', async ({ page }) => {
    await page.goto('/register');
    await expectNoSeriousA11yViolations(page, 'register');

    await page.getByLabel('Име', { exact: true }).fill('Иван Петров');
    await page.getByLabel('Имейл').fill(uniqueEmail());
    const password = unbreachedPassword();
    await page.getByLabel('Парола', { exact: true }).fill(password);
    await page.getByLabel('Повторете паролата').fill(password);

    await page.getByRole('button', { name: 'Създай профил' }).click();

    await expect(page).toHaveURL(/\/email\/verify/);
    await expect(page.getByRole('heading', { name: 'Потвърдете имейла си' })).toBeVisible();
});

test('a rejected registration announces the error rather than failing silently', async ({ page }) => {
    await page.goto('/register');

    await page.getByLabel('Име', { exact: true }).fill('Тест');
    await page.getByLabel('Имейл').fill(uniqueEmail());
    // Too short and without digits — must fail the password policy.
    await page.getByLabel('Парола', { exact: true }).fill('short');
    await page.getByLabel('Повторете паролата').fill('short');

    await page.getByRole('button', { name: 'Създай профил' }).click();

    await expect(page.getByRole('alert')).toBeVisible();
    await expect(page).toHaveURL(/\/register/);
});

test('the login page is reachable and accessible', async ({ page }) => {
    await page.goto('/login');

    await expect(page.getByLabel('Имейл')).toBeVisible();
    await expect(page.getByLabel('Парола')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Забравена парола?' })).toBeVisible();

    await expectNoSeriousA11yViolations(page, 'login');
});

test('wrong credentials produce one generic error', async ({ page }) => {
    await page.goto('/login');

    await page.getByLabel('Имейл').fill('nobody@example.com');
    await page.getByLabel('Парола').fill('definitely-wrong-42');
    await page.getByRole('button', { name: 'Вход' }).click();

    const alert = page.getByRole('alert');
    await expect(alert).toBeVisible();
    // The message must not reveal whether the address exists.
    await expect(alert).not.toContainText('не съществува');
});

test('the member area is closed to anonymous visitors', async ({ page }) => {
    await page.goto('/member/listings');

    await expect(page).toHaveURL(/\/login/);
});

test('the forgot-password form answers without revealing the address', async ({ page }) => {
    await page.goto('/forgot-password');
    await expectNoSeriousA11yViolations(page, 'forgot password');

    await page.getByLabel('Имейл').fill('nobody-here@example.com');
    await page.getByRole('button', { name: 'Изпрати линк' }).click();

    await expect(page.getByRole('status')).toBeVisible();
});
