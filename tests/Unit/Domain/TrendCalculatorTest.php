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
use TopicSignals\Domain\Service\TrendCalculator;

final class TrendCalculatorTest extends TestCase
{
    private TrendCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new TrendCalculator();
    }

    public function test_detects_growing_trend(): void
    {
        $series = $this->seriesFromCounts([100, 100, 100, 300, 300, 300]);

        $percentChange = $this->calculator->percentChange($series);

        self::assertEqualsWithDelta(200.0, $percentChange, 0.01);
        self::assertSame(TrendDirection::GROWING, $this->calculator->direction($percentChange));
    }

    public function test_detects_declining_trend(): void
    {
        $series = $this->seriesFromCounts([300, 300, 300, 100, 100, 100]);

        $percentChange = $this->calculator->percentChange($series);

        self::assertLessThan(0, $percentChange);
        self::assertSame(TrendDirection::DECLINING, $this->calculator->direction($percentChange));
    }

    public function test_detects_stable_trend_for_small_fluctuation(): void
    {
        $series = $this->seriesFromCounts([100, 102, 98, 101, 99, 103]);

        $percentChange = $this->calculator->percentChange($series);

        self::assertSame(TrendDirection::STABLE, $this->calculator->direction($percentChange));
    }

    public function test_handles_zero_baseline_without_division_error(): void
    {
        $series = $this->seriesFromCounts([0, 0, 0, 50, 60, 70]);

        $percentChange = $this->calculator->percentChange($series);

        self::assertSame(100.0, $percentChange);
        self::assertSame(TrendDirection::GROWING, $this->calculator->direction($percentChange));
    }

    /** @param int[] $counts */
    private function seriesFromCounts(array $counts): PageviewSeries
    {
        $start = new DateTimeImmutable('2024-01-01');
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
