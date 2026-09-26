import { test, expect, type Page } from '@playwright/test';

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

test.describe('Price input', () => {
    /**
     * e2e-flow: typing a price key by key keeps exactly what was typed
     * intent: regression — the field reformatted to "x,00" on every keystroke, so after the
     *   first digit the caret jumped behind the decimals and further digits became cents
     * steps: open new item form -> type "1290" -> value stays "1290" -> blur -> "1290,00";
     *   type "12,5" -> blur -> "12,50"; the live total follows
     */
    test('keeps typed digits and formats only on blur', async ({ page }) => {
        await login(page, 'gostikvladko9@gmail.com', 'password');
        await page.goto('/items/create');

        const price = fieldByLegend(page, 'Cena za kus');

        await price.tap();
        await price.pressSequentially('1290');
        await expect(price).toHaveValue('1290');
        await expect(page.getByText('1 290,00 €')).toBeVisible();

        await fieldByLegend(page, 'Názov').tap();
        await expect(price).toHaveValue('1290,00');

        await price.fill('');
        await price.pressSequentially('12,5');
        await expect(price).toHaveValue('12,5');

        await fieldByLegend(page, 'Názov').tap();
        await expect(price).toHaveValue('12,50');
    });
});
