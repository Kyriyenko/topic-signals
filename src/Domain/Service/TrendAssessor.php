<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Service;

use TopicSignals\Domain\Model\PageviewSeries;
use TopicSignals\Domain\Model\TrendAssessment;

/** Combines trend direction and confidence into a single, ready-to-report assessment. */
final class TrendAssessor
{
    /** Assumptions that apply to every Wikipedia-pageviews-based assessment. */
    private const array STANDARD_ASSUMPTIONS = [
        'Wikipedia pageview interest is a proxy signal, not proof of willingness to pay for a product.',
        'Spikes can be caused by news events, anniversaries or social media mentions unrelated to product demand.',
        'Pageview volume reflects readers of that language edition, not necessarily speakers of that language or residents of a specific country.',
    ];

    public function __construct(
        private readonly TrendCalculator $trendCalculator,
        private readonly ConfidenceEvaluator $confidenceEvaluator,
    ) {
    }

    public function assess(PageviewSeries $series): TrendAssessment
    {
        $stats = $this->trendCalculator->calculate($series);
        $direction = $this->trendCalculator->direction($stats);
        $confidence = $this->confidenceEvaluator->evaluate($series, $stats);

        return new TrendAssessment(
            direction: $direction,
            percentChange: $stats->percentChange(),
            confidenceIntervalLow: $stats->confidenceIntervalLow(),
            confidenceIntervalHigh: $stats->confidenceIntervalHigh(),
            method: $stats->method(),
            spikesRemoved: $stats->spikesRemoved(),
            confidence: $confidence['level'],
            confidenceReasons: $confidence['reasons'],
            assumptions: self::STANDARD_ASSUMPTIONS,
        );
    }
}
