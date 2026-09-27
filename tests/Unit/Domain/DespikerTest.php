<?php

declare(strict_types=1);

namespace TopicSignals\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use TopicSignals\Domain\Service\Despiker;

final class DespikerTest extends TestCase
{
    private Despiker $despiker;

    protected function setUp(): void
    {
        $this->despiker = new Despiker();
    }

    public function test_flattens_a_single_news_driven_spike(): void
    {
        $counts = [100, 105, 95, 102, 5000, 98, 101, 99, 103, 97];

        $result = $this->despiker->despike($counts);

        self::assertSame(1, $result['spikesRemoved']);
        self::assertLessThan(200, $result['values'][4], 'The spike month should be smoothed toward its neighbors.');
        self::assertSame(100, $result['values'][0], 'Non-spike months must stay untouched.');
    }

    public function test_leaves_a_genuinely_growing_series_untouched(): void
    {
        $counts = [100, 120, 140, 160, 180, 200, 220, 240, 260, 280, 300, 320];

        $result = $this->despiker->despike($counts);

        self::assertSame(0, $result['spikesRemoved']);
        self::assertSame($counts, $result['values']);
    }

    public function test_skips_despiking_when_too_few_points(): void
    {
        $counts = [100, 5000, 100];

        $result = $this->despiker->despike($counts);

        self::assertSame(0, $result['spikesRemoved']);
        self::assertSame($counts, $result['values']);
    }

    public function test_handles_multiple_separate_spikes(): void
    {
        $counts = [100, 100, 5000, 100, 100, 100, 4800, 100, 100, 100];

        $result = $this->despiker->despike($counts);

        self::assertSame(2, $result['spikesRemoved']);
    }
}
