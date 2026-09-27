<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Service;

use Random\Engine\Mt19937;
use Random\Randomizer;
use TopicSignals\Domain\Exception\EmptyPageviewSeriesException;
use TopicSignals\Domain\Model\PageviewSeries;
use TopicSignals\Domain\Model\TrendDirection;
use TopicSignals\Domain\Model\TrendMethod;
use TopicSignals\Domain\Model\TrendStatistics;

/**
 * Computes a growth estimate with a 90% bootstrap confidence interval, using
 * year-over-year comparison (12 recent months vs. the 12 before) when enough
 * history exists — this cancels seasonality, unlike a flat percent change.
 */
final class TrendCalculator
{
    private const int BOOTSTRAP_ITERATIONS = 2000;
    private const int BOOTSTRAP_SEED = 42;
    private const float PERCENTILE_LOW = 0.05;
    private const float PERCENTILE_HIGH = 0.95;
    private const int YEAR_OVER_YEAR_MIN_POINTS = 24;

    public function __construct(private readonly Despiker $despiker = new Despiker())
    {
    }

    public function calculate(PageviewSeries $series): TrendStatistics
    {
        if ($series->isEmpty()) {
            throw EmptyPageviewSeriesException::forAssessment();
        }

        $despiked = $this->despiker->despike($series->viewCounts());
        $counts = $despiked['values'];

        return count($counts) >= self::YEAR_OVER_YEAR_MIN_POINTS
            ? $this->fromPeriods(
                array_slice($counts, -24, 12),
                array_slice($counts, -12),
                TrendMethod::YEAR_OVER_YEAR,
                $despiked['spikesRemoved'],
            )
            : $this->halfPeriod($counts, $despiked['spikesRemoved']);
    }

    public function direction(TrendStatistics $stats): TrendDirection
    {
        return match (true) {
            $stats->confidenceIntervalLow() > 0.0 => TrendDirection::GROWING,
            $stats->confidenceIntervalHigh() < 0.0 => TrendDirection::DECLINING,
            default => TrendDirection::STABLE,
        };
    }

    /** @param int[] $counts */
    private function halfPeriod(array $counts, int $spikesRemoved): TrendStatistics
    {
        $midpoint = (int) floor(count($counts) / 2);

        if ($midpoint === 0) {
            return new TrendStatistics(0.0, 0.0, 0.0, TrendMethod::HALF_PERIOD, $spikesRemoved);
        }

        return $this->fromPeriods(
            array_slice($counts, 0, $midpoint),
            array_slice($counts, $midpoint),
            TrendMethod::HALF_PERIOD,
            $spikesRemoved,
        );
    }

    /**
     * @param int[] $prior
     * @param int[] $recent
     */
    private function fromPeriods(array $prior, array $recent, TrendMethod $method, int $spikesRemoved): TrendStatistics
    {
        $priorSum = array_sum($prior);
        $recentSum = array_sum($recent);

        if ($priorSum <= 0) {
            $pointEstimate = $recentSum > 0 ? 100.0 : 0.0;

            return new TrendStatistics($pointEstimate, $pointEstimate, $pointEstimate, $method, $spikesRemoved);
        }

        $pointEstimate = (($recentSum - $priorSum) / $priorSum) * 100.0;
        [$low, $high] = $this->bootstrapInterval(array_values($prior), array_values($recent));

        return new TrendStatistics($pointEstimate, $low, $high, $method, $spikesRemoved);
    }

    /**
     * Resamples matched (prior, recent) month pairs with replacement to build
     * a 90% confidence interval for the growth percentage.
     *
     * @param int[] $prior
     * @param int[] $recent
     *
     * @return array{0: float, 1: float}
     */
    private function bootstrapInterval(array $prior, array $recent): array
    {
        $pairCount = min(count($prior), count($recent));

        if ($pairCount === 0) {
            return [0.0, 0.0];
        }

        $randomizer = new Randomizer(new Mt19937(self::BOOTSTRAP_SEED));
        $samples = [];

        for ($iteration = 0; $iteration < self::BOOTSTRAP_ITERATIONS; $iteration++) {
            $resampledPrior = 0;
            $resampledRecent = 0;

            for ($i = 0; $i < $pairCount; $i++) {
                $index = $randomizer->getInt(0, $pairCount - 1);
                $resampledPrior += $prior[$index];
                $resampledRecent += $recent[$index];
            }

            if ($resampledPrior <= 0) {
                continue;
            }

            $samples[] = (($resampledRecent - $resampledPrior) / $resampledPrior) * 100.0;
        }

        if ($samples === []) {
            return [0.0, 0.0];
        }

        sort($samples);
        $lastIndex = count($samples) - 1;

        return [
            $samples[(int) floor(self::PERCENTILE_LOW * $lastIndex)],
            $samples[(int) floor(self::PERCENTILE_HIGH * $lastIndex)],
        ];
    }
}
