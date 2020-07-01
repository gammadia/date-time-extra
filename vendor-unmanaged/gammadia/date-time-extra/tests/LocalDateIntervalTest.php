<?php

declare(strict_types=1);

namespace Gammadia\DateTimeExtra\Test\Unit;

use _HumbugBox4ff14b593cb3\Nette\InvalidArgumentException;
use Brick\DateTime\DayOfWeek;
use Brick\DateTime\LocalDate;
use Brick\DateTime\LocalDateTime;
use Brick\DateTime\Parser\DateTimeParseException;
use Brick\DateTime\Period;
use Brick\DateTime\TimeZoneRegion;
use Gammadia\DateTimeExtra\IntervalParseException;
use Gammadia\DateTimeExtra\LocalDateInterval;
use Gammadia\DateTimeExtra\LocalDateTimeInterval;
use Gammadia\DateTimeExtra\ZonedDateTimeInterval;
use PHPUnit\Framework\TestCase;

class LocalDateIntervalTest extends TestCase
{
    public function testBetween(): void
    {
        $start = LocalDate::of(2010, 1, 1);
        $end = LocalDate::of(2011, 1, 1);

        self::assertInstanceOf(LocalDateInterval::class, LocalDateInterval::between($start, $end));
        self::assertTrue($this->interval('2010|2011')->equals(LocalDateInterval::between($start, $end)));
    }

    public function testSince(): void
    {
        $start = LocalDate::of(2010, 1, 1);

        self::assertInstanceOf(LocalDateInterval::class, LocalDateInterval::since($start));
        self::assertTrue($this->interval('2010|----')->equals(LocalDateInterval::since($start)));
    }

    public function testUntil(): void
    {
        $end = LocalDate::of(2011, 1, 1);

        self::assertInstanceOf(LocalDateInterval::class, LocalDateInterval::until($end));
        self::assertTrue($this->interval('----|2011')->equals(LocalDateInterval::until($end)));
    }

    public function testAtomic(): void
    {
        $date = LocalDate::of(2009, 1, 1);

        self::assertInstanceOf(LocalDateInterval::class, LocalDateInterval::atomic($date));
        self::assertTrue($this->interval('2009|2009')->equals(LocalDateInterval::atomic($date)));
    }

    public function testOfCurrentWeek(): void
    {
        $start = LocalDate::of(2020, 6, 1);
        $end = LocalDate::of(2020, 6, 7);

        self::assertTrue(LocalDateInterval::between($start, $end)->equals(LocalDateInterval::ofCurrentWeek($start)));
        self::assertFalse(LocalDateInterval::between($start, $end)->equals(LocalDateInterval::ofCurrentWeek($start->plusDays(7))));

        $this->expectException(\RuntimeException::class);
        LocalDateInterval::ofCurrentWeek($start->plusDays(1));
    }

    public function testToFullDays(): void
    {
        self::assertInstanceOf(LocalDateTimeInterval::class, $this->interval('2012|2013')->toFullDays());
        self::assertTrue(LocalDateTimeInterval::between(LocalDateTime::of(2012, 1, 1), LocalDateTime::of(2013, 1, 1))->isEqualTo($this->interval('2012|2013')->toFullDays()));
        self::assertTrue(LocalDateTimeInterval::since(LocalDateTime::of(2012, 1, 1))->isEqualTo($this->interval('2012|----')->toFullDays()));
        self::assertTrue(LocalDateTimeInterval::until(LocalDateTime::of(2012, 1, 1))->isEqualTo($this->interval('----|2012')->toFullDays()));
        self::assertFalse(LocalDateTimeInterval::between(LocalDateTime::of(2012, 1, 1), LocalDateTime::of(2013, 1, 1))->isEqualTo($this->interval('2011|2012')->toFullDays()));
        self::assertFalse(LocalDateTimeInterval::since(LocalDateTime::of(2012, 1, 1))->isEqualTo($this->interval('----|2012')->toFullDays()));
        self::assertFalse(LocalDateTimeInterval::until(LocalDateTime::of(2012, 1, 1))->isEqualTo($this->interval('2012|----')->toFullDays()));
    }

    public function testInTimezoneSaoPaulo(): void
    {
        $saoPaulo = TimeZoneRegion::of('America/Sao_Paulo');

        $ldt1 = LocalDateTime::of(2016, 10, 16);
        $ldt2 = LocalDateTime::of(2016, 10, 16);

        self::assertTrue(
            LocalDateInterval::between($ldt1->getDate(), $ldt2->getDate())->inTimeZone($saoPaulo)->equals(
                ZonedDateTimeInterval::between($ldt1->atTimeZone($saoPaulo), $ldt2->atTimeZone($saoPaulo))
            )
        );
    }

