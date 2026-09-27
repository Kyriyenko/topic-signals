<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Service;

use TopicSignals\Domain\Exception\EmptyPageviewSeriesException;
use TopicSignals\Domain\Model\ConfidenceLevel;
use TopicSignals\Domain\Model\PageviewSeries;
use TopicSignals\Domain\Model\TrendMethod;
use TopicSignals\Domain\Model\TrendStatistics;

/**
 * Estimates how much a trend conclusion can be trusted: sample size, volatility,
 * how much of the series has non-zero data, and how wide the bootstrap
 * confidence interval turned out to be.
 */
final class ConfidenceEvaluator
{
    private const int MIN_POINTS_FOR_ANY_TREND = 6;
    private const float HIGH_VOLATILITY_CV = 0.75;
    private const float LOW_COVERAGE_RATIO = 0.6;
    private const float WIDE_INTERVAL_PERCENTAGE_POINTS = 150.0;

    /** @return array{level: ConfidenceLevel, reasons: string[]} */
    public function evaluate(PageviewSeries $series, TrendStatistics $stats): array
    {
        if ($series->isEmpty()) {
            throw EmptyPageviewSeriesException::forAssessment();
        }

        $reasons = [];
        $penalties = 0;

        $pointCount = $series->count();

        if ($pointCount < self::MIN_POINTS_FOR_ANY_TREND) {
            $penalties += 2;
            $reasons[] = sprintf('Only %d data points available; too little history to trust any trend.', $pointCount);
        } elseif ($stats->method() === TrendMethod::HALF_PERIOD) {
            $penalties += 1;
            $reasons[] = 'Fewer than 24 months of data, so the estimate compares the first and second half of '
                . 'the period instead of year-over-year, which is less robust to seasonal swings.';
        }

        $counts = $series->viewCounts();
        $mean = array_sum($counts) / $pointCount;
        $coefficientOfVariation = $mean > 0.0 ? $this->standardDeviation($counts, $mean) / $mean : 0.0;

        if ($coefficientOfVariation > self::HIGH_VOLATILITY_CV) {
            $penalties += 1;
            $reasons[] = sprintf(
                'Pageviews are highly volatile (coefficient of variation %.2f), which can mask or exaggerate a trend.',
                $coefficientOfVariation,
            );
        }

        $nonZeroCount = count(array_filter($counts, static fn (int $v) => $v > 0));
        $coverageRatio = $nonZeroCount / $pointCount;

        if ($coverageRatio < self::LOW_COVERAGE_RATIO) {
            $penalties += 1;
            $reasons[] = sprintf(
                'Only %d%% of the periods have non-zero views; the topic may be too niche for this language edition.',
                (int) round($coverageRatio * 100),
            );
        }

        $intervalWidth = abs($stats->confidenceIntervalHigh() - $stats->confidenceIntervalLow());

        if ($intervalWidth > self::WIDE_INTERVAL_PERCENTAGE_POINTS) {
            $penalties += 1;
            $reasons[] = sprintf(
                'The 90%% confidence interval for growth is wide (%+.0f%% to %+.0f%%), so the exact size of the trend is uncertain.',
                $stats->confidenceIntervalLow(),
                $stats->confidenceIntervalHigh(),
            );
        }

        if ($stats->spikesRemoved() > 0) {
            $reasons[] = sprintf(
                '%d unusually high month(s) (likely a news-driven spike) were smoothed out before calculating the trend.',
                $stats->spikesRemoved(),
            );
        }

        $level = match (true) {
            $penalties >= 2 => ConfidenceLevel::LOW,
            $penalties === 1 => ConfidenceLevel::MEDIUM,
            default => ConfidenceLevel::HIGH,
        };

        if ($reasons === []) {
            $reasons[] = 'Sufficient, stable, non-zero data and a narrow confidence interval across the requested period.';
        }

        return ['level' => $level, 'reasons' => $reasons];
    }

    /** @param int[] $values */
    private function standardDeviation(array $values, float $mean): float
    {
        $count = count($values);
        if ($count < 2) {
            return 0.0;
        }

        $sumSquaredDiffs = array_sum(array_map(
            static fn (int $v) => ($v - $mean) ** 2,
            $values,
        ));

        return sqrt($sumSquaredDiffs / $count);
    }
}
