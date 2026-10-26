<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\ExpenseData;
use App\Data\ItemPaymentsData;
use App\Data\PatchExpenseData;
use App\Data\SaveExpenseData;
use App\Data\SaveItemPaymentData;
use App\Enums\ExpenseType;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Item;
use App\Models\User;
use App\Services\ExpenseService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Str;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group('Expenses', 'Expense ledger: item payments and standalone expenses')]
#[Authenticated]
final class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenseService,
    ) {}

    #[Authorize('viewAny', Expense::class)]
    #[Endpoint('List expenses', 'Paginated ledger (newest payment first) plus `totals` over the whole filtered set.')]
    #[QueryParam('filter[type]', 'string', 'item (payments for items) or other (standalone expenses).', required: false, example: 'other')]
    #[QueryParam('filter[search]', 'string', 'Search in title or item name.', required: false, example: 'notar')]
    #[QueryParam('filter[from]', 'string', 'Paid on or after (Y-m-d).', required: false, example: '2026-10-01')]
    #[QueryParam('filter[to]', 'string', 'Paid on or before (Y-m-d).', required: false, example: '2026-10-31')]
    #[QueryParam('filter[item]', 'string', 'Only payments of the item UUID.', required: false, example: '0195d123-0000-7000-0000-000000000001')]
    #[QueryParam('per_page', 'integer', 'Number of results per page (default 25, clamped 1-100).', required: false, example: 25)]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    public function index(Request $request): JsonResponse
    {
        $filter = $request->query('filter');
        $filter = is_array($filter) ? $filter : [];

        $type = is_string($filter['type'] ?? null) ? ExpenseType::tryFrom($filter['type']) : null;
        $search = is_string($filter['search'] ?? null) && trim($filter['search']) !== '' ? trim($filter['search']) : null;
        $from = $this->dateOrNull($filter['from'] ?? null);
        $to = $this->dateOrNull($filter['to'] ?? null);
        $item = $filter['item'] ?? null;

        $query = Expense::query()
            ->when($type, fn (Builder $query, ExpenseType $type) => $query->ofType($type))
            ->when($search !== null, fn (Builder $query) => $query->searchText((string) $search))
            ->paidBetween($from, $to)
            ->when(is_string($item) && $item !== '', fn (Builder $query) => Str::isUuid($item)
                ? $query->where('item_id', $item)
                : $query->whereRaw('1 = 0'));

        $totals = $this->expenseService->totalsFor($query);

        $perPage = min(max($request->integer('per_page', 25), 1), 100);

        $page = $query
            ->with(['item:id,name', 'createdBy:id,name'])
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Expense $expense) => ExpenseData::fromModel($expense));

        return response()->json([...$page->toArray(), 'totals' => $totals]);
    }

    #[Authorize('view', 'expense')]
    #[Endpoint('Get expense', 'Returns a single ledger row.')]
    #[UrlParam('expense', 'string', 'The UUID of the expense.', example: '0195d123-0000-7000-0000-000000000003')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    #[Response(null, 404, 'Expense not found')]
    public function show(Expense $expense): JsonResponse
    {
        return response()->json(ExpenseData::fromModel($expense));
    }

    #[Authorize('create', Expense::class)]
    #[Endpoint('Create standalone expense', 'Creates an expense not linked to an item. Title is required.')]
    #[Response(null, 422, 'Validation error')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    public function store(SaveExpenseData $data, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $expense = $this->expenseService->create($data, $user);

        return response()->json($this->expenseService->resultFor($expense), 201);
    }

    #[Authorize('update', 'expense')]
    #[Endpoint('Update expense', 'Partial update (title, amount, paid_at, note). Title is rejected for item payments.')]
    #[UrlParam('expense', 'string', 'The UUID of the expense.', example: '0195d123-0000-7000-0000-000000000003')]
    #[Response(null, 422, 'Validation error, empty body, or title sent for an item payment')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    #[Response(null, 404, 'Expense not found')]
    public function update(PatchExpenseData $data, Expense $expense): JsonResponse
    {
        $expense = $this->expenseService->patch($expense, $data);

        return response()->json($this->expenseService->resultFor($expense));
    }

    #[Authorize('delete', 'expense')]
    #[Endpoint('Delete expense', 'Deletes a ledger row (item payment or standalone expense).')]
    #[UrlParam('expense', 'string', 'The UUID of the expense.', example: '0195d123-0000-7000-0000-000000000003')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    #[Response(null, 404, 'Expense not found')]
    public function destroy(Expense $expense): HttpResponse
    {
        $this->expenseService->delete($expense);

        return response()->noContent();
    }

    #[Authorize('view', 'item')]
    #[Endpoint('List item payments', 'Payments of an item (newest first) with the computed payment summary.')]
    #[UrlParam('item', 'string', 'The UUID of the item.', example: '0195d123-0000-7000-0000-000000000001')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    #[Response(null, 404, 'Item not found')]
    public function itemIndex(Item $item): JsonResponse
    {
        return response()->json(new ItemPaymentsData(ExpenseData::forItem($item), $this->expenseService->summaryFor($item)));
    }

    #[Authorize('update', 'item')]
    #[Endpoint('Add item payment', 'Records a payment for an item. Overpayment is allowed and returned as `warning`.')]
    #[UrlParam('item', 'string', 'The UUID of the item.', example: '0195d123-0000-7000-0000-000000000001')]
    #[Response(null, 422, 'Validation error')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    #[Response(null, 404, 'Item not found')]
    public function itemStore(SaveItemPaymentData $data, Item $item, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $expense = $this->expenseService->createPayment($item, $data, $user);

        return response()->json($this->expenseService->resultFor($expense), 201);
    }

    private function dateOrNull(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date?->format('Y-m-d') === $value ? $value : null;
    }
}
