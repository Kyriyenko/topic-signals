<?php

declare(strict_types=1);

namespace TopicSignals\Application\Dto;

final readonly class AssessTopicGrowthResponse
{
    /** @param string[] $assumptions */
    public function __construct(
        public string $topic,
        public TrendResult $trend,
        public array $assumptions,
        public ?string $chartPath,
        public ?string $reportPath,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'topic' => $this->topic,
            'trend' => $this->trend->toArray(),
            'assumptions' => $this->assumptions,
            'chart_path' => $this->chartPath,
            'report_path' => $this->reportPath,
        ];
    }
}
