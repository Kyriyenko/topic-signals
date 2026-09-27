<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Model;

enum TrendMethod: string
{
    /** Sums the most recent 12 months vs. the 12 months before that — cancels seasonality. */
    case YEAR_OVER_YEAR = 'year_over_year';

    /** Fewer than 24 months available: compares the first vs. second half of the period instead. */
    case HALF_PERIOD = 'half_period';
}
