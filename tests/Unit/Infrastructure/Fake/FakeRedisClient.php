<?php

declare(strict_types=1);

namespace TopicSignals\Tests\Unit\Infrastructure\Fake;

use Predis\ClientInterface;
use Predis\Command\CommandInterface;
use Predis\Configuration\OptionsInterface;
use Predis\Connection\ConnectionInterface;

/** In-memory stand-in for Predis\Client, supporting only the "get"/"setex" commands our cache decorators use. */
final class FakeRedisClient implements ClientInterface
{
    /** @var array<string, mixed> */
    private array $store = [];

    public int $getCallCount = 0;

    public function __call($method, $arguments)
    {
        return match (strtolower((string) $method)) {
            'get' => $this->handleGet($arguments[0]),
            'setex' => $this->handleSetex($arguments[0], $arguments[2]),
            default => throw new \RuntimeException("Unsupported command in FakeRedisClient: {$method}"),
        };
    }

    private function handleGet(string $key): mixed
    {
        $this->getCallCount++;

        return $this->store[$key] ?? null;
    }

    private function handleSetex(string $key, mixed $value): bool
    {
        $this->store[$key] = $value;

        return true;
    }

    public function getCommandFactory()
    {
        throw new \RuntimeException('Not implemented in FakeRedisClient');
    }

    public function getOptions(): OptionsInterface
    {
        throw new \RuntimeException('Not implemented in FakeRedisClient');
    }

    public function connect()
    {
    }

    public function disconnect()
    {
    }

    public function getConnection(): ConnectionInterface
    {
        throw new \RuntimeException('Not implemented in FakeRedisClient');
    }

    public function createCommand($method, $arguments = []): CommandInterface
    {
        throw new \RuntimeException('Not implemented in FakeRedisClient');
    }

    public function executeCommand(CommandInterface $command)
    {
        throw new \RuntimeException('Not implemented in FakeRedisClient');
    }
}
