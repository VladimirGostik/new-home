<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\ExpenseType;
use App\Models\Expense;
use App\Models\Item;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $item_id,
        public readonly ?string $item_name,
        public readonly ?string $title,
        public readonly string $display_name,
        public readonly ExpenseType $type,
        public readonly float $amount,
        public readonly string $paid_at,
        public readonly ?string $note,
        public readonly ?string $created_by_name,
        public readonly string $created_at,
    ) {}

    /** @return list<self> */
    public static function forItem(Item $item): array
    {
        /** @var list<self> $payments */
        $payments = $item->payments()
            ->with(['item:id,name', 'createdBy:id,name'])
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Expense $expense) => self::fromModel($expense))
            ->values()
            ->all();

        return $payments;
    }

    public static function fromModel(Expense $expense): self
    {
        $expense->loadMissing(['item:id,name', 'createdBy:id,name']);

        $itemName = $expense->item?->name;

        return new self(
            id: $expense->id,
            item_id: $expense->item_id,
            item_name: $itemName,
            title: $expense->title,
            display_name: $itemName ?? $expense->title ?? '',
            type: $expense->item_id !== null ? ExpenseType::Item : ExpenseType::Other,
            amount: (float) $expense->amount,
            paid_at: $expense->paid_at->toDateString(),
            note: $expense->note,
            created_by_name: $expense->createdBy?->name,
            created_at: $expense->created_at?->toIso8601String() ?? '',
        );
    }
}
