<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Service;

use TopicSignals\Domain\Exception\EmptyPageviewSeriesException;
use TopicSignals\Domain\Model\PageviewSeries;
use TopicSignals\Domain\Model\TrendDirection;

/** Classifies growth/decline by comparing the average of the first and second half of a series. */
final class TrendCalculator
{
    private const float STABLE_THRESHOLD_PERCENT = 10.0;

    public function percentChange(PageviewSeries $series): float
    {
        if ($series->isEmpty()) {
            throw EmptyPageviewSeriesException::forAssessment();
        }

        $counts = $series->viewCounts();
        $midpoint = (int) floor(count($counts) / 2);

        if ($midpoint === 0) {
            return 0.0;
        }

        $firstHalf = array_slice($counts, 0, $midpoint);
        $secondHalf = array_slice($counts, $midpoint);

        $firstAverage = array_sum($firstHalf) / count($firstHalf);
        $secondAverage = array_sum($secondHalf) / count($secondHalf);

        if ($firstAverage <= 0.0) {
            return $secondAverage > 0.0 ? 100.0 : 0.0;
        }

        return (($secondAverage - $firstAverage) / $firstAverage) * 100.0;
    }

    public function direction(float $percentChange): TrendDirection
    {
        if ($percentChange > self::STABLE_THRESHOLD_PERCENT) {
            return TrendDirection::GROWING;
        }

        if ($percentChange < -self::STABLE_THRESHOLD_PERCENT) {
            return TrendDirection::DECLINING;
        }

        return TrendDirection::STABLE;
    }
}
