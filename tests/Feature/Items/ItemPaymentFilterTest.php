<?php

declare(strict_types=1);

namespace Tests\Feature\Items;

use App\Models\Expense;
use App\Models\Item;
use App\Models\ItemVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class ItemPaymentFilterTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    /** @return array{unpaid: Item, partial: Item, paid: Item, overpaid: Item, unpriced: Item} */
    private function seedItems(): array
    {
        $unpaid = Item::factory()->create(['name' => 'Unpaid', 'unit_price' => 100, 'quantity' => 1]);
        $partial = Item::factory()->create(['name' => 'Partial', 'unit_price' => 100, 'quantity' => 1]);
        $paid = Item::factory()->create(['name' => 'Paid', 'unit_price' => 100, 'quantity' => 1]);
        $overpaid = Item::factory()->create(['name' => 'Overpaid', 'unit_price' => 100, 'quantity' => 1]);
        $unpriced = Item::factory()->create(['name' => 'Unpriced', 'unit_price' => null, 'quantity' => 1]);

        Expense::factory()->forItem($partial)->create(['amount' => 40]);
        Expense::factory()->forItem($paid)->create(['amount' => 60]);
        Expense::factory()->forItem($paid)->create(['amount' => 40]);
        Expense::factory()->forItem($overpaid)->create(['amount' => 130]);
        Expense::factory()->forItem($unpriced)->create(['amount' => 10]);

        return compact('unpaid', 'partial', 'paid', 'overpaid', 'unpriced');
    }

    /** @return list<string> */
    private function webNames(string $query): array
    {
        $user = $this->userWithPermission('view items');
        $names = [];

        $this->withoutVite()->actingAs($user)->get('/items?'.$query)
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$names): void {
                $page->component('Items/Index');
                $names = collect($page->toArray()['props']['items']['data'])->pluck('name')->sort()->values()->all();
            });

        return $names;
    }

    public function test_web_filter_unpaid(): void
    {
        $this->seedItems();

        $this->assertSame(['Unpaid'], $this->webNames('filter[payment_status]=unpaid'));
    }

    public function test_web_filter_partial_includes_unpriced_item_with_payment(): void
    {
        $this->seedItems();

        $this->assertSame(['Partial', 'Unpriced'], $this->webNames('filter[payment_status]=partial'));
    }

    public function test_web_filter_paid_includes_overpaid(): void
    {
        $this->seedItems();

        $this->assertSame(['Overpaid', 'Paid'], $this->webNames('filter[payment_status]=paid'));
    }

    public function test_web_invalid_filter_value_is_ignored(): void
    {
        $this->seedItems();

        $this->assertCount(5, $this->webNames('filter[payment_status]=bogus'));
    }

    public function test_web_filter_options_contain_payment_statuses(): void
    {
        $user = $this->userWithPermission('view items');

        $this->withoutVite()->actingAs($user)->get('/items')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('filterOptions.payment_statuses', 3)
                ->where('filterOptions.payment_statuses.0.id', 'unpaid'),
            );
    }

    public function test_web_filter_combines_with_search(): void
    {
        $this->seedItems();

        $this->assertSame(['Overpaid'], $this->webNames('filter[payment_status]=paid&filter[search]=over'));
    }

    public function test_api_filter_returns_correct_paginator_totals(): void
    {
        $this->seedItems();
        Sanctum::actingAs($this->userWithPermission('view items'));

        $response = $this->getJson('/api/items?filter[payment_status]=paid&per_page=1');

        $response->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(1, 'data');
    }

    public function test_api_items_carry_payment_fields(): void
    {
        $this->seedItems();
        Sanctum::actingAs($this->userWithPermission('view items'));

        $row = collect($this->getJson('/api/items')->assertOk()->json('data'))->keyBy('name');

        $this->assertSame('unpaid', $row['Unpaid']['payment_status']);
        $this->assertEquals(100, $row['Unpaid']['remaining']);

        $this->assertSame('partial', $row['Partial']['payment_status']);
        $this->assertEquals(40, $row['Partial']['paid_total']);
        $this->assertEquals(60, $row['Partial']['remaining']);

        $this->assertSame('paid', $row['Paid']['payment_status']);
        $this->assertEquals(0, $row['Paid']['remaining']);

        $this->assertSame('paid', $row['Overpaid']['payment_status']);
        $this->assertEquals(30, $row['Overpaid']['overpaid']);
        $this->assertEquals(0, $row['Overpaid']['remaining']);

        $this->assertSame('partial', $row['Unpriced']['payment_status']);
        $this->assertNull($row['Unpriced']['remaining']);
        $this->assertEquals(0, $row['Unpriced']['overpaid']);
    }

    public function test_api_detail_carries_payments_newest_first(): void
    {
        $item = Item::factory()->create(['unit_price' => 100, 'quantity' => 1]);
        Expense::factory()->forItem($item)->create(['amount' => 10, 'paid_at' => '2026-10-01']);
        Expense::factory()->forItem($item)->create(['amount' => 20, 'paid_at' => '2026-10-05']);
        Sanctum::actingAs($this->userWithPermission('view items'));

        $response = $this->getJson("/api/items/{$item->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'payments')
            ->assertJsonPath('payments.0.paid_at', '2026-10-05')
            ->assertJsonPath('item.paid_total', 30)
            ->assertJsonPath('item.payment_status', 'partial');
    }

    public function test_show_prop_carries_payments_and_item_fields(): void
    {
        $item = Item::factory()->create(['unit_price' => 100, 'quantity' => 2]);
        Expense::factory()->forItem($item)->create(['amount' => 50, 'paid_at' => '2026-10-01']);
        Expense::factory()->forItem($item)->create(['amount' => 25, 'paid_at' => '2026-10-03']);
        $user = $this->userWithPermission('view items');

        $this->withoutVite()->actingAs($user)->get("/items/{$item->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Items/Show')
                ->has('payments', 2)
                ->where('payments.0.paid_at', '2026-10-03')
                ->where('item.paid_total', 75)
                ->where('item.remaining', 125)
                ->where('item.payment_status', 'partial'),
            );
    }

    public function test_selected_variant_price_drives_remaining(): void
    {
        $item = Item::factory()->create(['unit_price' => 100, 'quantity' => 2]);
        $variant = ItemVariant::factory()->create(['item_id' => $item->id, 'unit_price' => 150]);
        $this->actingAs($this->adminUser())->post("/items/{$item->id}/variants/{$variant->id}/select")->assertRedirect();
        Expense::factory()->forItem($item)->create(['amount' => 100]);
        Sanctum::actingAs($this->userWithPermission('view items'));

        $this->getJson("/api/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('item.total_price', 300)
            ->assertJsonPath('item.remaining', 200);
    }

    public function test_list_queries_do_not_grow_with_item_count(): void
    {
        $user = $this->userWithPermission('view items');
        Item::factory()->count(2)->create(['unit_price' => 10])->each(fn (Item $item) => Expense::factory()->forItem($item)->create());

        $this->withoutVite()->actingAs($user)->get('/items')->assertOk();

        DB::enableQueryLog();
        $this->withoutVite()->actingAs($user)->get('/items')->assertOk();
        $few = count(DB::getQueryLog());

        Item::factory()->count(6)->create(['unit_price' => 10])->each(fn (Item $item) => Expense::factory()->forItem($item)->create());
        DB::flushQueryLog();
        $this->withoutVite()->actingAs($user)->get('/items')->assertOk();
        $many = count(DB::getQueryLog());

        $this->assertSame($few, $many);
    }
}
