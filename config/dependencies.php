<?php

declare(strict_types=1);

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface as HttpClientInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Predis\Client as RedisClient;
use Psr\Log\LoggerInterface;
use TopicSignals\Application\Port\ArticleResolverPort;
use TopicSignals\Application\Port\WikipediaPageviewsPort;
use TopicSignals\Infrastructure\Wikimedia\WikipediaArticleResolver;
use TopicSignals\Infrastructure\Wikimedia\WikipediaPageviews;

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

    HttpClientInterface::class => function () {
        return new HttpClient([
            'timeout' => 10.0,
            'headers' => [
                'User-Agent' => $_ENV['WIKIMEDIA_USER_AGENT']
                    ?? 'topic-signals-skill/1.0 (https://github.com/Kyriyenko/topic-signals)',
            ],
        ]);
    },

    ArticleResolverPort::class => \DI\autowire(WikipediaArticleResolver::class),
    WikipediaPageviewsPort::class => \DI\autowire(WikipediaPageviews::class),
];
