<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Item;
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
