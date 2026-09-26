<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ItemVariant;
use App\Models\User;

final class ItemVariantPolicy
{
    public function update(User $user, ItemVariant $variant): bool
    {
        return $user->can('edit items');
    }

    public function delete(User $user, ItemVariant $variant): bool
    {
        return $user->can('delete items');
    }

    public function select(User $user, ItemVariant $variant): bool
    {
        return $user->can('edit items');
    }

    public function vote(User $user, ItemVariant $variant): bool
    {
        return $user->can('view items');
    }
}
