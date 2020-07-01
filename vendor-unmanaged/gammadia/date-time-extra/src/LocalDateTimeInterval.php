<?php

declare(strict_types=1);

namespace Gammadia\DateTimeExtra;

use Brick\DateTime\Duration;
use Brick\DateTime\LocalDateTime;
use Brick\DateTime\Period;
use Brick\DateTime\TimeZoneOffset;
use Brick\DateTime\TimeZoneRegion;
use Symfony\Component\String\UnicodeString;
use Traversable;

class LocalDateTimeInterval
{
    /**
     * @var LocalDateTime|null
     */
    private $start;

    /**
     * @var LocalDateTime|null
     */
    private $end;

    private function __construct(?LocalDateTime $start, ?LocalDateTime $end)
    {
        if ($start && $end && $start->isAfter($end)) {
            throw new \InvalidArgumentException("Start after end: ${start} / ${end}");
        }

        $this->start = $start;
        $this->end = $end;
    }

    /**
     * <p>Creates a finite half-open interval between given time points. </p>
     *
     * @param LocalDateTime $start the local datetime of lower boundary (inclusive)
     * @param LocalDateTime $end the local datetime of upper boundary (exclusive)
     *
     * @return  self LocalDateTimeInterval interval
     */
    public static function between(?LocalDateTime $start, ?LocalDateTime $end): self
    {
        return new self($start, $end);
    }

    /**
     * <p>Creates an infinite half-open interval since given start. </p>
     *
     * @param LocalDateTime $start the local datetime of lower boundary (inclusive)
     *
     * @return self new local datetime interval
     */
    public static function since(LocalDateTime $start): self
    {
        return new self($start, null);
    }

    /**
     * <p>Creates an infinite open interval until given end. </p>
     *
     * @param LocalDateTime $end the local datetime of upper boundary (exclusive)
     *
     * @return self new timestamp interval
     */
    public static function until(LocalDateTime $end): self
    {
        return new self(null, $end);
    }

    /**
     * <p>Yields the nullable start time point. </p>
     */
    public function getStart(): ?LocalDateTime
    {
        return $this->start;
    }

    /**
     * <p>Yields the nullable end time point. </p>
     */
    public function getEnd(): ?LocalDateTime
    {
        return $this->end;
    }

    /**
     * <p>Yields the start time point if not null. </p>
     */
    public function getFiniteStart(): LocalDateTime
    {
        if (null === $this->start) {
            throw new \RuntimeException('getFiniteStart() method can not return null');
        }

        return $this->start;
    }

    /**
     * <p>Yields the start time point if not null. </p>
     */
    public function getFiniteEnd(): LocalDateTime
    {
        if (null === $this->end) {
            throw new \RuntimeException('getFiniteEnd() method can not return null');
        }

        return $this->end;
    }

    /**
     * <p>Yields a copy of this interval with given start time. </p>
     */
    public function withStart(LocalDateTime $t): self
    {
        return self::between($t, $this->end);
    }

    /**
     * <p>Yields a copy of this interval with given end time. </p>
     */
    public function withEnd(LocalDateTime $t): self
    {
        return self::between($this->start, $t);
    }

    /**
     * <p>Yields a descriptive string of start and end. </p>
     */
    public function toString(): string
    {
        $output = '';
        if ($this->hasInfiniteStart()) {
            $output .= InfinityStyle::SYMBOL;
        } else {
            $output .= $this->start;
        }

        $output .= '/';

        if ($this->hasInfiniteEnd()) {
            $output .= InfinityStyle::SYMBOL;
        } else {
            $output .= $this->end;
        }

        return $output;
    }

    /**
     * Combines this local datetime interval with the timezone offset UTC+00:00 to a global UTC-interval.
     *
     * @return InstantInterval global timestamp interval interpreted at offset UTC+00:00
     */
    public function atUTC(): InstantInterval
    {
        return InstantInterval::between(
            $this->start ? $this->start->atTimeZone(TimeZoneOffset::utc())->getInstant() : null,
            $this->end ? $this->end->atTimeZone(TimeZoneOffset::utc())->getInstant() : null
        );
    }

