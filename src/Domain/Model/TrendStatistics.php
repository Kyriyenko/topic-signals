<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Model;

final readonly class TrendStatistics
{
    public function __construct(
        private float $percentChange,
        private float $confidenceIntervalLow,
        private float $confidenceIntervalHigh,
        private TrendMethod $method,
    ) {
    }

    public function percentChange(): float
    {
        return $this->percentChange;
    }

    public function confidenceIntervalLow(): float
    {
        return $this->confidenceIntervalLow;
    }

    public function confidenceIntervalHigh(): float
    {
        return $this->confidenceIntervalHigh;
    }

    public function method(): TrendMethod
    {
        return $this->method;
    }
}
