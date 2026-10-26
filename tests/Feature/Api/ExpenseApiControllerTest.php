<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Expense;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class ExpenseApiControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_index_returns_paginated_rows_with_totals_over_whole_filtered_set(): void
    {
        Sanctum::actingAs($this->userWithPermission('view items'));
        $item = Item::factory()->create(['name' => 'Sedačka']);
        Expense::factory()->forItem($item)->create(['amount' => 100, 'paid_at' => '2026-10-03']);
        Expense::factory()->create(['title' => 'Notár', 'amount' => 50, 'paid_at' => '2026-10-10']);
        Expense::factory()->create(['title' => 'Iné', 'amount' => 25, 'paid_at' => '2026-09-01']);

        $response = $this->getJson('/api/expenses?per_page=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('total', 3)
            ->assertJsonPath('data.0.display_name', 'Notár')
            ->assertJsonPath('totals.total', 175)
            ->assertJsonPath('totals.items_total', 100)
            ->assertJsonPath('totals.manual_total', 75)
            ->assertJsonPath('totals.count', 3);
    }

    public function test_index_filters(): void
    {
        Sanctum::actingAs($this->userWithPermission('view items'));
        $item = Item::factory()->create(['name' => 'Sedačka']);
        $other = Item::factory()->create(['name' => 'Stôl']);
        Expense::factory()->forItem($item)->create(['amount' => 100, 'paid_at' => '2026-10-03']);
        Expense::factory()->forItem($other)->create(['amount' => 10, 'paid_at' => '2026-10-04']);
        Expense::factory()->create(['title' => 'Notár', 'amount' => 50, 'paid_at' => '2026-10-10']);

        $this->getJson('/api/expenses?filter[type]=other')->assertJsonPath('totals.total', 50)->assertJsonCount(1, 'data');
        $this->getJson('/api/expenses?filter[type]=item')->assertJsonPath('totals.total', 110);
        $this->getJson('/api/expenses?filter[search]=notár')->assertJsonCount(1, 'data');
        $this->getJson('/api/expenses?filter[search]=sedač')->assertJsonPath('data.0.item_id', $item->id);
        $this->getJson("/api/expenses?filter[item]={$other->id}")->assertJsonPath('totals.total', 10);
        $this->getJson('/api/expenses?filter[item]=not-a-uuid')->assertJsonCount(0, 'data');
        $this->getJson('/api/expenses?filter[from]=2026-10-04&filter[to]=2026-10-09')->assertJsonCount(1, 'data')->assertJsonPath('totals.count', 1);
        $this->getJson('/api/expenses?filter[type]=bogus')->assertJsonCount(3, 'data');
    }

    public function test_index_ignores_impossible_calendar_dates(): void
    {
        Sanctum::actingAs($this->userWithPermission('view items'));
        Expense::factory()->create(['amount' => 50, 'paid_at' => '2026-10-10']);

        $this->getJson('/api/expenses?filter[from]=2026-02-30')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/expenses?filter[to]=2026-13-45')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/expenses?filter[from]=2026-13-45&filter[to]=2026-02-30')->assertOk()->assertJsonPath('totals.count', 1);
    }

    public function test_index_per_page_is_clamped(): void
    {
        Sanctum::actingAs($this->userWithPermission('view items'));
        Expense::factory()->count(3)->create();

        $this->getJson('/api/expenses?per_page=0')->assertJsonPath('per_page', 1);
        $this->getJson('/api/expenses?per_page=500')->assertJsonPath('per_page', 100);
    }

    public function test_show_returns_expense(): void
    {
        Sanctum::actingAs($this->userWithPermission('view items'));
        $expense = Expense::factory()->create(['title' => 'Notár']);

        $this->getJson("/api/expenses/{$expense->id}")->assertOk()->assertJsonPath('title', 'Notár')->assertJsonPath('type', 'other');
        $this->getJson('/api/expenses/0195d123-0000-7000-0000-000000000099')->assertNotFound();
    }

    public function test_store_creates_standalone_expense(): void
    {
        $user = $this->userWithPermission('edit items');
        Sanctum::actingAs($user);

        $this->postJson('/api/expenses', ['title' => 'Notár', 'amount' => 150.5, 'paid_at' => today()->toDateString(), 'note' => 'poplatok'])
            ->assertCreated()
            ->assertJsonPath('expense.title', 'Notár')
            ->assertJsonPath('expense.type', 'other')
            ->assertJsonPath('item_summary', null)
            ->assertJsonPath('warning', null);

        $this->assertSame($user->id, Expense::query()->firstOrFail()->created_by);
    }

    public function test_store_validation(): void
    {
        Sanctum::actingAs($this->userWithPermission('edit items'));
        $valid = ['title' => 'x', 'amount' => 10, 'paid_at' => today()->toDateString()];

        $this->postJson('/api/expenses', [...$valid, 'title' => null])->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->postJson('/api/expenses', [...$valid, 'amount' => 0])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson('/api/expenses', [...$valid, 'amount' => 1.005])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson('/api/expenses', [...$valid, 'paid_at' => today()->addDay()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('paid_at');
        $this->postJson('/api/expenses', [...$valid, 'paid_at' => null])->assertUnprocessable()->assertJsonValidationErrors('paid_at');
        $this->postJson('/api/expenses', [...$valid, 'title' => str_repeat('a', 256)])->assertUnprocessable()->assertJsonValidationErrors('title');
    }

    public function test_update_patches_standalone_expense(): void
    {
        Sanctum::actingAs($this->userWithPermission('edit items'));
        $expense = Expense::factory()->create(['title' => 'Old', 'amount' => 10, 'note' => 'keep']);

        $this->patchJson("/api/expenses/{$expense->id}", ['title' => 'New', 'amount' => 20])
            ->assertOk()
            ->assertJsonPath('expense.title', 'New')
            ->assertJsonPath('expense.amount', 20)
            ->assertJsonPath('expense.note', 'keep');

        $this->putJson("/api/expenses/{$expense->id}", ['note' => null])->assertOk()->assertJsonPath('expense.note', null);
    }

    public function test_update_item_payment_amount_returns_summary_and_warning(): void
    {
        Sanctum::actingAs($this->userWithPermission('edit items'));
        $item = Item::factory()->create(['unit_price' => 100, 'quantity' => 1]);
        $payment = Expense::factory()->forItem($item)->create(['amount' => 50]);

        $response = $this->patchJson("/api/expenses/{$payment->id}", ['amount' => 130]);

        $response->assertOk()->assertJsonPath('item_summary.overpaid', 30);
        $this->assertNotNull($response->json('warning'));
    }

    public function test_update_rejects_title_on_item_payment(): void
    {
        Sanctum::actingAs($this->userWithPermission('edit items'));
        $payment = Expense::factory()->forItem(Item::factory()->create())->create();

        $this->patchJson("/api/expenses/{$payment->id}", ['title' => 'Nope'])
            ->assertUnprocessable()->assertJsonValidationErrors('title');
    }

    public function test_update_with_empty_body_is_rejected(): void
    {
        Sanctum::actingAs($this->userWithPermission('edit items'));
        $expense = Expense::factory()->create();

        $this->patchJson("/api/expenses/{$expense->id}", [])->assertUnprocessable();
    }

    public function test_update_validates_amount_and_date(): void
    {
        Sanctum::actingAs($this->userWithPermission('edit items'));
        $expense = Expense::factory()->create();

        $this->patchJson("/api/expenses/{$expense->id}", ['amount' => -5])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->patchJson("/api/expenses/{$expense->id}", ['paid_at' => today()->addDay()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('paid_at');
    }

    public function test_destroy_deletes_payment_and_standalone(): void
    {
        Sanctum::actingAs($this->userWithPermission('edit items'));
        $payment = Expense::factory()->forItem(Item::factory()->create())->create();
        $standalone = Expense::factory()->create();

        $this->deleteJson("/api/expenses/{$payment->id}")->assertNoContent();
        $this->deleteJson("/api/expenses/{$standalone->id}")->assertNoContent();

        $this->assertSame(0, Expense::query()->count());
    }

    public function test_view_only_user_can_read_but_not_write(): void
    {
        Sanctum::actingAs($this->userWithPermission('view items'));
        $expense = Expense::factory()->create();
        $payment = Expense::factory()->forItem(Item::factory()->create())->create();

        $this->getJson('/api/expenses')->assertOk();
        $this->postJson('/api/expenses', ['title' => 'x', 'amount' => 1, 'paid_at' => today()->toDateString()])->assertForbidden();
        $this->patchJson("/api/expenses/{$expense->id}", ['amount' => 5])->assertForbidden();
        $this->patchJson("/api/expenses/{$payment->id}", ['amount' => 5])->assertForbidden();
        $this->deleteJson("/api/expenses/{$expense->id}")->assertForbidden();
        $this->deleteJson("/api/expenses/{$payment->id}")->assertForbidden();
    }

    public function test_guest_gets_401(): void
    {
        $expense = Expense::factory()->create();

        $this->getJson('/api/expenses')->assertUnauthorized();
        $this->getJson("/api/expenses/{$expense->id}")->assertUnauthorized();
        $this->postJson('/api/expenses', [])->assertUnauthorized();
        $this->deleteJson("/api/expenses/{$expense->id}")->assertUnauthorized();
    }

    public function test_user_without_permissions_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/expenses')->assertForbidden();
    }
}
