<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

final class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view items');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->can('view items');
    }

    public function create(User $user): bool
    {
        return $user->can('edit items');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->canModify($user, $expense);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->canModify($user, $expense);
    }

    private function canModify(User $user, Expense $expense): bool
    {
        return $expense->item !== null
            ? $user->can('update', $expense->item)
            : $user->can('edit items');
    }
}
