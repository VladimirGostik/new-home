<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Item;
use App\Models\User;

final class ItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view items');
    }

    public function view(User $user, Item $item): bool
    {
        return $user->can('view items');
    }

    public function create(User $user): bool
    {
        return $user->can('create items');
    }

    public function update(User $user, Item $item): bool
    {
        return $user->can('edit items');
    }

    public function delete(User $user, Item $item): bool
    {
        return $user->can('delete items');
    }
}
