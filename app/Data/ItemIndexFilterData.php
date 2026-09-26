<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ItemIndexFilterData extends Data
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?string $room = null,
        public readonly ?string $assignee = null,
        public readonly ?string $status = null,
        public readonly ?string $priority = null,
        public readonly ?int $per_page = null,
    ) {}
}
