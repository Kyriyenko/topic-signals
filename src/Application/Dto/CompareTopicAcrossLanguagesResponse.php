<?php

declare(strict_types=1);

namespace TopicSignals\Application\Dto;

final readonly class CompareTopicAcrossLanguagesResponse
{
    /**
     * @param TrendResult[] $results ordered by percent change, descending
     * @param UnresolvedLanguage[] $unresolved languages that could not be analyzed
     * @param string[] $assumptions
     */
    public function __construct(
        public string $topic,
        public array $results,
        public array $unresolved,
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
            'results' => array_map(static fn (TrendResult $r) => $r->toArray(), $this->results),
            'unresolved' => array_map(static fn (UnresolvedLanguage $u) => $u->toArray(), $this->unresolved),
            'assumptions' => $this->assumptions,
            'chart_path' => $this->chartPath,
            'report_path' => $this->reportPath,
        ];
    }
}
