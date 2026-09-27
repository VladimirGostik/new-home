<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final class ValidItemAllocations implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $seenRooms = [];
        $total = 0.0;

        foreach ($value as $allocation) {
            if (! is_array($allocation)) {
                continue;
            }

            $roomKey = $allocation['room_id'] ?? 'house';

            if (in_array($roomKey, $seenRooms, true)) {
                $fail('app.allocations_duplicate_room')->translate();

                return;
            }

            $seenRooms[] = $roomKey;

            if (is_numeric($allocation['quantity'] ?? null)) {
                $total += (float) $allocation['quantity'];
            }
        }

        if ($total > 99999.99) {
            $fail('app.allocations_total_too_large')->translate(['max' => '99 999,99']);
        }
    }
}
