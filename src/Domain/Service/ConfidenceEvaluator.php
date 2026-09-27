<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Service;

use TopicSignals\Domain\Exception\EmptyPageviewSeriesException;
use TopicSignals\Domain\Model\ConfidenceLevel;
use TopicSignals\Domain\Model\PageviewSeries;

/**
 * Estimates how much a trend conclusion can be trusted, based on sample size,
 * volatility and how much of the series has non-zero data.
 */
final class ConfidenceEvaluator
{
    private const int MIN_POINTS_FOR_HIGH = 12;
    private const int MIN_POINTS_FOR_MEDIUM = 6;
    private const float HIGH_VOLATILITY_CV = 0.75;
    private const float LOW_COVERAGE_RATIO = 0.6;

    /** @return array{level: ConfidenceLevel, reasons: string[]} */
    public function evaluate(PageviewSeries $series): array
    {
        if ($series->isEmpty()) {
            throw EmptyPageviewSeriesException::forAssessment();
        }

        $reasons = [];
        $penalties = 0;

        $pointCount = $series->count();
        if ($pointCount < self::MIN_POINTS_FOR_MEDIUM) {
            $penalties += 2;
            $reasons[] = sprintf(
                'Only %d data points available; short series make the trend unreliable.',
                $pointCount,
            );
        } elseif ($pointCount < self::MIN_POINTS_FOR_HIGH) {
            $penalties += 1;
            $reasons[] = sprintf(
                'Only %d data points available; a longer history would increase confidence.',
                $pointCount,
            );
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

        $level = match (true) {
            $penalties >= 2 => ConfidenceLevel::LOW,
            $penalties === 1 => ConfidenceLevel::MEDIUM,
            default => ConfidenceLevel::HIGH,
        };

        if ($reasons === []) {
            $reasons[] = 'Sufficient, stable, non-zero data across the requested period.';
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
