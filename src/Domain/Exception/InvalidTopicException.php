<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Exception;

final class InvalidTopicException extends DomainException
{
    public static function forEmptyLabel(): self
    {
        return new self('Topic label must not be empty.');
    }

    public static function forTooLongLabel(): self
    {
        return new self('Topic label must not exceed 200 characters.');
    }
}