    public function testLengthInDays(): void
    {
        self::assertSame(365, $this->interval('2009|2010')->getLengthInDays());
        self::assertSame(366, $this->interval('2012|2013')->getLengthInDays());
        self::assertSame(730, $this->interval('2009|2011')->getLengthInDays());
        self::assertSame(731, $this->interval('2012|2014')->getLengthInDays());

        $this->expectException(\RuntimeException::class);
        $this->interval('----|2009')->getLengthInDays();
    }

    public function testGetDurationInYearsMonthsDays(): void
    {
        self::assertTrue(Period::of(1, 0, 0)->isEqualTo($this->interval('2009|2010')->getDurationInYearsMonthsDays()));
        self::assertTrue(Period::of(1, 0, 0)->isEqualTo($this->interval('2012|2013')->getDurationInYearsMonthsDays()));
        self::assertTrue(Period::of(1, 1, 0)->isEqualTo(LocalDateInterval::between(LocalDate::of(2010, 1, 1), LocalDate::of(2011, 2, 1))->getDurationInYearsMonthsDays()));
        self::assertTrue(Period::of(1, 1, 0)->isEqualTo(LocalDateInterval::between(LocalDate::of(2012, 1, 1), LocalDate::of(2013, 2, 1))->getDurationInYearsMonthsDays()));
        self::assertTrue(Period::of(1, 1, 1)->isEqualTo(LocalDateInterval::between(LocalDate::of(2010, 1, 1), LocalDate::of(2011, 2, 2))->getDurationInYearsMonthsDays()));
        self::assertTrue(Period::of(1, 1, 1)->isEqualTo(LocalDateInterval::between(LocalDate::of(2012, 1, 1), LocalDate::of(2013, 2, 2))->getDurationInYearsMonthsDays()));

        $this->expectException(\RuntimeException::class);
        $this->interval('----|2009')->getDurationInYearsMonthsDays();
    }

    public function testGetPeriod()
    {
        $interval =
            LocalDateInterval::between(
                LocalDate::of(2014, 1, 1),
                LocalDate::of(2014, 1, 30)
            );

        self::assertTrue(Period::parse('P29D')->isEqualTo($interval->getPeriod()));
    }

    public function testMove(): void
    {
        self::assertTrue(
            LocalDateInterval::parse('2012-01-02/2012-01-03')->equals(
                LocalDateInterval::parse('2012-01-01/2012-01-02')->move(Period::parse('P1D'))
            )
        );

        self::assertTrue(
            LocalDateInterval::parse('2012-01-09/2012-01-10')->equals(
                LocalDateInterval::parse('2012-01-01/2012-01-02')->move(Period::parse('P1W1D'))
            )
        );

        self::assertTrue(
            LocalDateInterval::parse('2012-02-09/2012-02-10')->equals(
                LocalDateInterval::parse('2012-01-01/2012-01-02')->move(Period::parse('P1M1W1D'))
            )
        );

        self::assertTrue(
            LocalDateInterval::parse('2012-01-01/2012-01-02')->equals(
                LocalDateInterval::parse('2012-02-09/2012-02-10')->move(Period::parse('-P1M1W1D'))
            )
        );

        //Leap year
        self::assertTrue(
            LocalDateInterval::parse('2017-02-28/2017-06-01')->equals(
                LocalDateInterval::parse('2016-02-29/2016-06-01')->move(Period::parse('P1Y'))
            )
        );

        self::assertTrue(
            LocalDateInterval::parse('2016-02-28/2016-06-01')->equals(
                LocalDateInterval::parse('2017-02-28/2017-06-01')->move(Period::parse('-P1Y'))
            )
        );

        self::assertTrue(
            LocalDateInterval::parse('2015-02-28/2015-06-01')->equals(
                LocalDateInterval::parse('2016-02-29/2016-06-01')->move(Period::parse('-P1Y'))
            )
        );
    }

    /**
     * @dataProvider iterateDailyProvider
     */
    public function testIterateDaily(int $expectedCount, LocalDate $start, LocalDate $end)
    {
        self::assertCount(
            $expectedCount,
            iterator_to_array(
                LocalDateInterval::iterateDaily($start, $end)
            )
        );
    }

    public function iterateDailyProvider()
    {
        yield [367, LocalDate::of(2012, 1, 1), LocalDate::of(2013, 1, 1)];
        yield [366, LocalDate::of(2010, 1, 1), LocalDate::of(2011, 1, 1)];
        yield [32, LocalDate::of(2010, 1, 1), LocalDate::of(2010, 2, 1)];
        yield [31, LocalDate::of(2010, 4, 1), LocalDate::of(2010, 5, 1)];
        yield [7, LocalDate::of(2010, 1, 1), LocalDate::of(2010, 1, 7)];
        yield [2, LocalDate::of(2010, 1, 1), LocalDate::of(2010, 1, 2)];
    }

