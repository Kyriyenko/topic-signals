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
use TopicSignals\Domain\Service\ConfidenceEvaluator;

final class ConfidenceEvaluatorTest extends TestCase
{
    private ConfidenceEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ConfidenceEvaluator();
    }

    public function test_long_stable_series_yields_high_confidence(): void
    {
        $counts = array_fill(0, 24, 1000);
        $series = $this->seriesFromCounts($counts);

        $result = $this->evaluator->evaluate($series);

        self::assertSame(ConfidenceLevel::HIGH, $result['level']);
    }

    public function test_short_series_yields_low_confidence(): void
    {
        $series = $this->seriesFromCounts([100, 200, 150]);

        $result = $this->evaluator->evaluate($series);

        self::assertSame(ConfidenceLevel::LOW, $result['level']);
        self::assertNotEmpty($result['reasons']);
    }

    public function test_highly_volatile_series_lowers_confidence(): void
    {
        $counts = [];
        for ($i = 0; $i < 12; $i++) {
            $counts[] = $i % 2 === 0 ? 10 : 2000;
        }
        $series = $this->seriesFromCounts($counts);

        $result = $this->evaluator->evaluate($series);

        self::assertNotSame(ConfidenceLevel::HIGH, $result['level']);
    }

    public function test_mostly_zero_series_lowers_confidence(): void
    {
        $counts = array_merge(array_fill(0, 10, 0), [50, 60]);
        $series = $this->seriesFromCounts($counts);

        $result = $this->evaluator->evaluate($series);

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
