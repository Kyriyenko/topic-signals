<?php

declare(strict_types=1);

namespace TopicSignals\Tests\Feature;

use PHPUnit\Framework\TestCase;
use TopicSignals\Application\Dto\AssessTopicGrowthRequest;
use TopicSignals\Application\UseCase\AssessTopicGrowth;
use TopicSignals\Domain\Service\ConfidenceEvaluator;
use TopicSignals\Domain\Service\TrendAssessor;
use TopicSignals\Domain\Service\TrendCalculator;
use TopicSignals\Tests\Feature\Fake\FakeArticleResolver;
use TopicSignals\Tests\Feature\Fake\FakeChartRenderer;
use TopicSignals\Tests\Feature\Fake\FakeReportRenderer;
use TopicSignals\Tests\Feature\Fake\FakeWikipediaPageviews;

final class AssessTopicGrowthTest extends TestCase
{
    public function test_assesses_growing_topic_with_chart(): void
    {
        $useCase = new AssessTopicGrowth(
            new FakeArticleResolver(['uk' => 'Astronomy']),
            new FakeWikipediaPageviews(['uk' => [1000, 1000, 1000, 1000, 1000, 1000, 2500, 2600, 2700, 2800, 2900, 3000]]),
            new TrendAssessor(new TrendCalculator(), new ConfidenceEvaluator()),
            new FakeChartRenderer(),
            new FakeReportRenderer(),
        );

        $response = $useCase->execute(new AssessTopicGrowthRequest(
            topic: 'Astronomy',
            language: 'uk',
            periodMonths: 12,
            generateChart: true,
            generateReport: false,
        ));

        self::assertSame('growing', $response->trend->direction);
        self::assertGreaterThan(0, $response->trend->percentChange);
        self::assertNotNull($response->chartPath);
        self::assertNull($response->reportPath);
        self::assertNotEmpty($response->assumptions);
    }

    public function test_throws_when_article_cannot_be_resolved(): void
    {
        $useCase = new AssessTopicGrowth(
            new FakeArticleResolver([]),
            new FakeWikipediaPageviews([]),
            new TrendAssessor(new TrendCalculator(), new ConfidenceEvaluator()),
            new FakeChartRenderer(),
            new FakeReportRenderer(),
        );

        $this->expectException(\TopicSignals\Application\Exception\ArticleNotFoundException::class);

        $useCase->execute(new AssessTopicGrowthRequest(topic: 'Nonexistent topic xyz', language: 'uk'));
    }
}
