<?php

declare(strict_types=1);

namespace TopicSignals\Application\Port;

use TopicSignals\Application\Exception\AmbiguousArticleMatchException;
use TopicSignals\Application\Exception\ArticleNotFoundException;
use TopicSignals\Domain\Model\ArticleReference;
use TopicSignals\Domain\Model\Language;
use TopicSignals\Domain\Model\Topic;

interface ArticleResolverPort
{
    /**
     * @throws ArticleNotFoundException
     * @throws AmbiguousArticleMatchException
     */
    public function resolve(Topic $topic, Language $language): ArticleReference;
}
