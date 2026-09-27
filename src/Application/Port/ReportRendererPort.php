<?php

declare(strict_types=1);

namespace TopicSignals\Application\Port;

use TopicSignals\Application\Dto\ReportContent;

interface ReportRendererPort
{
    /** @return string absolute path to the generated one-page PDF file */
    public function renderOnePagePdf(ReportContent $content, string $outputFileName): string;
}
