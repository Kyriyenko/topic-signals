<?php

declare(strict_types=1);

namespace TopicSignals\Application\Exception;

use TopicSignals\Domain\Model\Language;
use TopicSignals\Domain\Model\Topic;

final class AmbiguousArticleMatchException extends ApplicationException
{
    /** @param string[] $candidateTitles */
    public function __construct(
        string $message,
        private readonly array $candidateTitles,
    ) {
        parent::__construct($message);
    }

    /** @param string[] $candidateTitles */
    public static function forTopic(Topic $topic, Language $language, array $candidateTitles): self
    {
        return new self(
            sprintf(
                'Topic "%s" matches multiple articles in the "%s" language edition; please pick one.',
                $topic->label(),
                $language->code(),
            ),
            $candidateTitles,
        );
    }

    /** @return string[] */
    public function candidateTitles(): array
    {
        return $this->candidateTitles;
    }
}
