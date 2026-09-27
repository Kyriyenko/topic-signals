<?php

declare(strict_types=1);

namespace TopicSignals\Interface\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use TopicSignals\Application\Exception\AmbiguousArticleMatchException;

/** Shared JSON output shape for CLI commands, so the calling agent parses one consistent format. */
final class JsonResult
{
    public static function success(OutputInterface $output, array $data): int
    {
        $output->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return Command::SUCCESS;
    }

    public static function error(OutputInterface $output, \Throwable $e): int
    {
        $error = [
            'type' => (new \ReflectionClass($e))->getShortName(),
            'message' => $e->getMessage(),
        ];

        if ($e instanceof AmbiguousArticleMatchException) {
            $error['candidates'] = $e->candidateTitles();
        }

        $output->writeln(json_encode(['error' => $error], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return Command::FAILURE;
    }
}
