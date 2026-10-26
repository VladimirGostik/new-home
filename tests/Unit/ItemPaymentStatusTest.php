<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Data\ItemPaymentSummaryData;
use App\Enums\ItemPaymentStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ItemPaymentStatusTest extends TestCase
{
    /** @return array<string, array{?float, float, ItemPaymentStatus, ?float, float}> */
    public static function matrix(): array
    {
        return [
            'nothing paid' => [5000.0, 0.0, ItemPaymentStatus::Unpaid, 5000.0, 0.0],
            'partial' => [5000.0, 3200.0, ItemPaymentStatus::Partial, 1800.0, 0.0],
            'exact' => [5000.0, 5000.0, ItemPaymentStatus::Paid, 0.0, 0.0],
            'overpaid' => [5000.0, 5200.0, ItemPaymentStatus::Paid, 0.0, 200.0],
            'unpriced paid something' => [null, 100.0, ItemPaymentStatus::Partial, null, 0.0],
            'unpriced nothing paid' => [null, 0.0, ItemPaymentStatus::Unpaid, null, 0.0],
            'zero priced nothing paid' => [0.0, 0.0, ItemPaymentStatus::Unpaid, 0.0, 0.0],
            'cent below total' => [10.0, 9.99, ItemPaymentStatus::Partial, 0.01, 0.0],
        ];
    }

    #[DataProvider('matrix')]
    public function test_from_amounts(?float $total, float $paid, ItemPaymentStatus $status, ?float $remaining, float $overpaid): void
    {
        $this->assertSame($status, ItemPaymentStatus::fromAmounts($total, $paid));

        $summary = ItemPaymentSummaryData::fromAmounts($total, $paid);

        $this->assertSame($status, $summary->payment_status);
        $this->assertSame($remaining, $summary->remaining);
        $this->assertSame($overpaid, $summary->overpaid);
        $this->assertSame($paid, $summary->paid_total);
    }
}
