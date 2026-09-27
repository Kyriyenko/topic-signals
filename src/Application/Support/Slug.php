<?php

declare(strict_types=1);

namespace TopicSignals\Application\Support;

/** Turns free-text parts into a filesystem-safe slug for generated chart/report file names. */
final class Slug
{
    public static function make(string ...$parts): string
    {
        $slug = strtolower(implode('-', $parts));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? 'file';

        return trim($slug, '-');
    }
}
