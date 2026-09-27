<?php

declare(strict_types=1);

namespace TopicSignals\Application\Dto;

final readonly class CompareTopicAcrossLanguagesRequest
{
    /** @param string[] $languages */
    public function __construct(
        public string $topic,
        public array $languages,
        public int $periodMonths = 24,
        public bool $generateChart = true,
        public bool $generateReport = false,
    ) {
    }
}
