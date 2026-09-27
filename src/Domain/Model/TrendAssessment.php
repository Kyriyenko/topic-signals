<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Model;

final readonly class TrendAssessment
{
    /**
     * @param string[] $confidenceReasons human-readable reasons behind the confidence level
     * @param string[] $assumptions limitations and assumptions the reader should know
     */
    public function __construct(
        private TrendDirection $direction,
        private float $percentChange,
        private ConfidenceLevel $confidence,
        private array $confidenceReasons,
        private array $assumptions,
    ) {
    }

    public function direction(): TrendDirection
    {
        return $this->direction;
    }

    public function percentChange(): float
    {
        return $this->percentChange;
    }

    public function confidence(): ConfidenceLevel
    {
        return $this->confidence;
    }

    /** @return string[] */
    public function confidenceReasons(): array
    {
        return $this->confidenceReasons;
    }

    /** @return string[] */
    public function assumptions(): array
    {
        return $this->assumptions;
    }
}
