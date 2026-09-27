<?php

declare(strict_types=1);

namespace TopicSignals\Infrastructure\Chart;

use Psr\Log\LoggerInterface;
use TopicSignals\Application\Exception\ChartRenderingFailedException;
use TopicSignals\Application\Port\ChartRendererPort;
use TopicSignals\Domain\Model\PageviewSeries;

/** Renders a line chart by shelling out to the bundled matplotlib script. */
final class PythonChartRenderer implements ChartRendererPort
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $pythonBin,
        private readonly string $scriptPath,
        private readonly string $outputDir,
    ) {
    }

    public function renderLineChart(string $title, array $seriesByLabel, string $outputFileName): string
    {
        if (!is_dir($this->outputDir) && !mkdir($this->outputDir, 0775, true) && !is_dir($this->outputDir)) {
            throw ChartRenderingFailedException::forReason("Cannot create output directory {$this->outputDir}");
        }

        $outputPath = rtrim($this->outputDir, '/') . '/' . $outputFileName;

        $spec = [
            'title' => $title,
            'output_path' => $outputPath,
            'series' => array_map(
                static fn (string $label, PageviewSeries $series) => [
                    'label' => strtoupper($label),
                    'dates' => array_map(static fn ($p) => $p->date()->format('Y-m-d'), $series->points()),
                    'values' => $series->viewCounts(),
                ],
                array_keys($seriesByLabel),
                array_values($seriesByLabel),
            ),
        ];

        $process = proc_open(
            [$this->pythonBin, $this->scriptPath],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (!is_resource($process)) {
            throw ChartRenderingFailedException::forReason('Could not start the chart rendering process.');
        }

        fwrite($pipes[0], json_encode($spec, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0 || !file_exists($outputPath)) {
            $this->logger->error('Chart rendering failed', ['stderr' => $stderr, 'exit_code' => $exitCode]);

            throw ChartRenderingFailedException::forReason(trim((string) $stderr) ?: 'Unknown rendering error.');
        }

        return $outputPath;
    }
}
