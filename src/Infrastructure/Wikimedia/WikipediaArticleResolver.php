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
    private const array STOPWORDS = [
        'a', 'an', 'the', 'of', 'in', 'on', 'at', 'to', 'for', 'and', 'or', 'is', 'are',
        'was', 'were', 'be', 'been', 'being', 'with', 'by', 'from', 'as', 'that', 'this',
        'these', 'those', 'it', 'its', 'into', 'about', 'over', 'under',
    ];

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function resolve(Topic $topic, Language $language): ArticleReference
    {
        $pivot = new Language(self::PIVOT_LANGUAGE);

        // Two independent search modes: full-text (ranks by article body content)
        // and title/prefix search (the same engine behind Wikipedia's own search
        // box autocomplete). A topic can rank outside the top results of one mode
        // while being the very first hit of the other.
        $searchResults = $this->mergeUniqueByTitle(
            $this->search($topic, $pivot),
            $this->openSearch($topic, $pivot),
        );

        if ($searchResults === []) {
            throw ArticleNotFoundException::forTopic($topic, $language);
        }

        $matchIndex = $this->firstRelevantMatchIndex($topic, $searchResults);

        if ($matchIndex === null) {
            // The search returned results, but none of them are actually about this topic
            // (e.g. they only share an incidental word) — a confidently wrong match is worse
            // than an honest "not found".
            throw ArticleNotFoundException::forTopic($topic, $language);
        }

        $bestMatch = $searchResults[$matchIndex];
        $props = $this->fetchPageProps($bestMatch['title'], $pivot);

        if ($props['isDisambiguation']) {
            $candidates = array_values(array_map(
                static fn (array $r) => $r['title'],
                array_filter($searchResults, static fn ($r, $i) => $i !== $matchIndex, ARRAY_FILTER_USE_BOTH),
            ));
            $candidates = array_slice($candidates, 0, 5);

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

    /**
     * Finds the first search result whose title contains a stem of every
     * significant word of the topic. MediaWiki full-text search ranks by
     * text-body relevance, so its top hit can be an article that merely
     * mentions one of the topic's words rather than being about the topic
     * (e.g. "Artificial Inu" matching "Shiba Inu (cryptocurrency)" on "Inu"
     * alone) — requiring every word to be present, not just any one, rules
     * those out. Words are compared as 5-character stems, not exact strings,
     * so "learning" still matches "learner" in "English-language learner".
     *
     * @param array<int, array{title: string}> $searchResults
     */
    private function firstRelevantMatchIndex(Topic $topic, array $searchResults): ?int
    {
        $significantStems = $this->significantStems($topic->label());

        // A topic with no word left after removing stopwords can't be filtered
        // this way — trust the search engine's own ranking instead.
        if ($significantStems === []) {
            return 0;
        }

        foreach ($searchResults as $index => $result) {
            $titleStems = $this->significantStems($result['title']);

            if (array_intersect($significantStems, $titleStems) === $significantStems) {
                return $index;
            }
        }

        return null;
    }

    /** @return string[] */
    private function significantStems(string $text): array
    {
        preg_match_all('/[a-z0-9]+/', strtolower($text), $matches);

        $words = array_filter(
            $matches[0],
            static fn (string $word) => mb_strlen($word) >= 2 && !in_array($word, self::STOPWORDS, true),
        );

        return array_values(array_unique(array_map(
            static fn (string $word) => mb_substr($word, 0, 5),
            $words,
        )));
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

    /**
     * Title/prefix search — the same engine as Wikipedia's search-box
     * autocomplete. Complements full-text search, which ranks by article
     * body content and can bury (or miss) a topic that IS a title match.
     *
     * @return array<int, array{title: string}>
     */
    private function openSearch(Topic $topic, Language $language): array
    {
        $data = $this->request("https://{$language->code()}.wikipedia.org/w/api.php", [
            'action' => 'opensearch',
            'search' => $topic->label(),
            'limit' => 6,
            'namespace' => 0,
            'format' => 'json',
        ]);

        $titles = $data[1] ?? [];

        return array_map(static fn (string $title) => ['title' => $title], $titles);
    }

    /**
     * @param array<int, array{title: string}> ...$resultSets
     *
     * @return array<int, array{title: string}>
     */
    private function mergeUniqueByTitle(array ...$resultSets): array
    {
        $seenTitles = [];
        $merged = [];

        foreach ($resultSets as $results) {
            foreach ($results as $result) {
                if (!isset($seenTitles[$result['title']])) {
                    $seenTitles[$result['title']] = true;
                    $merged[] = $result;
                }
            }
        }

        return $merged;
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
