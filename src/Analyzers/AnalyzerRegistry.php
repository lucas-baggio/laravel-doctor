<?php

declare(strict_types=1);

namespace LaravelDoctor\Analyzers;

use LaravelDoctor\Report;

final class AnalyzerRegistry
{
    /** @var array<string, AnalyzerInterface> */
    private array $analyzers = [];

    public function __construct()
    {
        $this->registerDefault();
    }

    private function registerDefault(): void
    {
        $this->register('environment', new EnvironmentAnalyzer());
        $this->register('psr12', new Psr12Analyzer());
        $this->register('routes_tests', new RoutesTestsAnalyzer());
        $this->register('controllers', new ControllersAnalyzer());
        $this->register('policies', new PoliciesAnalyzer());
        $this->register('security', new SecurityAnalyzer());
        $this->register('hardcoded', new HardcodedAnalyzer());
        $this->register('test_coverage', new TestCoverageAnalyzer());
        $this->register('dependencies', new DependenciesAnalyzer());
    }

    public function register(string $key, AnalyzerInterface $analyzer): void
    {
        $this->analyzers[$key] = $analyzer;
    }

    public function run(string $projectPath, Report $report, array $config): void
    {
        $enabled = $config['analyzers'] ?? array_fill_keys(array_keys($this->analyzers), true);
        foreach ($this->analyzers as $key => $analyzer) {
            if ($enabled[$key] ?? true) {
                $analyzer->analyze($projectPath, $report, $config);
            }
        }
    }
}
