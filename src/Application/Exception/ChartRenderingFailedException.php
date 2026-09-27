<?php

declare(strict_types=1);

namespace TopicSignals\Application\Exception;

final class ChartRenderingFailedException extends ApplicationException
{
    public static function forReason(string $reason): self
    {
        return new self(sprintf('Chart rendering failed: %s', $reason));
    }
}
