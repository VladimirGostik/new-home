<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ReorderRoomsData extends Data
{
    /** @param array<int, string> $ids */
    public function __construct(
        #[Required]
        public readonly array $ids,
    ) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'uuid', 'distinct', 'exists:rooms,id'],
        ];
    }
}