    /**
     * Combines this local timestamp interval with given timezone to a ZonedInterval.
     *
     * @param TimeZoneRegion $timezoneId timezone id
     *
     * @return ZonedDateTimeInterval zoned datetime interval interpreted in given timezone
     */
    public function atTimeZone(TimeZoneRegion $timezoneId): ZonedDateTimeInterval
    {
        return ZonedDateTimeInterval::between(
            $this->start ? $this->start->atTimeZone($timezoneId) : null,
            $this->end ? $this->end->atTimeZone($timezoneId) : null
        );
    }

    /**
     * <p>Interpreters a given text as interval. </p>
     *
     * @param string $text text to be parsed
     */
    public static function parse(string $text): self
    {
        [$startStr, $endStr] = explode('/', trim($text), 2);

        $startStr = new UnicodeString($startStr);
        $endStr = new UnicodeString($endStr);

        $startsWithPeriod = $startStr->startsWith('P');
        $startsWithInfinity = $startStr->equalsTo(InfinityStyle::SYMBOL);

        $endsWithPeriod = $endStr->startsWith('P');
        $endsWithInfinity = $endStr->equalsTo(InfinityStyle::SYMBOL);

        if ($startsWithPeriod && $endsWithPeriod) {
            throw IntervalParseException::uniqueDuration($text);
        }

        if (($startsWithPeriod && $endsWithInfinity) ||
            ($startsWithInfinity && $endsWithPeriod)
        ) {
            throw IntervalParseException::durationIncompatibleWithInfinity($text);
        }

        //START
        if ($startsWithInfinity) {
            $ldt1 = null;
        } elseif ($startsWithPeriod) {
            $ldt2 = LocalDateTime::parse($endStr->toString());
            $ldt1 = $startStr->indexOf('T')
                ? $ldt2->minusDuration(Duration::parse($startStr->toString()))
                : $ldt2->minusPeriod(Period::parse($startStr->toString()));

            return self::between($ldt1, $ldt2);
        } else {
            $ldt1 = LocalDateTime::parse($startStr->toString());
        }

        //END
        if ($endsWithInfinity) {
            $ldt2 = null;
        } elseif ($endsWithPeriod) {
            $ldt2 = $endStr->indexOf('T')
                ? ($ldt1 ? $ldt1->plusDuration(Duration::parse($endStr->toString())) : null)
                : ($ldt1 ? $ldt1->plusPeriod(Period::parse($endStr->toString())) : null);
        } else {
            $ldt2 = LocalDateTime::parse($endStr->toString());
        }

        return self::between($ldt1, $ldt2);
    }

    /**
     * <p>Moves this interval along the POSIX-axis by given duration or period. </p>
     *
     * @param Duration|Period $periodOrDuration
     *
     * @return self moved copy of this interval
     */
    public function move($periodOrDuration): self
    {
        /** @var Period|Duration|mixed $periodOrDuration (for static analysis)) */
        if ($periodOrDuration instanceof Period) {
            return new self(
                $this->start ? $this->start->plusPeriod($periodOrDuration) : null,
                $this->end ? $this->end->plusPeriod($periodOrDuration) : null
            );
        }

        if ($periodOrDuration instanceof Duration) {
            return new self(
                $this->start ? $this->start->plusDuration($periodOrDuration) : null,
                $this->end ? $this->end->plusDuration($periodOrDuration) : null
            );
        }

        throw new \RuntimeException('The given value must be either Duration or Period.');
    }

    /**
     * <p>Yields the length of this interval and applies a timezone offset correction . </p>
     *
     * @return Duration duration including a zonal correction
     */
    public function getDuration(): Duration
    {
        if (!$this->isFinite()) {
            throw new \RuntimeException('Yield duration with infinite boundary is not possible.');
        }

        return $this->atUTC()->getDuration();
    }

