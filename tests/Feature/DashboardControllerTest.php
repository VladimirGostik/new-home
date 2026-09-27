<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class DashboardControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_totals_multiply_price_by_quantity_and_skip_unpriced_items(): void
    {
        $user = $this->adminUser();

        Item::factory()->create(['unit_price' => 100.00, 'quantity' => 2, 'status' => ItemStatus::Planned]);
        Item::factory()->create(['unit_price' => 50.25, 'quantity' => 1, 'status' => ItemStatus::Bought]);
        Item::factory()->create(['unit_price' => null, 'quantity' => 3, 'status' => ItemStatus::Planned]);

        $this->withoutVite()->actingAs($user)->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('dashboard.totals.items_count', 3)
                ->where('dashboard.totals.bought_count', 1)
                ->where('dashboard.totals.unpriced_count', 1)
                ->where('dashboard.totals.total', 250.25)
                ->where('dashboard.totals.bought_total', 50.25)
                ->where('dashboard.totals.remaining_total', 200)
                ->where('dashboard.unassigned_count', 3),
            );
    }

    public function test_rooms_are_listed_in_order_with_whole_house_last(): void
    {
        $user = $this->adminUser();
        $kitchen = Room::factory()->create(['name' => 'Kuchyňa', 'sort_order' => 1]);
        $living = Room::factory()->create(['name' => 'Obývačka', 'sort_order' => 0]);

        Item::factory()->create(['room_id' => $kitchen->id, 'unit_price' => 10, 'quantity' => 3]);
        Item::factory()->create(['room_id' => null, 'unit_price' => 7.5, 'quantity' => 2, 'status' => ItemStatus::Bought]);

        $this->withoutVite()->actingAs($user)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('dashboard.rooms', 3)
                ->where('dashboard.rooms.0.id', $living->id)
                ->where('dashboard.rooms.0.summary.items_count', 0)
                ->where('dashboard.rooms.0.summary.total', 0)
                ->where('dashboard.rooms.1.id', $kitchen->id)
                ->where('dashboard.rooms.1.summary.total', 30)
                ->where('dashboard.rooms.2.id', null)
                ->where('dashboard.rooms.2.name', 'Celý dom')
                ->where('dashboard.rooms.2.summary.bought_total', 15),
            );
    }

    public function test_people_show_bought_and_remaining_amounts_plus_unassigned_row(): void
    {
        $user = $this->adminUser();
        $user->update(['name' => 'Anna']);
        User::factory()->create(['name' => 'Bob']); // nothing assigned → not listed

        Item::factory()->create(['assigned_user_id' => $user->id, 'unit_price' => 100, 'quantity' => 1, 'status' => ItemStatus::Bought]);
        Item::factory()->create(['assigned_user_id' => $user->id, 'unit_price' => 40, 'quantity' => 2, 'status' => ItemStatus::Planned]);
        Item::factory()->create(['assigned_user_id' => null, 'unit_price' => 5, 'quantity' => 1]);

        $this->withoutVite()->actingAs($user)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('dashboard.people', 2)
                ->where('dashboard.people.0.name', 'Anna')
                ->where('dashboard.people.0.summary.items_count', 2)
                ->where('dashboard.people.0.summary.bought_total', 100)
                ->where('dashboard.people.0.summary.remaining_total', 80)
                ->where('dashboard.people.1.id', null)
                ->where('dashboard.people.1.name', 'Nepriradené')
                ->where('dashboard.people.1.summary.total', 5)
                ->missing('dashboard.people.2'),
            );
    }

    public function test_empty_plan_returns_zero_totals_and_no_unassigned_row(): void
    {
        $user = $this->adminUser();

        $this->withoutVite()->actingAs($user)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dashboard.totals.total', 0)
                ->where('dashboard.totals.items_count', 0)
                ->has('dashboard.people', 0)
                ->has('dashboard.rooms', 1),
            );
    }

    public function test_priced_variants_on_unpriced_item_do_not_affect_totals(): void
    {
        $user = $this->adminUser();
        $item = Item::factory()->create(['unit_price' => null, 'quantity' => 2]);
        ItemVariant::factory()->create(['item_id' => $item->id, 'unit_price' => 150]);

        $this->withoutVite()->actingAs($user)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dashboard.totals.items_count', 1)
                ->where('dashboard.totals.unpriced_count', 1)
                ->where('dashboard.totals.total', 0),
            );
    }

    public function test_totals_include_copied_price_after_variant_is_selected(): void
    {
        $user = $this->adminUser();
        $item = Item::factory()->create(['unit_price' => null, 'quantity' => 2]);
        $variant = ItemVariant::factory()->create(['item_id' => $item->id, 'unit_price' => 150]);
        $item->update(['selected_variant_id' => $variant->id, 'unit_price' => $variant->unit_price]);

        $this->withoutVite()->actingAs($user)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dashboard.totals.unpriced_count', 0)
                ->where('dashboard.totals.total', 300),
            );
    }

    public function test_multi_room_item_total_equals_sum_of_its_room_shares(): void
    {
        $user = $this->adminUser();
        $roomA = Room::factory()->create(['sort_order' => 0]);
        $roomB = Room::factory()->create(['sort_order' => 1]);
        $roomC = Room::factory()->create(['sort_order' => 2]);
        Item::factory()->allocatedTo([
            ['room_id' => $roomA->id, 'quantity' => 12],
            ['room_id' => $roomB->id, 'quantity' => 8],
            ['room_id' => $roomC->id, 'quantity' => 6],
        ])->create(['unit_price' => 24.90]);

        $this->withoutVite()->actingAs($user)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dashboard.totals.items_count', 1)
                ->where('dashboard.totals.total', 647.40)
                ->where('dashboard.rooms.0.summary.total', 298.80)
                ->where('dashboard.rooms.1.summary.total', 199.20)
                ->where('dashboard.rooms.2.summary.total', 149.40),
            );
    }

    public function test_rounds_each_allocation_line_before_summing_not_the_total_quantity(): void
    {
        $user = $this->adminUser();
        $roomA = Room::factory()->create();
        $roomB = Room::factory()->create();
        Item::factory()->allocatedTo([
            ['room_id' => $roomA->id, 'quantity' => 0.33],
            ['room_id' => $roomB->id, 'quantity' => 0.33],
        ])->create(['unit_price' => 0.99]);

        // round(0.99 × 0.33, 2) = 0.33 per line, twice = 0.66 — not round(0.99 × 0.66, 2) = 0.65.
        $this->withoutVite()->actingAs($user)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('dashboard.totals.total', 0.66));
    }

    public function test_person_summary_counts_multi_room_item_once(): void
    {
        $user = $this->adminUser();
        $roomA = Room::factory()->create();
        $roomB = Room::factory()->create();
        Item::factory()->allocatedTo([
            ['room_id' => $roomA->id, 'quantity' => 2],
            ['room_id' => $roomB->id, 'quantity' => 3],
        ])->create(['unit_price' => 10, 'assigned_user_id' => $user->id]);

        $this->withoutVite()->actingAs($user)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dashboard.people.0.summary.items_count', 1)
                ->where('dashboard.people.0.summary.total', 50),
            );
    }

    public function test_unpriced_multi_room_item_is_counted_once(): void
    {
        $user = $this->adminUser();
        $roomA = Room::factory()->create();
        $roomB = Room::factory()->create();
        Item::factory()->allocatedTo([
            ['room_id' => $roomA->id, 'quantity' => 2],
            ['room_id' => $roomB->id, 'quantity' => 3],
        ])->create(['unit_price' => null]);

        $this->withoutVite()->actingAs($user)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dashboard.totals.items_count', 1)
                ->where('dashboard.totals.unpriced_count', 1)
                ->where('dashboard.totals.total', 0),
            );
    }
}
