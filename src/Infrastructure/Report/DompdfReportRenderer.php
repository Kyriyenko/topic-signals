<?php

declare(strict_types=1);

namespace TopicSignals\Infrastructure\Report;

use Dompdf\Dompdf;
use Dompdf\Options;
use TopicSignals\Application\Dto\ReportContent;
use TopicSignals\Application\Port\ReportRendererPort;

/** Renders a one-page PDF summary via dompdf, embedding the chart image inline. */
final class DompdfReportRenderer implements ReportRendererPort
{
    public function __construct(
        private readonly string $outputDir,
    ) {
    }

    public function renderOnePagePdf(ReportContent $content, string $outputFileName): string
    {
        if (!is_dir($this->outputDir) && !mkdir($this->outputDir, 0775, true) && !is_dir($this->outputDir)) {
            throw new \RuntimeException("Cannot create output directory {$this->outputDir}");
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultPaperSize', 'a4');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->buildHtml($content));
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        $outputPath = rtrim($this->outputDir, '/') . '/' . $outputFileName;
        file_put_contents($outputPath, $dompdf->output());

        return $outputPath;
    }

    private function buildHtml(ReportContent $content): string
    {
        $chartHtml = '';
        if ($content->chartImagePath !== null && is_file($content->chartImagePath)) {
            $encoded = base64_encode(file_get_contents($content->chartImagePath));
            $chartHtml = '<img class="chart" src="data:image/png;base64,' . $encoded . '" />';
        }

        $generatedAt = (new \DateTimeImmutable())->format('Y-m-d H:i');

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' . $this->css() . '</style></head><body>'
            . '<h1>' . $this->escape($content->title) . '</h1>'
            . '<p class="meta">Generated ' . $generatedAt . ' UTC from Wikipedia pageview data.</p>'
            . $chartHtml
            . $this->section('Key findings', $content->keyFindings)
            . $this->section('Assumptions & limitations', $content->assumptions)
            . $this->section('Recommended next steps', $content->recommendedNextSteps)
            . '</body></html>';
    }

    /** @param string[] $items */
    private function section(string $heading, array $items): string
    {
        if ($items === []) {
            return '';
        }

        $listItems = implode('', array_map(
            fn (string $item) => '<li>' . $this->escape($item) . '</li>',
            $items,
        ));

        return '<h2>' . $this->escape($heading) . '</h2><ul>' . $listItems . '</ul>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function css(): string
    {
        return '
            body { font-family: sans-serif; font-size: 11px; color: #222; }
            h1 { font-size: 18px; margin-bottom: 2px; }
            h2 { font-size: 13px; margin-top: 14px; margin-bottom: 4px; border-bottom: 1px solid #ccc; }
            .meta { color: #666; font-size: 9px; margin-top: 0; }
            .chart { width: 100%; max-height: 260px; object-fit: contain; margin-top: 8px; }
            ul { margin: 0; padding-left: 16px; }
            li { margin-bottom: 3px; }
        ';
    }
}
