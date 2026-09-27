<?php

declare(strict_types=1);

namespace TopicSignals\Application\Exception;

final class DataSourceUnavailableException extends ApplicationException
{
    public static function forWikimediaApi(string $reason): self
    {
        return new self(sprintf('Wikimedia API is unavailable: %s', $reason));
    }
}
