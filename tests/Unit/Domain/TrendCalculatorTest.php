<?php

declare(strict_types=1);

namespace TopicSignals\Tests\Unit\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TopicSignals\Domain\Model\ArticleReference;
use TopicSignals\Domain\Model\DateRange;
use TopicSignals\Domain\Model\Language;
use TopicSignals\Domain\Model\PageviewPoint;
use TopicSignals\Domain\Model\PageviewSeries;
use TopicSignals\Domain\Model\TrendDirection;
use TopicSignals\Domain\Model\TrendMethod;
use TopicSignals\Domain\Service\TrendCalculator;

final class TrendCalculatorTest extends TestCase
{
    private TrendCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new TrendCalculator();
    }

    public function test_uses_year_over_year_with_24_months_of_data_and_detects_growth(): void
    {
        $prior12 = array_fill(0, 12, 1000);
        $recent12 = array_fill(0, 12, 3000);
        $series = $this->seriesFromCounts([...$prior12, ...$recent12]);

        $stats = $this->calculator->calculate($series);

        self::assertSame(TrendMethod::YEAR_OVER_YEAR, $stats->method());
        self::assertEqualsWithDelta(200.0, $stats->percentChange(), 0.01);
        self::assertGreaterThan(0.0, $stats->confidenceIntervalLow());
        self::assertSame(TrendDirection::GROWING, $this->calculator->direction($stats));
    }

    public function test_detects_declining_trend_with_confidence_interval_entirely_negative(): void
    {
        $prior12 = array_fill(0, 12, 3000);
        $recent12 = array_fill(0, 12, 1000);
        $series = $this->seriesFromCounts([...$prior12, ...$recent12]);

        $stats = $this->calculator->calculate($series);

        self::assertLessThan(0.0, $stats->confidenceIntervalHigh());
        self::assertSame(TrendDirection::DECLINING, $this->calculator->direction($stats));
    }

    public function test_noisy_flat_series_has_confidence_interval_straddling_zero(): void
    {
        $prior12 = [95, 105, 98, 102, 97, 103, 99, 101, 96, 104, 100, 100];
        $recent12 = [100, 98, 103, 97, 101, 99, 104, 96, 102, 98, 100, 100];
        $series = $this->seriesFromCounts([...$prior12, ...$recent12]);

        $stats = $this->calculator->calculate($series);

        self::assertSame(TrendDirection::STABLE, $this->calculator->direction($stats));
        self::assertLessThanOrEqual(0.0, $stats->confidenceIntervalLow());
        self::assertGreaterThanOrEqual(0.0, $stats->confidenceIntervalHigh());
    }

    public function test_falls_back_to_half_period_method_with_fewer_than_24_months(): void
    {
        $series = $this->seriesFromCounts([1000, 1000, 1000, 3000, 3000, 3000]);

        $stats = $this->calculator->calculate($series);

        self::assertSame(TrendMethod::HALF_PERIOD, $stats->method());
        self::assertEqualsWithDelta(200.0, $stats->percentChange(), 0.01);
    }

    public function test_handles_zero_baseline_without_division_error(): void
    {
        $series = $this->seriesFromCounts([0, 0, 0, 50, 60, 70]);

        $stats = $this->calculator->calculate($series);

        self::assertSame(100.0, $stats->percentChange());
        self::assertSame(TrendDirection::GROWING, $this->calculator->direction($stats));
    }

    /** @param int[] $counts */
    private function seriesFromCounts(array $counts): PageviewSeries
    {
        $start = new DateTimeImmutable('2023-01-01');
        $points = [];

        foreach ($counts as $index => $views) {
            $points[] = new PageviewPoint($start->modify("+{$index} months"), $views);
        }

        return new PageviewSeries(
            new ArticleReference(new Language('en'), 'Example'),
            new DateRange($start, $start->modify('+' . count($counts) . ' months')),
            $points,
        );
    }
}
