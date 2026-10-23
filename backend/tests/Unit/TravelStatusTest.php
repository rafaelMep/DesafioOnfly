<?php

namespace Tests\Unit;

use App\Enums\TravelStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TravelStatusTest extends TestCase
{
    #[DataProvider('transitions')]
    public function test_only_requested_orders_can_be_approved_or_cancelled(TravelStatus $from, TravelStatus $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    public static function transitions(): array
    {
        return [
            'request to approved' => [TravelStatus::Requested, TravelStatus::Approved, true],
            'request to cancelled' => [TravelStatus::Requested, TravelStatus::Cancelled, true],
            'request to request' => [TravelStatus::Requested, TravelStatus::Requested, false],
            'approved to cancelled' => [TravelStatus::Approved, TravelStatus::Cancelled, false],
            'approved to approved' => [TravelStatus::Approved, TravelStatus::Approved, false],
            'approved to request' => [TravelStatus::Approved, TravelStatus::Requested, false],
            'cancelled to approved' => [TravelStatus::Cancelled, TravelStatus::Approved, false],
            'cancelled to cancelled' => [TravelStatus::Cancelled, TravelStatus::Cancelled, false],
            'cancelled to request' => [TravelStatus::Cancelled, TravelStatus::Requested, false],
        ];
    }
}
