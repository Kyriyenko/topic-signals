<?php

declare(strict_types=1);

namespace TopicSignals\Tests\Feature;

use PHPUnit\Framework\TestCase;
use TopicSignals\Application\Dto\CompareTopicAcrossLanguagesRequest;
use TopicSignals\Application\UseCase\CompareTopicAcrossLanguages;
use TopicSignals\Domain\Service\ConfidenceEvaluator;
use TopicSignals\Domain\Service\TrendAssessor;
use TopicSignals\Domain\Service\TrendCalculator;
use TopicSignals\Tests\Feature\Fake\FakeArticleResolver;
use TopicSignals\Tests\Feature\Fake\FakeChartRenderer;
use TopicSignals\Tests\Feature\Fake\FakeReportRenderer;
use TopicSignals\Tests\Feature\Fake\FakeWikipediaPageviews;

final class CompareTopicAcrossLanguagesTest extends TestCase
{
    public function test_compares_languages_and_ranks_by_growth(): void
    {
        $useCase = new CompareTopicAcrossLanguages(
            new FakeArticleResolver(['pl' => 'Intermittent fasting', 'cs' => 'Přerušovaný půst']),
            new FakeWikipediaPageviews([
                'pl' => [500, 500, 500, 1500, 1500, 1500],
                'cs' => [500, 500, 500, 550, 500, 520],
            ]),
            new TrendAssessor(new TrendCalculator(), new ConfidenceEvaluator()),
            new FakeChartRenderer(),
            new FakeReportRenderer(),
        );

        $response = $useCase->execute(new CompareTopicAcrossLanguagesRequest(
            topic: 'Intermittent fasting',
            languages: ['pl', 'cs'],
            periodMonths: 6,
            generateChart: true,
            generateReport: true,
        ));

        self::assertCount(2, $response->results);
        self::assertSame('pl', $response->results[0]->language);
        self::assertSame('growing', $response->results[0]->direction);
        self::assertEmpty($response->unresolved);
        self::assertNotNull($response->chartPath);
        self::assertNotNull($response->reportPath);
    }

    public function test_continues_when_one_language_cannot_be_resolved(): void
    {
        $useCase = new CompareTopicAcrossLanguages(
            new FakeArticleResolver(['pl' => 'Intermittent fasting']),
            new FakeWikipediaPageviews(['pl' => [500, 500, 500, 1500, 1500, 1500]]),
            new TrendAssessor(new TrendCalculator(), new ConfidenceEvaluator()),
            new FakeChartRenderer(),
            new FakeReportRenderer(),
        );

        $response = $useCase->execute(new CompareTopicAcrossLanguagesRequest(
            topic: 'Intermittent fasting',
            languages: ['pl', 'xx'],
            periodMonths: 6,
        ));

        self::assertCount(1, $response->results);
        self::assertCount(1, $response->unresolved);
        self::assertSame('xx', $response->unresolved[0]->language);
    }
}
