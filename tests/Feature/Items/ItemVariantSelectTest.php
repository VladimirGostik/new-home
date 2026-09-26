<?php

declare(strict_types=1);

namespace Tests\Feature\Items;

use App\Enums\ItemPriority;
use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\ItemVariantVote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\CreatesUsers;
use Tests\Support\RefreshesModels;
use Tests\TestCase;

final class ItemVariantSelectTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;
    use RefreshesModels;

    public function test_select_copies_price_url_and_photo_without_touching_status_or_assignee(): void
    {
        $user = $this->userWithPermission('edit items');
        $assignee = User::factory()->create();
        $item = Item::factory()->create([
            'assigned_user_id' => $assignee->id,
            'status' => ItemStatus::Planned,
            'priority' => ItemPriority::High,
        ]);
        $variant = ItemVariant::factory()->create([
            'item_id' => $item->id,
            'unit_price' => 42.5,
            'url' => 'https://example.test/product',
        ]);
        $variant->addMedia(UploadedFile::fake()->image('sofa.jpg'))->toMediaCollection('photo');

        $response = $this->actingAs($user)->post("/items/{$item->id}/variants/{$variant->id}/select");

        $response->assertRedirect();
        $fresh = $this->refreshed($item);
        $this->assertSame($variant->id, $fresh->selected_variant_id);
        $this->assertSame('42.50', $fresh->unit_price);
        $this->assertSame('https://example.test/product', $fresh->url);
        $this->assertSame(ItemStatus::Planned, $fresh->status);
        $this->assertSame($assignee->id, $fresh->assigned_user_id);

        $itemPhoto = $fresh->getFirstMedia('photo');
        $variantPhoto = $this->refreshed($variant)->getFirstMedia('photo');
        $this->assertNotNull($itemPhoto);
        $this->assertNotNull($variantPhoto);
        $this->assertNotSame($itemPhoto->id, $variantPhoto->id);
        $this->assertNotSame($itemPhoto->uuid, $variantPhoto->uuid);
    }

    public function test_selecting_another_variant_switches_selection_and_recopies_including_nulls(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        $first = ItemVariant::factory()->create(['item_id' => $item->id, 'unit_price' => 10, 'url' => 'https://a.test']);
        $first->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('photo');
        $second = ItemVariant::factory()->create(['item_id' => $item->id, 'unit_price' => null, 'url' => null]);

        $this->actingAs($user)->post("/items/{$item->id}/variants/{$first->id}/select")->assertRedirect();
        $response = $this->actingAs($user)->post("/items/{$item->id}/variants/{$second->id}/select");

        $response->assertRedirect();
        $fresh = $this->refreshed($item);
        $this->assertSame($second->id, $fresh->selected_variant_id);
        $this->assertNull($fresh->unit_price);
        $this->assertNull($fresh->url);
        $this->assertNull($fresh->getFirstMedia('photo'));
    }

    public function test_reselecting_same_variant_is_idempotent(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id, 'unit_price' => 20]);
        $variant->addMedia(UploadedFile::fake()->image('v.jpg'))->toMediaCollection('photo');

        $this->actingAs($user)->post("/items/{$item->id}/variants/{$variant->id}/select")->assertRedirect();
        $this->actingAs($user)->post("/items/{$item->id}/variants/{$variant->id}/select")->assertRedirect();

        $fresh = $this->refreshed($item);
        $this->assertSame($variant->id, $fresh->selected_variant_id);
        $this->assertCount(1, $fresh->getMedia('photo'));
    }

    public function test_unselect_clears_selection_and_item_fields_but_keeps_variant_photo(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create(['status' => ItemStatus::Bought]);
        $variant = ItemVariant::factory()->create(['item_id' => $item->id, 'unit_price' => 30, 'url' => 'https://b.test']);
        $variant->addMedia(UploadedFile::fake()->image('v.jpg'))->toMediaCollection('photo');
        $this->actingAs($user)->post("/items/{$item->id}/variants/{$variant->id}/select")->assertRedirect();
        $other = ItemVariant::factory()->create(['item_id' => $item->id]);
        $vote = ItemVariantVote::factory()->create(['item_variant_id' => $variant->id, 'item_id' => $item->id]);

        $response = $this->actingAs($user)->delete("/items/{$item->id}/selected-variant");

        $response->assertRedirect();
        $fresh = $this->refreshed($item);
        $this->assertNull($fresh->selected_variant_id);
        $this->assertNull($fresh->unit_price);
        $this->assertNull($fresh->url);
        $this->assertNull($fresh->getFirstMedia('photo'));
        $this->assertNotNull($this->refreshed($variant)->getFirstMedia('photo'));
        $this->assertModelExists($other);
        $this->assertModelExists($vote);
        $this->assertSame(ItemStatus::Bought, $fresh->status);
    }

    public function test_unselect_when_nothing_selected_is_a_noop(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create(['unit_price' => 15, 'url' => 'https://manual.test']);
        $item->addMedia(UploadedFile::fake()->image('manual.jpg'))->toMediaCollection('photo');

        $response = $this->actingAs($user)->delete("/items/{$item->id}/selected-variant");

        $response->assertRedirect();
        $fresh = $this->refreshed($item);
        $this->assertSame('15.00', $fresh->unit_price);
        $this->assertSame('https://manual.test', $fresh->url);
        $this->assertNotNull($fresh->getFirstMedia('photo'));
    }

    public function test_unselect_after_selected_variant_was_deleted_is_a_noop(): void
    {
        $user = $this->userWithPermission('edit items', 'delete items');
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id, 'unit_price' => 60, 'url' => 'https://c.test']);
        $this->actingAs($user)->post("/items/{$item->id}/variants/{$variant->id}/select")->assertRedirect();
        $this->actingAs($user)->delete("/items/{$item->id}/variants/{$variant->id}")->assertRedirect();
        $this->assertNull($this->refreshed($item)->selected_variant_id);

        $response = $this->actingAs($user)->delete("/items/{$item->id}/selected-variant");

        $response->assertRedirect();
        $fresh = $this->refreshed($item);
        $this->assertSame('60.00', $fresh->unit_price);
        $this->assertSame('https://c.test', $fresh->url);
    }

    public function test_unselect_is_forbidden_without_edit_items_permission(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->delete("/items/{$item->id}/selected-variant");

        $response->assertForbidden();
    }

    public function test_votes_and_unselected_variants_are_unaffected_by_select(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        $chosen = ItemVariant::factory()->create(['item_id' => $item->id]);
        $other = ItemVariant::factory()->create(['item_id' => $item->id]);
        $vote = ItemVariantVote::factory()->create(['item_variant_id' => $other->id, 'item_id' => $item->id]);

        $this->actingAs($user)->post("/items/{$item->id}/variants/{$chosen->id}/select")->assertRedirect();

        $this->assertModelExists($other);
        $this->assertModelExists($vote);
    }

    public function test_select_is_forbidden_without_edit_items_permission(): void
    {
        $user = User::factory()->create();
        $variant = ItemVariant::factory()->create();

        $response = $this->actingAs($user)->post("/items/{$variant->item_id}/variants/{$variant->id}/select");

        $response->assertForbidden();
    }

    public function test_select_with_foreign_variant_returns_not_found(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        $foreignVariant = ItemVariant::factory()->create();

        $response = $this->actingAs($user)->post("/items/{$item->id}/variants/{$foreignVariant->id}/select");

        $response->assertNotFound();
    }
}
