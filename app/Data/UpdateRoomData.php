<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class UpdateRoomData extends Data
{
    public function __construct(
        #[Required, Max(100)]
        public readonly string $name,
        public readonly ?string $description = null,
        #[Min(0)]
        public readonly int $sort_order = 0,
    ) {}
}
