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

    ArticleResolverPort::class => \DI\autowire(WikipediaArticleResolver::class),
    WikipediaPageviewsPort::class => \DI\autowire(WikipediaPageviews::class),

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
