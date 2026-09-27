<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Exception;

use DateTimeImmutable;

final class InvalidDateRangeException extends DomainException
{
    public static function startAfterEnd(DateTimeImmutable $start, DateTimeImmutable $end): self
    {
        return new self(sprintf(
            'Range start "%s" must not be after end "%s".',
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        ));
    }
}
