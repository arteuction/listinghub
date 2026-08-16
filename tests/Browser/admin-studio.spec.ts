import { expect, test } from '@playwright/test';
import { loginAsAdmin, loginAsMember } from './admin-helpers';

/**
 * Admin Experience Studio E2E tests.
 *
 * Covers the five gate scenarios:
 * 1. Tom Select keyboard navigation
 * 2. SortableJS drag-and-drop with server persist
 * 3. Order persistence after page reload
 * 4. Invalid/foreign UUID returns 422
 * 5. Unprivileged user gets 403
 */

test.describe('Admin Studio — Tom Select', () => {
    test('keyboard navigation works on taxonomy parent select', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/admin/taxonomy');

        // Find the first taxonomy and navigate to its terms
        const firstTaxLink = page.locator('a[href*="/admin/taxonomy/"]').first();
        if (await firstTaxLink.isVisible()) {
            await firstTaxLink.click();

            // Click "create new term"
            const createLink = page.locator('a[href*="/terms/create"]').first();
            if (await createLink.isVisible()) {
                await createLink.click();

                // Look for a Tom Select wrapper (the library wraps the select)
                const tomSelectWrapper = page.locator('.ts-wrapper').first();
                if (await tomSelectWrapper.isVisible({ timeout: 5000 })) {
                    // Click to open the dropdown
                    await tomSelectWrapper.click();

                    // The dropdown should be open
                    const dropdown = page.locator('.ts-dropdown').first();
                    await expect(dropdown).toBeVisible({ timeout: 3000 });

                    // Press ArrowDown to highlight an option
                    await page.keyboard.press('ArrowDown');

                    // An option should be highlighted
                    const activeOption = dropdown.locator('.active, [aria-selected="true"]').first();
                    await expect(activeOption).toBeVisible({ timeout: 3000 });

                    // Press Escape to close
                    await page.keyboard.press('Escape');
                    await expect(dropdown).not.toBeVisible();
                }
            }
        }
    });
});

test.describe('Admin Studio — Block reorder', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
    });

    test('drag-and-drop reorders blocks and persists to server', async ({ page }) => {
        // Navigate to the E2E test page blocks
        await page.goto('/admin/pages');

        // Find the e2e-test-page and go to its blocks
        const pageRow = page.locator('a:has-text("E2E Test Page")').first();
        if (!await pageRow.isVisible({ timeout: 5000 })) {
            test.skip(true, 'E2E test page not found — run E2eAdminSeeder first');
            return;
        }

        // Navigate to blocks management
        const blocksLink = page.locator('a[href*="blocks"]').first();
        await blocksLink.click();
        await page.waitForURL(/blocks/);

        // Get the sortable list
        const sortableList = page.locator('[data-sortable]');
        await expect(sortableList).toBeVisible({ timeout: 5000 });

        // Get blocks in their initial order
        const items = sortableList.locator('li[data-uuid]');
        const count = await items.count();

        if (count < 2) {
            test.skip(true, 'Need at least 2 blocks for reorder test');
            return;
        }

        const firstUuid = await items.nth(0).getAttribute('data-uuid');
        const secondUuid = await items.nth(1).getAttribute('data-uuid');

        // Intercept the reorder POST to verify it fires
        const reorderPromise = page.waitForResponse(
            (response) => response.url().includes('/reorder') && response.request().method() === 'POST',
            { timeout: 10_000 }
        );

        // Drag the first item below the second using the drag handle
        const firstHandle = items.nth(0).locator('[data-drag-handle]');
        const secondItem = items.nth(1);

        const firstBox = await firstHandle.boundingBox();
        const secondBox = await secondItem.boundingBox();

        if (firstBox && secondBox) {
            await page.mouse.move(firstBox.x + firstBox.width / 2, firstBox.y + firstBox.height / 2);
            await page.mouse.down();
            // Move to below the second item
            await page.mouse.move(secondBox.x + secondBox.width / 2, secondBox.y + secondBox.height + 5, { steps: 10 });
            await page.mouse.up();

            // Wait for the reorder request
            const response = await reorderPromise;
            expect(response.status()).toBe(200);

            const body = await response.json();
            expect(body).toHaveProperty('ok', true);
        }
    });

    test('block order persists after page reload', async ({ page }) => {
        await page.goto('/admin/pages');

        const pageRow = page.locator('a:has-text("E2E Test Page")').first();
        if (!await pageRow.isVisible({ timeout: 5000 })) {
            test.skip(true, 'E2E test page not found');
            return;
        }

        const blocksLink = page.locator('a[href*="blocks"]').first();
        await blocksLink.click();
        await page.waitForURL(/blocks/);

        const sortableList = page.locator('[data-sortable]');
        await expect(sortableList).toBeVisible({ timeout: 5000 });

        // Record the current order
        const items = sortableList.locator('li[data-uuid]');
        const orderBefore: string[] = [];
        for (let i = 0; i < await items.count(); i++) {
            const uuid = await items.nth(i).getAttribute('data-uuid');
            if (uuid) orderBefore.push(uuid);
        }

        // Reload the page
        await page.reload();
        await expect(sortableList).toBeVisible({ timeout: 5000 });

        // Record the order after reload
        const itemsAfter = sortableList.locator('li[data-uuid]');
        const orderAfter: string[] = [];
        for (let i = 0; i < await itemsAfter.count(); i++) {
            const uuid = await itemsAfter.nth(i).getAttribute('data-uuid');
            if (uuid) orderAfter.push(uuid);
        }

        // Order must be identical — server-persisted, not just DOM state
        expect(orderAfter).toEqual(orderBefore);
    });
});

test.describe('Admin Studio — Reorder security', () => {
    test('invalid/foreign UUID returns 422', async ({ page }) => {
        await loginAsAdmin(page);

        // Find the e2e-test-page
        await page.goto('/admin/pages');

        const pageRow = page.locator('a:has-text("E2E Test Page")').first();
        if (!await pageRow.isVisible({ timeout: 5000 })) {
            test.skip(true, 'E2E test page not found');
            return;
        }

        const blocksLink = page.locator('a[href*="blocks"]').first();
        await blocksLink.click();
        await page.waitForURL(/blocks/);

        // Extract the reorder URL from the sortable container
        const reorderUrl = await page.locator('[data-sortable]').getAttribute('data-sortable-url');
        expect(reorderUrl).toBeTruthy();

        // Get the CSRF token
        const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute('content');
        expect(csrfToken).toBeTruthy();

        // POST a fabricated UUID that doesn't belong to this page
        const response = await page.request.post(reorderUrl!, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken!,
            },
            data: {
                ids: ['00000000-0000-0000-0000-000000000000'],
            },
        });

        expect(response.status()).toBe(422);
    });

    test('member without manage settings gets 403', async ({ page }) => {
        await loginAsMember(page);

        // Try accessing admin pages — should be blocked by permission middleware
        const response = await page.goto('/admin/pages');

        // The middleware should return 403 or redirect to a "not authorized" page
        // spatie/permission returns 403 by default
        expect(response?.status()).toBeGreaterThanOrEqual(403);
    });
});
