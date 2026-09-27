<?php

declare(strict_types=1);

use Predis\Client as RedisClient;
use Psr\Log\LoggerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

return [
    RedisClient::class => function () {
        return new RedisClient([
            'scheme' => 'tcp',
            'host' => $_ENV['REDIS_HOST'] ?? 'redis',
            'port' => (int) ($_ENV['REDIS_PORT'] ?? 6379),
        ]);
    },

    LoggerInterface::class => function () {
        $logger = new Logger('topic-signals');
        $logger->pushHandler(new StreamHandler('php://stderr', Logger::INFO));

        return $logger;
    },
];
