<?php

declare(strict_types=1);

namespace Tests\Feature\Items;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\ItemVariantVote;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class ItemVariantVoteTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_cast_vote_creates_row(): void
    {
        $user = $this->userWithPermission('view items');
        $variant = ItemVariant::factory()->create();

        $response = $this->actingAs($user)->post("/items/{$variant->item_id}/variants/{$variant->id}/vote");

        $response->assertRedirect();
        $this->assertDatabaseHas('item_variant_votes', [
            'item_id' => $variant->item_id,
            'item_variant_id' => $variant->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_casting_vote_on_another_variant_of_same_item_moves_the_vote(): void
    {
        $user = $this->userWithPermission('view items');
        $item = Item::factory()->create();
        $first = ItemVariant::factory()->create(['item_id' => $item->id]);
        $second = ItemVariant::factory()->create(['item_id' => $item->id]);

        $this->actingAs($user)->post("/items/{$item->id}/variants/{$first->id}/vote")->assertRedirect();
        $this->actingAs($user)->post("/items/{$item->id}/variants/{$second->id}/vote")->assertRedirect();

        $this->assertSame(1, ItemVariantVote::query()->where('item_id', $item->id)->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('item_variant_votes', [
            'item_id' => $item->id,
            'user_id' => $user->id,
            'item_variant_id' => $second->id,
        ]);
    }

    public function test_different_users_each_hold_their_own_vote(): void
    {
        $userA = $this->userWithPermission('view items');
        $userB = User::factory()->create();
        $userB->syncPermissions(['view items']);
        $variant = ItemVariant::factory()->create();

        $this->actingAs($userA)->post("/items/{$variant->item_id}/variants/{$variant->id}/vote")->assertRedirect();
        $this->actingAs($userB)->post("/items/{$variant->item_id}/variants/{$variant->id}/vote")->assertRedirect();

        $this->assertSame(2, ItemVariantVote::query()->where('item_variant_id', $variant->id)->count());
    }

    public function test_votes_on_two_different_items_coexist(): void
    {
        $user = $this->userWithPermission('view items');
        $variantA = ItemVariant::factory()->create();
        $variantB = ItemVariant::factory()->create();

        $this->actingAs($user)->post("/items/{$variantA->item_id}/variants/{$variantA->id}/vote")->assertRedirect();
        $this->actingAs($user)->post("/items/{$variantB->item_id}/variants/{$variantB->id}/vote")->assertRedirect();

        $this->assertSame(2, ItemVariantVote::query()->where('user_id', $user->id)->count());
    }

    public function test_retract_removes_own_vote_only(): void
    {
        $user = $this->userWithPermission('view items');
        $other = User::factory()->create();
        $variant = ItemVariant::factory()->create();
        ItemVariantVote::factory()->create(['item_variant_id' => $variant->id, 'item_id' => $variant->item_id, 'user_id' => $user->id]);
        ItemVariantVote::factory()->create(['item_variant_id' => $variant->id, 'item_id' => $variant->item_id, 'user_id' => $other->id]);

        $response = $this->actingAs($user)->delete("/items/{$variant->item_id}/vote");

        $response->assertRedirect();
        $this->assertDatabaseMissing('item_variant_votes', ['item_id' => $variant->item_id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('item_variant_votes', ['item_id' => $variant->item_id, 'user_id' => $other->id]);
    }

    public function test_retract_with_no_existing_vote_does_not_error(): void
    {
        $user = $this->userWithPermission('view items');
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->delete("/items/{$item->id}/vote");

        $response->assertRedirect();
    }

    public function test_vote_with_variant_not_belonging_to_item_returns_not_found(): void
    {
        $user = $this->userWithPermission('view items');
        $item = Item::factory()->create();
        $foreignVariant = ItemVariant::factory()->create();

        $response = $this->actingAs($user)->post("/items/{$item->id}/variants/{$foreignVariant->id}/vote");

        $response->assertNotFound();
    }

    public function test_inserting_second_vote_for_same_user_and_item_directly_violates_unique_constraint(): void
    {
        $user = User::factory()->create();
        $variant = ItemVariant::factory()->create();
        ItemVariantVote::factory()->create(['item_variant_id' => $variant->id, 'item_id' => $variant->item_id, 'user_id' => $user->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        ItemVariantVote::factory()->create(['item_variant_id' => $variant->id, 'item_id' => $variant->item_id, 'user_id' => $user->id]);
    }

    public function test_vote_with_mismatching_item_id_and_variant_violates_foreign_key(): void
    {
        $variant = ItemVariant::factory()->create();
        $otherItem = Item::factory()->create();

        $this->expectException(QueryException::class);

        ItemVariantVote::factory()->create([
            'item_variant_id' => $variant->id,
            'item_id' => $otherItem->id,
        ]);
    }

    public function test_vote_is_forbidden_without_view_items_permission(): void
    {
        $user = User::factory()->create();
        $variant = ItemVariant::factory()->create();

        $response = $this->actingAs($user)->post("/items/{$variant->item_id}/variants/{$variant->id}/vote");

        $response->assertForbidden();
    }

    public function test_retract_is_forbidden_without_view_items_permission(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->delete("/items/{$item->id}/vote");

        $response->assertForbidden();
    }
}
