<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Service;

/**
 * Flattens news-driven upward spikes before trend calculation, so a single
 * viral month doesn't get mistaken for sustained growth. A month only
 * qualifies as a spike when it is BOTH statistically extreme (more than 5
 * robust standard deviations above its local neighborhood's median, via MAD)
 * AND at least double that local median in absolute terms — the two-part
 * gate avoids flagging ordinary noise on small, low-traffic articles.
 * Downward dips are never flattened: a real decline is exactly what this
 * tool is meant to detect, not something to smooth away.
 */
final class Despiker
{
    private const int WINDOW_MONTHS = 2;
    private const float SIGMA_THRESHOLD = 5.0;
    private const float MIN_SPIKE_RATIO = 2.0;
    private const float MAD_TO_SIGMA = 1.4826;
    private const int MIN_POINTS_TO_DESPIKE = 5;

    /**
     * @param int[] $counts
     *
     * @return array{values: int[], spikesRemoved: int}
     */
    public function despike(array $counts): array
    {
        $counts = array_values($counts);
        $n = count($counts);

        if ($n < self::MIN_POINTS_TO_DESPIKE) {
            return ['values' => $counts, 'spikesRemoved' => 0];
        }

        $result = $counts;
        $spikesRemoved = 0;

        for ($i = 0; $i < $n; $i++) {
            $neighborhood = $this->neighborhood($counts, $i, $n);
            $median = $this->median($neighborhood);
            $robustSigma = $this->medianAbsoluteDeviation($neighborhood, $median) * self::MAD_TO_SIGMA;

            $exceedsRatio = $median > 0.0
                ? $counts[$i] > $median * self::MIN_SPIKE_RATIO
                : $counts[$i] > 0;
            $exceedsSigma = $robustSigma > 0.0
                ? ($counts[$i] - $median) > self::SIGMA_THRESHOLD * $robustSigma
                : $counts[$i] > $median;

            if ($exceedsRatio && $exceedsSigma) {
                $result[$i] = (int) round($median);
                $spikesRemoved++;
            }
        }

        return ['values' => $result, 'spikesRemoved' => $spikesRemoved];
    }

    /** @param int[] $counts
     * @return int[] the ±WINDOW_MONTHS neighborhood around index $i, excluding $i itself */
    private function neighborhood(array $counts, int $i, int $n): array
    {
        $start = max(0, $i - self::WINDOW_MONTHS);
        $end = min($n - 1, $i + self::WINDOW_MONTHS);

        $neighborhood = [];
        for ($j = $start; $j <= $end; $j++) {
            if ($j !== $i) {
                $neighborhood[] = $counts[$j];
            }
        }

        return $neighborhood;
    }

    /** @param int[] $values */
    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2.0;
        }

        return (float) $values[$middle];
    }

    /** @param int[] $values */
    private function medianAbsoluteDeviation(array $values, float $median): float
    {
        $deviations = array_map(static fn (int $v) => abs($v - $median), $values);

        return $this->median($deviations);
    }
}
