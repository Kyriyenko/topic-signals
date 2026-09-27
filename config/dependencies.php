<?php

declare(strict_types=1);

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface as HttpClientInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Predis\Client as RedisClient;
use Psr\Log\LoggerInterface;
use TopicSignals\Application\Port\ArticleResolverPort;
use TopicSignals\Application\Port\ChartRendererPort;
use TopicSignals\Application\Port\ReportRendererPort;
use TopicSignals\Application\Port\WikipediaPageviewsPort;
use TopicSignals\Infrastructure\Cache\CachedArticleResolver;
use TopicSignals\Infrastructure\Cache\CachedWikipediaPageviews;
use TopicSignals\Infrastructure\Chart\PythonChartRenderer;
use TopicSignals\Infrastructure\Report\DompdfReportRenderer;
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

    ArticleResolverPort::class => function ($container) {
        return new CachedArticleResolver(
            $container->make(WikipediaArticleResolver::class),
            $container->get(RedisClient::class),
            (int) ($_ENV['ARTICLE_CACHE_TTL_SECONDS'] ?? 604_800),
        );
    },

    WikipediaPageviewsPort::class => function ($container) {
        return new CachedWikipediaPageviews(
            $container->make(WikipediaPageviews::class),
            $container->get(RedisClient::class),
            (int) ($_ENV['PAGEVIEWS_CACHE_TTL_SECONDS'] ?? 86_400),
        );
    },

    ChartRendererPort::class => function ($container) {
        return new PythonChartRenderer(
            $container->get(LoggerInterface::class),
            $_ENV['CHART_PYTHON_BIN'] ?? '/opt/chart-venv/bin/python',
            $_ENV['CHART_SCRIPT_PATH'] ?? dirname(__DIR__) . '/docker/chart/render.py',
            $_ENV['CHART_OUTPUT_DIR'] ?? dirname(__DIR__) . '/var/charts',
        );
    },

    ReportRendererPort::class => function () {
        return new DompdfReportRenderer(
            $_ENV['REPORT_OUTPUT_DIR'] ?? dirname(__DIR__) . '/var/reports',
        );
    },
];
