<?php

declare(strict_types=1);

namespace TopicSignals\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use TopicSignals\Application\Exception\ArticleNotFoundException;
use TopicSignals\Application\Port\ArticleResolverPort;
use TopicSignals\Domain\Model\ArticleReference;
use TopicSignals\Domain\Model\Language;
use TopicSignals\Domain\Model\Topic;
use TopicSignals\Infrastructure\Cache\CachedArticleResolver;
use TopicSignals\Tests\Unit\Infrastructure\Fake\FakeRedisClient;

final class CachedArticleResolverTest extends TestCase
{
    public function test_caches_successful_resolution_on_second_call(): void
    {
        $inner = new class implements ArticleResolverPort {
            public int $callCount = 0;

            public function resolve(Topic $topic, Language $language): ArticleReference
            {
                $this->callCount++;

                return new ArticleReference($language, 'Astronomy');
            }
        };

        $redis = new FakeRedisClient();
        $resolver = new CachedArticleResolver($inner, $redis);

        $topic = new Topic('Astronomy');
        $language = new Language('en');

        $first = $resolver->resolve($topic, $language);
        $second = $resolver->resolve($topic, $language);

        self::assertSame('Astronomy', $first->title());
        self::assertSame('Astronomy', $second->title());
        self::assertSame(1, $inner->callCount, 'Inner resolver must only be called once; the second call should hit cache.');
    }

    public function test_does_not_cache_failed_resolution(): void
    {
        $inner = new class implements ArticleResolverPort {
            public int $callCount = 0;

            public function resolve(Topic $topic, Language $language): ArticleReference
            {
                $this->callCount++;

                throw ArticleNotFoundException::forTopic($topic, $language);
            }
        };

        $resolver = new CachedArticleResolver($inner, new FakeRedisClient());
        $topic = new Topic('Nonexistent');
        $language = new Language('en');

        foreach ([1, 2] as $attempt) {
            try {
                $resolver->resolve($topic, $language);
                self::fail('Expected ArticleNotFoundException');
            } catch (ArticleNotFoundException) {
                // expected on every attempt
            }
        }

        self::assertSame(2, $inner->callCount, 'A failed resolution must never be served from cache.');
    }
}
