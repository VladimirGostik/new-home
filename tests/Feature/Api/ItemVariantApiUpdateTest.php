<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesUsers;
use Tests\Support\RefreshesModels;
use Tests\TestCase;

final class ItemVariantApiUpdateTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;
    use RefreshesModels;

    // ── update (patch) ────────────────────────────────────────────────────────

    public function test_update_changes_only_name_leaving_price_url_and_photo_untouched(): void
    {
        Storage::fake('public');
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id, 'unit_price' => 10, 'url' => 'https://old.test']);
        $variant->addMedia(UploadedFile::fake()->image('v.jpg'))->toMediaCollection('photo');
        $originalUuid = $this->firstMedia($variant, 'photo')->uuid;
        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/items/{$item->id}/variants/{$variant->id}", ['name' => 'New name']);

        $response->assertOk();
        $response->assertJsonPath('name', 'New name');
        $fresh = $this->refreshed($variant);
        $this->assertSame('10.00', $fresh->unit_price);
        $this->assertSame('https://old.test', $fresh->url);
        $this->assertSame($originalUuid, $this->firstMedia($fresh, 'photo')->uuid);
    }

    public function test_update_price_of_selected_variant_mirrors_onto_item_and_marks_comparison_stale(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->withVariantComparison()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id, 'unit_price' => 10]);
        $item->update(['selected_variant_id' => $variant->id, 'unit_price' => 10]);
        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/items/{$item->id}/variants/{$variant->id}", ['unit_price' => 25]);

        $response->assertOk();
        $this->assertSame('25.00', $this->refreshed($item)->unit_price);
        $this->assertTrue($this->refreshed($item)->variant_comparison_is_stale);
    }

    public function test_update_with_empty_body_returns_422(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/items/{$item->id}/variants/{$variant->id}", []);

        $response->assertUnprocessable();
    }

    public function test_update_is_forbidden_without_permission(): void
    {
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->patchJson("/api/items/{$item->id}/variants/{$variant->id}", ['name' => 'X']);

        $response->assertForbidden();
    }

    public function test_update_returns_404_for_variant_belonging_to_another_item(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        $otherItem = Item::factory()->create();
        $foreignVariant = ItemVariant::factory()->create(['item_id' => $otherItem->id]);
        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/items/{$item->id}/variants/{$foreignVariant->id}", ['name' => 'X']);

        $response->assertNotFound();
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_deletes_variant_and_marks_comparison_stale(): void
    {
        $user = $this->userWithPermission('delete items');
        $item = Item::factory()->withVariantComparison()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        ItemVariant::factory()->create(['item_id' => $item->id]);
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/items/{$item->id}/variants/{$variant->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('item_variants', ['id' => $variant->id]);
        $this->assertTrue($this->refreshed($item)->variant_comparison_is_stale);
    }

    public function test_destroy_is_forbidden_without_delete_items_permission(): void
    {
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        Sanctum::actingAs($this->userWithPermission('edit items'));

        $response = $this->deleteJson("/api/items/{$item->id}/variants/{$variant->id}");

        $response->assertForbidden();
    }

    public function test_destroy_returns_404_for_variant_belonging_to_another_item(): void
    {
        $user = $this->userWithPermission('delete items');
        $item = Item::factory()->create();
        $otherItem = Item::factory()->create();
        $foreignVariant = ItemVariant::factory()->create(['item_id' => $otherItem->id]);
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/items/{$item->id}/variants/{$foreignVariant->id}");

        $response->assertNotFound();
    }

    // ── select ────────────────────────────────────────────────────────────────

    public function test_select_mirrors_variant_price_onto_item(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create(['unit_price' => null]);
        $variant = ItemVariant::factory()->create(['item_id' => $item->id, 'unit_price' => 42]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/items/{$item->id}/variants/{$variant->id}/select");

        $response->assertOk();
        $response->assertJsonPath('item.unit_price', 42);
        $this->assertSame($variant->id, $this->refreshed($item)->selected_variant_id);
    }

    public function test_select_is_forbidden_without_permission(): void
    {
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson("/api/items/{$item->id}/variants/{$variant->id}/select");

        $response->assertForbidden();
    }

    public function test_select_returns_404_for_variant_belonging_to_another_item(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        $otherItem = Item::factory()->create();
        $foreignVariant = ItemVariant::factory()->create(['item_id' => $otherItem->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/items/{$item->id}/variants/{$foreignVariant->id}/select");

        $response->assertNotFound();
    }

    // ── unselect ──────────────────────────────────────────────────────────────

    public function test_unselect_clears_item_price_and_url(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id, 'unit_price' => 42, 'url' => 'https://variant.test']);
        $item->update(['selected_variant_id' => $variant->id, 'unit_price' => 42, 'url' => 'https://variant.test']);
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/items/{$item->id}/selected-variant");

        $response->assertOk();
        $fresh = $this->refreshed($item);
        $this->assertNull($fresh->selected_variant_id);
        $this->assertNull($fresh->unit_price);
        $this->assertNull($fresh->url);
    }

    public function test_unselect_is_forbidden_without_permission(): void
    {
        $item = Item::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->deleteJson("/api/items/{$item->id}/selected-variant");

        $response->assertForbidden();
    }

    public function test_unselect_returns_404_for_unknown_item(): void
    {
        $user = $this->userWithPermission('edit items');
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/items/00000000-0000-0000-0000-000000000000/selected-variant');

        $response->assertNotFound();
    }
}
