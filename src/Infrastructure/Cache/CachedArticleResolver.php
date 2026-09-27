<?php

declare(strict_types=1);

namespace TopicSignals\Infrastructure\Cache;

use Predis\ClientInterface as RedisClientInterface;
use TopicSignals\Application\Port\ArticleResolverPort;
use TopicSignals\Domain\Model\ArticleReference;
use TopicSignals\Domain\Model\Language;
use TopicSignals\Domain\Model\Topic;

/** Caches successful topic-to-article resolutions; failures are never cached and always retried. */
final class CachedArticleResolver implements ArticleResolverPort
{
    public function __construct(
        private readonly ArticleResolverPort $inner,
        private readonly RedisClientInterface $redis,
        private readonly int $ttlSeconds = 604_800,
    ) {
    }

    public function resolve(Topic $topic, Language $language): ArticleReference
    {
        $key = $this->cacheKey($topic, $language);
        $cached = $this->redis->get($key);

        if ($cached !== null) {
            $title = json_decode($cached, true)['title'];

            return new ArticleReference($language, $title);
        }

        $article = $this->inner->resolve($topic, $language);

        $this->redis->setex($key, $this->ttlSeconds, json_encode(['title' => $article->title()]));

        return $article;
    }

    private function cacheKey(Topic $topic, Language $language): string
    {
        return sprintf('topic-signals:article:%s:%s', $language->code(), md5(strtolower($topic->label())));
    }
}
