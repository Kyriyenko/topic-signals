<?php

declare(strict_types=1);

namespace TopicSignals\Application\Dto;

/** Serializable projection of a single language's trend, shared across use case responses. */
final readonly class TrendResult
{
    /**
     * @param string[] $confidenceReasons
     */
    public function __construct(
        public string $language,
        public string $articleTitle,
        public string $direction,
        public float $percentChange,
        public string $confidence,
        public array $confidenceReasons,
        public int $dataPointsCount,
        public int $totalViews,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'language' => $this->language,
            'article_title' => $this->articleTitle,
            'direction' => $this->direction,
            'percent_change' => round($this->percentChange, 1),
            'confidence' => $this->confidence,
            'confidence_reasons' => $this->confidenceReasons,
            'data_points_count' => $this->dataPointsCount,
            'total_views' => $this->totalViews,
        ];
    }
}