    /**
     * @dataProvider iterateProvider
     */
    public function testIterate(int $expectedCount, string $strPeriod)
    {
        self::assertCount(
            $expectedCount,
            iterator_to_array(
                $this->interval('2010|2011')->iterate(
                    Period::parse($strPeriod)
                )
            )
        );
    }

    public function iterateProvider()
    {
        yield [366, 'P1D'];
        yield [53, 'P1W'];
        yield [13, 'P1M'];
        yield [2, 'P1Y'];
        yield [183, 'P2D'];
        yield [27, 'P2W'];
        yield [7, 'P2M'];
        yield [1, 'P2Y'];
    }

    /**
     * @dataProvider iterateExcludingProvider
     */
    public function testIterateExcluding(int $expectedCount, LocalDateInterval $interval, array $excludedDays)
    {
        self::assertCount(
            $expectedCount,
            iterator_to_array(
                $interval->iterateExcluding($excludedDays)
            )
        );
    }

    public function iterateExcludingProvider()
    {
        yield [366, $this->interval('2011|2012'), []];
        yield [262, $this->interval('2012|2013'), [DayOfWeek::sunday(), DayOfWeek::saturday()]];
        yield [260, $this->interval('2011|2012'), [DayOfWeek::sunday(), DayOfWeek::saturday()]];
        yield [314, $this->interval('2012|2013'), [DayOfWeek::sunday()]];
        yield [313, $this->interval('2011|2012'), [DayOfWeek::sunday()]];
        yield [0, $this->interval('2011|2012'), DayOfWeek::all()];

        yield [10, LocalDateInterval::between(LocalDate::of(2011,1,1), LocalDate::of(2011, 2,1)), [DayOfWeek::monday(), DayOfWeek::tuesday(), DayOfWeek::wednesday(), DayOfWeek::thursday(), DayOfWeek::friday()]];
        yield [5, LocalDateInterval::ofCurrentWeek(LocalDate::of(2011, 1, 3)), [DayOfWeek::sunday(), DayOfWeek::saturday()]];
    }

    /**
     * @dataProvider iterateWeekBasedProvider
     */
    public function testIterateWeekBased(int $expectedCount, LocalDateInterval $interval, int $years, int $weeks, int $days)
    {
        self::assertCount(
            $expectedCount,
            iterator_to_array(
                $interval->iterateWeekBased($years, $weeks, $days)
            )
        );
    }

    public function iterateWeekBasedProvider()
    {
        yield [367, $this->interval('2012|2013'), 0, 0, 1];
        yield [366, $this->interval('2011|2012'), 0, 0, 1];
        yield [53, $this->interval('2011|2012'), 0, 1, 0];
        yield [46, $this->interval('2011|2012'), 0, 1, 1];
        yield [5, $this->interval('2009|2013'), 1, 0, 0];
        yield [4, $this->interval('2009|2013'), 1, 1, 1];
    }

    public function testParseLocalDateAndPeriod(): void
    {
        $start = LocalDate::of(2012, 4, 1);
        $end = LocalDate::of(2012, 4, 5);
        $expected = LocalDateInterval::between($start, $end);

        self::assertTrue(LocalDateInterval::parse('2012-04-01/P4D')->equals($expected));
    }

    public function testParsePeriodAndLocalDate(): void
    {
        $start = LocalDate::of(2012, 4, 1);
        $end = LocalDate::of(2012, 4, 5);
        $expected = LocalDateInterval::between($start, $end);

        self::assertTrue(LocalDateInterval::parse('P4D/2012-04-05')->equals($expected));
    }

    /**
     * @dataProvider providerParseInvalidIntervalsThrowsIntervalParseException
     */
    public function testParseInvalidStringThrowsIntervalParseException(string $text): void
    {
        $this->expectException(IntervalParseException::class);

        LocalDateInterval::parse($text);
    }

    /**
     * @return string[][]
     */
    public function providerParseInvalidIntervalsThrowsIntervalParseException(): array
    {
        return [
            ['P4D/P2D'],
            ['-/P2D'],
            ['P4D/-'],
        ];
    }

    public function testParseAlways(): void
    {
        self::assertTrue(LocalDateInterval::parse('-/-')->equals(LocalDateInterval::between(null, null)));
    }

    public function testParseInfinity(): void
    {
        $tsp = LocalDate::of(2015, 1, 1);

        self::assertTrue(
            LocalDateInterval::parse('2015-01-01/-')->equals(LocalDateInterval::since($tsp))
        );

        self::assertTrue(
            LocalDateInterval::parse('-/2015-01-01')->equals(LocalDateInterval::until($tsp))
        );
    }

    public function testToString(): void
    {
        $start = LocalDate::of(2020, 6, 29);
        $end = LocalDate::of(2020, 6, 30);

        self::assertSame('2020-06-29/2020-06-30', LocalDateInterval::between($start, $end)->toString());
        self::assertSame('2009-01-01/-', $this->interval('2009|----')->toString());
        self::assertSame('-/2010-01-01', $this->interval('----|2010')->toString());
    }

