<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Model;

final readonly class PageviewSeries
{
    /** @var PageviewPoint[] */
    private array $points;

    /** @param PageviewPoint[] $points ordered chronologically */
    public function __construct(
        private ArticleReference $article,
        private DateRange $range,
        array $points,
    ) {
        $this->points = array_values($points);
    }

    public function article(): ArticleReference
    {
        return $this->article;
    }

    public function range(): DateRange
    {
        return $this->range;
    }

    /** @return PageviewPoint[] */
    public function points(): array
    {
        return $this->points;
    }

    public function isEmpty(): bool
    {
        return $this->points === [];
    }

    public function count(): int
    {
        return count($this->points);
    }

    public function totalViews(): int
    {
        return array_sum(array_map(static fn (PageviewPoint $p) => $p->views(), $this->points));
    }

    /** @return int[] */
    public function viewCounts(): array
    {
        return array_map(static fn (PageviewPoint $p) => $p->views(), $this->points);
    }
}
