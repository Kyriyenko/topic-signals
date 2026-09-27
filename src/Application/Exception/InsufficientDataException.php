<?php

declare(strict_types=1);

namespace TopicSignals\Application\Exception;

use TopicSignals\Domain\Model\ArticleReference;

final class InsufficientDataException extends ApplicationException
{
    public static function forArticle(ArticleReference $article): self
    {
        return new self(sprintf(
            'No pageview data is available for "%s" in the "%s" language edition over the requested period.',
            $article->title(),
            $article->language()->code(),
        ));
    }
}