    /**
     * <p>Iterate through every moment which is the result of addition of given duration or period
     * to start until the end of this interval is reached. </p>
     *
     * @param Period|Duration $periodOrDuration
     *
     * @return Traversable<LocalDateTime>
     */
    public function iterate($periodOrDuration): Traversable
    {
        /** @var Period|Duration|mixed $periodOrDuration (for static analysis)) */
        if (!$this->isFinite()) {
            throw new \RuntimeException('Streaming is not supported for infinite intervals.');
        }

        if (!($periodOrDuration instanceof Period || $periodOrDuration instanceof Duration)) {
            throw new \RuntimeException('Instance of Duration or Period expected.');
        }

        for ($start = $this->getFiniteStart(); $start->isBefore($this->getFiniteEnd());) {
            yield $start;

            $start = $periodOrDuration instanceof Period
                ? $start->plusPeriod($periodOrDuration)
                : $start->plusDuration($periodOrDuration);
        }
    }

    /**
     * <p>Determines if this interval is empty. </p>
     */
    public function isEmpty(): bool
    {
        if ($this->isFinite()) {
            return 0 === $this->getFiniteStart()->compareTo($this->getFiniteEnd());
        }

        return false;
    }

    /**
     * <p>Is this interval before the given time point? </p>
     */
    public function isBefore(LocalDateTime $t): bool
    {
        if ($this->hasInfiniteEnd()) {
            return false;
        }

        return $this->getFiniteEnd()->isBeforeOrEqualTo($t);
    }

    /**
     * <p>Is this interval before the other one? </p>
     */
    public function isBeforeInterval(self $other): bool
    {
        if ($other->hasInfiniteStart() || $this->hasInfiniteEnd()) {
            return false;
        }

        $endA = $this->getFiniteEnd();
        $startB = $other->getFiniteStart();

        return $endA->isBeforeOrEqualTo($startB);
    }

    /**
     * <p>Is this interval after the given time point? </p>
     */
    public function isAfter(LocalDateTime $t): bool
    {
        if ($this->hasInfiniteStart()) {
            return false;
        }

        return $this->getFiniteStart()->isAfter($t);
    }

    /**
     * <p>Is this interval after the other one? </p>
     */
    public function isAfterInterval(self $other): bool
    {
        return $other->isBeforeInterval($this);
    }

    /**
     * <p>Queries if given time point belongs to this interval. </p>
     */
    public function contains(LocalDateTime $t): bool
    {
        if ($this->hasInfiniteStart()) {
            $startCondition = true;
        } else {
            $startCondition = !$this->getFiniteStart()->isAfter($t);
        }

        if (!$startCondition) {
            return false; // short-cut
        }

        if ($this->hasInfiniteEnd()) {
            $endCondition = true;
        } else {
            $endCondition = $this->getFiniteEnd()->isAfter($t);
        }

        return $endCondition;
    }

    /**
     * <p>Does this interval contain the other one? </p>
     */
    public function containsInterval(self $other): bool
    {
        if (!$other->isFinite()) {
            return false;
        }

        if ($this->hasInfiniteStart() && $other->getFiniteEnd()->isBeforeOrEqualTo($this->getFiniteEnd())) {
            return true;
        }

        if ($this->hasInfiniteStart() && !$other->getFiniteEnd()->isBeforeOrEqualTo($this->getFiniteEnd())) {
            return false;
        }

        if ($this->hasInfiniteEnd() && $other->getFiniteStart()->isAfterOrEqualTo($this->getFiniteStart())) {
            return true;
        }

        if ($this->hasInfiniteEnd() && !$other->getFiniteStart()->isAfterOrEqualTo($this->getFiniteStart())) {
            return false;
        }

        if ($other->getFiniteStart()->isAfterOrEqualTo($this->getFiniteStart()) && $other->getFiniteEnd()->isBeforeOrEqualTo($this->getFiniteEnd())) {
            return true;
        }

        return false;
    }

    /**
     * <p>ALLEN-relation: Does this interval precede the other one such that
     * there is a gap between? </p>
     */
    public function precedes(self $other): bool
    {
        if ($other->hasInfiniteStart() || $this->hasInfiniteEnd()) {
            return false;
        }

        $endA = $this->getFiniteEnd();
        $startB = $other->getStart();

        if (null === $startB) {
            return true;
        }

        return $endA->isBefore($startB);
    }