    public function testGetStart(): void
    {
        $start = LocalDate::of(2020, 6, 29);
        $end = LocalDate::of(2020, 6, 30);

        self::assertSame($start, LocalDateInterval::between($start, $end)->getStart());
    }

    public function testGetEnd(): void
    {
        $start = LocalDate::of(2020, 6, 29);
        $end = LocalDate::of(2020, 6, 30);

        self::assertSame($end, LocalDateInterval::between($start, $end)->getEnd());
    }

    public function testWithStart(): void
    {
        $start = LocalDate::of(2020, 6, 29);
        $end = LocalDate::of(2020, 6, 30);
        $newStart = LocalDate::of(2020, 6, 28);

        self::assertTrue(LocalDateInterval::between($newStart, $end)->equals(LocalDateInterval::between($start, $end)->withStart($newStart)));
    }

    public function testWithEnd(): void
    {
        $start = LocalDate::of(2020, 6, 29);
        $end = LocalDate::of(2020, 6, 30);
        $newEnd = LocalDate::of(2020, 7, 1);

        self::assertTrue(LocalDateInterval::between($start, $newEnd)->equals(LocalDateInterval::between($start, $end)->withEnd($newEnd)));
    }

    public function testIsEmpty(): void
    {
        $interval = LocalDateInterval::atomic(LocalDate::of(2016, 2, 28));

        self::assertFalse(LocalDateInterval::since(LocalDate::of(2016, 2, 28))->isEmpty());
        self::assertFalse(LocalDateInterval::until(LocalDate::of(2016, 2, 28))->isEmpty());
        self::assertTrue($interval->isEmpty());
    }

    public function testIsBefore(): void
    {
        self::assertFalse($this->interval('2010|2011')->isBefore(LocalDate::parse('2011-01-01')));
        self::assertFalse($this->interval('2011|2012')->isBefore(LocalDate::parse('2011-01-01')));
        self::assertFalse($this->interval('2012|----')->isBefore(LocalDate::parse('2011-01-01')));
        self::assertTrue($this->interval('----|2010')->isBefore(LocalDate::parse('2011-01-01')));
    }

    public function testIsBeforeInterval(): void
    {
        self::assertFalse($this->interval('2011|2012')->isBeforeInterval($this->interval('2012|2013')));
        self::assertFalse($this->interval('2012|2013')->isBeforeInterval($this->interval('2012|2013')));
        self::assertFalse($this->interval('2013|----')->isBeforeInterval($this->interval('2012|2013')));
        self::assertTrue($this->interval('----|2010')->isBeforeInterval($this->interval('2012|2013')));
        self::assertTrue($this->interval('2010|2011')->isBeforeInterval($this->interval('2012|2013')));
    }

    public function testIsAfter(): void
    {
        self::assertFalse($this->interval('----|2010')->isAfter(LocalDate::parse('2010-01-01')));
        self::assertFalse($this->interval('2010|2011')->isAfter(LocalDate::parse('2010-01-01')));
        self::assertTrue($this->interval('2011|2012')->isAfter(LocalDate::parse('2010-01-01')));
        self::assertTrue($this->interval('2012|----')->isAfter(LocalDate::parse('2010-01-01')));
    }

    public function testIsAfterInterval(): void
    {
        self::assertFalse($this->interval('2011|2012')->isAfterInterval($this->interval('2010|2011')));
        self::assertFalse($this->interval('----|2010')->isAfterInterval($this->interval('2010|2011')));
        self::assertFalse($this->interval('2010|2011')->isAfterInterval($this->interval('2010|2011')));
        self::assertTrue($this->interval('2012|2013')->isAfterInterval($this->interval('2010|2011')));
        self::assertTrue($this->interval('2013|----')->isAfterInterval($this->interval('2010|2011')));
    }

    public function testContains(): void
    {
        self::assertFalse($this->interval('----|2010')->contains(LocalDate::parse('2010-01-01')));
        self::assertTrue($this->interval('2010|2011')->contains(LocalDate::parse('2010-01-01')));
        self::assertTrue($this->interval('2010|----')->contains(LocalDate::parse('2010-01-01')));
    }

    public function testContainsInterval(): void
    {
        self::assertFalse($this->interval('2010|2011')->containsInterval($this->interval('2011|2012')));
        self::assertFalse($this->interval('2012|2013')->containsInterval($this->interval('2011|2012')));
        self::assertFalse($this->interval('----|2012')->containsInterval($this->interval('----|2011')));
        self::assertFalse($this->interval('2011|----')->containsInterval($this->interval('2010|2011')));
        self::assertTrue($this->interval('2010|2013')->containsInterval($this->interval('2011|2012')));
        self::assertTrue($this->interval('2010|2012')->containsInterval($this->interval('2011|2012')));
        self::assertTrue($this->interval('2011|2013')->containsInterval($this->interval('2011|2012')));
        self::assertTrue($this->interval('2011|2012')->containsInterval($this->interval('2011|2012')));
        self::assertTrue($this->interval('----|2012')->containsInterval($this->interval('2010|2011')));
        self::assertTrue($this->interval('----|2011')->containsInterval($this->interval('2010|2011')));
        self::assertTrue($this->interval('2010|----')->containsInterval($this->interval('2010|2011')));
    }

