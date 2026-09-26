<?php

declare(strict_types=1);

namespace Tests\Feature\Items;

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\Room;
use App\Models\TemporaryUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class ItemControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

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
}
