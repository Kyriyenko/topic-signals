<?php

declare(strict_types=1);

namespace TopicSignals\Tests\Feature\Fake;

use TopicSignals\Application\Exception\ArticleNotFoundException;
use TopicSignals\Application\Port\ArticleResolverPort;
use TopicSignals\Domain\Model\ArticleReference;
use TopicSignals\Domain\Model\Language;
use TopicSignals\Domain\Model\Topic;

final class FakeArticleResolver implements ArticleResolverPort
{
    /** @var array<string, string> language code => article title */
    private array $titlesByLanguage;

    /** @param array<string, string> $titlesByLanguage */
    public function __construct(array $titlesByLanguage = [])
    {
        $this->titlesByLanguage = $titlesByLanguage;
    }

    public function resolve(Topic $topic, Language $language): ArticleReference
    {
        $title = $this->titlesByLanguage[$language->code()] ?? null;

        if ($title === null) {
            throw ArticleNotFoundException::forTopic($topic, $language);
        }

        return new ArticleReference($language, $title);
    }
}