    public function testCollapse(): void
    {
        self::assertTrue($this->interval('2011|2011')->equals($this->interval('2011|2012')->collapse()));
        self::assertTrue($this->interval('2011|2011')->equals($this->interval('2011|----')->collapse()));
        self::assertTrue($this->interval('2011|2011')->equals($this->interval('2011|2011')->collapse()));

        $this->expectException(\RuntimeException::class);
        $this->interval('----|2010')->collapse();
    }

    public function testEquals(): void
    {
        self::assertTrue($this->interval('----|2011')->equals($this->interval('----|2011')));
        self::assertTrue($this->interval('2010|2011')->equals($this->interval('2010|2011')));
        self::assertTrue($this->interval('2012|----')->equals($this->interval('2012|----')));
        self::assertTrue($this->interval('2012|2013')->equals($this->interval('2012|2013')));
        self::assertTrue($this->interval('2011|2011')->equals($this->interval('2011|2011')));
        self::assertTrue($this->interval('2012|----')->equals($this->interval('2012|----')));

        self::assertFalse($this->interval('----|2011')->equals($this->interval('2011|2011')));
        self::assertFalse($this->interval('2012|----')->equals($this->interval('2012|2013')));
        self::assertFalse($this->interval('2010|2011')->equals($this->interval('2010|----')));
        self::assertFalse($this->interval('2012|2013')->equals($this->interval('----|2011')));

        self::assertFalse($this->interval('2012|2013')->equals($this->interval('2011|2013')));
        self::assertFalse($this->interval('----|2013')->equals($this->interval('----|2012')));
        self::assertFalse($this->interval('2012|----')->equals($this->interval('2013|----')));
    }

    public function testPrecedes(): void
    {
        self::assertFalse($this->interval('2009|----')->precedes($this->interval('2010|2011')));
        self::assertFalse($this->interval('2009|2010')->precedes($this->interval('----|2011')));
        self::assertFalse($this->interval('2009|2010')->precedes($this->interval('2010|2011')));
        self::assertTrue($this->interval('2009|2010')->precedes($this->interval('2010|2011', '+P1D')));
    }

    public function testPrecededBy(): void
    {
        self::assertFalse($this->interval('2010|2011')->precededBy($this->interval('2009|----')));
        self::assertFalse($this->interval('----|2011')->precededBy($this->interval('2009|2010')));
        self::assertFalse($this->interval('2010|2011')->precededBy($this->interval('2009|2010')));
        self::assertTrue($this->interval('2010|2011')->precededBy($this->interval('2009|2010', '-P1D')));
    }

    public function testMeets(): void
    {
        self::assertFalse($this->interval('2009|----')->meets($this->interval('2010|2011')));
        self::assertFalse($this->interval('2009|2010')->meets($this->interval('----|2011')));
        self::assertFalse($this->interval('2009|2010')->meets($this->interval('2010|2011', '+P1D')));
        self::assertTrue($this->interval('2009|2010')->meets($this->interval('2010|2011')));
    }

    public function testMetBy(): void
    {
        self::assertFalse($this->interval('2010|2011')->metBy($this->interval('2009|----')));
        self::assertFalse($this->interval('----|2011')->metBy($this->interval('2009|2010')));
        self::assertFalse($this->interval('2010|2011')->metBy($this->interval('2009|2010', '-P1D')));
        self::assertTrue($this->interval('2010|2011')->metBy($this->interval('2009|2010')));
    }

    public function testOverlaps(): void
    {
        $_this = $this;
        self::assertFalse($this->interval('----|2010')->overlaps($this->interval('2010|2013')));
        self::assertFalse($this->interval('2009|2010')->overlaps($this->interval('2010|2013')));
        self::assertFalse($this->interval('2013|2014')->overlaps($this->interval('2010|2013')));
        self::assertFalse($this->interval('2010|2011')->overlaps($this->interval('2010|2013')));
        self::assertFalse($this->interval('2011|2012')->overlaps($this->interval('2010|2013')));
        self::assertFalse($this->interval('2011|2014')->overlaps($this->interval('2010|2013')));
        self::assertFalse($this->interval('2013|----')->overlaps($this->interval('2010|2013')));
        self::assertFalse($this->interval('2013|2014')->overlaps($this->interval('2010|2013')));
        self::assertFalse($this->interval('2013|----')->overlaps($this->interval('2010|2013')));

        self::assertTrue($_this->interval('----|2010')->overlaps($this->interval('2010|2013', '-P1D')));
        self::assertTrue($_this->interval('2009|2010')->overlaps($this->interval('2010|2013', '-P1D')));
    }

