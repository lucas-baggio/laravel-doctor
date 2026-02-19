<?php

declare(strict_types=1);

namespace LaravelDoctor\Analyzers;

use LaravelDoctor\Diagnostic;
use LaravelDoctor\Report;

final class EnvironmentAnalyzer implements AnalyzerInterface
{
    private const REQUIRED_KEYS = ['APP_KEY', 'APP_ENV', 'APP_DEBUG'];
    private const RECOMMENDED_KEYS = ['CACHE_DRIVER', 'QUEUE_CONNECTION', 'SESSION_DRIVER', 'DB_CONNECTION'];

    public function analyze(string $projectPath, Report $report, array $config): void
    {
        $envPath = $projectPath . '/.env';
        $envExamplePath = $projectPath . '/.env.example';

        if (!is_file($envPath)) {
            $report->add(new Diagnostic(
                'environment',
                'error',
                'Arquivo .env não encontrado',
                'Crie um .env a partir de .env.example: cp .env.example .env && php artisan key:generate',
                $envPath,
                null,
                false
            ));
            return;
        }

        $content = @file_get_contents($envPath);
        if ($content === false) {
            $report->add(new Diagnostic(
                'environment',
                'error',
                'Não foi possível ler o arquivo .env',
                'Verifique permissões do arquivo .env',
                $envPath,
                null,
                false
            ));
            return;
        }

        $vars = $this->parseEnv($content);

        foreach (self::REQUIRED_KEYS as $key) {
            $value = $vars[$key] ?? null;
            if ($value === null || trim((string) $value) === '') {
                $report->add(new Diagnostic(
                    'environment',
                    'error',
                    "Variável de ambiente obrigatória ausente ou vazia: {$key}",
                    $key === 'APP_KEY'
                        ? 'Execute: php artisan key:generate'
                        : "Defina {$key} no .env",
                    $envPath,
                    null,
                    $key === 'APP_KEY'
                ));
            }
        }

        foreach (self::RECOMMENDED_KEYS as $key) {
            if (!isset($vars[$key]) || trim((string) $vars[$key]) === '') {
                $report->add(new Diagnostic(
                    'environment',
                    'warning',
                    "Variável recomendada ausente ou vazia: {$key}",
                    "Defina {$key} no .env (ex: CACHE_DRIVER=file, QUEUE_CONNECTION=sync)",
                    $envPath,
                    null,
                    false
                ));
            }
        }

        if (isset($vars['APP_DEBUG']) && in_array(strtolower((string) $vars['APP_DEBUG']), ['true', '1'], true)) {
            $report->add(new Diagnostic(
                'environment',
                'warning',
                'APP_DEBUG está ativado (produção não deve usar true)',
                'Em produção defina APP_DEBUG=false',
                $envPath,
                null,
                false
            ));
        }
    }

    /** @return array<string, string> */
    private function parseEnv(string $content): array
    {
        $vars = [];
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$name, $value] = explode('=', $line, 2);
                $vars[trim($name)] = trim($value, " \t\"'");
            }
        }
        return $vars;
    }
}
