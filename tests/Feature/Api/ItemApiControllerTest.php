<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\ItemAllocation;
use App\Models\ItemVariant;
use App\Models\ItemVariantVote;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesUsers;
use Tests\Support\RefreshesModels;
use Tests\TestCase;

final class ItemApiControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;
    use RefreshesModels;

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_items_with_variant_summary(): void
    {
        $user = $this->userWithPermission('view items');
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        $item->update(['selected_variant_id' => $variant->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/items');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['*' => ['id', 'name', 'variants_count', 'selected_variant_id', 'selected_variant_name']],
            'total',
        ]);
        $response->assertJsonPath('data.0.variants_count', 1);
        $response->assertJsonPath('data.0.selected_variant_name', $variant->name);
    }

    public function test_index_filters_by_room_uuid(): void
    {
        $user = $this->userWithPermission('view items');
        $room = Room::factory()->create();
        Item::factory()->create(['room_id' => $room->id]);
        Item::factory()->create(['room_id' => null]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/items?filter[room]={$room->id}");

        $response->assertOk();
        $response->assertJsonPath('total', 1);
    }

    public function test_index_filters_by_room_house_for_items_without_room(): void
    {
        $user = $this->userWithPermission('view items');
        $room = Room::factory()->create();
        Item::factory()->create(['room_id' => $room->id]);
        Item::factory()->create(['room_id' => null]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/items?filter[room]=house');

        $response->assertOk();
        $response->assertJsonPath('total', 1);
    }

    public function test_index_exposes_multi_room_item_with_joined_room_names_and_summed_quantity(): void
    {
        $user = $this->userWithPermission('view items');
        $roomA = Room::factory()->create(['name' => 'Kúpeľňa', 'sort_order' => 0]);
        $roomB = Room::factory()->create(['name' => 'Kuchyňa', 'sort_order' => 1]);
        Item::factory()->allocatedTo([
            ['room_id' => $roomA->id, 'quantity' => 12.5],
            ['room_id' => $roomB->id, 'quantity' => 8],
        ])->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/items');

        $response->assertOk();
        $response->assertJsonPath('data.0.room_id', null);
        $response->assertJsonPath('data.0.room_name', 'Kúpeľňa, Kuchyňa');
        $response->assertJsonPath('data.0.quantity', 20.5);
        $response->assertJsonCount(2, 'data.0.allocations');

        $this->getJson("/api/items?filter[room]={$roomA->id}")->assertOk();
        $this->getJson("/api/items?filter[room]={$roomB->id}")->assertOk();
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/items');

        $response->assertUnauthorized();
    }

    public function test_index_is_forbidden_without_permission(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/items');

        $response->assertForbidden();
    }

    public function test_index_clamps_large_per_page_to_100(): void
    {
        $user = $this->userWithPermission('view items');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/items?per_page=500');

        $response->assertOk();
        $response->assertJsonPath('per_page', 100);
    }

    public function test_index_clamps_zero_per_page_to_1(): void
    {
        $user = $this->userWithPermission('view items');
        Item::factory()->count(2)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/items?per_page=0');

        $response->assertOk();
        $response->assertJsonPath('per_page', 1);
    }

    // ── show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_item_with_variants_and_comparison(): void
    {
        $user = $this->userWithPermission('view items');
        $item = Item::factory()->withVariantComparison()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        $item->update(['selected_variant_id' => $variant->id]);
        ItemVariantVote::factory()->create(['item_variant_id' => $variant->id, 'item_id' => $item->id, 'user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/items/{$item->id}");

        $response->assertOk();
        $response->assertJsonPath('item.id', $item->id);
        $response->assertJsonPath('variants.0.vote_count', 1);
        $response->assertJsonPath('variants.0.is_my_vote', true);
        $response->assertJsonPath('variants.0.is_selected', true);
        $response->assertJsonPath('comparison.text', $item->variant_comparison);
        $response->assertJsonPath('comparison.is_stale', false);
    }

    public function test_show_returns_null_comparison_when_none_saved(): void
    {
        $user = $this->userWithPermission('view items');
        $item = Item::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/items/{$item->id}");

        $response->assertOk();
        $response->assertJsonPath('comparison', null);
    }

    public function test_show_returns_404_for_nonexistent_item(): void
    {
        $user = $this->userWithPermission('view items');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/items/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    public function test_show_is_forbidden_without_permission(): void
    {
        $item = Item::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson("/api/items/{$item->id}");

        $response->assertForbidden();
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_item_with_defaults(): void
    {
        $user = $this->userWithPermission('create items');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/items', ['name' => 'Milk']);

        $response->assertCreated();
        $response->assertJsonPath('name', 'Milk');
        $response->assertJsonPath('status', 'planned');
        $response->assertJsonPath('priority', 'medium');
        $response->assertJsonPath('quantity', 1);
        $this->assertDatabaseHas('items', ['name' => 'Milk']);
    }

    public function test_store_with_legacy_room_and_quantity_creates_one_allocation(): void
    {
        $user = $this->userWithPermission('create items');
        $room = Room::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/items', ['name' => 'Milk', 'room_id' => $room->id, 'quantity' => 3]);

        $response->assertCreated();
        $item = Item::query()->where('name', 'Milk')->firstOrFail();
        $this->assertSame(1, $item->allocations()->count());
        $response->assertJsonPath('room_id', $room->id);
        $response->assertJsonPath('room_name', $room->name);
        $response->assertJsonPath('quantity', 3);
    }

    public function test_store_without_room_creates_whole_house_allocation(): void
    {
        $user = $this->userWithPermission('create items');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/items', ['name' => 'Milk']);

        $response->assertCreated();
        $item = Item::query()->where('name', 'Milk')->firstOrFail();
        $this->assertSame(1, $item->allocations()->count());
        $this->assertDatabaseHas('item_allocations', ['item_id' => $item->id, 'room_id' => null]);
    }

    public function test_store_with_decimal_legacy_quantity_is_accepted(): void
    {
        $user = $this->userWithPermission('create items');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/items', ['name' => 'Paint', 'quantity' => 2.5]);

        $response->assertCreated();
        $response->assertJsonPath('quantity', 2.5);
    }

    public function test_store_with_allocations_array_creates_item(): void
    {
        $user = $this->userWithPermission('create items');
        $room = Room::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/items', [
            'name' => 'Dlažba',
            'allocations' => [
                ['room_id' => $room->id, 'quantity' => 12.5],
                ['room_id' => null, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated();
        $item = Item::query()->where('name', 'Dlažba')->firstOrFail();
        $this->assertSame(2, $item->allocations()->count());
    }

    public function test_store_with_allocation_missing_quantity_returns_422(): void
    {
        $user = $this->userWithPermission('create items');
        $room = Room::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/items', [
            'name' => 'Dlažba',
            'allocations' => [['room_id' => $room->id]],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['allocations.0.quantity']);
    }

    public function test_store_with_both_allocations_and_legacy_room_id_returns_422(): void
    {
        $user = $this->userWithPermission('create items');
        $room = Room::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/items', [
            'name' => 'Dlažba',
            'room_id' => $room->id,
            'allocations' => [['room_id' => $room->id, 'quantity' => 1]],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['allocations']);
    }

    public function test_store_with_missing_name_returns_422(): void
    {
        $user = $this->userWithPermission('create items');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/items', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_store_with_unknown_room_id_returns_422(): void
    {
        $user = $this->userWithPermission('create items');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/items', [
            'name' => 'Milk',
            'room_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['room_id']);
    }

    public function test_store_with_negative_unit_price_returns_422(): void
    {
        $user = $this->userWithPermission('create items');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/items', ['name' => 'Milk', 'unit_price' => -5]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['unit_price']);
    }

    public function test_store_is_forbidden_without_permission(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/items', ['name' => 'Milk']);

        $response->assertForbidden();
    }

    // ── update (patch) ────────────────────────────────────────────────────────

    public function test_update_changes_only_the_sent_field(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create(['name' => 'Old name', 'note' => 'Keep me', 'unit_price' => 5]);
        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/items/{$item->id}", ['name' => 'New name']);

        $response->assertOk();
        $response->assertJsonPath('name', 'New name');
        $fresh = $this->refreshed($item);
        $this->assertSame('Keep me', $fresh->note);
        $this->assertSame('5.00', $fresh->unit_price);
        $this->assertSame(1, $fresh->allocations()->count());
    }

    public function test_update_via_put_unassigns_with_explicit_null(): void
    {
        $user = $this->userWithPermission('edit items');
        $assignee = User::factory()->create();
        $item = Item::factory()->create(['assigned_user_id' => $assignee->id]);
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/items/{$item->id}", ['assigned_user_id' => null]);

        $response->assertOk();
        $this->assertNull($this->refreshed($item)->assigned_user_id);
    }

    public function test_update_with_empty_body_returns_422(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/items/{$item->id}", []);

        $response->assertUnprocessable();
    }

    public function test_update_with_invalid_status_returns_422(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/items/{$item->id}", ['status' => 'not-a-status']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_update_unit_price_while_variant_selected_returns_422(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id, 'unit_price' => 10]);
        $item->update(['selected_variant_id' => $variant->id]);
        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/items/{$item->id}", ['unit_price' => 999]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['unit_price']);
    }

    public function test_update_is_forbidden_without_permission(): void
    {
        $item = Item::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->patchJson("/api/items/{$item->id}", ['name' => 'X']);

        $response->assertForbidden();
    }

    public function test_update_returns_404_for_unknown_item(): void
    {
        $user = $this->userWithPermission('edit items');
        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/items/00000000-0000-0000-0000-000000000000', ['name' => 'X']);

        $response->assertNotFound();
    }

    // ── allocations ───────────────────────────────────────────────────────────

    public function test_update_allocations_replaces_rooms_and_quantities(): void
    {
        $user = $this->userWithPermission('edit items');
        $roomA = Room::factory()->create();
        $roomB = Room::factory()->create();
        $item = Item::factory()->create();
        Sanctum::actingAs($user);

        $payload = ['allocations' => [
            ['room_id' => $roomA->id, 'quantity' => 12.5],
            ['room_id' => $roomB->id, 'quantity' => 8],
        ]];

        $response = $this->putJson("/api/items/{$item->id}/allocations", $payload);
        $response->assertOk();
        $this->assertSame(2, $item->allocations()->count());

        // Repeating the same payload is idempotent — same two rows, same totals.
        $response = $this->putJson("/api/items/{$item->id}/allocations", $payload);
        $response->assertOk();
        $this->assertSame(2, $this->refreshed($item)->allocations()->count());
        $response->assertJsonPath('quantity', 20.5);
    }

    public function test_update_allocations_with_missing_quantity_returns_422(): void
    {
        $user = $this->userWithPermission('edit items');
        $room = Room::factory()->create();
        $item = Item::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/items/{$item->id}/allocations", ['allocations' => [
            ['room_id' => $room->id],
        ]]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['allocations.0.quantity']);
    }

    public function test_update_allocations_with_duplicate_room_returns_422(): void
    {
        $user = $this->userWithPermission('edit items');
        $room = Room::factory()->create();
        $item = Item::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/items/{$item->id}/allocations", ['allocations' => [
            ['room_id' => $room->id, 'quantity' => 1],
            ['room_id' => $room->id, 'quantity' => 2],
        ]]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['allocations']);
    }

    public function test_update_allocations_requires_authentication(): void
    {
        $item = Item::factory()->create();

        $response = $this->putJson("/api/items/{$item->id}/allocations", ['allocations' => [['room_id' => null, 'quantity' => 1]]]);

        $response->assertUnauthorized();
    }

    public function test_update_allocations_is_forbidden_without_permission(): void
    {
        $item = Item::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->putJson("/api/items/{$item->id}/allocations", ['allocations' => [['room_id' => null, 'quantity' => 1]]]);

        $response->assertForbidden();
    }

    public function test_update_allocations_returns_404_for_unknown_item(): void
    {
        $user = $this->userWithPermission('edit items');
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/items/00000000-0000-0000-0000-000000000000/allocations', ['allocations' => [['room_id' => null, 'quantity' => 1]]]);

        $response->assertNotFound();
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_deletes_item_variants_and_allocations(): void
    {
        $user = $this->userWithPermission('delete items');
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        $allocationId = $item->allocations()->firstOrFail()->id;
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/items/{$item->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
        $this->assertDatabaseMissing('item_variants', ['id' => $variant->id]);
        $this->assertDatabaseMissing('item_allocations', ['item_id' => $item->id]);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => (new ItemAllocation)->getMorphClass(),
            'subject_id' => $allocationId,
            'event' => 'deleted',
        ]);
    }

    public function test_destroy_is_forbidden_without_delete_items_permission(): void
    {
        // Same web rule: edit items is not enough — destroy requires delete items.
        $item = Item::factory()->create();
        Sanctum::actingAs($this->userWithPermission('edit items'));

        $response = $this->deleteJson("/api/items/{$item->id}");

        $response->assertForbidden();
    }

    public function test_destroy_returns_404_for_unknown_item(): void
    {
        $user = $this->userWithPermission('delete items');
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/items/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    // ── updateVariantComparison ──────────────────────────────────────────────

    public function test_update_variant_comparison_saves_text_and_clears_stale(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->withVariantComparison(stale: true)->create();
        ItemVariant::factory()->count(2)->create(['item_id' => $item->id]);
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/items/{$item->id}/variant-comparison", ['text' => 'New comparison text']);

        $response->assertOk();
        $response->assertJsonPath('text', 'New comparison text');
        $response->assertJsonPath('is_stale', false);
        $fresh = $this->refreshed($item);
        $this->assertSame('New comparison text', $fresh->variant_comparison);
        $this->assertFalse($fresh->variant_comparison_is_stale);
        $this->assertNotNull($fresh->variant_comparison_generated_at);
    }

    public function test_update_variant_comparison_overwrites_and_bumps_generated_at(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->withVariantComparison()->create(['variant_comparison_generated_at' => now()->subDay()]);
        ItemVariant::factory()->count(2)->create(['item_id' => $item->id]);
        $originalGeneratedAt = $item->variant_comparison_generated_at;
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/items/{$item->id}/variant-comparison", ['text' => 'Second version']);

        $response->assertOk();
        $fresh = $this->refreshed($item);
        $this->assertNotNull($originalGeneratedAt);
        $this->assertNotNull($fresh->variant_comparison_generated_at);
        $this->assertSame('Second version', $fresh->variant_comparison);
        $this->assertTrue($fresh->variant_comparison_generated_at->greaterThan($originalGeneratedAt));
    }

    public function test_update_variant_comparison_with_no_variants_returns_422(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/items/{$item->id}/variant-comparison", ['text' => 'Text']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['text']);
    }

    public function test_update_variant_comparison_with_one_variant_returns_422(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        ItemVariant::factory()->create(['item_id' => $item->id]);
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/items/{$item->id}/variant-comparison", ['text' => 'Text']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['text']);
    }

    public function test_update_variant_comparison_with_empty_text_returns_422(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        ItemVariant::factory()->count(2)->create(['item_id' => $item->id]);
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/items/{$item->id}/variant-comparison", ['text' => '']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['text']);
    }

    public function test_update_variant_comparison_with_text_over_max_length_returns_422(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        ItemVariant::factory()->count(2)->create(['item_id' => $item->id]);
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/items/{$item->id}/variant-comparison", ['text' => str_repeat('a', 20001)]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['text']);
    }

    public function test_update_variant_comparison_is_forbidden_without_permission(): void
    {
        $item = Item::factory()->create();
        ItemVariant::factory()->count(2)->create(['item_id' => $item->id]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->putJson("/api/items/{$item->id}/variant-comparison", ['text' => 'Text']);

        $response->assertForbidden();
    }
}
