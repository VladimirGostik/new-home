<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

final class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view rooms');
    }

    public function view(User $user, Room $room): bool
    {
        return $user->can('view rooms');
    }

    public function create(User $user): bool
    {
        return $user->can('create rooms');
    }

    public function update(User $user, Room $room): bool
    {
        return $user->can('edit rooms');
    }

    public function reorder(User $user): bool
    {
        return $user->can('edit rooms');
    }

    public function delete(User $user, Room $room): bool
    {
        return $user->can('delete rooms');
    }
}
