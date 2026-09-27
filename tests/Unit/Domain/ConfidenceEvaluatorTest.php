<?php

declare(strict_types=1);

namespace TopicSignals\Tests\Unit\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TopicSignals\Domain\Model\ArticleReference;
use TopicSignals\Domain\Model\ConfidenceLevel;
use TopicSignals\Domain\Model\DateRange;
use TopicSignals\Domain\Model\Language;
use TopicSignals\Domain\Model\PageviewPoint;
use TopicSignals\Domain\Model\PageviewSeries;
use TopicSignals\Domain\Model\TrendMethod;
use TopicSignals\Domain\Model\TrendStatistics;
use TopicSignals\Domain\Service\ConfidenceEvaluator;

final class ConfidenceEvaluatorTest extends TestCase
{
    private ConfidenceEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ConfidenceEvaluator();
    }

    public function test_long_stable_series_with_narrow_interval_yields_high_confidence(): void
    {
        $counts = array_fill(0, 24, 1000);
        $series = $this->seriesFromCounts($counts);
        $stats = new TrendStatistics(0.0, -2.0, 2.0, TrendMethod::YEAR_OVER_YEAR);

        $result = $this->evaluator->evaluate($series, $stats);

        self::assertSame(ConfidenceLevel::HIGH, $result['level']);
    }

    public function test_short_series_yields_low_confidence(): void
    {
        $series = $this->seriesFromCounts([100, 200, 150]);
        $stats = new TrendStatistics(50.0, -10.0, 120.0, TrendMethod::HALF_PERIOD);

        $result = $this->evaluator->evaluate($series, $stats);

        self::assertSame(ConfidenceLevel::LOW, $result['level']);
        self::assertNotEmpty($result['reasons']);
    }

    public function test_half_period_method_alone_is_not_high_confidence(): void
    {
        $counts = array_fill(0, 12, 1000);
        $series = $this->seriesFromCounts($counts);
        $stats = new TrendStatistics(0.0, -2.0, 2.0, TrendMethod::HALF_PERIOD);

        $result = $this->evaluator->evaluate($series, $stats);

        self::assertNotSame(ConfidenceLevel::HIGH, $result['level']);
    }

    public function test_wide_confidence_interval_lowers_confidence(): void
    {
        $counts = array_fill(0, 24, 1000);
        $series = $this->seriesFromCounts($counts);
        $stats = new TrendStatistics(20.0, -80.0, 120.0, TrendMethod::YEAR_OVER_YEAR);

        $result = $this->evaluator->evaluate($series, $stats);

        self::assertNotSame(ConfidenceLevel::HIGH, $result['level']);
    }

    public function test_mostly_zero_series_lowers_confidence(): void
    {
        $counts = array_merge(array_fill(0, 10, 0), [50, 60]);
        $series = $this->seriesFromCounts($counts);
        $stats = new TrendStatistics(0.0, -1.0, 1.0, TrendMethod::HALF_PERIOD);

        $result = $this->evaluator->evaluate($series, $stats);

        self::assertNotSame(ConfidenceLevel::HIGH, $result['level']);
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