    public function testOverlappedBy(): void
    {
        $_this = $this;
        self::assertFalse($this->interval('2010|2013')->overlappedBy($this->interval('----|2010')));
        self::assertFalse($this->interval('2010|2013')->overlappedBy($this->interval('2009|2010')));
        self::assertFalse($this->interval('2010|2013')->overlappedBy($this->interval('2013|2014')));
        self::assertFalse($this->interval('2010|2013')->overlappedBy($this->interval('2010|2011')));
        self::assertFalse($this->interval('2010|2013')->overlappedBy($this->interval('2011|2012')));
        self::assertFalse($this->interval('2010|2013')->overlappedBy($this->interval('2011|2014')));
        self::assertFalse($this->interval('2010|2013')->overlappedBy($this->interval('2013|----')));
        self::assertFalse($this->interval('2010|2013')->overlappedBy($this->interval('2013|2014')));
        self::assertFalse($this->interval('2010|2013')->overlappedBy($this->interval('2013|----')));

        self::assertTrue($_this->interval('2010|2013', '-P1D')->overlappedBy($this->interval('----|2010')));
        self::assertTrue($_this->interval('2010|2013', '-P1D')->overlappedBy($this->interval('2009|2010')));
    }

    public function testFinishes(): void
    {
        self::assertFalse($this->interval('2009|2011')->finishes($this->interval('2010|2011')));
        self::assertFalse($this->interval('2010|2011')->finishes($this->interval('2009|2012')));
        self::assertFalse($this->interval('2010|----')->finishes($this->interval('2009|2011')));
        self::assertFalse($this->interval('2010|2011')->finishes($this->interval('2009|----')));
        self::assertFalse($this->interval('----|2011')->finishes($this->interval('2009|2011')));
        self::assertTrue($this->interval('2010|2011')->finishes($this->interval('2009|2011')));
        self::assertTrue($this->interval('2010|----')->finishes($this->interval('2009|----')));
        self::assertTrue($this->interval('2010|2011')->finishes($this->interval('----|2011')));
    }

    public function testFinishedBy(): void
    {
        self::assertFalse($this->interval('2010|2011')->finishedBy($this->interval('2009|2011')));
        self::assertFalse($this->interval('2009|2012')->finishedBy($this->interval('2010|2011')));
        self::assertFalse($this->interval('2009|2011')->finishedBy($this->interval('2010|----')));
        self::assertFalse($this->interval('2009|----')->finishedBy($this->interval('2010|2011')));
        self::assertFalse($this->interval('2009|2011')->finishedBy($this->interval('----|2011')));
        self::assertTrue($this->interval('2009|2011')->finishedBy($this->interval('2010|2011')));
        self::assertTrue($this->interval('2009|----')->finishedBy($this->interval('2010|----')));
        self::assertTrue($this->interval('----|2011')->finishedBy($this->interval('2010|2011')));
    }

    public function testStarts(): void
    {
        self::assertFalse($this->interval('----|2011')->starts($this->interval('----|2011')));
        self::assertFalse($this->interval('2009|----')->starts($this->interval('2009|2013')));
        self::assertFalse($this->interval('2009|----')->starts($this->interval('2009|----')));
        self::assertFalse($this->interval('2009|2012')->starts($this->interval('2009|2011')));
        self::assertFalse($this->interval('2009|2011')->starts($this->interval('2009|2011')));
        self::assertTrue($this->interval('----|2010')->starts($this->interval('----|2013')));
        self::assertTrue($this->interval('2009|2010')->starts($this->interval('2009|2011')));
        self::assertTrue($this->interval('2009|2010')->starts($this->interval('2009|----')));
    }

    public function testStartedBy(): void
    {
        self::assertFalse($this->interval('----|2011')->startedBy($this->interval('----|2011')));
        self::assertFalse($this->interval('2009|2013')->startedBy($this->interval('2009|----')));
        self::assertFalse($this->interval('2009|----')->startedBy($this->interval('2009|----')));
        self::assertFalse($this->interval('2009|2011')->startedBy($this->interval('2009|2012')));
        self::assertFalse($this->interval('2009|2011')->startedBy($this->interval('2009|2011')));
        self::assertTrue($this->interval('----|2013')->startedBy($this->interval('----|2010')));
        self::assertTrue($this->interval('2009|2011')->startedBy($this->interval('2009|2010')));
        self::assertTrue($this->interval('2009|----')->startedBy($this->interval('2009|2010')));
    }

