<?php

declare(strict_types=1);

namespace Tests\Feature\Expenses;

use App\Models\Expense;
use App\Models\Item;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class ExpenseControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private function seedOctober(): Item
    {
        $item = Item::factory()->create(['name' => 'Sedačka']);
        Expense::factory()->forItem($item)->create(['amount' => 100, 'paid_at' => '2026-10-03']);
        Expense::factory()->create(['title' => 'Notár', 'amount' => 50, 'paid_at' => '2026-10-10']);
        Expense::factory()->create(['title' => 'Septembrový', 'amount' => 999, 'paid_at' => '2026-09-20']);

        return $item;
    }

    public function test_index_lists_month_with_totals_split(): void
    {
        $this->seedOctober();
        $user = $this->userWithPermission('view items');

        $this->withoutVite()->actingAs($user)->get('/expenses?month=2026-10')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Expenses/Index')
                ->where('month', '2026-10')
                ->has('expenses', 2)
                ->where('expenses.0.display_name', 'Notár')
                ->where('expenses.1.display_name', 'Sedačka')
                ->where('totals.total', 150)
                ->where('totals.items_total', 100)
                ->where('totals.manual_total', 50)
                ->where('totals.count', 2)
                ->where('filters.month', '2026-10')
                ->has('filterOptions.types', 2),
            );
    }

    public function test_index_month_switch_shows_other_month(): void
    {
        $this->seedOctober();
        $user = $this->userWithPermission('view items');

        $this->withoutVite()->actingAs($user)->get('/expenses?month=2026-09')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('expenses', 1)
                ->where('totals.total', 999),
            );
    }

    public function test_index_invalid_or_missing_month_falls_back_to_current(): void
    {
        $user = $this->userWithPermission('view items');
        $current = CarbonImmutable::now()->format('Y-m');

        $this->withoutVite()->actingAs($user)->get('/expenses?month=garbage')
            ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('month', $current));
        $this->withoutVite()->actingAs($user)->get('/expenses?month=2026-13')
            ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('month', $current));
        $this->withoutVite()->actingAs($user)->get('/expenses')
            ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('month', $current));
    }

    public function test_index_filters_by_type_and_search(): void
    {
        $this->seedOctober();
        $user = $this->userWithPermission('view items');

        $this->withoutVite()->actingAs($user)->get('/expenses?month=2026-10&filter[type]=other')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('expenses', 1)->where('expenses.0.title', 'Notár')->where('totals.total', 50));
        $this->withoutVite()->actingAs($user)->get('/expenses?month=2026-10&filter[search]=notár')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('expenses', 1)->where('filters.search', 'notár'));
        $this->withoutVite()->actingAs($user)->get('/expenses?month=2026-10&filter[search]=sedač')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('expenses', 1)->where('expenses.0.type', 'item'));
        $this->withoutVite()->actingAs($user)->get('/expenses?month=2026-10&filter[search]=nothing')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('expenses', 0)->where('totals.count', 0)->where('totals.total', 0));
    }

    public function test_index_requires_permission(): void
    {
        $this->get('/expenses')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/expenses')->assertForbidden();
    }

    public function test_store_creates_standalone_expense(): void
    {
        $user = $this->userWithPermission('view items', 'edit items');

        $this->actingAs($user)->from('/expenses')
            ->post('/expenses', ['title' => 'Notár', 'amount' => 150, 'paid_at' => today()->toDateString(), 'note' => null])
            ->assertRedirect('/expenses')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('expenses', ['title' => 'Notár', 'item_id' => null, 'created_by' => $user->id]);
    }

    public function test_store_validates_and_requires_edit_permission(): void
    {
        $editor = $this->userWithPermission('edit items');

        $this->actingAs($editor)->post('/expenses', ['amount' => 150, 'paid_at' => today()->toDateString()])->assertSessionHasErrors('title');
        $this->actingAs($editor)->post('/expenses', ['title' => 'x', 'amount' => 0, 'paid_at' => today()->toDateString()])->assertSessionHasErrors('amount');
        $this->actingAs($this->userWithPermission('view items'))
            ->post('/expenses', ['title' => 'x', 'amount' => 1, 'paid_at' => today()->toDateString()])->assertForbidden();
    }

    public function test_store_payment_creates_item_expense(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();

        $this->actingAs($user)->post("/items/{$item->id}/payments", ['amount' => 25, 'paid_at' => today()->toDateString()])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('expenses', ['item_id' => $item->id, 'title' => null, 'created_by' => $user->id]);
    }

    public function test_store_payment_forbidden_without_edit_permission(): void
    {
        $item = Item::factory()->create();

        $this->actingAs($this->userWithPermission('view items'))
            ->post("/items/{$item->id}/payments", ['amount' => 25, 'paid_at' => today()->toDateString()])
            ->assertForbidden();
    }

    public function test_update_changes_expense_and_rejects_title_on_payment(): void
    {
        $user = $this->userWithPermission('edit items');
        $expense = Expense::factory()->create(['title' => 'Old', 'amount' => 10]);
        $payment = Expense::factory()->forItem(Item::factory()->create())->create(['amount' => 10]);

        $this->actingAs($user)->put("/expenses/{$expense->id}", ['title' => 'New', 'amount' => 30, 'paid_at' => today()->toDateString(), 'note' => null])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'title' => 'New', 'amount' => 30]);

        $this->actingAs($user)->put("/expenses/{$payment->id}", ['amount' => 40, 'paid_at' => today()->toDateString(), 'note' => 'n'])->assertRedirect();
        $this->assertDatabaseHas('expenses', ['id' => $payment->id, 'amount' => 40, 'note' => 'n']);

        $this->actingAs($user)->put("/expenses/{$payment->id}", ['title' => 'x', 'amount' => 40])->assertSessionHasErrors('title');
    }

    public function test_update_and_destroy_forbidden_for_view_only(): void
    {
        $user = $this->userWithPermission('view items');
        $expense = Expense::factory()->create();

        $this->actingAs($user)->put("/expenses/{$expense->id}", ['amount' => 5])->assertForbidden();
        $this->actingAs($user)->delete("/expenses/{$expense->id}")->assertForbidden();
    }

    public function test_destroy_deletes_payment_and_standalone_with_edit_permission_only(): void
    {
        $user = $this->userWithPermission('edit items');
        $expense = Expense::factory()->create();
        $payment = Expense::factory()->forItem(Item::factory()->create())->create();

        $this->actingAs($user)->delete("/expenses/{$expense->id}")->assertRedirect()->assertSessionHas('success');
        $this->actingAs($user)->delete("/expenses/{$payment->id}")->assertRedirect();

        $this->assertSame(0, Expense::query()->count());
    }

    public function test_destroy_unknown_expense_is_404(): void
    {
        $user = $this->userWithPermission('edit items');

        $this->actingAs($user)->delete('/expenses/0195d123-0000-7000-0000-000000000099')->assertNotFound();
    }
}
