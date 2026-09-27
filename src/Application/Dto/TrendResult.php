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
        public float $confidenceIntervalLow,
        public float $confidenceIntervalHigh,
        public string $method,
        public int $spikesRemoved,
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
            'confidence_interval_90' => [
                'low' => round($this->confidenceIntervalLow, 1),
                'high' => round($this->confidenceIntervalHigh, 1),
            ],
            'method' => $this->method,
            'spikes_removed' => $this->spikesRemoved,
            'confidence' => $this->confidence,
            'confidence_reasons' => $this->confidenceReasons,
            'data_points_count' => $this->dataPointsCount,
            'total_views' => $this->totalViews,
        ];
    }
}
