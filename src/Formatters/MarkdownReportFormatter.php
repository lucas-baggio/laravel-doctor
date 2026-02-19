<?php

declare(strict_types=1);

namespace LaravelDoctor\Formatters;

use LaravelDoctor\Diagnostic;
use LaravelDoctor\Report;

final class MarkdownReportFormatter implements FormatterInterface
{
    public function format(Report $report, bool $verbose): string
    {
        $score = $report->getScore();
        $lines = [
            '# Laravel Doctor Report',
            '',
            "**Score:** {$score} / 100",
            "**Project:** `" . $report->projectPath . "`",
            '',
            '## Diagnostics',
            '',
            '| Category | Severity | Message | Recommendation |',
            '|----------|----------|---------|----------------|',
        ];
        foreach ($report->getDiagnostics() as $d) {
            $loc = $verbose && ($d->file !== null || $d->line !== null)
                ? ($d->file ?? '') . ($d->line !== null ? ':' . $d->line : '')
                : '-';
            $lines[] = sprintf(
                '| %s | %s | %s | %s |',
                $d->category,
                $d->severity,
                $this->escapeMd($d->message),
                $this->escapeMd($d->recommendation)
            );
        }
        $lines[] = '';
        return implode("\n", $lines);
    }

    private function escapeMd(string $s): string
    {
        return str_replace(['|', "\n"], ['\\|', ' '], $s);
    }
}
