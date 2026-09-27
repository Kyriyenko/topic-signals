<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Model;

use TopicSignals\Domain\Exception\InvalidLanguageCodeException;

/** Wikipedia language edition code, e.g. "en", "uk", "pl". */
final readonly class Language
{
    private string $code;

    public function __construct(string $code)
    {
        $normalized = strtolower(trim($code));

        if (!preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $normalized)) {
            throw InvalidLanguageCodeException::forCode($code);
        }

        $this->code = $normalized;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

}
