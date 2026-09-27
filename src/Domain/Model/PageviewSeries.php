<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Model;

/**
 * @implements \IteratorAggregate<int, PageviewPoint>
 */
final readonly class PageviewSeries implements \IteratorAggregate, \Countable
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

    public function averageViews(): float
    {
        return $this->isEmpty() ? 0.0 : $this->totalViews() / $this->count();
    }

    /** @return int[] */
    public function viewCounts(): array
    {
        return array_map(static fn (PageviewPoint $p) => $p->views(), $this->points);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->points);
    }
}