    /**
     * <p>ALLEN-relation: Equivalent to $other->precedes($this). </p>
     */
    public function precededBy(self $other): bool
    {
        return $other->precedes($this);
    }

    /**
     * <p>ALLEN-relation: Does this interval precede the other one such that
     * there is no gap between? </p>
     */
    public function meets(self $other): bool
    {
        if ($other->hasInfiniteStart() || $this->hasInfiniteEnd()) {
            return false;
        }

        return $this->getFiniteEnd()->isEqualTo($other->getFiniteStart());
    }

    /**
     * <p>ALLEN-relation: Equivalent to $other->meets($this). </p>
     */
    public function metBy(self $other): bool
    {
        return $other->meets($this);
    }

    /**
     * <p>ALLEN-relation: Does this interval finish the other one such that
     * both end time points are equal and the start of this interval is after
     * the start of the other one? </p>
     */
    public function finishes(self $other): bool
    {
        if ((null === $this->end && null !== $other->end) ||
            (null !== $this->end && null === $other->end)) {
            return false;
        }

        if ($this->end && $other->end && !$this->end->isEqualTo($other->end)) {
            return false;
        }

        if ($other->hasInfiniteStart()) {
            return true;
        }

        if ($this->hasInfiniteStart()) {
            return false;
        }

        return $other->getFiniteStart()->isBefore($this->getFiniteStart());
    }

    /**
     * <p>ALLEN-relation: Equivalent to $other->finishes($this). </p>
     */
    public function finishedBy(self $other): bool
    {
        return $other->finishes($this);
    }

    /**
     * <p>ALLEN-relation: Does this interval start the other one such that both
     * start time points are equal and the end of this interval is before the
     * end of the other one? </p>
     */
    public function starts(self $other): bool
    {
        if ((null === $this->start && null !== $other->start) ||
            (null !== $this->start && null === $other->start)) {
            return false;
        }

        if ($this->start && $other->start && !$this->start->isEqualTo($other->start)) {
            return false;
        }

        if ($other->hasInfiniteEnd() && !$this->hasInfiniteEnd()) {
            return true;
        }

        if ($this->hasInfiniteEnd()) {
            return false;
        }

        return $other->getFiniteEnd()->isAfter($this->getFiniteEnd());
    }

    /**
     * <p>ALLEN-relation: Equivalent to $other->starts($this). </p>
     */
    public function startedBy(self $other): bool
    {
        return $other->starts($this);
    }

    /**
     * <p>ALLEN-relation: Does this interval enclose the other one such that
     * this start is before the start of the other one and this end is after
     * the end of the other one? </p>
     */
    public function encloses(self $other): bool
    {
        if (!$other->isFinite()) {
            return false;
        }

        if ($this->hasInfiniteStart() && $other->getFiniteEnd()->isBefore($this->getFiniteEnd())) {
            return true;
        }

        if ($this->hasInfiniteStart() && !$other->getFiniteEnd()->isBefore($this->getFiniteEnd())) {
            return false;
        }

        if ($this->hasInfiniteEnd() && $other->getFiniteStart()->isAfter($this->getFiniteStart())) {
            return true;
        }

        if ($this->hasInfiniteEnd() && !$other->getFiniteStart()->isAfter($this->getFiniteStart())) {
            return false;
        }

        if ($other->getFiniteStart()->isAfter($this->getFiniteStart()) &&
            $other->getFiniteEnd()->isBefore($this->getFiniteEnd())
        ) {
            return true;
        }

        return false;
    }

    /**
     * <p>ALLEN-relation: Equivalent to $other->encloses($this). </p>
     */
    public function enclosedBy(self $other): bool
    {
        return $other->encloses($this);
    }

    /**
     * <p>ALLEN-relation: Does this interval overlaps the other one such that
     * the start of this interval is still before the start of the other
     * one? </p>
     */
    public function overlaps(self $other): bool
    {
        return
            (
                $this->hasInfiniteStart() ||
                $other->hasInfiniteEnd() ||
                (
                    $this->getFiniteStart()->isBefore($other->getFiniteEnd()) &&
                    $this->getFiniteStart()->isBefore($other->getFiniteStart())
                )
            ) &&
            (
                $this->hasInfiniteEnd() ||
                $other->hasInfiniteStart() ||
                (
                    $this->getFiniteEnd()->isAfter($other->getFiniteStart()) &&
                    $this->getFiniteEnd()->isBefore($other->getFiniteEnd())
                )
            );
    }

