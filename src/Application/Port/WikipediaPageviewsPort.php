<?php

declare(strict_types=1);

namespace TopicSignals\Application\Port;

use TopicSignals\Application\Exception\DataSourceUnavailableException;
use TopicSignals\Domain\Model\ArticleReference;
use TopicSignals\Domain\Model\DateRange;
use TopicSignals\Domain\Model\PageviewSeries;

interface WikipediaPageviewsPort
{
    /** @throws DataSourceUnavailableException */
    public function fetchMonthlyViews(ArticleReference $article, DateRange $range): PageviewSeries;
}
