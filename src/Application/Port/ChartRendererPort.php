<?php

declare(strict_types=1);

namespace TopicSignals\Application\Port;

use TopicSignals\Domain\Model\PageviewSeries;

interface ChartRendererPort
{
    /**
     * @param array<string, PageviewSeries> $seriesByLabel keyed by legend label, e.g. language code
     *
     * @return string absolute path to the generated PNG file
     */
    public function renderLineChart(string $title, array $seriesByLabel, string $outputFileName): string;
}