    /**
     * <p>ALLEN-relation: Equivalent to $other->overlaps($this). </p>
     */
    public function overlappedBy(self $other): bool
    {
        return $other->overlaps($this);
    }

    /**
     * <p>Queries if this interval intersects the other one such that there is at least one common time point. </p>
     *
     * @param self $other another interval which might have an intersection with this interval
     *
     * @return bool "true" if there is an non-empty intersection of this interval and the other one else "false"
     */
    public function intersects(self $other): bool
    {
        return
            (
                $this->hasInfiniteStart() ||
                $other->hasInfiniteEnd() ||
                $this->getFiniteStart()->isBefore($other->getFiniteEnd())) &&
            (
                $this->hasInfiniteEnd() ||
                $other->hasInfiniteStart() ||
                $this->getFiniteEnd()->isAfter($other->getFiniteStart())
            );
    }

    /**
     * <p>Queries if this interval abuts the other one such that there is neither any overlap nor any gap between. </p>
     *
     * <p>Equivalent to the expression {@code this.meets(other) ^ this.metBy(other)}. Empty intervals never abut. </p>
     */
    public function abuts(self $other): bool
    {
        return (bool) ($this->meets($other) ^ $this->metBy($other));
    }

    /**
     * <p>Changes this interval to an empty interval with the same
     * start anchor. </p>
     *
     * @return self empty interval with same start (anchor always inclusive)
     */
    public function collapse(): self
    {
        if ($this->hasInfiniteStart()) {
            throw new \RuntimeException('An interval with infinite past cannot be collapsed.');
        }

        return self::between($this->start, $this->start);
    }

    /**
     * <p>Obtains the intersection of this interval and other one if present. </p>
     *
     * @param self $other another interval which might have an intersection with this interval
     *
     * @return  self|null wrapper around the found intersection or null
     */
    public function findIntersection(self $other): ?self
    {
        if ($this->intersects($other)) {
            if ($this->hasInfiniteStart() || $other->hasInfiniteStart()) {
                $start = null;
            } else {
                $start = LocalDateTime::maxOf($this->getFiniteStart(), $other->getFiniteStart());
            }

            if ($this->hasInfiniteEnd() || $other->hasInfiniteEnd()) {
                $end = null;
            } else {
                $end = LocalDateTime::minOf($this->getFiniteEnd(), $other->getFiniteEnd());
            }

            return self::between($start, $end);
        }

        return null;
    }

    /**
     * <p>Compares the boundaries (start and end) and also the time axis
     * of this and the other interval. </p>
     */
    public function equals(self $other): bool
    {
        if (($this->hasInfiniteStart() && !$other->hasInfiniteStart()) ||
            (!$this->hasInfiniteStart() && $other->hasInfiniteStart()) ||
            ($this->hasInfiniteEnd() && !$other->hasInfiniteEnd()) ||
            (!$this->hasInfiniteEnd() && $other->hasInfiniteEnd())) {
            return false;
        }

        return (
            ($this->hasInfiniteStart() && $other->hasInfiniteStart()) ||
            $this->getFiniteStart()->isEqualTo($other->getFiniteStart())
        ) && (
            ($this->hasInfiniteEnd() && $other->hasInfiniteEnd()) ||
            $this->getFiniteEnd()->isEqualTo($other->getFiniteEnd())
        );
    }

    /**
     * <p>Determines if this interval has finite boundaries. </p>
     */
    public function isFinite(): bool
    {
        return !($this->hasInfiniteStart() || $this->hasInfiniteEnd());
    }

    /**
     * <p>Determines if this interval has infinite start boundary. </p>
     */
    public function hasInfiniteStart(): bool
    {
        return null === $this->start;
    }

    /**
     * <p>Determines if this interval has infinite end boundary. </p>
     */
    public function hasInfiniteEnd(): bool
    {
        return null === $this->end;
    }
}
