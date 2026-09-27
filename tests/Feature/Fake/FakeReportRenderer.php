<?php

declare(strict_types=1);

namespace TopicSignals\Tests\Feature\Fake;

use TopicSignals\Application\Dto\ReportContent;
use TopicSignals\Application\Port\ReportRendererPort;

final class FakeReportRenderer implements ReportRendererPort
{
    public function renderOnePagePdf(ReportContent $content, string $outputFileName): string
    {
        return '/tmp/' . $outputFileName;
    }
}
