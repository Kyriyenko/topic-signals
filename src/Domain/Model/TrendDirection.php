<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Model;

enum TrendDirection: string
{
    case GROWING = 'growing';
    case DECLINING = 'declining';
    case STABLE = 'stable';
}
