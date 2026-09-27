<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\Validation\Filled;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Url;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PatchItemVariantData extends Data
{
    public function __construct(
        // Filled, not Required — Required would cancel the "sometimes" Optional properties get.
        #[Filled, Max(255)]
        public readonly string|Optional $name,
        public readonly float|null|Optional $unit_price,
        #[Url, Max(2048)]
        public readonly string|null|Optional $url,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            // 'sometimes'/'nullable' re-stated: a custom rules() entry replaces the auto-inferred ones.
            'unit_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $keys = ['name', 'unit_price', 'url'];

            if (array_intersect($keys, array_keys($validator->getData())) === []) {
                $validator->errors()->add('name', __('app.patch_empty'));
            }
        });
    }
}
