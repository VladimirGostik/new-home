<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\ExpenseData;
use App\Data\ExpenseIndexFilterData;
use App\Data\PatchExpenseData;
use App\Data\SaveExpenseData;
use App\Data\SaveItemPaymentData;
use App\Enums\ExpenseType;
use App\Models\Expense;
use App\Models\Item;
use App\Models\User;
use App\Navigation\NavItem;
use App\Services\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

final class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenseService,
    ) {}

    #[Authorize('viewAny', Expense::class)]
    #[NavItem(label: 'app.expenses', route: 'expenses.index', icon: 'BanknotesIcon', permission: 'view items', order: 35)]
    public function index(Request $request): Response
    {
        $search = $request->query('filter')['search'] ?? null;
        $search = is_string($search) && trim($search) !== '' ? trim($search) : null;
        $typeValue = $request->query('filter')['type'] ?? null;
        $type = is_string($typeValue) ? ExpenseType::tryFrom($typeValue) : null;
        $month = $request->query('month');

        $result = $this->expenseService->forMonth(is_string($month) ? $month : null, $search, $type);

        return Inertia::render('Expenses/Index', [
            'month' => $result['month']->format('Y-m'),
            'expenses' => $result['expenses']->map(fn (Expense $expense) => ExpenseData::fromModel($expense))->values()->all(),
            'totals' => $result['totals'],
            'filters' => new ExpenseIndexFilterData($result['month']->format('Y-m'), $search, $type),
            'filterOptions' => ['types' => ExpenseType::options()],
        ]);
    }

    #[Authorize('create', Expense::class)]
    public function store(SaveExpenseData $data, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->expenseService->create($data, $user);

        return back()->with('success', __('app.expense_created'));
    }

    #[Authorize('update', 'item')]
    public function storePayment(SaveItemPaymentData $data, Item $item, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->expenseService->createPayment($item, $data, $user);

        return back()->with('success', __('app.payment_created'));
    }

    #[Authorize('update', 'expense')]
    public function update(PatchExpenseData $data, Expense $expense): RedirectResponse
    {
        $this->expenseService->patch($expense, $data);

        return back()->with('success', __('app.expense_updated'));
    }

    #[Authorize('delete', 'expense')]
    public function destroy(Expense $expense): RedirectResponse
    {
        $this->expenseService->delete($expense);

        return back()->with('success', __('app.expense_deleted'));
    }
}
