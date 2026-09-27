<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ItemAllocationInputData extends Data
{
    public function __construct(
        #[Required]
        public readonly float $quantity,
        #[Uuid, Exists('rooms', 'id')]
        public readonly ?string $room_id = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            // 'required' re-stated: a custom rules() entry replaces the #[Required] attribute's inferred rule, not merges with it.
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:9999.99', 'decimal:0,2'],
        ];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'quantity' => __('app.allocation_quantity'),
            'room_id' => __('app.allocation_room'),
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'quantity.min' => __('app.allocation_quantity_min'),
        ];
    }
}
