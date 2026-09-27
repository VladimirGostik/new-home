<?php

declare(strict_types=1);

namespace App\Data;

use App\Rules\OwnedTemporaryMedia;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Url;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class CreateItemVariantData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public readonly string $name,
        public readonly ?float $unit_price = null,
        #[Url, Max(2048)]
        public readonly ?string $url = null,
        public readonly ?string $photo_uuid = null,
        public readonly ?string $photo_url = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'photo_uuid' => ['nullable', 'string', 'uuid', new OwnedTemporaryMedia],
            'photo_url' => ['nullable', 'string', 'url:http,https', 'max:2048', 'prohibits:photo_uuid'],
        ];
    }
}
