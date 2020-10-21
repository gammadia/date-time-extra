<?php

declare(strict_types=1);

namespace Gammadia\DateTimeExtra\Test\Unit\Helper;

use App\Domain\Shared\Percentage;
use Brick\DateTime\Duration;
use Gammadia\DateTimeExtra\Helper\DurationHelper;
use Gammadia\DateTimeExtra\LocalDateTimeInterval;
use PHPUnit\Framework\TestCase;

final class DurationHelperTest extends TestCase
{
    /**
     * @dataProvider hoursToDuration
     */
    public function testHoursToDuration(float $hours, Duration $expected): void
    {
        self::assertSame((string) $expected, (string) DurationHelper::hoursToDuration($hours));
    }

    /**
     * @return iterable<mixed>
     */
    public function hoursToDuration(): iterable
    {
        yield [48, Duration::ofDays(2)];
        yield [8, Duration::ofHours(8)];
        yield [8.25, Duration::ofMinutes(495)];
        yield [0.01, Duration::ofSeconds(36)];
        yield [0.000005, Duration::ofMillis(18)];
    }

    /**
     * @dataProvider applyPercentage
     */
    public function testApplyPercentage(Duration $input, Percentage $rate, Duration $expected): void
    {
        self::assertSame((string) $expected, (string) DurationHelper::applyPercentage($input, $rate));

        self::markTestIncomplete('@todo Need more tests');
    }

    /**
     * @return iterable<mixed>
     */
    public function applyPercentage(): iterable
    {
        yield [Duration::ofDays(365), Percentage::total(), Duration::ofDays(365)];
        yield [Duration::ofHours(8), Percentage::of(50.0), Duration::ofHours(4)];
        yield [Duration::ofHours(8), Percentage::ofRatio(1, 2), Duration::ofHours(4)];
        yield [Duration::ofMinutes(10), Percentage::of(12.34), Duration::ofMillis(74040)];
    }

    /**
     * @dataProvider applyDailyPercentage
     */
    public function testApplyDailyPercentage(Duration $input, string $timeRange, Duration $expected): void
    {
        self::assertSame(
            (string) $expected,
            (string) DurationHelper::applyDailyPercentage($input, LocalDateTimeInterval::parse($timeRange))
        );

        self::markTestIncomplete('@todo Need more tests');
    }

    /**
     * @return iterable<mixed>
     */
    public function applyDailyPercentage(): iterable
    {
        yield 'All day equals 100%' => [Duration::ofHours(8), '2020-01-02T00:00/2020-01-03T00:00', Duration::ofHours(8)];
        yield 'Half-day equals 50%' => [Duration::ofHours(8), '2020-01-02T00:00/2020-01-02T12:00', Duration::ofHours(4)];
    }
}
