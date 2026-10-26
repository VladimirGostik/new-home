<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\ItemPaymentStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ItemPaymentSummaryData extends Data
{
    public function __construct(
        public readonly ?float $total_price,
        public readonly float $paid_total,
        public readonly ?float $remaining,
        public readonly ItemPaymentStatus $payment_status,
        public readonly float $overpaid,
    ) {}

    public static function fromAmounts(?float $totalPrice, float $paidTotal): self
    {
        $paid = round($paidTotal, 2);

        return new self(
            total_price: $totalPrice,
            paid_total: $paid,
            remaining: $totalPrice !== null ? round(max($totalPrice - $paid, 0), 2) : null,
            payment_status: ItemPaymentStatus::fromAmounts($totalPrice, $paid),
            overpaid: $totalPrice !== null ? round(max($paid - $totalPrice, 0), 2) : 0.0,
        );
    }
}
