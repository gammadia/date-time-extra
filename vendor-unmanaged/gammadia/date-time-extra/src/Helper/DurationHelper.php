<?php

declare(strict_types=1);

namespace Gammadia\DateTimeExtra\Helper;

use App\Domain\Shared\Percentage;
use Brick\DateTime\Duration;
use Brick\DateTime\LocalTime;

final class DurationHelper
{
    public static function percentage(Duration $a, Duration $b): Percentage
    {
        return Percentage::ofRatio($a->toNanos(), $b->toNanos());
    }

    public static function hoursToDuration(float $hours): Duration
    {
        return Duration::ofNanos((int) ($hours * LocalTime::SECONDS_PER_HOUR * LocalTime::NANOS_PER_SECOND));
    }

    public static function applyPercentage(Duration $duration, Percentage $rate): Duration
    {
        return Duration::ofNanos((int) ($duration->toNanos() * $rate->factor()));
    }
}