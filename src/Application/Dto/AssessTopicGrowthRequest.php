<?php

declare(strict_types=1);

namespace TopicSignals\Application\Dto;

final readonly class AssessTopicGrowthRequest
{
    public function __construct(
        public string $topic,
        public string $language,
        public int $periodMonths = 24,
        public bool $generateChart = true,
        public bool $generateReport = false,
    ) {
    }
}
