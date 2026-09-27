<?php

declare(strict_types=1);

namespace TopicSignals\Application\Dto;

final readonly class UnresolvedLanguage
{
    public function __construct(
        public string $language,
        public string $reason,
    ) {
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return ['language' => $this->language, 'reason' => $this->reason];
    }
}
