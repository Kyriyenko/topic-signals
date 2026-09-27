<?php

declare(strict_types=1);

namespace TopicSignals\Tests\Feature\Fake;

use DateTimeImmutable;
use TopicSignals\Application\Port\WikipediaPageviewsPort;
use TopicSignals\Domain\Model\ArticleReference;
use TopicSignals\Domain\Model\DateRange;
use TopicSignals\Domain\Model\PageviewPoint;
use TopicSignals\Domain\Model\PageviewSeries;

final class FakeWikipediaPageviews implements WikipediaPageviewsPort
{
    /** @var array<string, int[]> language code => monthly view counts */
    private array $countsByLanguage;

    /** @param array<string, int[]> $countsByLanguage */
    public function __construct(array $countsByLanguage)
    {
        $this->countsByLanguage = $countsByLanguage;
    }

    public function fetchMonthlyViews(ArticleReference $article, DateRange $range): PageviewSeries
    {
        $counts = $this->countsByLanguage[$article->language()->code()] ?? [];
        $start = new DateTimeImmutable('2023-01-01');

        $points = [];
        foreach ($counts as $index => $views) {
            $points[] = new PageviewPoint($start->modify("+{$index} months"), $views);
        }

        return new PageviewSeries($article, $range, $points);
    }
}
