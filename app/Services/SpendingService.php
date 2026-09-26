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
use Illuminate\Support\Collection;
use stdClass;

/**
 * Money aggregation over items. Line total = unit_price × quantity; items
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
        $row = $this->aggregate($query)->first();

        return $row !== null ? $this->toSummary($row) : new SpendSummaryData;
    }

    /**
     * All rooms in their display order, followed by "Celý dom" (items without a room).
     *
     * @return array<int, SpendGroupData>
     */
    public function byRoom(): array
    {
        $rows = $this->aggregate(Item::query(), 'room_id')->keyBy('group_key');

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
        $rows = $this->aggregate(Item::query(), 'assigned_user_id')->keyBy('group_key');

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
     * @param  Builder<Item>  $query
     * @param  'room_id'|'assigned_user_id'|null  $groupBy
     * @return Collection<int, stdClass>
     */
    private function aggregate(Builder $query, ?string $groupBy = null): Collection
    {
        $bought = ItemStatus::Bought->value;

        // Clone: toBase() may return the caller's own builder, which must not inherit these selects.
        $query = (clone $query)->toBase()
            ->selectRaw('COUNT(*) AS items_count')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS bought_count', [$bought])
            ->selectRaw('SUM(CASE WHEN unit_price IS NULL THEN 1 ELSE 0 END) AS unpriced_count')
            ->selectRaw('COALESCE(SUM(unit_price * quantity), 0) AS total')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN unit_price * quantity END), 0) AS bought_total', [$bought]);

        // COALESCE to '' so the NULL group ("Celý dom" / unassigned) is addressable by key.
        match ($groupBy) {
            'room_id' => $query->selectRaw("COALESCE(CAST(room_id AS VARCHAR(36)), '') AS group_key")->groupBy('room_id'),
            'assigned_user_id' => $query->selectRaw("COALESCE(CAST(assigned_user_id AS VARCHAR(36)), '') AS group_key")->groupBy('assigned_user_id'),
            null => null,
        };

        return $query->get();
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
