<?php

declare(strict_types=1);

namespace TopicSignals\Application\UseCase;

use TopicSignals\Application\Dto\AssessTopicGrowthRequest;
use TopicSignals\Application\Dto\AssessTopicGrowthResponse;
use TopicSignals\Application\Dto\ReportContent;
use TopicSignals\Application\Dto\TrendResult;
use TopicSignals\Application\Port\ArticleResolverPort;
use TopicSignals\Application\Port\ChartRendererPort;
use TopicSignals\Application\Port\ReportRendererPort;
use TopicSignals\Application\Port\WikipediaPageviewsPort;
use TopicSignals\Domain\Model\DateRange;
use TopicSignals\Domain\Model\Language;
use TopicSignals\Domain\Model\Topic;
use TopicSignals\Domain\Service\TrendAssessor;

/** Assesses whether interest in a single topic is growing in one Wikipedia language edition. */
final class AssessTopicGrowth
{
    public function __construct(
        private readonly ArticleResolverPort $articleResolver,
        private readonly WikipediaPageviewsPort $pageviews,
        private readonly TrendAssessor $trendAssessor,
        private readonly ChartRendererPort $chartRenderer,
        private readonly ReportRendererPort $reportRenderer,
    ) {
    }

    public function execute(AssessTopicGrowthRequest $request): AssessTopicGrowthResponse
    {
        $topic = new Topic($request->topic);
        $language = new Language($request->language);
        $range = DateRange::lastMonths($request->periodMonths);

        $article = $this->articleResolver->resolve($topic, $language);
        $series = $this->pageviews->fetchMonthlyViews($article, $range);
        $assessment = $this->trendAssessor->assess($series);

        $trendResult = new TrendResult(
            language: $language->code(),
            articleTitle: $article->title(),
            direction: $assessment->direction()->value,
            percentChange: $assessment->percentChange(),
            confidence: $assessment->confidence()->value,
            confidenceReasons: $assessment->confidenceReasons(),
            dataPointsCount: $series->count(),
            totalViews: $series->totalViews(),
        );

        $chartPath = null;
        if ($request->generateChart) {
            $chartPath = $this->chartRenderer->renderLineChart(
                title: sprintf('%s — %s Wikipedia', $topic->label(), strtoupper($language->code())),
                seriesByLabel: [$language->code() => $series],
                outputFileName: $this->safeFileName($topic->label(), $language->code()) . '.png',
            );
        }

        $reportPath = null;
        if ($request->generateReport) {
            $reportPath = $this->reportRenderer->renderOnePagePdf(
                new ReportContent(
                    title: sprintf('%s — growth assessment (%s)', $topic->label(), strtoupper($language->code())),
                    keyFindings: [
                        sprintf(
                            'Interest is %s (%+.1f%% between the first and second half of the period).',
                            $assessment->direction()->value,
                            $assessment->percentChange(),
                        ),
                        sprintf('Confidence in this trend: %s.', $assessment->confidence()->value),
                        ...$assessment->confidenceReasons(),
                    ],
                    assumptions: $assessment->assumptions(),
                    recommendedNextSteps: [
                        'Cross-check this signal with another source (e.g. app store search trends) before committing budget.',
                        'Re-run this assessment periodically to confirm the trend persists.',
                    ],
                    chartImagePath: $chartPath,
                ),
                outputFileName: $this->safeFileName($topic->label(), $language->code()) . '.pdf',
            );
        }

        return new AssessTopicGrowthResponse(
            topic: $topic->label(),
            trend: $trendResult,
            assumptions: $assessment->assumptions(),
            chartPath: $chartPath,
            reportPath: $reportPath,
        );
    }

    private function safeFileName(string ...$parts): string
    {
        $slug = strtolower(implode('-', $parts));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? 'report';

        return trim($slug, '-');
    }
}
