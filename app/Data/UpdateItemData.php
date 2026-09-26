<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\ItemPriority;
use App\Enums\ItemStatus;
use App\Rules\OwnedTemporaryMedia;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Url;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class UpdateItemData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public readonly string $name,
        public readonly ?string $note = null,
        #[Uuid, Exists('rooms', 'id')]
        public readonly ?string $room_id = null,
        public readonly ?float $unit_price = null,
        #[Min(1), Max(9999)]
        public readonly int $quantity = 1,
        #[Url, Max(2048)]
        public readonly ?string $url = null,
        #[Uuid, Exists('users', 'id')]
        public readonly ?string $assigned_user_id = null,
        public readonly ItemStatus $status = ItemStatus::Planned,
        public readonly ItemPriority $priority = ItemPriority::Medium,
        public readonly ?string $photo_uuid = null,
        public readonly bool $remove_photo = false,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'photo_uuid' => ['nullable', 'string', 'uuid', new OwnedTemporaryMedia],
        ];
    }
}
