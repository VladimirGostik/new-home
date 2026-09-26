import { test, expect, type Page } from '@playwright/test';

/**
 * The Login form renders each field as `<fieldset><legend>label</legend><input></fieldset>`
 * with no `for`/`id`/`aria-labelledby` link, so `getByLabel` cannot resolve it. Scope by the
 * legend text instead, which is stable and mirrors what the user reads on screen.
 */
function fieldByLegend(page: Page, legend: string | RegExp) {
    return page.locator('fieldset', { hasText: legend }).getByRole('textbox');
}

async function login(page: Page, email: string, password: string) {
    await page.goto('/login');
    await fieldByLegend(page, 'E-mail').fill(email);
    await fieldByLegend(page, 'Heslo').fill(password);
    await page.getByRole('button', { name: 'Prihlásenie' }).tap();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'));
}

test.describe('Table filters dropdown (Users list)', () => {
    /**
     * e2e-flow: admin opens the "Filtrovať" dropdown, adds a filter, then dismisses it
     * intent: regression for iOS WebKit tap not opening the CSS-only :focus-within dropdown
     * steps: login as admin -> go to /users -> tap "Filtrovať" -> assert open + options
     *   visible -> tap "Meno" option -> assert filter added + dropdown closed -> reopen ->
     *   tap outside -> assert closed
     */
    test('admin opens the filter dropdown, adds a filter, and dismisses it', async ({ page }) => {
        await login(page, 'admin@example.com', 'password');
        await page.goto('/users');

        const trigger = page.getByRole('button', { name: 'Filtrovať' });
        await trigger.tap();

        await expect(trigger).toHaveAttribute('aria-expanded', 'true');
        const listId = await trigger.getAttribute('aria-controls');
        const optionsList = page.locator(`#${listId}`);
        await expect(optionsList).toBeVisible();
        const nameOption = optionsList.getByRole('button', { name: 'Meno' });
        await expect(nameOption).toBeVisible();

        await nameOption.tap();

        await expect(trigger).toHaveAttribute('aria-expanded', 'false');
        await expect(page.getByPlaceholder('Meno')).toBeVisible();

        await trigger.tap();
        await expect(trigger).toHaveAttribute('aria-expanded', 'true');

        await page.locator('main').tap({ position: { x: 10, y: 10 } });

        await expect(trigger).toHaveAttribute('aria-expanded', 'false');
        await expect(optionsList).toBeHidden();
    });
});
