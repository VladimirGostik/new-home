<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class SaveItemPaymentData extends Data
{
    public function __construct(
        public readonly float $amount,
        public readonly string $paid_at,
        #[Max(255)]
        public readonly ?string $note = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99', 'decimal:0,2'],
            'paid_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }
}
