<?php

declare(strict_types=1);

namespace TopicSignals\Application\Dto;

final readonly class ReportContent
{
    /**
     * @param string[] $keyFindings
     * @param string[] $assumptions
     * @param string[] $recommendedNextSteps
     */
    public function __construct(
        public string $title,
        public array $keyFindings,
        public array $assumptions,
        public array $recommendedNextSteps,
        public ?string $chartImagePath,
    ) {
    }
}
