<?php

declare(strict_types=1);

namespace App\Data;

use App\Rules\ValidItemAllocations;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class SyncItemAllocationsData extends Data
{
    public function __construct(
        /** @var list<ItemAllocationInputData> */
        #[DataCollectionOf(ItemAllocationInputData::class), Required]
        public readonly array $allocations = [],
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            // 'required' re-stated: a custom rules() entry replaces the #[Required] attribute's inferred rule, not merges with it.
            'allocations' => ['required', 'array', 'min:1', 'max:50', new ValidItemAllocations],
        ];
    }
}