    public function testEncloses(): void
    {
        self::assertFalse($this->interval('2010|2011')->encloses($this->interval('2011|2012')));
        self::assertFalse($this->interval('2010|2012')->encloses($this->interval('2011|2012')));
        self::assertFalse($this->interval('2011|2013')->encloses($this->interval('2011|2012')));
        self::assertFalse($this->interval('2011|2012')->encloses($this->interval('2011|2012')));
        self::assertFalse($this->interval('2012|2013')->encloses($this->interval('2011|2012')));
        self::assertFalse($this->interval('----|2012')->encloses($this->interval('----|2011')));
        self::assertFalse($this->interval('----|2011')->encloses($this->interval('2010|2011')));
        self::assertFalse($this->interval('2010|----')->encloses($this->interval('2010|2011')));
        self::assertTrue($this->interval('----|2012')->encloses($this->interval('2010|2011')));
        self::assertTrue($this->interval('2010|2013')->encloses($this->interval('2011|2012')));
        self::assertTrue($this->interval('2010|----')->encloses($this->interval('2010|2011', '+P1D')));
        self::assertTrue($this->interval('2009|----')->encloses($this->interval('2010|2011')));
    }

    public function testEnclosedBy(): void
    {
        self::assertFalse($this->interval('2011|2012')->enclosedBy($this->interval('2010|2011')));
        self::assertFalse($this->interval('2011|2012')->enclosedBy($this->interval('2010|2012')));
        self::assertFalse($this->interval('2011|2012')->enclosedBy($this->interval('2011|2013')));
        self::assertFalse($this->interval('2011|2012')->enclosedBy($this->interval('2011|2012')));
        self::assertFalse($this->interval('2011|2012')->enclosedBy($this->interval('2012|2013')));
        self::assertFalse($this->interval('----|2011')->enclosedBy($this->interval('----|2012')));
        self::assertFalse($this->interval('2010|2011')->enclosedBy($this->interval('----|2011')));
        self::assertFalse($this->interval('2010|2011')->enclosedBy($this->interval('2010|----')));
        self::assertTrue($this->interval('2010|2011')->enclosedBy($this->interval('----|2012')));
        self::assertTrue($this->interval('2011|2012')->enclosedBy($this->interval('2010|2013')));
        self::assertTrue($this->interval('2010|2011')->enclosedBy($this->interval('2009|----')));
    }

    public function testIntersects(): void
    {
        self::assertFalse($this->interval('----|2010')->intersects($this->interval('2010|2013')));
        self::assertFalse($this->interval('2009|2010')->intersects($this->interval('2010|2013')));
        self::assertFalse($this->interval('2013|2014')->intersects($this->interval('2010|2013')));
        self::assertFalse($this->interval('2013|----')->intersects($this->interval('2010|2013')));
        self::assertTrue($this->interval('2010|2011')->intersects($this->interval('2010|2013')));
        self::assertTrue($this->interval('2011|2012')->intersects($this->interval('2010|2013')));
        self::assertTrue($this->interval('2011|2014')->intersects($this->interval('2010|2013')));
        self::assertTrue($this->interval('----|2010')->intersects($this->interval('2010|2013', '-P1D')));
        self::assertTrue($this->interval('2009|2010')->intersects($this->interval('2010|2013', '-P1D')));
        self::assertTrue($this->interval('2013|2014')->intersects($this->interval('2010|2013', '+P1D')));
        self::assertTrue($this->interval('2013|----')->intersects($this->interval('2010|2013', '+P1D')));
    }

    public function testAbuts(): void
    {
        self::assertFalse($this->interval('2011|2012')->abuts($this->interval('2011|2012')));
        self::assertFalse($this->interval('2010|2011')->abuts($this->interval('2011|2012', '+P1D')));
        self::assertTrue($this->interval('----|2011')->abuts($this->interval('2011|2012')));
        self::assertTrue($this->interval('2010|2011')->abuts($this->interval('2011|2012')));
        self::assertTrue($this->interval('2012|----')->abuts($this->interval('2011|2012')));
        self::assertTrue($this->interval('2012|2013')->abuts($this->interval('2011|2012')));
        self::assertTrue($this->interval('2012|----')->abuts($this->interval('2011|2012')));
    }

    public function testFindIntersection(): void
    {
        self::assertNull($this->interval('2009|2010')->findIntersection($this->interval('2010|2013')));
        self::assertNull($this->interval('2013|2014')->findIntersection($this->interval('2010|2013')));
        self::assertNull($this->interval('2013|----')->findIntersection($this->interval('2010|2013')));
        self::assertNull($this->interval('----|2010')->findIntersection($this->interval('2010|2013')));

        $intersection = $this->interval('2009|2011')->findIntersection($this->interval('2010|2013'));
        self::assertTrue($intersection && $this->interval('2010|2011')->equals($intersection));

        $intersection2 = $this->interval('2010|2011')->findIntersection($this->interval('2010|2013'));
        self::assertTrue($intersection2 && $this->interval('2010|2011')->equals($intersection2));

        $intersection3 = $this->interval('2011|2012')->findIntersection($this->interval('2010|2013'));
        self::assertTrue($intersection3 && $this->interval('2011|2012')->equals($intersection3));

        $intersection4 = $this->interval('2011|2014')->findIntersection($this->interval('2010|2013'));
        self::assertTrue($intersection4 && $this->interval('2011|2013')->equals($intersection4));
    }

