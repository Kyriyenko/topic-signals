<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Exception;

final class InvalidLanguageCodeException extends DomainException
{
    public static function forCode(string $code): self
    {
        return new self(sprintf('"%s" is not a valid Wikipedia language code.', $code));
    }
}
