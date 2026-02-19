<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Unit;

use LaravelDoctor\Diagnostic;
use LaravelDoctor\Report;
use PHPUnit\Framework\TestCase;

final class ReportTest extends TestCase
{
    public function test_score_starts_at_100_with_no_diagnostics(): void
    {
        $report = new Report('/tmp/project', []);
        self::assertSame(100, $report->getScore());
    }

    public function test_score_decreases_with_diagnostics(): void
    {
        $config = [
            'severity_weights' => [
                'error' => 10,
                'warning' => 5,
                'info' => 2,
            ],
        ];
        $report = new Report('/tmp/project', $config);
        $report->add(new Diagnostic('security', 'error', 'Test', 'Fix it', null, null, false));
        $report->add(new Diagnostic('quality', 'warning', 'Test', 'Fix it', null, null, false));
        $score = $report->getScore();
        self::assertLessThan(100, $score);
        self::assertGreaterThanOrEqual(0, $score);
    }

    public function test_to_array_includes_score_and_diagnostics(): void
    {
        $report = new Report('/tmp/project', []);
        $report->add(new Diagnostic('security', 'info', 'Msg', 'Rec', 'file.php', 1, false));
        $data = $report->toArray();
        self::assertArrayHasKey('score', $data);
        self::assertArrayHasKey('projectPath', $data);
        self::assertArrayHasKey('diagnostics', $data);
        self::assertCount(1, $data['diagnostics']);
        self::assertSame('security', $data['diagnostics'][0]['category']);
        self::assertSame('Msg', $data['diagnostics'][0]['message']);
    }
}
