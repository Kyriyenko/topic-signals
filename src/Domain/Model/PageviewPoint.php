<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Model;

use DateTimeImmutable;

final readonly class PageviewPoint
{
    public function __construct(
        private DateTimeImmutable $date,
        private int $views,
    ) {
    }

    public function date(): DateTimeImmutable
    {
        return $this->date;
    }

    public function views(): int
    {
        return $this->views;
    }
}
