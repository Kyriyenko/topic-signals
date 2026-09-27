<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Model;

use DateTimeImmutable;
use TopicSignals\Domain\Exception\InvalidDateRangeException;

final readonly class DateRange
{
    public function __construct(
        private DateTimeImmutable $start,
        private DateTimeImmutable $end,
    ) {
        if ($this->start > $this->end) {
            throw InvalidDateRangeException::startAfterEnd($this->start, $this->end);
        }
    }

    public static function lastMonths(int $months, ?DateTimeImmutable $now = null): self
    {
        $end = $now ?? new DateTimeImmutable('yesterday');
        $start = $end->modify("-{$months} months");

        return new self($start, $end);
    }

    public function start(): DateTimeImmutable
    {
        return $this->start;
    }

    public function end(): DateTimeImmutable
    {
        return $this->end;
    }
}
