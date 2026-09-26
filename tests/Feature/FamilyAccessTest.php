<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\RefreshesModels;
use Tests\TestCase;

/**
 * Auth profile role-only: any family member (role `user`) sees and edits the whole plan,
 * only admins manage accounts.
 */
final class FamilyAccessTest extends TestCase
{
    use RefreshDatabase;
    use RefreshesModels;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->member = User::factory()->create();
        $this->member->assignRole('user');
    }

    public function test_member_can_browse_everything(): void
    {
        $room = Room::factory()->create();

        foreach (['/', '/items', '/my-items', '/items/create', '/rooms', "/rooms/{$room->id}", '/rooms/create'] as $url) {
            $this->withoutVite()->actingAs($this->member)->get($url)->assertOk();
        }
    }

    public function test_member_can_edit_items_created_by_others_and_toggle_status(): void
    {
        $item = Item::factory()->create(['status' => ItemStatus::Planned]);

        $this->actingAs($this->member)->patch("/items/{$item->id}/status")->assertRedirect();
        $this->assertSame(ItemStatus::Bought, $item->fresh()?->status);

        $this->actingAs($this->member)->delete("/items/{$item->id}")->assertRedirect();
        $this->assertModelMissing($item);
    }

    public function test_member_can_manage_rooms(): void
    {
        $this->actingAs($this->member)
            ->post('/rooms', ['name' => 'Pracovňa', 'sort_order' => 5])
            ->assertRedirect(route('rooms.index'));

        $this->assertDatabaseHas('rooms', ['name' => 'Pracovňa']);
    }

    public function test_member_cannot_manage_accounts(): void
    {
        $this->actingAs($this->member)->get('/users')->assertForbidden();
        $this->actingAs($this->member)->get('/roles')->assertForbidden();
    }

    public function test_member_can_upload_photos_and_browse_media(): void
    {
        $this->assertTrue($this->member->can('upload files'));
        Storage::fake('public');

        $this->actingAs($this->member)
            ->post('/uploads', ['file' => UploadedFile::fake()->image('sofa.jpg')], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->withoutVite()->actingAs($this->member)->get('/media')->assertOk();
    }

    public function test_member_can_manage_variants_and_votes(): void
    {
        $item = Item::factory()->create();

        $this->withoutVite()->actingAs($this->member)->get("/items/{$item->id}")->assertOk();

        $this->actingAs($this->member)
            ->post("/items/{$item->id}/variants", ['name' => 'Ikea sofa'])
            ->assertRedirect();
        $variant = ItemVariant::query()->where('item_id', $item->id)->firstOrFail();

        $this->actingAs($this->member)
            ->put("/items/{$item->id}/variants/{$variant->id}", ['name' => 'Ikea sofa 2'])
            ->assertRedirect();

        $this->actingAs($this->member)
            ->post("/items/{$item->id}/variants/{$variant->id}/vote")
            ->assertRedirect();
        $this->assertDatabaseHas('item_variant_votes', ['item_variant_id' => $variant->id, 'user_id' => $this->member->id]);

        $second = ItemVariant::factory()->create(['item_id' => $item->id]);
        $this->actingAs($this->member)
            ->post("/items/{$item->id}/variants/{$second->id}/vote")
            ->assertRedirect();
        $this->assertDatabaseHas('item_variant_votes', ['item_variant_id' => $second->id, 'user_id' => $this->member->id]);

        $this->actingAs($this->member)
            ->post("/items/{$item->id}/variants/{$second->id}/select")
            ->assertRedirect();
        $this->assertSame($second->id, $this->refreshed($item)->selected_variant_id);

        $this->actingAs($this->member)
            ->delete("/items/{$item->id}/selected-variant")
            ->assertRedirect();
        $this->assertNull($this->refreshed($item)->selected_variant_id);

        $this->actingAs($this->member)
            ->delete("/items/{$item->id}/vote")
            ->assertRedirect();
        $this->assertDatabaseMissing('item_variant_votes', ['user_id' => $this->member->id]);

        $this->actingAs($this->member)
            ->delete("/items/{$item->id}/variants/{$variant->id}")
            ->assertRedirect();
        $this->assertModelMissing($variant);
    }
}
