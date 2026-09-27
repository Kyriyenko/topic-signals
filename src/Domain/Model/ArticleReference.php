<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Model;

/** A Wikipedia article resolved for a given language edition. */
final readonly class ArticleReference
{
    public function __construct(
        private Language $language,
        private string $title,
        private ?float $matchConfidence = null,
    ) {
    }

    public function language(): Language
    {
        return $this->language;
    }

    public function title(): string
    {
        return $this->title;
    }

    /** Confidence of the topic-to-article resolution, 0..1, null when resolved unambiguously. */
    public function matchConfidence(): ?float
    {
        return $this->matchConfidence;
    }
}
