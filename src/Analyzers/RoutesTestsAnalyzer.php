<?php

declare(strict_types=1);

namespace LaravelDoctor\Analyzers;

use LaravelDoctor\Diagnostic;
use LaravelDoctor\Report;
use Symfony\Component\Finder\Finder;

final class RoutesTestsAnalyzer implements AnalyzerInterface
{
    public function analyze(string $projectPath, Report $report, array $config): void
    {
        $routesPath = $projectPath . '/routes';
        $testsPath = $projectPath . '/tests';

        if (!is_dir($routesPath)) {
            $report->add(new Diagnostic(
                'documentation',
                'info',
                'Diretório routes/ não encontrado',
                'Projeto pode não usar rotas Laravel padrão.',
                null,
                null,
                false
            ));
            return;
        }

        $routes = $this->extractRoutesFromFiles($routesPath);
        if ($routes === []) {
            return;
        }

        $testFiles = [];
        if (is_dir($testsPath)) {
            $finder = new Finder();
            $finder->files()->in($testsPath)->name('*Test*.php')->name('*Test.php');
            foreach ($finder as $f) {
                $content = file_get_contents($f->getPathname());
                $testFiles[$f->getPathname()] = $content;
            }
        }

        foreach ($routes as $route) {
            $hasTest = false;
            foreach ($testFiles as $content) {
                if ($this->routeMentionedInTest($route, $content)) {
                    $hasTest = true;
                    break;
                }
            }
            if (!$hasTest) {
                $report->add(new Diagnostic(
                    'documentation',
                    'warning',
                    "Rota sem teste aparente: {$route['method']} {$route['uri']}",
                    "Crie um teste em tests/ (Feature ou Unit) que cubra esta rota (ex: \$response = \$this->get/post('{$route['uri']}')).",
                    $route['file'] ?? null,
                    $route['line'] ?? null,
                    false
                ));
            }
        }
    }

    /** @return list<array{method: string, uri: string, file?: string, line?: int}> */
    private function extractRoutesFromFiles(string $routesPath): array
    {
        $routes = [];
        $finder = new Finder();
        $finder->files()->in($routesPath)->name('*.php');
        foreach ($finder as $file) {
            $content = file_get_contents($file->getPathname());
            $lines = explode("\n", $content);
            foreach ($lines as $num => $line) {
                if (preg_match('/->(get|post|put|patch|delete|any)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $line, $m)) {
                    $routes[] = [
                        'method' => strtoupper($m[1]),
                        'uri' => $m[2],
                        'file' => $file->getRelativePathname(),
                        'line' => $num + 1,
                    ];
                }
                if (preg_match('/Route::(get|post|put|patch|delete|any)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $line, $m)) {
                    $routes[] = [
                        'method' => strtoupper($m[1]),
                        'uri' => $m[2],
                        'file' => $file->getRelativePathname(),
                        'line' => $num + 1,
                    ];
                }
            }
        }
        return $routes;
    }

    private function routeMentionedInTest(array $route, string $testContent): bool
    {
        $uri = $route['uri'];
        $method = strtolower($route['method']);
        $snippets = [
            "\$this->{$method}(",
            "\$this->{$method} ('",
            "\$this->{$method}(\"",
            "'{$uri}'",
            "\"{$uri}\"",
        ];
        foreach ($snippets as $s) {
            if (str_contains($testContent, $s)) {
                return true;
            }
        }
        return false;
    }
}
