<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesUsers;
use Tests\Support\RefreshesModels;
use Tests\TestCase;

final class ItemVariantApiControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;
    use RefreshesModels;

    public function test_store_creates_variant(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/items/{$item->id}/variants", ['name' => 'Ikea sofa']);

        $response->assertCreated();
        $response->assertJsonPath('name', 'Ikea sofa');
        $this->assertDatabaseHas('item_variants', ['item_id' => $item->id, 'name' => 'Ikea sofa']);
    }

    public function test_store_marks_existing_comparison_as_stale(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->withVariantComparison()->create();
        ItemVariant::factory()->count(2)->create(['item_id' => $item->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/items/{$item->id}/variants", ['name' => 'Another variant']);

        $response->assertCreated();
        $this->assertTrue($this->refreshed($item)->variant_comparison_is_stale);
    }

    public function test_store_with_missing_name_returns_422(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/items/{$item->id}/variants", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_store_is_forbidden_without_edit_items_permission(): void
    {
        $item = Item::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson("/api/items/{$item->id}/variants", ['name' => 'Sofa']);

        $response->assertForbidden();
    }

    public function test_store_returns_404_for_unknown_item(): void
    {
        $user = $this->userWithPermission('edit items');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/items/00000000-0000-0000-0000-000000000000/variants', ['name' => 'Sofa']);

        $response->assertNotFound();
    }

    public function test_store_requires_authentication(): void
    {
        $item = Item::factory()->create();

        $response = $this->postJson("/api/items/{$item->id}/variants", ['name' => 'Sofa']);

        $response->assertUnauthorized();
    }
}
