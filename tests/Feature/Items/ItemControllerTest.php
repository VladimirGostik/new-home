<?php

declare(strict_types=1);

namespace Tests\Feature\Items;

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\ItemVariantVote;
use App\Models\Room;
use App\Models\TemporaryUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesUsers;
use Tests\Support\RefreshesModels;
use Tests\TestCase;

final class ItemControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;
    use RefreshesModels;

    public function test_index_is_accessible_with_view_items_permission(): void
    {
        $user = $this->userWithPermission('view items');
        Item::factory()->count(3)->create();

        $response = $this->withoutVite()->actingAs($user)->get('/items');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Items/Index')
            ->has('items.data', 3)
            ->has('filters')
            ->has('filterOptions'),
        );
    }

    public function test_index_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/items');

        $response->assertForbidden();
    }

    public function test_index_filters_by_room_house_returns_items_without_room(): void
    {
        $user = $this->userWithPermission('view items');
        $room = Room::factory()->create();
        Item::factory()->create(['room_id' => $room->id]);
        Item::factory()->create(['room_id' => null]);

        $response = $this->withoutVite()->actingAs($user)->get('/items?filter[room]=house');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('items.data', 1));
    }

    public function test_index_filters_by_room_id_returns_items_in_that_room(): void
    {
        $user = $this->userWithPermission('view items');
        $room = Room::factory()->create();
        Item::factory()->create(['room_id' => $room->id]);
        Item::factory()->create(['room_id' => null]);

        $response = $this->withoutVite()->actingAs($user)->get("/items?filter[room]={$room->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('items.data', 1));
    }

    public function test_index_filters_by_assignee_me_returns_items_assigned_to_current_user(): void
    {
        $user = $this->userWithPermission('view items');
        $other = User::factory()->create();
        Item::factory()->create(['assigned_user_id' => $user->id]);
        Item::factory()->create(['assigned_user_id' => $other->id]);

        $response = $this->withoutVite()->actingAs($user)->get('/items?filter[assignee]=me');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('items.data', 1));
    }

    public function test_index_filters_by_assignee_none_returns_unassigned_items(): void
    {
        $user = $this->userWithPermission('view items');
        Item::factory()->create(['assigned_user_id' => $user->id]);
        Item::factory()->create(['assigned_user_id' => null]);

        $response = $this->withoutVite()->actingAs($user)->get('/items?filter[assignee]=none');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('items.data', 1));
    }

    public function test_create_is_accessible_with_create_items_permission(): void
    {
        $user = $this->userWithPermission('create items');

        $response = $this->withoutVite()->actingAs($user)->get('/items/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Items/Form'));
    }

    public function test_create_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/items/create');

        $response->assertForbidden();
    }

    public function test_store_creates_item_and_redirects_with_success(): void
    {
        $user = $this->userWithPermission('create items');

        $response = $this->actingAs($user)->post('/items', [
            'name' => 'Milk',
            'quantity' => 2,
            'unit_price' => 1.5,
        ]);

        $response->assertRedirect(route('items.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('items', ['name' => 'Milk', 'quantity' => 2]);
    }

    public function test_store_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/items', ['name' => 'Milk']);

        $response->assertForbidden();
    }

    public function test_store_with_missing_name_returns_validation_error(): void
    {
        $user = $this->userWithPermission('create items');

        $response = $this->actingAs($user)->post('/items', ['name' => '']);

        $response->assertInvalid(['name']);
    }

    public function test_store_attaches_photo_from_owned_temporary_upload(): void
    {
        Storage::fake('public');
        $user = $this->userWithPermission('create items');
        $upload = TemporaryUpload::factory()->create(['user_id' => $user->id]);
        $media = $upload->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('default');

        $response = $this->actingAs($user)->post('/items', [
            'name' => 'Milk',
            'photo_uuid' => $media->uuid,
        ]);

        $response->assertRedirect(route('items.index'));
        $item = Item::query()->where('name', 'Milk')->firstOrFail();
        $this->assertNotNull($item->getFirstMedia('photo'));
    }

    public function test_edit_is_accessible_with_edit_items_permission(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();

        $response = $this->withoutVite()->actingAs($user)->get("/items/{$item->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Items/Form')
            ->has('item'),
        );
    }

    public function test_edit_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->get("/items/{$item->id}/edit");

        $response->assertForbidden();
    }

    public function test_update_modifies_item_and_redirects_with_success(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create(['name' => 'Old name']);

        $response = $this->actingAs($user)->put("/items/{$item->id}", [
            'name' => 'New name',
            'quantity' => $item->quantity,
        ]);

        $response->assertRedirect(route('items.index'));
        $response->assertSessionHas('success');
        $this->assertSame('New name', $item->fresh()->name);
    }

    public function test_update_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->put("/items/{$item->id}", ['name' => 'New name']);

        $response->assertForbidden();
    }

    public function test_toggle_status_flips_planned_to_bought(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create(['status' => ItemStatus::Planned]);

        $response = $this->actingAs($user)->patch("/items/{$item->id}/status");

        $response->assertRedirect();
        $this->assertSame(ItemStatus::Bought, $item->fresh()->status);
    }

    public function test_toggle_status_flips_bought_to_planned(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create(['status' => ItemStatus::Bought]);

        $response = $this->actingAs($user)->patch("/items/{$item->id}/status");

        $response->assertRedirect();
        $this->assertSame(ItemStatus::Planned, $item->fresh()->status);
    }

    public function test_toggle_status_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->patch("/items/{$item->id}/status");

        $response->assertForbidden();
    }

    public function test_destroy_deletes_item_and_redirects_with_success(): void
    {
        $user = $this->userWithPermission('delete items');
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->delete("/items/{$item->id}");

        $response->assertRedirect(route('items.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    public function test_destroy_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->delete("/items/{$item->id}");

        $response->assertForbidden();
    }

    public function test_destroy_with_variants_removes_variants_votes_and_variant_media(): void
    {
        $user = $this->userWithPermission('delete items');
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        $variant->addMedia(UploadedFile::fake()->image('v.jpg'))->toMediaCollection('photo');
        $vote = ItemVariantVote::factory()->create(['item_variant_id' => $variant->id, 'item_id' => $item->id]);

        $response = $this->actingAs($user)->delete("/items/{$item->id}");

        $response->assertRedirect(route('items.index'));
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
        $this->assertDatabaseMissing('item_variants', ['id' => $variant->id]);
        $this->assertDatabaseMissing('item_variant_votes', ['id' => $vote->id]);
        $this->assertDatabaseMissing('media', ['model_type' => (new ItemVariant)->getMorphClass(), 'model_id' => $variant->id]);
    }

    public function test_update_while_variant_selected_keeps_mirrored_price_url_and_photo(): void
    {
        Storage::fake('public');
        $user = $this->userWithPermission('edit items', 'create items');
        $room = Room::factory()->create();
        $item = Item::factory()->create(['name' => 'Old name', 'unit_price' => 10, 'url' => 'https://old.test']);
        $variant = ItemVariant::factory()->create(['item_id' => $item->id, 'unit_price' => 10, 'url' => 'https://old.test']);
        $variant->addMedia(UploadedFile::fake()->image('v.jpg'))->toMediaCollection('photo');
        $item->update(['selected_variant_id' => $variant->id]);
        $this->firstMedia($variant, 'photo')->copy($item, 'photo');
        $originalUuid = $this->firstMedia($this->refreshed($item), 'photo')->uuid;

        $upload = TemporaryUpload::factory()->create(['user_id' => $user->id]);
        $unusedMedia = $upload->addMedia(UploadedFile::fake()->image('unused.jpg'))->toMediaCollection('default');

        $response = $this->actingAs($user)->put("/items/{$item->id}", [
            'name' => 'New name',
            'note' => 'New note',
            'room_id' => $room->id,
            'quantity' => 4,
            'assigned_user_id' => null,
            'status' => 'bought',
            'priority' => 'high',
            'unit_price' => 999,
            'url' => 'https://attacker.test',
            'photo_uuid' => $unusedMedia->uuid,
        ]);

        $response->assertRedirect(route('items.index'));
        $fresh = $this->refreshed($item);
        $this->assertSame('New name', $fresh->name);
        $this->assertSame('New note', $fresh->note);
        $this->assertSame($room->id, $fresh->room_id);
        $this->assertSame(4, $fresh->quantity);
        $this->assertSame(ItemStatus::Bought, $fresh->status);
        $this->assertSame('10.00', $fresh->unit_price);
        $this->assertSame('https://old.test', $fresh->url);
        $this->assertSame($originalUuid, $this->firstMedia($fresh, 'photo')->uuid);
        $this->assertDatabaseHas('media', ['uuid' => $unusedMedia->uuid, 'model_type' => (new TemporaryUpload)->getMorphClass()]);
    }

    public function test_update_while_variant_selected_with_remove_photo_keeps_mirrored_photo(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        $variant->addMedia(UploadedFile::fake()->image('v.jpg'))->toMediaCollection('photo');
        $item->update(['selected_variant_id' => $variant->id]);
        $this->firstMedia($variant, 'photo')->copy($item, 'photo');

        $response = $this->actingAs($user)->put("/items/{$item->id}", [
            'name' => $item->name,
            'quantity' => $item->quantity,
            'remove_photo' => true,
        ]);

        $response->assertRedirect(route('items.index'));
        $this->assertNotNull($this->refreshed($item)->getFirstMedia('photo'));
    }

    public function test_update_after_unselecting_variant_applies_manual_price_url_and_photo(): void
    {
        Storage::fake('public');
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id, 'unit_price' => 10]);
        $item->update(['selected_variant_id' => $variant->id]);
        $this->actingAs($user)->delete("/items/{$item->id}/selected-variant")->assertRedirect();

        $upload = TemporaryUpload::factory()->create(['user_id' => $user->id]);
        $media = $upload->addMedia(UploadedFile::fake()->image('manual.jpg'))->toMediaCollection('default');

        $response = $this->actingAs($user)->put("/items/{$item->id}", [
            'name' => $item->name,
            'quantity' => $item->quantity,
            'unit_price' => 55,
            'url' => 'https://manual.test',
            'photo_uuid' => $media->uuid,
        ]);

        $response->assertRedirect(route('items.index'));
        $fresh = $this->refreshed($item);
        $this->assertSame('55.00', $fresh->unit_price);
        $this->assertSame('https://manual.test', $fresh->url);
        $this->assertNotNull($fresh->getFirstMedia('photo'));
    }

    public function test_index_exposes_variant_summary_props(): void
    {
        $user = $this->userWithPermission('view items');
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        $item->update(['selected_variant_id' => $variant->id]);

        $response = $this->withoutVite()->actingAs($user)->get('/items');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('items.data.0.selected_variant_id', $variant->id)
            ->where('items.data.0.selected_variant_name', $variant->name)
            ->where('items.data.0.variants_count', 1),
        );
    }

    public function test_edit_exposes_variant_summary_props(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        ItemVariant::factory()->count(2)->create(['item_id' => $item->id]);

        $response = $this->withoutVite()->actingAs($user)->get("/items/{$item->id}/edit");

        $response->assertInertia(fn (AssertableInertia $page) => $page->where('item.variants_count', 2));
    }

    public function test_show_exposes_variant_summary_props(): void
    {
        $user = $this->userWithPermission('view items');
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        $item->update(['selected_variant_id' => $variant->id]);

        $response = $this->withoutVite()->actingAs($user)->get("/items/{$item->id}");

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('item.selected_variant_id', $variant->id)
            ->where('item.selected_variant_name', $variant->name)
            ->where('item.variants_count', 1),
        );
    }

    public function test_index_query_count_is_constant_regardless_of_item_count(): void
    {
        // Separate users (each with a freshly seeded permission set) so Spatie's
        // static permission cache from the first request cannot skew the second count.
        $userA = $this->userWithPermission('view items');
        Item::factory()->count(2)->create();
        DB::enableQueryLog();
        $this->withoutVite()->actingAs($userA)->get('/items')->assertOk();
        $countFor2 = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        $userB = $this->userWithPermission('view items');
        Item::factory()->count(3)->create();
        DB::enableQueryLog();
        $this->withoutVite()->actingAs($userB)->get('/items')->assertOk();
        $countFor5 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($countFor2, $countFor5);
    }

    public function test_deleting_room_nulls_item_room_id(): void
    {
        $room = Room::factory()->create();
        $item = Item::factory()->create(['room_id' => $room->id]);

        $room->delete();

        $this->assertNull($item->fresh()->room_id);
    }

    public function test_deleting_user_nulls_item_assigned_user_id(): void
    {
        $assignee = User::factory()->create();
        $item = Item::factory()->create(['assigned_user_id' => $assignee->id]);

        $assignee->delete();

        $this->assertNull($item->fresh()->assigned_user_id);
    }

    public function test_show_exposes_null_comparison_when_none_saved(): void
    {
        $user = $this->userWithPermission('view items');
        $item = Item::factory()->create();

        $response = $this->withoutVite()->actingAs($user)->get("/items/{$item->id}");

        $response->assertInertia(fn (AssertableInertia $page) => $page->where('comparison', null));
    }

    public function test_show_exposes_populated_comparison(): void
    {
        $user = $this->userWithPermission('view items');
        $item = Item::factory()->withVariantComparison()->create();

        $response = $this->withoutVite()->actingAs($user)->get("/items/{$item->id}");

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('comparison.text', $item->variant_comparison)
            ->where('comparison.is_stale', false),
        );
    }
}
