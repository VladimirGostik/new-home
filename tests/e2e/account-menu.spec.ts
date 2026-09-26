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

test.describe('Account menu (mobile top bar)', () => {
    /**
     * e2e-flow: admin reaches Users via the account menu and creates + deletes a user
     * intent: regression for iOS WebKit tap not opening the CSS-only :focus-within dropdown
     * steps: login as admin -> tap account menu -> tap "Používatelia" -> tap "Vytvoriť" ->
     *   fill + save a new user -> assert it appears in the list -> delete it via the row action
     */
    test('admin navigates to Users through the account menu and manages a user', async ({ page }) => {
        await login(page, 'admin@example.com', 'password');

        const trigger = page.getByRole('button', { name: 'Menu účtu' });
        await trigger.tap();
        await expect(trigger).toHaveAttribute('aria-expanded', 'true');

        await page.getByRole('link', { name: 'Používatelia' }).tap();

        await expect(page).toHaveURL(/\/users$/);
        await expect(trigger).toHaveAttribute('aria-expanded', 'false');

        await page.getByRole('link', { name: 'Vytvoriť' }).tap();
        await expect(page).toHaveURL(/\/users\/create$/);

        const email = `e2e-account-menu-${Date.now()}@example.com`;
        await fieldByLegend(page, 'Meno').fill('E2E Account Menu User');
        await fieldByLegend(page, 'E-mail').fill(email);
        await fieldByLegend(page, 'Heslo').first().fill('password123');
        await fieldByLegend(page, 'Potvrdiť heslo').fill('password123');

        await expect(page.getByRole('checkbox', { name: 'Člen rodiny' })).toBeChecked();

        await page.getByRole('button', { name: 'Uložiť' }).tap();
        await expect(page).toHaveURL(/\/users$/);

        const row = page.getByRole('row', { name: new RegExp(email) });
        await expect(row).toBeVisible();

        await row.getByTitle('Odstrániť').tap();
        await page.getByRole('button', { name: 'Áno' }).tap();
        await expect(row).not.toBeAttached();
    });

    /**
     * e2e-flow: family member sees a role-scoped menu and can dismiss it by tapping outside
     * intent: regression for the same WebKit :focus-within bug on the non-admin menu contents
     * steps: login as family member -> tap account menu -> assert "Profil" shown and
     *   "Používatelia" absent -> tap "Profil" -> reopen menu -> tap outside -> assert closed
     */
    test('family member sees Profil but not Používatelia, and outside tap closes the menu', async ({ page }) => {
        await login(page, 'gostikvladko9@gmail.com', 'password');

        const trigger = page.getByRole('button', { name: 'Menu účtu' });
        await trigger.tap();
        await expect(trigger).toHaveAttribute('aria-expanded', 'true');

        const profileLink = page.getByRole('link', { name: 'Profil' });
        await expect(profileLink).toBeVisible();
        await expect(page.getByRole('link', { name: 'Používatelia' })).toHaveCount(0);

        await profileLink.tap();
        await expect(page).toHaveURL(/\/profile$/);

        await trigger.tap();
        await expect(trigger).toHaveAttribute('aria-expanded', 'true');
        await expect(page.getByRole('link', { name: 'Profil' })).toBeVisible();

        await page.locator('main').tap({ position: { x: 10, y: 10 } });

        await expect(trigger).toHaveAttribute('aria-expanded', 'false');
        await expect(page.getByRole('link', { name: 'Profil' })).toBeHidden();
    });
});
