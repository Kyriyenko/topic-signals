<?php

declare(strict_types=1);

namespace TopicSignals\Infrastructure\Wikimedia;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use TopicSignals\Application\Exception\AmbiguousArticleMatchException;
use TopicSignals\Application\Exception\ArticleNotFoundException;
use TopicSignals\Application\Exception\DataSourceUnavailableException;
use TopicSignals\Application\Port\ArticleResolverPort;
use TopicSignals\Domain\Model\ArticleReference;
use TopicSignals\Domain\Model\Language;
use TopicSignals\Domain\Model\Topic;

/**
 * Resolves a topic via English search, then crosses to the target language
 * via Wikidata sitelinks — searching non-English editions directly matches
 * articles that merely mention the English words, not the right topic.
 */
final class WikipediaArticleResolver implements ArticleResolverPort
{
    private const string PIVOT_LANGUAGE = 'en';
    private const string WIKIDATA_API = 'https://www.wikidata.org/w/api.php';

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function resolve(Topic $topic, Language $language): ArticleReference
    {
        $pivot = new Language(self::PIVOT_LANGUAGE);
        $searchResults = $this->search($topic, $pivot);

        if ($searchResults === []) {
            throw ArticleNotFoundException::forTopic($topic, $language);
        }

        $bestMatch = $searchResults[0];
        $props = $this->fetchPageProps($bestMatch['title'], $pivot);

        if ($props['isDisambiguation']) {
            $candidates = array_map(static fn (array $r) => $r['title'], array_slice($searchResults, 1, 5));

            if ($candidates === []) {
                throw ArticleNotFoundException::forTopic($topic, $language);
            }

            throw AmbiguousArticleMatchException::forTopic($topic, $language, $candidates);
        }

        if ($language->equals($pivot)) {
            return new ArticleReference($language, $bestMatch['title']);
        }

        if ($props['wikidataId'] === null) {
            throw ArticleNotFoundException::forTopic($topic, $language);
        }

        $localTitle = $this->fetchSitelinkTitle($props['wikidataId'], $language);

        if ($localTitle === null) {
            throw ArticleNotFoundException::forTopic($topic, $language);
        }

        return new ArticleReference($language, $localTitle);
    }

    /** @return array<int, array{title: string}> */
    private function search(Topic $topic, Language $language): array
    {
        $data = $this->request("https://{$language->code()}.wikipedia.org/w/api.php", [
            'action' => 'query',
            'list' => 'search',
            'srsearch' => $topic->label(),
            'srlimit' => 6,
            'format' => 'json',
        ]);

        return $data['query']['search'] ?? [];
    }

    /** @return array{isDisambiguation: bool, wikidataId: ?string} */
    private function fetchPageProps(string $title, Language $language): array
    {
        $data = $this->request("https://{$language->code()}.wikipedia.org/w/api.php", [
            'action' => 'query',
            'titles' => $title,
            'prop' => 'pageprops',
            'ppprop' => 'wikibase_item|disambiguation',
            'format' => 'json',
        ]);

        $pages = $data['query']['pages'] ?? [];
        $page = reset($pages) ?: [];
        $props = $page['pageprops'] ?? [];

        return [
            'isDisambiguation' => array_key_exists('disambiguation', $props),
            'wikidataId' => $props['wikibase_item'] ?? null,
        ];
    }

    private function fetchSitelinkTitle(string $wikidataId, Language $language): ?string
    {
        $siteKey = $language->code() . 'wiki';

        $data = $this->request(self::WIKIDATA_API, [
            'action' => 'wbgetentities',
            'ids' => $wikidataId,
            'props' => 'sitelinks',
            'sitefilter' => $siteKey,
            'format' => 'json',
        ]);

        return $data['entities'][$wikidataId]['sitelinks'][$siteKey]['title'] ?? null;
    }

    /** @param array<string, scalar> $query */
    private function request(string $url, array $query): array
    {
        try {
            $response = $this->httpClient->request('GET', $url, ['query' => $query]);
        } catch (GuzzleException $e) {
            $this->logger->warning('Wikimedia API request failed', ['url' => $url, 'error' => $e->getMessage()]);

            throw DataSourceUnavailableException::forWikimediaApi($e->getMessage());
        }

        return json_decode((string) $response->getBody(), true) ?? [];
    }
}
