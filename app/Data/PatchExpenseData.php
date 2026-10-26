<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\Validation\Filled;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PatchExpenseData extends Data
{
    public function __construct(
        #[Filled, Max(255)]
        public readonly string|Optional $title,
        public readonly float|Optional $amount,
        public readonly string|Optional $paid_at,
        #[Max(255)]
        public readonly string|null|Optional $note,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'amount' => ['sometimes', 'required', 'numeric', 'gt:0', 'max:99999999.99', 'decimal:0,2'],
            'paid_at' => ['sometimes', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $keys = ['title', 'amount', 'paid_at', 'note'];

            if (array_intersect($keys, array_keys($validator->getData())) === []) {
                $validator->errors()->add('amount', __('app.patch_empty'));
            }
        });
    }
}
