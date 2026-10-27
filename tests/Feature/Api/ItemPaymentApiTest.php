<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Expense;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class ItemPaymentApiTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_payments_walk_unpaid_partial_paid_overpaid(): void
    {
        Sanctum::actingAs($this->userWithPermission('view items', 'edit items'));
        $item = Item::factory()->create(['unit_price' => 5000, 'quantity' => 1]);

        $this->getJson("/api/items/{$item->id}/payments")
            ->assertOk()
            ->assertJsonPath('payments', [])
            ->assertJsonPath('summary.payment_status', 'unpaid')
            ->assertJsonPath('summary.remaining', 5000);

        $this->postJson("/api/items/{$item->id}/payments", ['amount' => 2000, 'paid_at' => today()->toDateString()])->assertCreated();
        $second = $this->postJson("/api/items/{$item->id}/payments", ['amount' => 1200, 'paid_at' => today()->toDateString()]);

        $second->assertCreated()
            ->assertJsonPath('item_summary.payment_status', 'partial')
            ->assertJsonPath('item_summary.paid_total', 3200)
            ->assertJsonPath('item_summary.remaining', 1800)
            ->assertJsonPath('warning', null)
            ->assertJsonPath('expense.type', 'item')
            ->assertJsonPath('expense.item_id', $item->id);

        $this->postJson("/api/items/{$item->id}/payments", ['amount' => 1800, 'paid_at' => today()->toDateString()])
            ->assertCreated()
            ->assertJsonPath('item_summary.payment_status', 'paid')
            ->assertJsonPath('item_summary.remaining', 0)
            ->assertJsonPath('warning', null);

        $over = $this->postJson("/api/items/{$item->id}/payments", ['amount' => 200, 'paid_at' => today()->toDateString()]);

        $over->assertCreated()
            ->assertJsonPath('item_summary.payment_status', 'paid')
            ->assertJsonPath('item_summary.overpaid', 200)
            ->assertJsonPath('item_summary.remaining', 0);
        $this->assertNotNull($over->json('warning'));

        $this->getJson("/api/items/{$item->id}/payments")->assertJsonCount(4, 'payments');
    }

    public function test_unpriced_item_is_partial_with_null_remaining(): void
    {
        Sanctum::actingAs($this->userWithPermission('view items', 'edit items'));
        $item = Item::factory()->create(['unit_price' => null]);

        $this->postJson("/api/items/{$item->id}/payments", ['amount' => 100, 'paid_at' => today()->toDateString()])
            ->assertCreated()
            ->assertJsonPath('item_summary.payment_status', 'partial')
            ->assertJsonPath('item_summary.remaining', null)
            ->assertJsonPath('item_summary.overpaid', 0)
            ->assertJsonPath('warning', null);
    }

    public function test_selected_variant_price_drives_remaining(): void
    {
        $user = $this->userWithPermission('view items', 'edit items');
        Sanctum::actingAs($user);
        $item = Item::factory()->create(['unit_price' => 100, 'quantity' => 2]);
        $variant = ItemVariant::factory()->create(['item_id' => $item->id, 'unit_price' => 150]);

        $this->postJson("/api/items/{$item->id}/variants/{$variant->id}/select")->assertOk();
        $this->postJson("/api/items/{$item->id}/payments", ['amount' => 100, 'paid_at' => today()->toDateString()])
            ->assertCreated()
            ->assertJsonPath('item_summary.total_price', 300)
            ->assertJsonPath('item_summary.remaining', 200);
    }

    public function test_store_records_creator_and_never_a_title(): void
    {
        $user = $this->userWithPermission('edit items');
        Sanctum::actingAs($user);
        $item = Item::factory()->create();

        $this->postJson("/api/items/{$item->id}/payments", ['amount' => 10, 'paid_at' => today()->toDateString(), 'title' => 'x'])->assertCreated();

        $expense = Expense::query()->firstOrFail();
        $this->assertSame($user->id, $expense->created_by);
        $this->assertNull($expense->title);
    }

    public function test_paid_at_today_ok_and_tomorrow_rejected(): void
    {
        Sanctum::actingAs($this->userWithPermission('edit items'));
        $item = Item::factory()->create();

        $this->postJson("/api/items/{$item->id}/payments", ['amount' => 10, 'paid_at' => today()->addDay()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('paid_at');
        $this->postJson("/api/items/{$item->id}/payments", ['amount' => 10, 'paid_at' => today()->toDateString()])->assertCreated();
    }

    public function test_future_paid_at_message_is_localized(): void
    {
        $user = $this->userWithPermission('edit items');
        $user->update(['locale' => 'sk']);
        Sanctum::actingAs($user);
        $item = Item::factory()->create();

        $this->postJson("/api/items/{$item->id}/payments", ['amount' => 10, 'paid_at' => today()->addDay()->toDateString()])
            ->assertJsonValidationErrors(['paid_at' => 'Dátum platby musí byť dnes alebo skôr.']);
    }

    /** @return array<string, array{mixed}> */
    public static function badAmounts(): array
    {
        return ['zero' => [0], 'negative' => [-5], 'three decimals' => [1.005], 'missing' => [null], 'too large' => [100000000]];
    }

    #[DataProvider('badAmounts')]
    public function test_invalid_amount_is_rejected(mixed $amount): void
    {
        Sanctum::actingAs($this->userWithPermission('edit items'));
        $item = Item::factory()->create();

        $this->postJson("/api/items/{$item->id}/payments", ['amount' => $amount, 'paid_at' => today()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('amount');
    }

    public function test_missing_paid_at_is_rejected(): void
    {
        Sanctum::actingAs($this->userWithPermission('edit items'));
        $item = Item::factory()->create();

        $this->postJson("/api/items/{$item->id}/payments", ['amount' => 10])
            ->assertUnprocessable()->assertJsonValidationErrors('paid_at');
    }

    public function test_view_only_user_can_list_but_not_create(): void
    {
        Sanctum::actingAs($this->userWithPermission('view items'));
        $item = Item::factory()->create();

        $this->getJson("/api/items/{$item->id}/payments")->assertOk();
        $this->postJson("/api/items/{$item->id}/payments", ['amount' => 10, 'paid_at' => today()->toDateString()])->assertForbidden();
    }

    public function test_guest_gets_401_and_unknown_item_404(): void
    {
        $item = Item::factory()->create();

        $this->getJson("/api/items/{$item->id}/payments")->assertUnauthorized();

        Sanctum::actingAs($this->userWithPermission('view items'));
        $this->getJson('/api/items/0195d123-0000-7000-0000-000000000099/payments')->assertNotFound();
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $item = Item::factory()->create();

        $this->getJson("/api/items/{$item->id}/payments")->assertForbidden();
    }
}
