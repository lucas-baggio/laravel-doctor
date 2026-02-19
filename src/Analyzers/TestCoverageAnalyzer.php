<?php

declare(strict_types=1);

namespace LaravelDoctor\Analyzers;

use LaravelDoctor\Diagnostic;
use LaravelDoctor\Report;
use Symfony\Component\Finder\Finder;

final class TestCoverageAnalyzer implements AnalyzerInterface
{
    public function analyze(string $projectPath, Report $report, array $config): void
    {
        $testsPath = $projectPath . '/tests';
        if (!is_dir($testsPath)) {
            $report->add(new Diagnostic(
                'documentation',
                'warning',
                'Pasta tests/ não encontrada',
                'Crie a pasta tests/ e adicione testes com PHPUnit (Feature e Unit).',
                null,
                null,
                false
            ));
            return;
        }

        $finder = new Finder();
        $finder->files()->in($testsPath)->name('*Test*.php')->name('*Test.php');
        $count = iterator_count($finder);
        if ($count === 0) {
            $report->add(new Diagnostic(
                'documentation',
                'warning',
                'Nenhum arquivo de teste encontrado em tests/',
                'Adicione testes PHPUnit (ex: tests/Feature/ExampleTest.php).',
                null,
                null,
                false
            ));
            return;
        }

        $phpunitXml = $projectPath . '/phpunit.xml';
        if (!is_file($phpunitXml) && !is_file($projectPath . '/phpunit.xml.dist')) {
            $report->add(new Diagnostic(
                'documentation',
                'info',
                'phpunit.xml não encontrado',
                'Crie phpunit.xml ou phpunit.xml.dist para configurar o PHPUnit.',
                null,
                null,
                false
            ));
        }

        $report->add(new Diagnostic(
            'documentation',
            'info',
            "Testes básicos: {$count} arquivo(s) de teste encontrado(s) em tests/",
            'Execute php artisan test ou ./vendor/bin/phpunit para rodar os testes.',
            null,
            null,
            false
        ));
    }
}
