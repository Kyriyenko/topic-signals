<?php

declare(strict_types=1);

namespace TopicSignals\Infrastructure\Wikimedia;

use DateTimeImmutable;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use TopicSignals\Application\Exception\DataSourceUnavailableException;
use TopicSignals\Application\Port\WikipediaPageviewsPort;
use TopicSignals\Domain\Model\ArticleReference;
use TopicSignals\Domain\Model\DateRange;
use TopicSignals\Domain\Model\PageviewPoint;
use TopicSignals\Domain\Model\PageviewSeries;

/** Fetches monthly pageview counts from the Wikimedia REST Pageviews API. */
final class WikipediaPageviews implements WikipediaPageviewsPort
{
    private const string BASE_URL = 'https://wikimedia.org/api/rest_v1/metrics/pageviews/per-article';

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function fetchMonthlyViews(ArticleReference $article, DateRange $range): PageviewSeries
    {
        $project = $article->language()->code() . '.wikipedia';
        $encodedTitle = rawurlencode(str_replace(' ', '_', $article->title()));

        $url = sprintf(
            '%s/%s/all-access/user/%s/monthly/%s/%s',
            self::BASE_URL,
            $project,
            $encodedTitle,
            $range->start()->format('Ymd'),
            $range->end()->format('Ymd'),
        );

        try {
            $response = $this->httpClient->request('GET', $url);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                // No pageview data at all for this article/period — a valid, if uninteresting, outcome.
                return new PageviewSeries($article, $range, []);
            }

            $this->logger->warning('Wikimedia pageviews request failed', ['error' => $e->getMessage()]);

            throw DataSourceUnavailableException::forWikimediaApi($e->getMessage());
        } catch (GuzzleException $e) {
            $this->logger->warning('Wikimedia pageviews request failed', ['error' => $e->getMessage()]);

            throw DataSourceUnavailableException::forWikimediaApi($e->getMessage());
        }

        $data = json_decode((string) $response->getBody(), true);
        $items = $data['items'] ?? [];

        $points = array_map(
            static fn (array $item) => new PageviewPoint(
                DateTimeImmutable::createFromFormat('YmdH', $item['timestamp']),
                (int) $item['views'],
            ),
            $items,
        );

        usort($points, static fn (PageviewPoint $a, PageviewPoint $b) => $a->date() <=> $b->date());

        return new PageviewSeries($article, $range, $points);
    }
}
