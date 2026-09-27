<?php

declare(strict_types=1);

namespace TopicSignals\Infrastructure\Cache;

use DateTimeImmutable;
use Predis\ClientInterface as RedisClientInterface;
use TopicSignals\Application\Port\WikipediaPageviewsPort;
use TopicSignals\Domain\Model\ArticleReference;
use TopicSignals\Domain\Model\DateRange;
use TopicSignals\Domain\Model\PageviewPoint;
use TopicSignals\Domain\Model\PageviewSeries;

/** Caches monthly pageview series per article/date-range so repeat and follow-up queries skip the Wikimedia API. */
final class CachedWikipediaPageviews implements WikipediaPageviewsPort
{
    public function __construct(
        private readonly WikipediaPageviewsPort $inner,
        private readonly RedisClientInterface $redis,
        private readonly int $ttlSeconds = 86_400,
    ) {
    }

    public function fetchMonthlyViews(ArticleReference $article, DateRange $range): PageviewSeries
    {
        $key = $this->cacheKey($article, $range);
        $cached = $this->redis->get($key);

        if ($cached !== null) {
            return $this->deserialize($cached, $article, $range);
        }

        $series = $this->inner->fetchMonthlyViews($article, $range);

        $this->redis->setex($key, $this->ttlSeconds, $this->serialize($series));

        return $series;
    }

    private function cacheKey(ArticleReference $article, DateRange $range): string
    {
        return sprintf(
            'topic-signals:pageviews:%s:%s:%s:%s',
            $article->language()->code(),
            md5($article->title()),
            $range->start()->format('Ymd'),
            $range->end()->format('Ymd'),
        );
    }

    private function serialize(PageviewSeries $series): string
    {
        $points = array_map(
            static fn (PageviewPoint $p) => ['date' => $p->date()->format('Y-m-d'), 'views' => $p->views()],
            $series->points(),
        );

        return json_encode($points, JSON_THROW_ON_ERROR);
    }

    private function deserialize(string $json, ArticleReference $article, DateRange $range): PageviewSeries
    {
        $points = array_map(
            static fn (array $p) => new PageviewPoint(new DateTimeImmutable($p['date']), $p['views']),
            json_decode($json, true),
        );

        return new PageviewSeries($article, $range, $points);
    }
}
