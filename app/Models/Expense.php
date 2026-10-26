<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use App\Enums\ExpenseType;
use App\Utils\AllowedFilter;
use App\Utils\Filters;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property CarbonInterface $paid_at
 */
#[Fillable(['item_id', 'title', 'amount', 'paid_at', 'note', 'created_by'])]
final class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['item_id', 'title', 'amount', 'paid_at', 'note'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function inMonth(Builder $query, CarbonImmutable $month): void
    {
        $query->whereBetween('paid_at', [
            $month->startOfMonth()->toDateString(),
            $month->endOfMonth()->toDateString(),
        ]);
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function paidBetween(Builder $query, ?string $from, ?string $to): void
    {
        $query
            ->when($from !== null, fn (Builder $query) => $query->whereDate('paid_at', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->whereDate('paid_at', '<=', $to));
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function ofType(Builder $query, ExpenseType $type): void
    {
        $type === ExpenseType::Item
            ? $query->whereNotNull('item_id')
            : $query->whereNull('item_id');
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function searchText(Builder $query, string $text): void
    {
        $operator = AllowedFilter::defaultLikeOperator();
        $needle = '%'.Filters::escapeLikeValue($text).'%';

        $query->where(function (Builder $query) use ($operator, $needle): void {
            $query
                ->where('title', $operator, $needle)
                ->orWhereHas('item', fn (Builder $query) => $query->where('name', $operator, $needle));
        });
    }
}
