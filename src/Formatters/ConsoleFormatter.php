<?php

declare(strict_types=1);

namespace LaravelDoctor\Formatters;

use LaravelDoctor\Diagnostic;
use LaravelDoctor\Report;
use Symfony\Component\Console\Output\OutputInterface;

final class ConsoleFormatter implements FormatterInterface
{
    private const BAR_LENGTH = 24;
    private const CATEGORY_LABELS = [
        'security' => 'Security',
        'quality' => 'Quality',
        'documentation' => 'Testability',
        'environment' => 'Environment',
    ];

    public function __construct(
        private OutputInterface $output,
    ) {}

    public function format(Report $report, bool $verbose): string
    {
        return $this->buildOutput($report, $verbose, null);
    }

    public function output(Report $report, bool $verbose, ?float $durationSeconds = null): void
    {
        $this->output->writeln($this->buildOutput($report, $verbose, $durationSeconds));
    }

    private function buildOutput(Report $report, bool $verbose, ?float $durationSeconds): string
    {
        $score = $report->getScore();
        $color = $this->scoreColor($score);
        $label = $this->scoreLabel($score);

        $lines = [
            '',
            $this->renderHeader($score, $color, $label),
            $this->renderProgressBar($score, $color),
            '',
        ];

        $diagnostics = $report->getDiagnostics();
        $categoriesWithIssues = count(array_filter($report->getDiagnosticsGroupedByCategory(), fn ($d) => $d !== []));
        $summary = count($diagnostics) . ' diagnostic' . (count($diagnostics) !== 1 ? 's' : '')
            . ' across ' . $categoriesWithIssues . ' categor' . ($categoriesWithIssues !== 1 ? 'ies' : 'y');
        if ($durationSeconds !== null) {
            $summary .= ' in <fg=gray>' . $durationSeconds . 's</>';
        }
        $lines[] = '<fg=' . $color . '>' . $summary . '</>';
        $lines[] = '';

        $grouped = $report->getDiagnosticsGroupedByCategory();
        foreach ($grouped as $category => $list) {
            if ($list === []) {
                continue;
            }
            $label = self::CATEGORY_LABELS[$category] ?? $category;
            $lines[] = '<fg=white;options=bold>▸ ' . $label . '</>';
            foreach ($list as $d) {
                $lines[] = '  ' . $this->formatDiagnostic($d, $verbose);
            }
            $lines[] = '';
        }

        $lines[] = '';
        return implode("\n", $lines);
    }

    private function renderHeader(int $score, string $color, string $label): string
    {
        return "\n"
            . '<fg=white;options=bold>Laravel Doctor</> <fg=gray>v0.1</>' . "\n"
            . '<fg=white>Score:</> <fg=white;options=bold>' . $score . ' / 100</> <fg=' . $color . '>' . $label . '</>';
    }

    private function renderProgressBar(int $score, string $color): string
    {
        $filled = (int) round(self::BAR_LENGTH * $score / 100);
        $empty = self::BAR_LENGTH - $filled;
        $bar = '<fg=' . $color . '>' . str_repeat('█', $filled) . '</>'
            . '<fg=gray>' . str_repeat('░', $empty) . '</>';
        return '  ' . $bar;
    }

    private function scoreLabel(int $score): string
    {
        if ($score > 80) {
            return 'Good';
        }
        if ($score >= 50) {
            return 'Warning';
        }
        return 'Critical';
    }

    /** @return string color name */
    private function scoreColor(int $score): string
    {
        if ($score > 80) {
            return 'green';
        }
        if ($score >= 50) {
            return 'yellow';
        }
        return 'red';
    }

    private function formatDiagnostic(Diagnostic $d, bool $verbose): string
    {
        $icon = match ($d->severity) {
            'error' => '<fg=red>✗</>',
            'warning' => '<fg=yellow>⚠</>',
            default => '<fg=blue>ℹ</>',
        };
        $line = $icon . ' ' . $d->message;
        if ($verbose && ($d->file !== null || $d->line !== null)) {
            $line .= ' <fg=gray>(' . ($d->file ?? '') . ($d->line !== null ? ':' . $d->line : '') . ')</>';
        }
        return $line;
    }
}
