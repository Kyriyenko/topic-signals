<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Model;

use TopicSignals\Domain\Exception\InvalidTopicException;

/** Free-text topic as described by the user, e.g. "intermittent fasting". */
final readonly class Topic
{
    private string $label;

    public function __construct(string $label)
    {
        $trimmed = trim($label);

        if ($trimmed === '') {
            throw InvalidTopicException::forEmptyLabel();
        }

        if (mb_strlen($trimmed) > 200) {
            throw InvalidTopicException::forTooLongLabel();
        }

        $this->label = $trimmed;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function __toString(): string
    {
        return $this->label;
    }
}
