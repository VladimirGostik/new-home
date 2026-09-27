<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesUsers;
use Tests\Support\RefreshesModels;
use Tests\TestCase;

final class ItemPhotoFromUrlApiTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;
    use RefreshesModels;

    private const PHOTO_URL = 'https://93.184.216.34/photo.jpg';

    private function fakeSuccessfulPhotoResponse(): void
    {
        $bytes = (string) UploadedFile::fake()->image('p.jpg')->getContent();
        Http::fake([self::PHOTO_URL => fn () => Http::response($bytes, 200, ['Content-Type' => 'image/jpeg'])]);
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_item_with_photo_from_url(): void
    {
        Storage::fake(Config::string('media-library.disk_name'));
        Http::preventStrayRequests();
        $this->fakeSuccessfulPhotoResponse();
        Sanctum::actingAs($this->userWithPermission('create items'));

        $response = $this->postJson('/api/items', ['name' => 'Milk', 'photo_url' => self::PHOTO_URL]);

        $response->assertCreated();
        $this->assertNotNull($response->json('photo_url'));
        $this->assertNotNull($response->json('photo_thumb_url'));
        $item = Item::query()->where('name', 'Milk')->firstOrFail();
        $this->assertCount(1, $item->getMedia('photo'));
    }

    public function test_store_without_photo_url_sends_no_http_request(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        Sanctum::actingAs($this->userWithPermission('create items'));

        $response = $this->postJson('/api/items', ['name' => 'Bread']);

        $response->assertCreated();
        Http::assertNothingSent();
    }

    public function test_store_with_photo_uuid_and_photo_url_returns_422(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        Sanctum::actingAs($this->userWithPermission('create items'));

        $response = $this->postJson('/api/items', [
            'name' => 'Milk',
            'photo_uuid' => '11111111-1111-1111-1111-111111111111',
            'photo_url' => self::PHOTO_URL,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['photo_url']);
        Http::assertNothingSent();
    }

    public function test_store_with_photo_url_is_forbidden_without_create_items_permission(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/items', ['name' => 'Milk', 'photo_url' => self::PHOTO_URL]);

        $response->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_download_failure_on_store_creates_no_item(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::PHOTO_URL => Http::response('', 404)]);
        Sanctum::actingAs($this->userWithPermission('create items'));

        $response = $this->postJson('/api/items', ['name' => 'Milk', 'photo_url' => self::PHOTO_URL]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['photo_url']);
        $this->assertDatabaseMissing('items', ['name' => 'Milk']);
    }

    public function test_variant_store_creates_variant_with_photo_from_url(): void
    {
        Storage::fake(Config::string('media-library.disk_name'));
        Http::preventStrayRequests();
        $this->fakeSuccessfulPhotoResponse();
        $item = Item::factory()->create();
        Sanctum::actingAs($this->userWithPermission('edit items'));

        $response = $this->postJson("/api/items/{$item->id}/variants", ['name' => 'Sofa', 'photo_url' => self::PHOTO_URL]);

        $response->assertCreated();
        $this->assertNotNull($response->json('photo_url'));
        $this->assertNotNull($response->json('photo_thumb_url'));
    }

    // ── item photo update ────────────────────────────────────────────────────

    public function test_item_photo_update_replaces_photo(): void
    {
        Storage::fake(Config::string('media-library.disk_name'));
        Http::preventStrayRequests();
        $this->fakeSuccessfulPhotoResponse();
        $item = Item::factory()->create();
        $item->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection('photo');
        Sanctum::actingAs($this->userWithPermission('edit items'));

        $response = $this->putJson("/api/items/{$item->id}/photo", ['photo_url' => self::PHOTO_URL]);

        $response->assertOk();
        $this->assertNotNull($response->json('photo_url'));
        $this->assertCount(1, $this->refreshed($item)->getMedia('photo'));
    }

    public function test_item_photo_update_with_selected_variant_returns_422_without_download(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        $item->update(['selected_variant_id' => $variant->id]);
        Sanctum::actingAs($this->userWithPermission('edit items'));

        $response = $this->putJson("/api/items/{$item->id}/photo", ['photo_url' => self::PHOTO_URL]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['photo_url']);
        Http::assertNothingSent();
    }

    public function test_download_failure_on_item_photo_update_returns_422(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::PHOTO_URL => Http::response('', 404)]);
        $item = Item::factory()->create();
        Sanctum::actingAs($this->userWithPermission('edit items'));

        $response = $this->putJson("/api/items/{$item->id}/photo", ['photo_url' => self::PHOTO_URL]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['photo_url']);
    }

    public function test_item_photo_update_is_forbidden_without_edit_items_permission(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $item = Item::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->putJson("/api/items/{$item->id}/photo", ['photo_url' => self::PHOTO_URL]);

        $response->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_item_photo_update_requires_authentication(): void
    {
        $item = Item::factory()->create();

        $response = $this->putJson("/api/items/{$item->id}/photo", ['photo_url' => self::PHOTO_URL]);

        $response->assertUnauthorized();
    }

    // ── variant photo update ─────────────────────────────────────────────────

    public function test_variant_photo_update_replaces_variant_photo(): void
    {
        Storage::fake(Config::string('media-library.disk_name'));
        Http::preventStrayRequests();
        $this->fakeSuccessfulPhotoResponse();
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        Sanctum::actingAs($this->userWithPermission('edit items'));

        $response = $this->putJson("/api/items/{$item->id}/variants/{$variant->id}/photo", ['photo_url' => self::PHOTO_URL]);

        $response->assertOk();
        $this->assertNotNull($response->json('photo_url'));
        $this->assertCount(1, $this->refreshed($variant)->getMedia('photo'));
    }

    public function test_variant_photo_update_mirrors_onto_item_when_variant_is_selected(): void
    {
        Storage::fake(Config::string('media-library.disk_name'));
        Http::preventStrayRequests();
        $this->fakeSuccessfulPhotoResponse();
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        $item->update(['selected_variant_id' => $variant->id]);
        Sanctum::actingAs($this->userWithPermission('edit items'));

        $response = $this->putJson("/api/items/{$item->id}/variants/{$variant->id}/photo", ['photo_url' => self::PHOTO_URL]);

        $response->assertOk();
        $this->assertCount(1, $this->refreshed($item)->getMedia('photo'));
    }

    public function test_variant_photo_update_does_not_touch_item_when_variant_not_selected(): void
    {
        Storage::fake(Config::string('media-library.disk_name'));
        Http::preventStrayRequests();
        $this->fakeSuccessfulPhotoResponse();
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        Sanctum::actingAs($this->userWithPermission('edit items'));

        $response = $this->putJson("/api/items/{$item->id}/variants/{$variant->id}/photo", ['photo_url' => self::PHOTO_URL]);

        $response->assertOk();
        $this->assertCount(0, $this->refreshed($item)->getMedia('photo'));
    }

    public function test_variant_photo_update_marks_existing_comparison_as_stale(): void
    {
        Storage::fake(Config::string('media-library.disk_name'));
        Http::preventStrayRequests();
        $this->fakeSuccessfulPhotoResponse();
        $item = Item::factory()->withVariantComparison()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        ItemVariant::factory()->create(['item_id' => $item->id]);
        Sanctum::actingAs($this->userWithPermission('edit items'));

        $response = $this->putJson("/api/items/{$item->id}/variants/{$variant->id}/photo", ['photo_url' => self::PHOTO_URL]);

        $response->assertOk();
        $this->assertTrue($this->refreshed($item)->variant_comparison_is_stale);
    }

    public function test_variant_photo_update_is_forbidden_without_edit_items_permission(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->putJson("/api/items/{$item->id}/variants/{$variant->id}/photo", ['photo_url' => self::PHOTO_URL]);

        $response->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_variant_photo_update_requires_authentication(): void
    {
        $item = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $item->id]);

        $response = $this->putJson("/api/items/{$item->id}/variants/{$variant->id}/photo", ['photo_url' => self::PHOTO_URL]);

        $response->assertUnauthorized();
    }

    public function test_variant_photo_update_returns_404_for_variant_of_different_item(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $item = Item::factory()->create();
        $otherItem = Item::factory()->create();
        $variant = ItemVariant::factory()->create(['item_id' => $otherItem->id]);
        Sanctum::actingAs($this->userWithPermission('edit items'));

        $response = $this->putJson("/api/items/{$item->id}/variants/{$variant->id}/photo", ['photo_url' => self::PHOTO_URL]);

        $response->assertNotFound();
    }

    // ── throttle ─────────────────────────────────────────────────────────────

    public function test_thirty_first_request_with_photo_url_in_a_minute_returns_429(): void
    {
        Storage::fake(Config::string('media-library.disk_name'));
        Http::preventStrayRequests();
        $this->fakeSuccessfulPhotoResponse();
        Sanctum::actingAs($this->userWithPermission('create items'));

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $response = $this->postJson('/api/items', ['name' => "Item {$attempt}", 'photo_url' => self::PHOTO_URL]);

            $response->assertCreated();
        }

        $response = $this->postJson('/api/items', ['name' => 'Item 31', 'photo_url' => self::PHOTO_URL]);

        $response->assertStatus(429);
    }
}
