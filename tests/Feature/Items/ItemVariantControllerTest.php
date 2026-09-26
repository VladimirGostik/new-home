<?php

declare(strict_types=1);

namespace Tests\Feature\Items;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\ItemVariantVote;
use App\Models\TemporaryUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesUsers;
use Tests\Support\RefreshesModels;
use Tests\TestCase;

final class ItemVariantControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;
    use RefreshesModels;

    private function ownedPhotoUuid(User $user): string
    {
        Storage::fake('public');
        $upload = TemporaryUpload::factory()->create(['user_id' => $user->id]);

        return $upload->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('default')->uuid;
    }

    public function test_store_creates_variant_with_name_only(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->post("/items/{$item->id}/variants", ['name' => 'Ikea sofa']);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('item_variants', ['item_id' => $item->id, 'name' => 'Ikea sofa']);
    }

    public function test_store_creates_variant_with_price_url_and_photo(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        $uuid = $this->ownedPhotoUuid($user);

        $response = $this->actingAs($user)->post("/items/{$item->id}/variants", [
            'name' => 'Ikea sofa',
            'unit_price' => 199.99,
            'url' => 'https://example.test/sofa',
            'photo_uuid' => $uuid,
        ]);

        $response->assertRedirect();
        $variant = ItemVariant::query()->where('item_id', $item->id)->firstOrFail();
        $this->assertSame('199.99', $variant->unit_price);
        $this->assertSame('https://example.test/sofa', $variant->url);
        $this->assertNotNull($variant->getFirstMedia('photo'));
    }

    public function test_store_with_missing_name_returns_validation_error(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->post("/items/{$item->id}/variants", ['name' => '']);

        $response->assertInvalid(['name']);
    }

    public function test_store_with_invalid_url_returns_validation_error(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->post("/items/{$item->id}/variants", ['name' => 'Sofa', 'url' => 'not-a-url']);

        $response->assertInvalid(['url']);
    }

    public function test_store_with_negative_price_returns_validation_error(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->post("/items/{$item->id}/variants", ['name' => 'Sofa', 'unit_price' => -5]);

        $response->assertInvalid(['unit_price']);
    }

    public function test_store_with_foreign_temporary_upload_returns_validation_error(): void
    {
        $user = $this->userWithPermission('edit items');
        $other = User::factory()->create();
        $item = Item::factory()->create();
        $uuid = $this->ownedPhotoUuid($other);

        $response = $this->actingAs($user)->post("/items/{$item->id}/variants", ['name' => 'Sofa', 'photo_uuid' => $uuid]);

        $response->assertInvalid(['photo_uuid']);
    }

    public function test_store_is_forbidden_without_edit_items_permission(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->post("/items/{$item->id}/variants", ['name' => 'Sofa']);

        $response->assertForbidden();
    }

    public function test_update_changes_variant_fields(): void
    {
        $user = $this->userWithPermission('edit items');
        $variant = ItemVariant::factory()->create(['name' => 'Old name']);

        $response = $this->actingAs($user)->put("/items/{$variant->item_id}/variants/{$variant->id}", [
            'name' => 'New name',
            'unit_price' => 50,
        ]);

        $response->assertRedirect();
        $this->assertSame('New name', $this->refreshed($variant)->name);
        $this->assertSame('50.00', $this->refreshed($variant)->unit_price);
    }

    public function test_update_with_remove_photo_clears_photo(): void
    {
        $user = $this->userWithPermission('edit items');
        $variant = ItemVariant::factory()->create();
        $variant->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection('photo');

        $response = $this->actingAs($user)->put("/items/{$variant->item_id}/variants/{$variant->id}", [
            'name' => $variant->name,
            'remove_photo' => true,
        ]);

        $response->assertRedirect();
        $this->assertNull($this->refreshed($variant)->getFirstMedia('photo'));
    }

    public function test_update_with_new_photo_replaces_existing_photo(): void
    {
        $user = $this->userWithPermission('edit items');
        $variant = ItemVariant::factory()->create();
        $variant->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection('photo');
        $oldMediaId = $this->firstMedia($variant, 'photo')->id;
        $uuid = $this->ownedPhotoUuid($user);

        $response = $this->actingAs($user)->put("/items/{$variant->item_id}/variants/{$variant->id}", [
            'name' => $variant->name,
            'photo_uuid' => $uuid,
        ]);

        $response->assertRedirect();
        $fresh = $this->refreshed($variant)->getFirstMedia('photo');
        $this->assertNotNull($fresh);
        $this->assertNotSame($oldMediaId, $fresh->id);
    }

    public function test_update_variant_of_another_item_under_item_returns_not_found(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        $foreignVariant = ItemVariant::factory()->create();

        $response = $this->actingAs($user)->put("/items/{$item->id}/variants/{$foreignVariant->id}", ['name' => 'X']);

        $response->assertNotFound();
    }

    public function test_update_of_selected_variant_resyncs_item_price_and_url(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create(['unit_price' => 10, 'url' => 'https://old.test']);
        $variant = ItemVariant::factory()->create(['item_id' => $item->id, 'unit_price' => 10, 'url' => 'https://old.test']);
        $item->update(['selected_variant_id' => $variant->id]);

        $response = $this->actingAs($user)->put("/items/{$item->id}/variants/{$variant->id}", [
            'name' => $variant->name,
            'unit_price' => 77,
            'url' => 'https://new.test',
        ]);

        $response->assertRedirect();
        $fresh = $this->refreshed($item);
        $this->assertSame('77.00', $fresh->unit_price);
        $this->assertSame('https://new.test', $fresh->url);
    }

    public function test_update_of_selected_variant_resyncs_item_photo_with_own_media_copy(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        $item->update(['selected_variant_id' => $variant->id]);
        $uuid = $this->ownedPhotoUuid($user);

        $response = $this->actingAs($user)->put("/items/{$item->id}/variants/{$variant->id}", [
            'name' => $variant->name,
            'photo_uuid' => $uuid,
        ]);

        $response->assertRedirect();
        $itemMedia = $this->refreshed($item)->getFirstMedia('photo');
        $variantMedia = $this->refreshed($variant)->getFirstMedia('photo');
        $this->assertNotNull($itemMedia);
        $this->assertNotNull($variantMedia);
        $this->assertNotSame($itemMedia->id, $variantMedia->id);
    }

    public function test_update_of_selected_variant_remove_photo_clears_item_photo(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        $variant->addMedia(UploadedFile::fake()->image('v.jpg'))->toMediaCollection('photo');
        $item->update(['selected_variant_id' => $variant->id]);
        $this->firstMedia($variant, 'photo')->copy($item, 'photo');

        $response = $this->actingAs($user)->put("/items/{$item->id}/variants/{$variant->id}", [
            'name' => $variant->name,
            'remove_photo' => true,
        ]);

        $response->assertRedirect();
        $this->assertNull($this->refreshed($item)->getFirstMedia('photo'));
    }

    public function test_update_of_selected_variant_with_null_price_and_url_clears_item_fields(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create(['unit_price' => 10, 'url' => 'https://old.test']);
        $variant = ItemVariant::factory()->create(['item_id' => $item->id, 'unit_price' => 10, 'url' => 'https://old.test']);
        $item->update(['selected_variant_id' => $variant->id]);

        $response = $this->actingAs($user)->put("/items/{$item->id}/variants/{$variant->id}", ['name' => $variant->name]);

        $response->assertRedirect();
        $fresh = $this->refreshed($item);
        $this->assertNull($fresh->unit_price);
        $this->assertNull($fresh->url);
    }

    public function test_update_of_non_selected_variant_leaves_item_untouched(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create(['unit_price' => 10, 'url' => 'https://old.test']);
        $selected = ItemVariant::factory()->create(['item_id' => $item->id]);
        $item->update(['selected_variant_id' => $selected->id]);
        $other = ItemVariant::factory()->create(['item_id' => $item->id, 'unit_price' => 5]);

        $response = $this->actingAs($user)->put("/items/{$item->id}/variants/{$other->id}", [
            'name' => $other->name,
            'unit_price' => 999,
        ]);

        $response->assertRedirect();
        $fresh = $this->refreshed($item);
        $this->assertSame('10.00', $fresh->unit_price);
        $this->assertSame('https://old.test', $fresh->url);
    }

    public function test_update_is_forbidden_without_edit_items_permission(): void
    {
        $user = User::factory()->create();
        $variant = ItemVariant::factory()->create();

        $response = $this->actingAs($user)->put("/items/{$variant->item_id}/variants/{$variant->id}", ['name' => 'X']);

        $response->assertForbidden();
    }

    public function test_destroy_removes_variant_votes_and_media(): void
    {
        $user = $this->userWithPermission('delete items');
        $variant = ItemVariant::factory()->create();
        $variant->addMedia(UploadedFile::fake()->image('v.jpg'))->toMediaCollection('photo');
        ItemVariantVote::factory()->create(['item_variant_id' => $variant->id, 'item_id' => $variant->item_id]);

        $response = $this->actingAs($user)->delete("/items/{$variant->item_id}/variants/{$variant->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('item_variants', ['id' => $variant->id]);
        $this->assertDatabaseMissing('item_variant_votes', ['item_variant_id' => $variant->id]);
        $this->assertDatabaseMissing('media', ['model_type' => (new ItemVariant)->getMorphClass(), 'model_id' => $variant->id]);
    }

    public function test_destroy_of_selected_variant_nulls_selection_and_keeps_item_fields(): void
    {
        $user = $this->userWithPermission('delete items');
        $item = Item::factory()->create(['unit_price' => 10, 'url' => 'https://old.test']);
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        $item->update(['selected_variant_id' => $variant->id]);

        $response = $this->actingAs($user)->delete("/items/{$item->id}/variants/{$variant->id}");

        $response->assertRedirect();
        $fresh = $this->refreshed($item);
        $this->assertNull($fresh->selected_variant_id);
        $this->assertSame('10.00', $fresh->unit_price);
        $this->assertSame('https://old.test', $fresh->url);
    }

    public function test_destroy_is_forbidden_without_delete_items_permission(): void
    {
        $user = User::factory()->create();
        $variant = ItemVariant::factory()->create();

        $response = $this->actingAs($user)->delete("/items/{$variant->item_id}/variants/{$variant->id}");

        $response->assertForbidden();
    }

    public function test_show_renders_variants_with_vote_and_selection_metadata(): void
    {
        $user = $this->userWithPermission('view items');
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        $item->update(['selected_variant_id' => $variant->id]);
        ItemVariantVote::factory()->create(['item_variant_id' => $variant->id, 'item_id' => $item->id, 'user_id' => $user->id]);

        $response = $this->withoutVite()->actingAs($user)->get("/items/{$item->id}");

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Items/Show')
            ->has('variants', 1)
            ->where('variants.0.vote_count', 1)
            ->where('variants.0.voter_names', [$user->name])
            ->where('variants.0.is_my_vote', true)
            ->where('variants.0.is_selected', true),
        );
    }

    public function test_show_orders_voter_names_locale_aware_for_slovak_diacritics(): void
    {
        $user = $this->userWithPermission('view items');
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        $zofia = User::factory()->create(['name' => 'Žofia']);
        $adam = User::factory()->create(['name' => 'Adam']);
        $lubo = User::factory()->create(['name' => 'Ľubo']);
        foreach ([$zofia, $adam, $lubo] as $voter) {
            ItemVariantVote::factory()->create(['item_variant_id' => $variant->id, 'item_id' => $item->id, 'user_id' => $voter->id]);
        }

        $response = $this->withoutVite()->actingAs($user)->get("/items/{$item->id}");

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('variants.0.voter_names', ['Adam', 'Ľubo', 'Žofia']),
        );
    }

    public function test_show_is_forbidden_without_view_items_permission(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->get("/items/{$item->id}");

        $response->assertForbidden();
    }
}
