<?php

declare(strict_types=1);

namespace LaravelDoctor;

final class Report
{
    /** @var list<Diagnostic> */
    private array $diagnostics = [];

    private const CATEGORY_CAPS = [
        'security' => 40,
        'quality' => 30,
        'documentation' => 20, // Testability
        'environment' => 10,
    ];

    private const SEVERITY_WEIGHTS = [
        'error' => 10,
        'warning' => 5,
        'info' => 2,
    ];

    public function __construct(
        public readonly string $projectPath,
        public readonly array $config = [],
    ) {}

    public function add(Diagnostic $diagnostic): void
    {
        $this->diagnostics[] = $diagnostic;
    }

    /** @return list<Diagnostic> */
    public function getDiagnostics(): array
    {
        return $this->diagnostics;
    }

    /**
     * Pontuação por categoria com teto (cap). Nenhuma categoria tira mais que seu peso.
     * Bônus: +10 se tests/ tem mais de 5 arquivos, +5 se composer.lock existe.
     */
    public function getScore(): int
    {
        $deductionByCategory = $this->calculateDeductionByCategory();
        $totalDeduction = array_sum($deductionByCategory);
        $score = 100 - (int) $totalDeduction;

        $score += $this->getBonusPoints();
        return (int) max(0, min(100, $score));
    }

    /** @return array<string, int> Dedução por categoria (já limitada ao cap) */
    public function getDeductionByCategory(): array
    {
        return $this->calculateDeductionByCategory();
    }

    /** @return array<string, int> */
    private function calculateDeductionByCategory(): array
    {
        $weights = $this->config['severity_weights'] ?? self::SEVERITY_WEIGHTS;
        $sumByCategory = [];

        foreach ($this->diagnostics as $d) {
            $cat = $d->category;
            if (!isset(self::CATEGORY_CAPS[$cat])) {
                $cat = 'quality';
            }
            $points = $weights[$d->severity] ?? 5;
            $sumByCategory[$cat] = ($sumByCategory[$cat] ?? 0) + $points;
        }

        $capped = [];
        foreach (self::CATEGORY_CAPS as $cat => $cap) {
            $capped[$cat] = min($sumByCategory[$cat] ?? 0, $cap);
        }
        return $capped;
    }

    private function getBonusPoints(): int
    {
        $bonus = 0;
        $testsPath = $this->projectPath . '/tests';
        if (is_dir($testsPath)) {
            $count = 0;
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($testsPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($it as $f) {
                if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
                    $count++;
                }
            }
            if ($count > 5) {
                $bonus += 10;
            }
        }
        if (is_file($this->projectPath . '/composer.lock')) {
            $bonus += 5;
        }
        return $bonus;
    }

    /** @return array<string, list<Diagnostic>> Diagnósticos agrupados por categoria (para output) */
    public function getDiagnosticsGroupedByCategory(): array
    {
        $order = ['security', 'quality', 'documentation', 'environment'];
        $grouped = [];
        foreach ($order as $cat) {
            $grouped[$cat] = array_values(array_filter(
                $this->diagnostics,
                static fn (Diagnostic $d) => $d->category === $cat
            ));
        }
        return $grouped;
    }

    /** @return array{score: int, diagnostics: list<array>, projectPath: string, deductionByCategory: array} */
    public function toArray(): array
    {
        return [
            'score' => $this->getScore(),
            'projectPath' => $this->projectPath,
            'deductionByCategory' => $this->getDeductionByCategory(),
            'diagnostics' => array_map(
                static fn (Diagnostic $d) => $d->toArray(),
                $this->diagnostics
            ),
        ];
    }
}
