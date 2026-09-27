<?php

declare(strict_types=1);

namespace TopicSignals\Tests\Feature\Fake;

use TopicSignals\Application\Port\ChartRendererPort;

final class FakeChartRenderer implements ChartRendererPort
{
    public function renderLineChart(string $title, array $seriesByLabel, string $outputFileName): string
    {
        return '/tmp/' . $outputFileName;
    }
}
