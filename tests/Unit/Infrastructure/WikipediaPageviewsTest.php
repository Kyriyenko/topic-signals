<?php

declare(strict_types=1);

namespace TopicSignals\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use TopicSignals\Application\Exception\DataSourceUnavailableException;
use TopicSignals\Domain\Model\ArticleReference;
use TopicSignals\Domain\Model\DateRange;
use TopicSignals\Domain\Model\Language;
use TopicSignals\Infrastructure\Wikimedia\WikipediaPageviews;

final class WikipediaPageviewsTest extends TestCase
{
    private ArticleReference $article;
    private DateRange $range;

    protected function setUp(): void
    {
        $this->article = new ArticleReference(new Language('en'), 'Astronomy');
        $this->range = new DateRange(new DateTimeImmutable('2024-01-01'), new DateTimeImmutable('2024-03-01'));
    }

    public function test_parses_monthly_items_into_a_series(): void
    {
        $body = json_encode(['items' => [
            ['timestamp' => '2024010100', 'views' => 1000],
            ['timestamp' => '2024020100', 'views' => 1500],
        ]]);

        $pageviews = $this->pageviewsWithResponses([new Response(200, [], $body)]);
        $series = $pageviews->fetchMonthlyViews($this->article, $this->range);

        self::assertSame(2, $series->count());
        self::assertSame(2500, $series->totalViews());
        self::assertSame('2024-01', $series->points()[0]->date()->format('Y-m'));
    }

    public function test_returns_empty_series_when_wikimedia_has_no_data(): void
    {
        $notFound = new ClientException('Not Found', new Request('GET', 'x'), new Response(404));
        $pageviews = $this->pageviewsWithResponses([$notFound]);

        $series = $pageviews->fetchMonthlyViews($this->article, $this->range);

        self::assertTrue($series->isEmpty());
    }

    public function test_throws_on_server_error(): void
    {
        $serverError = new ServerException('Server Error', new Request('GET', 'x'), new Response(503));
        $pageviews = $this->pageviewsWithResponses([$serverError]);

        $this->expectException(DataSourceUnavailableException::class);

        $pageviews->fetchMonthlyViews($this->article, $this->range);
    }

    /** @param array<Response|\Throwable> $responses */
    private function pageviewsWithResponses(array $responses): WikipediaPageviews
    {
        $mock = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $logger = new Logger('test');
        $logger->pushHandler(new NullHandler());

        return new WikipediaPageviews($client, $logger);
    }
}
