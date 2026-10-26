<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ExpenseData;
use App\Data\ExpenseResultData;
use App\Data\ExpenseTotalsData;
use App\Data\ItemListItemData;
use App\Data\ItemPaymentSummaryData;
use App\Data\PatchExpenseData;
use App\Data\SaveExpenseData;
use App\Data\SaveItemPaymentData;
use App\Enums\ExpenseType;
use App\Models\Expense;
use App\Models\Item;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

final readonly class ExpenseService
{
    public function create(SaveExpenseData $data, User $user): Expense
    {
        /** @var Expense $expense */
        $expense = Expense::create([
            'item_id' => null,
            'title' => $data->title,
            'amount' => $data->amount,
            'paid_at' => $data->paid_at,
            'note' => $data->note,
            'created_by' => $user->id,
        ]);

        return $expense;
    }

    public function createPayment(Item $item, SaveItemPaymentData $data, User $user): Expense
    {
        /** @var Expense $expense */
        $expense = Expense::create([
            'item_id' => $item->id,
            'amount' => $data->amount,
            'paid_at' => $data->paid_at,
            'note' => $data->note,
            'created_by' => $user->id,
        ]);

        return $expense;
    }

    public function patch(Expense $expense, PatchExpenseData $data): Expense
    {
        if ($expense->item_id !== null && ! $data->title instanceof Optional) {
            throw ValidationException::withMessages([
                'title' => [__('app.expense_title_not_allowed_for_payment')],
            ]);
        }

        /** @var array<string, mixed> $attributes */
        $attributes = $data->toArray();
        $expense->update($attributes);

        return $expense->refresh();
    }

    public function delete(Expense $expense): void
    {
        $expense->delete();
    }

    /** @return array{month: CarbonImmutable, expenses: Collection<int, Expense>, totals: ExpenseTotalsData} */
    public function forMonth(?string $month, ?string $search, ?ExpenseType $type): array
    {
        $resolved = $this->resolveMonth($month);

        $query = Expense::query()
            ->inMonth($resolved)
            ->when($search !== null && $search !== '', fn (Builder $query) => $query->searchText((string) $search))
            ->when($type, fn (Builder $query, ExpenseType $type) => $query->ofType($type));

        $totals = $this->totalsFor($query);

        $expenses = $query
            ->with(['item:id,name', 'createdBy:id,name'])
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->get();

        return ['month' => $resolved, 'expenses' => $expenses, 'totals' => $totals];
    }

    /** @param Builder<Expense> $query */
    public function totalsFor(Builder $query): ExpenseTotalsData
    {
        $row = (clone $query)->toBase()
            ->reorder()
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN item_id IS NOT NULL THEN amount ELSE 0 END), 0) as items_total')
            ->selectRaw('COUNT(*) as aggregate_count')
            ->first();

        /** @var object{total: numeric-string|float|int|null, items_total: numeric-string|float|int|null, aggregate_count: int|string|null}|null $row */
        $total = round((float) ($row->total ?? 0), 2);
        $itemsTotal = round((float) ($row->items_total ?? 0), 2);

        return new ExpenseTotalsData(
            total: $total,
            items_total: $itemsTotal,
            manual_total: round($total - $itemsTotal, 2),
            count: (int) ($row->aggregate_count ?? 0),
        );
    }

    public function summaryFor(Item $item): ItemPaymentSummaryData
    {
        return ItemPaymentSummaryData::fromAmounts(
            ItemListItemData::totalPriceOf($item),
            (float) $item->payments()->sum('amount'),
        );
    }

    public function resultFor(Expense $expense): ExpenseResultData
    {
        $item = $expense->item;
        $summary = $item !== null ? $this->summaryFor($item) : null;

        $warning = $summary !== null && $summary->overpaid > 0
            ? Lang::string('app.payment_overpaid', ['amount' => number_format($summary->overpaid, 2, '.', '')])
            : null;

        return new ExpenseResultData(ExpenseData::fromModel($expense), $summary, $warning);
    }

    private function resolveMonth(?string $month): CarbonImmutable
    {
        if ($month !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1) {
            return CarbonImmutable::createFromFormat('!Y-m', $month) ?: CarbonImmutable::now()->startOfMonth();
        }

        return CarbonImmutable::now()->startOfMonth();
    }
}
