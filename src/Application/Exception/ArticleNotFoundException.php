<?php

declare(strict_types=1);

namespace TopicSignals\Application\Exception;

use TopicSignals\Domain\Model\Language;
use TopicSignals\Domain\Model\Topic;

final class ArticleNotFoundException extends ApplicationException
{
    public static function forTopic(Topic $topic, Language $language): self
    {
        return new self(sprintf(
            'No Wikipedia article was found for topic "%s" in the "%s" language edition.',
            $topic->label(),
            $language->code(),
        ));
    }
}
