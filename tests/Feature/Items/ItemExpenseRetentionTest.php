<?php

declare(strict_types=1);

namespace Tests\Feature\Items;

use App\Models\Expense;
use App\Models\Item;
use App\Services\ExpenseService;
use App\Services\ItemService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class ItemExpenseRetentionTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_deleting_item_keeps_payments_as_standalone_expenses_with_item_name(): void
    {
        $item = Item::factory()->create(['name' => 'Sedačka']);
        $first = Expense::factory()->forItem($item)->create(['amount' => 100, 'paid_at' => '2026-10-03']);
        $second = Expense::factory()->forItem($item)->create(['amount' => 50, 'paid_at' => '2026-10-05']);
        $before = app(ExpenseService::class)->forMonth('2026-10', null, null)['totals'];

        app(ItemService::class)->delete($item);

        $this->assertModelMissing($item);
        foreach ([$first, $second] as $expense) {
            $fresh = Expense::query()->findOrFail($expense->id);
            $this->assertNull($fresh->item_id);
            $this->assertSame('Sedačka', $fresh->title);
        }

        $after = app(ExpenseService::class)->forMonth('2026-10', null, null)['totals'];
        $this->assertEquals($before->total, $after->total);
        $this->assertSame(150.0, $after->total);
        $this->assertSame(0.0, $after->items_total);
        $this->assertSame(150.0, $after->manual_total);
        $this->assertInstanceOf(CarbonImmutable::class, app(ExpenseService::class)->forMonth('2026-10', null, null)['month']);
    }

    public function test_deleting_item_does_not_touch_other_items_or_standalone_expenses(): void
    {
        $item = Item::factory()->create(['name' => 'A']);
        $other = Item::factory()->create(['name' => 'B']);
        $otherPayment = Expense::factory()->forItem($other)->create();
        $standalone = Expense::factory()->create(['title' => 'Notár']);

        app(ItemService::class)->delete($item);

        $this->assertSame($other->id, Expense::query()->findOrFail($otherPayment->id)->item_id);
        $this->assertSame('Notár', Expense::query()->findOrFail($standalone->id)->title);
    }

    public function test_deleting_item_without_payments_works(): void
    {
        $item = Item::factory()->create();

        app(ItemService::class)->delete($item);

        $this->assertModelMissing($item);
    }

    public function test_delete_via_http_keeps_expenses(): void
    {
        $user = $this->adminUser();
        $item = Item::factory()->create(['name' => 'Stôl']);
        $payment = Expense::factory()->forItem($item)->create();

        $this->actingAs($user)->delete("/items/{$item->id}")->assertRedirect();

        $fresh = Expense::query()->findOrFail($payment->id);
        $this->assertNull($fresh->item_id);
        $this->assertSame('Stôl', $fresh->title);
    }
}