    private function interval(string $i, string $strDuration = ''): LocalDateInterval
    {
        $intervals = [
            '----|2009' => LocalDateInterval::until(LocalDate::of(2009, 1, 1)),
            '----|2010' => LocalDateInterval::until(LocalDate::of(2010, 1, 1)),
            '----|2011' => LocalDateInterval::until(LocalDate::of(2011, 1, 1)),
            '----|2012' => LocalDateInterval::until(LocalDate::of(2012, 1, 1)),
            '----|2013' => LocalDateInterval::until(LocalDate::of(2013, 1, 1)),
            '----|2014' => LocalDateInterval::until(LocalDate::of(2014, 1, 1)),
            '2009|2009' => LocalDateInterval::between(LocalDate::of(2009, 1, 1), LocalDate::of(2009, 1, 1)),
            '2009|2010' => LocalDateInterval::between(LocalDate::of(2009, 1, 1), LocalDate::of(2010, 1, 1)),
            '2009|2011' => LocalDateInterval::between(LocalDate::of(2009, 1, 1), LocalDate::of(2011, 1, 1)),
            '2009|2012' => LocalDateInterval::between(LocalDate::of(2009, 1, 1), LocalDate::of(2012, 1, 1)),
            '2009|2013' => LocalDateInterval::between(LocalDate::of(2009, 1, 1), LocalDate::of(2013, 1, 1)),
            '2009|2014' => LocalDateInterval::between(LocalDate::of(2009, 1, 1), LocalDate::of(2014, 1, 1)),
            '2010|2010' => LocalDateInterval::between(LocalDate::of(2010, 1, 1), LocalDate::of(2010, 1, 1)),
            '2010|2011' => LocalDateInterval::between(LocalDate::of(2010, 1, 1), LocalDate::of(2011, 1, 1)),
            '2010|2012' => LocalDateInterval::between(LocalDate::of(2010, 1, 1), LocalDate::of(2012, 1, 1)),
            '2010|2013' => LocalDateInterval::between(LocalDate::of(2010, 1, 1), LocalDate::of(2013, 1, 1)),
            '2010|2014' => LocalDateInterval::between(LocalDate::of(2010, 1, 1), LocalDate::of(2014, 1, 1)),
            '2011|2011' => LocalDateInterval::between(LocalDate::of(2011, 1, 1), LocalDate::of(2011, 1, 1)),
            '2011|2012' => LocalDateInterval::between(LocalDate::of(2011, 1, 1), LocalDate::of(2012, 1, 1)),
            '2011|2013' => LocalDateInterval::between(LocalDate::of(2011, 1, 1), LocalDate::of(2013, 1, 1)),
            '2011|2014' => LocalDateInterval::between(LocalDate::of(2011, 1, 1), LocalDate::of(2014, 1, 1)),
            '2012|2012' => LocalDateInterval::between(LocalDate::of(2012, 1, 1), LocalDate::of(2012, 1, 1)),
            '2012|2013' => LocalDateInterval::between(LocalDate::of(2012, 1, 1), LocalDate::of(2013, 1, 1)),
            '2012|2014' => LocalDateInterval::between(LocalDate::of(2012, 1, 1), LocalDate::of(2014, 1, 1)),
            '2013|2013' => LocalDateInterval::between(LocalDate::of(2013, 1, 1), LocalDate::of(2013, 1, 1)),
            '2013|2014' => LocalDateInterval::between(LocalDate::of(2013, 1, 1), LocalDate::of(2014, 1, 1)),
            '2014|2014' => LocalDateInterval::between(LocalDate::of(2014, 1, 1), LocalDate::of(2014, 1, 1)),
            '2009|----' => LocalDateInterval::since(LocalDate::of(2009, 1, 1)),
            '2010|----' => LocalDateInterval::since(LocalDate::of(2010, 1, 1)),
            '2011|----' => LocalDateInterval::since(LocalDate::of(2011, 1, 1)),
            '2012|----' => LocalDateInterval::since(LocalDate::of(2012, 1, 1)),
            '2013|----' => LocalDateInterval::since(LocalDate::of(2013, 1, 1)),
            '2014|----' => LocalDateInterval::since(LocalDate::of(2014, 1, 1)),
        ];

        return $strDuration ? $intervals[$i]->move(Period::parse($strDuration)) : $intervals[$i];
    }
}
