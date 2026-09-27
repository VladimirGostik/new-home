<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\DashboardData;
use App\Data\SpendGroupData;
use App\Data\SpendSummaryData;
use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Money aggregation over item allocations. Line total = round(unit_price × allocation
 * quantity, 2); an item's total is the sum of its allocations' line totals. Items
 * without a price are counted but excluded from every sum.
 */
final readonly class SpendingService
{
    public function dashboard(): DashboardData
    {
        return new DashboardData(
            totals: $this->summaryFor(Item::query()),
            unassigned_count: Item::query()->whereNull('assigned_user_id')->count(),
            rooms: $this->byRoom(),
            people: $this->byPerson(),
        );
    }

    /** @param Builder<Item> $query */
    public function summaryFor(Builder $query): SpendSummaryData
    {
        $row = $this->baseQuery($query)->first();

        return $row !== null ? $this->toSummary($row) : new SpendSummaryData;
    }

    /**
     * A single room's share (null = "Celý dom") across every item — not the item's
     * full total when it also has allocations in other rooms.
     */
    public function summaryForRoom(?string $roomId): SpendSummaryData
    {
        $query = $this->baseQuery(Item::query());

        $roomId === null
            ? $query->whereNull('item_allocations.room_id')
            : $query->where('item_allocations.room_id', $roomId);

        $row = $query->first();

        return $row !== null ? $this->toSummary($row) : new SpendSummaryData;
    }

    /**
     * All rooms in their display order, followed by "Celý dom" (allocations without a room).
     *
     * @return array<int, SpendGroupData>
     */
    public function byRoom(): array
    {
        $rows = $this->baseQuery(Item::query())
            ->selectRaw("COALESCE(CAST(item_allocations.room_id AS VARCHAR(36)), '') AS group_key")
            ->groupBy('item_allocations.room_id')
            ->get()
            ->keyBy('group_key');

        $groups = Room::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Room $room) => new SpendGroupData(
                id: $room->id,
                name: $room->name,
                summary: $this->summaryFromRow($rows->get($room->id)),
            ))
            ->all();

        $groups[] = new SpendGroupData(
            id: null,
            name: __('app.whole_house'),
            summary: $this->summaryFromRow($rows->get('')),
        );

        return $groups;
    }

    /**
     * Family members who have at least one item assigned, then unassigned items if any exist.
     *
     * @return array<int, SpendGroupData>
     */
    public function byPerson(): array
    {
        $rows = $this->baseQuery(Item::query())
            ->selectRaw("COALESCE(CAST(items.assigned_user_id AS VARCHAR(36)), '') AS group_key")
            ->groupBy('items.assigned_user_id')
            ->get()
            ->keyBy('group_key');

        $groups = User::query()
            ->whereIn('id', $rows->keys()->filter()->all())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => new SpendGroupData(
                id: $user->id,
                name: $user->name,
                summary: $this->summaryFromRow($rows->get($user->id)),
            ))
            ->all();

        if ($rows->has('')) {
            $groups[] = new SpendGroupData(
                id: null,
                name: __('app.unassigned_group'),
                summary: $this->summaryFromRow($rows->get('')),
            );
        }

        return $groups;
    }

    /**
     * Joins allocations to items restricted to `$query`'s item set, with the money
     * selects every caller needs. Callers add their own groupBy() / where() on top.
     *
     * @param  Builder<Item>  $query
     */
    private function baseQuery(Builder $query): QueryBuilder
    {
        $bought = ItemStatus::Bought->value;

        // Clone: the caller's own builder must not inherit this reshaped select.
        return DB::table('item_allocations')
            ->join('items', 'items.id', '=', 'item_allocations.item_id')
            ->whereIn('items.id', (clone $query)->select('items.id'))
            ->selectRaw('COUNT(DISTINCT items.id) AS items_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN items.status = ? THEN items.id END) AS bought_count', [$bought])
            ->selectRaw('COUNT(DISTINCT CASE WHEN items.unit_price IS NULL THEN items.id END) AS unpriced_count')
            ->selectRaw('COALESCE(SUM(ROUND(items.unit_price * item_allocations.quantity, 2)), 0) AS total')
            ->selectRaw('COALESCE(SUM(CASE WHEN items.status = ? THEN ROUND(items.unit_price * item_allocations.quantity, 2) END), 0) AS bought_total', [$bought]);
    }

    private function summaryFromRow(?object $row): SpendSummaryData
    {
        return $row !== null ? $this->toSummary($row) : new SpendSummaryData;
    }

    private function toSummary(object $row): SpendSummaryData
    {
        /** @var array<string, int|float|string|null> $values */
        $values = (array) $row;

        $total = round((float) ($values['total'] ?? 0), 2);
        $bought = round((float) ($values['bought_total'] ?? 0), 2);

        return new SpendSummaryData(
            items_count: (int) ($values['items_count'] ?? 0),
            bought_count: (int) ($values['bought_count'] ?? 0),
            unpriced_count: (int) ($values['unpriced_count'] ?? 0),
            total: $total,
            bought_total: $bought,
            remaining_total: round($total - $bought, 2),
        );
    }
}
