<?php

declare(strict_types=1);

namespace TopicSignals\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TopicSignals\Application\Port\WikipediaPageviewsPort;
use TopicSignals\Domain\Model\ArticleReference;
use TopicSignals\Domain\Model\DateRange;
use TopicSignals\Domain\Model\Language;
use TopicSignals\Domain\Model\PageviewPoint;
use TopicSignals\Domain\Model\PageviewSeries;
use TopicSignals\Infrastructure\Cache\CachedWikipediaPageviews;
use TopicSignals\Tests\Unit\Infrastructure\Fake\FakeRedisClient;

final class CachedWikipediaPageviewsTest extends TestCase
{
    public function test_caches_series_and_reconstructs_it_identically_on_second_call(): void
    {
        $article = new ArticleReference(new Language('uk'), 'Астрономія');
        $range = new DateRange(new DateTimeImmutable('2024-01-01'), new DateTimeImmutable('2024-03-01'));
        $points = [
            new PageviewPoint(new DateTimeImmutable('2024-01-01'), 100),
            new PageviewPoint(new DateTimeImmutable('2024-02-01'), 150),
        ];

        $inner = new class($article, $range, $points) implements WikipediaPageviewsPort {
            public int $callCount = 0;

            public function __construct(
                private readonly ArticleReference $article,
                private readonly DateRange $range,
                private readonly array $points,
            ) {
            }

            public function fetchMonthlyViews(ArticleReference $article, DateRange $range): PageviewSeries
            {
                $this->callCount++;

                return new PageviewSeries($this->article, $this->range, $this->points);
            }
        };

        $pageviews = new CachedWikipediaPageviews($inner, new FakeRedisClient());

        $first = $pageviews->fetchMonthlyViews($article, $range);
        $second = $pageviews->fetchMonthlyViews($article, $range);

        self::assertSame(1, $inner->callCount, 'Second call must be served from cache.');
        self::assertSame($first->totalViews(), $second->totalViews());
        self::assertSame(250, $second->totalViews());
        self::assertCount(2, $second->points());
        self::assertSame('2024-01-01', $second->points()[0]->date()->format('Y-m-d'));
    }
}
