<?php

declare(strict_types=1);

namespace TopicSignals\Tests\Unit\Infrastructure;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use TopicSignals\Application\Exception\AmbiguousArticleMatchException;
use TopicSignals\Application\Exception\ArticleNotFoundException;
use TopicSignals\Domain\Model\Language;
use TopicSignals\Domain\Model\Topic;
use TopicSignals\Infrastructure\Wikimedia\WikipediaArticleResolver;

final class WikipediaArticleResolverTest extends TestCase
{
    public function test_resolves_directly_in_the_pivot_language(): void
    {
        $resolver = $this->resolverWithResponses([
            $this->searchResponse([['title' => 'Astronomy']]),
            $this->pagePropsResponse(isDisambiguation: false, wikidataId: 'Q333'),
        ]);

        $article = $resolver->resolve(new Topic('Astronomy'), new Language('en'));

        self::assertSame('Astronomy', $article->title());
        self::assertSame('en', $article->language()->code());
    }

    public function test_crosses_to_target_language_via_wikidata_sitelink(): void
    {
        $resolver = $this->resolverWithResponses([
            $this->searchResponse([['title' => 'Astronomy']]),
            $this->pagePropsResponse(isDisambiguation: false, wikidataId: 'Q333'),
            $this->sitelinksResponse('Q333', 'ukwiki', 'Астрономія'),
        ]);

        $article = $resolver->resolve(new Topic('Astronomy'), new Language('uk'));

        self::assertSame('Астрономія', $article->title());
    }

    public function test_throws_not_found_when_target_language_has_no_sitelink(): void
    {
        $resolver = $this->resolverWithResponses([
            $this->searchResponse([['title' => 'Intermittent fasting']]),
            $this->pagePropsResponse(isDisambiguation: false, wikidataId: 'Q1666254'),
            $this->sitelinksResponse('Q1666254', 'plwiki', null),
        ]);

        $this->expectException(ArticleNotFoundException::class);

        $resolver->resolve(new Topic('Intermittent fasting'), new Language('pl'));
    }

    public function test_throws_not_found_when_search_has_no_results(): void
    {
        $resolver = $this->resolverWithResponses([
            $this->searchResponse([]),
        ]);

        $this->expectException(ArticleNotFoundException::class);

        $resolver->resolve(new Topic('Totally made up nonsense'), new Language('en'));
    }

    public function test_throws_ambiguous_when_top_match_is_a_disambiguation_page(): void
    {
        $resolver = $this->resolverWithResponses([
            $this->searchResponse([
                ['title' => 'Mercury'],
                ['title' => 'Mercury (planet)'],
                ['title' => 'Mercury (element)'],
            ]),
            $this->pagePropsResponse(isDisambiguation: true, wikidataId: null),
        ]);

        try {
            $resolver->resolve(new Topic('Mercury'), new Language('en'));
            self::fail('Expected AmbiguousArticleMatchException');
        } catch (AmbiguousArticleMatchException $e) {
            self::assertSame(['Mercury (planet)', 'Mercury (element)'], $e->candidateTitles());
        }
    }

    public function test_rejects_a_top_hit_that_only_shares_an_incidental_word(): void
    {
        $resolver = $this->resolverWithResponses([
            $this->searchResponse([
                ['title' => 'Shiba Inu (cryptocurrency)'],
                ['title' => 'Applications of artificial intelligence'],
            ]),
        ]);

        $this->expectException(ArticleNotFoundException::class);

        $resolver->resolve(new Topic('Artificial Inu'), new Language('en'));
    }

    public function test_skips_an_irrelevant_top_hit_and_picks_a_later_relevant_one(): void
    {
        $resolver = $this->resolverWithResponses([
            $this->searchResponse([
                ['title' => 'Something unrelated about ferns'],
                ['title' => 'Astronomy'],
            ]),
            $this->pagePropsResponse(isDisambiguation: false, wikidataId: 'Q333'),
        ]);

        $article = $resolver->resolve(new Topic('Astronomy'), new Language('en'));

        self::assertSame('Astronomy', $article->title());
    }

    public function test_matches_morphological_variants_via_stemming(): void
    {
        $resolver = $this->resolverWithResponses([
            $this->searchResponse([
                ['title' => 'English as a second or foreign language'],
                ['title' => 'English-language learner'],
            ]),
            $this->pagePropsResponse(isDisambiguation: false, wikidataId: 'Q17081060'),
        ]);

        $article = $resolver->resolve(new Topic('English language learning'), new Language('en'));

        self::assertSame('English-language learner', $article->title());
    }

    /** @param Response[] $responses */
    private function resolverWithResponses(array $responses): WikipediaArticleResolver
    {
        $mock = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $logger = new Logger('test');
        $logger->pushHandler(new NullHandler());

        return new WikipediaArticleResolver($client, $logger);
    }

    /** @param array<int, array{title: string}> $results */
    private function searchResponse(array $results): Response
    {
        return new Response(200, [], json_encode(['query' => ['search' => $results]]));
    }

    private function pagePropsResponse(bool $isDisambiguation, ?string $wikidataId): Response
    {
        $props = [];
        if ($isDisambiguation) {
            $props['disambiguation'] = '';
        }
        if ($wikidataId !== null) {
            $props['wikibase_item'] = $wikidataId;
        }

        return new Response(200, [], json_encode([
            'query' => ['pages' => ['123' => ['pageprops' => $props]]],
        ]));
    }

    private function sitelinksResponse(string $qid, string $siteKey, ?string $title): Response
    {
        $sitelinks = $title !== null ? [$siteKey => ['title' => $title]] : [];

        return new Response(200, [], json_encode([
            'entities' => [$qid => ['sitelinks' => $sitelinks]],
        ]));
    }
}
