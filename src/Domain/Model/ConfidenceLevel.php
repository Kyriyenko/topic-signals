<?php

declare(strict_types=1);

namespace TopicSignals\Domain\Model;

enum ConfidenceLevel: string
{
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';
}
