<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Exception;

final class EmptyPageviewSeriesException extends DomainException
{
    public static function forAssessment(): self
    {
        return new self('Cannot assess a trend from an empty pageview series.');
    }
}
