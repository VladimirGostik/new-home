<?php

declare(strict_types=1);

namespace Tests\Feature\Items;

use App\Enums\ItemPriority;
use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class MyItemsTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_shows_only_items_assigned_to_current_user_with_summary(): void
    {
        $user = $this->userWithPermission('view items');
        $other = User::factory()->create();

        Item::factory()->create(['assigned_user_id' => $user->id, 'unit_price' => 20, 'quantity' => 2, 'status' => ItemStatus::Bought]);
        Item::factory()->create(['assigned_user_id' => $user->id, 'unit_price' => null]);
        Item::factory()->create(['assigned_user_id' => $other->id, 'unit_price' => 999]);
        Item::factory()->create(['assigned_user_id' => null, 'unit_price' => 999]);

        $this->withoutVite()->actingAs($user)->get('/my-items')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Items/Mine')
                ->has('items', 2)
                ->where('summary.items_count', 2)
                ->where('summary.unpriced_count', 1)
                ->where('summary.bought_total', 40)
                ->where('summary.remaining_total', 0),
            );
    }

    public function test_orders_planned_first_then_by_priority(): void
    {
        $user = $this->userWithPermission('view items');
        $mine = ['assigned_user_id' => $user->id];

        Item::factory()->create([...$mine, 'name' => 'Bought high', 'status' => ItemStatus::Bought, 'priority' => ItemPriority::High]);
        Item::factory()->create([...$mine, 'name' => 'Planned low', 'status' => ItemStatus::Planned, 'priority' => ItemPriority::Low]);
        Item::factory()->create([...$mine, 'name' => 'Planned high', 'status' => ItemStatus::Planned, 'priority' => ItemPriority::High]);

        $this->withoutVite()->actingAs($user)->get('/my-items')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('items.0.name', 'Planned high')
                ->where('items.1.name', 'Planned low')
                ->where('items.2.name', 'Bought high'),
            );
    }

    public function test_items_index_uses_shopping_order_by_default(): void
    {
        $user = $this->userWithPermission('view items');

        Item::factory()->create(['name' => 'B bought', 'status' => ItemStatus::Bought, 'priority' => ItemPriority::High]);
        Item::factory()->create(['name' => 'A medium', 'status' => ItemStatus::Planned, 'priority' => ItemPriority::Medium]);
        Item::factory()->create(['name' => 'C high', 'status' => ItemStatus::Planned, 'priority' => ItemPriority::High]);

        $this->withoutVite()->actingAs($user)->get('/items')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('items.data.0.name', 'C high')
                ->where('items.data.1.name', 'A medium')
                ->where('items.data.2.name', 'B bought'),
            );

        $this->withoutVite()->actingAs($user)->get('/items?sort=name')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('items.data.0.name', 'A medium'));
    }

    public function test_requires_view_items_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/my-items')->assertForbidden();
    }
}
