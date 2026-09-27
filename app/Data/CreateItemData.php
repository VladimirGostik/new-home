<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\ItemPriority;
use App\Enums\ItemStatus;
use App\Rules\OwnedTemporaryMedia;
use App\Rules\ValidItemAllocations;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Url;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class CreateItemData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public readonly string $name,
        public readonly ?string $note = null,
        #[Uuid, Exists('rooms', 'id')]
        public readonly ?string $room_id = null,
        public readonly ?float $unit_price = null,
        public readonly float $quantity = 1,
        #[Url, Max(2048)]
        public readonly ?string $url = null,
        #[Uuid, Exists('users', 'id')]
        public readonly ?string $assigned_user_id = null,
        public readonly ItemStatus $status = ItemStatus::Planned,
        public readonly ItemPriority $priority = ItemPriority::Medium,
        public readonly ?string $photo_uuid = null,
        public readonly ?string $photo_url = null,
        /** @var list<ItemAllocationInputData>|null */
        #[DataCollectionOf(ItemAllocationInputData::class)]
        public readonly ?array $allocations = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'quantity' => ['numeric', 'min:0.01', 'max:9999.99', 'decimal:0,2'],
            'photo_uuid' => ['nullable', 'string', 'uuid', new OwnedTemporaryMedia],
            'photo_url' => ['nullable', 'string', 'url:http,https', 'max:2048', 'prohibits:photo_uuid'],
            'allocations' => ['sometimes', 'array', 'min:1', 'max:50', 'prohibits:room_id,quantity', new ValidItemAllocations],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'allocations.prohibits' => __('app.allocations_prohibits_legacy'),
        ];
    }

    /** @return list<ItemAllocationInputData> */
    public function resolvedAllocations(): array
    {
        return $this->allocations ?? [new ItemAllocationInputData(quantity: $this->quantity, room_id: $this->room_id)];
    }
}
