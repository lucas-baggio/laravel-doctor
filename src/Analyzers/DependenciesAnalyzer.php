<?php

declare(strict_types=1);

namespace LaravelDoctor\Analyzers;

use LaravelDoctor\Diagnostic;
use LaravelDoctor\Report;

final class DependenciesAnalyzer implements AnalyzerInterface
{
    public function analyze(string $projectPath, Report $report, array $config): void
    {
        $lockPath = $projectPath . '/composer.lock';
        if (!is_file($lockPath)) {
            $report->add(new Diagnostic(
                'security',
                'warning',
                'composer.lock não encontrado',
                'Execute composer update e commite o composer.lock para garantir builds reproduzíveis.',
                null,
                null,
                false
            ));
            return;
        }

        $data = @json_decode(file_get_contents($lockPath), true);
        if (!is_array($data)) {
            $report->add(new Diagnostic(
                'security',
                'warning',
                'composer.lock inválido ou não legível',
                'Verifique o conteúdo de composer.lock.',
                'composer.lock',
                null,
                false
            ));
            return;
        }

        $report->add(new Diagnostic(
            'security',
            'info',
            'Dependências: composer.lock presente. Para verificar vulnerabilidades, execute: composer audit',
            'Adicione "composer audit" no CI ou rode localmente com frequência.',
            null,
            null,
            false
        ));
    }
}
