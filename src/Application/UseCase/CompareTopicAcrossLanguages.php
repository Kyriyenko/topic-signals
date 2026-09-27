<?php

declare(strict_types=1);

namespace TopicSignals\Application\UseCase;

use TopicSignals\Application\Dto\CompareTopicAcrossLanguagesRequest;
use TopicSignals\Application\Dto\CompareTopicAcrossLanguagesResponse;
use TopicSignals\Application\Dto\ReportContent;
use TopicSignals\Application\Dto\TrendResult;
use TopicSignals\Application\Dto\UnresolvedLanguage;
use TopicSignals\Application\Exception\AmbiguousArticleMatchException;
use TopicSignals\Application\Exception\ApplicationException;
use TopicSignals\Application\Exception\InsufficientDataException;
use TopicSignals\Application\Port\ArticleResolverPort;
use TopicSignals\Application\Port\ChartRendererPort;
use TopicSignals\Application\Port\ReportRendererPort;
use TopicSignals\Application\Port\WikipediaPageviewsPort;
use TopicSignals\Application\Support\Slug;
use TopicSignals\Domain\Model\DateRange;
use TopicSignals\Domain\Model\Language;
use TopicSignals\Domain\Model\Topic;
use TopicSignals\Domain\Service\TrendAssessor;

/** Compares interest in one topic across several Wikipedia language editions. */
final class CompareTopicAcrossLanguages
{
    public function __construct(
        private readonly ArticleResolverPort $articleResolver,
        private readonly WikipediaPageviewsPort $pageviews,
        private readonly TrendAssessor $trendAssessor,
        private readonly ChartRendererPort $chartRenderer,
        private readonly ReportRendererPort $reportRenderer,
    ) {
    }

    public function execute(CompareTopicAcrossLanguagesRequest $request): CompareTopicAcrossLanguagesResponse
    {
        $topic = new Topic($request->topic);
        $range = DateRange::lastMonths($request->periodMonths);

        $results = [];
        $unresolved = [];
        $seriesByLabel = [];
        $assumptions = [];

        foreach ($request->languages as $languageCode) {
            $language = new Language($languageCode);

            try {
                $article = $this->articleResolver->resolve($topic, $language);
                $series = $this->pageviews->fetchMonthlyViews($article, $range);

                if ($series->isEmpty()) {
                    throw InsufficientDataException::forArticle($article);
                }

                $assessment = $this->trendAssessor->assess($series);

                $results[] = new TrendResult(
                    language: $language->code(),
                    articleTitle: $article->title(),
                    direction: $assessment->direction()->value,
                    percentChange: $assessment->percentChange(),
                    confidence: $assessment->confidence()->value,
                    confidenceReasons: $assessment->confidenceReasons(),
                    dataPointsCount: $series->count(),
                    totalViews: $series->totalViews(),
                );
                $seriesByLabel[$language->code()] = $series;
                $assumptions = $assessment->assumptions();
            } catch (AmbiguousArticleMatchException $e) {
                $unresolved[] = new UnresolvedLanguage(
                    $language->code(),
                    $e->getMessage() . ' Candidates: ' . implode(', ', $e->candidateTitles()),
                );
            } catch (ApplicationException $e) {
                $unresolved[] = new UnresolvedLanguage($language->code(), $e->getMessage());
            }
        }

        usort($results, static fn (TrendResult $a, TrendResult $b) => $b->percentChange <=> $a->percentChange);

        $chartPath = null;
        if ($request->generateChart && $seriesByLabel !== []) {
            $chartPath = $this->chartRenderer->renderLineChart(
                title: sprintf('%s — interest by language edition', $topic->label()),
                seriesByLabel: $seriesByLabel,
                outputFileName: Slug::make($topic->label()) . '-compare.png',
            );
        }

        $reportPath = null;
        if ($request->generateReport && $results !== []) {
            $reportPath = $this->reportRenderer->renderOnePagePdf(
                new ReportContent(
                    title: sprintf('%s — language comparison', $topic->label()),
                    keyFindings: array_map(
                        static fn (TrendResult $r) => sprintf(
                            '%s: %s (%+.1f%%, confidence %s)',
                            strtoupper($r->language),
                            $r->direction,
                            $r->percentChange,
                            $r->confidence,
                        ),
                        $results,
                    ),
                    assumptions: $assumptions,
                    recommendedNextSteps: [
                        'Investigate the highest-confidence, fastest-growing language edition first.',
                        'For low-confidence results, extend the observation period before deciding.',
                    ],
                    chartImagePath: $chartPath,
                ),
                outputFileName: Slug::make($topic->label()) . '-compare.pdf',
            );
        }

        return new CompareTopicAcrossLanguagesResponse(
            topic: $topic->label(),
            results: $results,
            unresolved: $unresolved,
            assumptions: $assumptions,
            chartPath: $chartPath,
            reportPath: $reportPath,
        );
    }
}
