<?php

declare(strict_types=1);

namespace LaravelDoctor\Analyzers;

use LaravelDoctor\Report;

interface AnalyzerInterface
{
    public function analyze(string $projectPath, Report $report, array $config): void;
}
