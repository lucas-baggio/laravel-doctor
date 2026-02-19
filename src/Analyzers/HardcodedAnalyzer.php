<?php

declare(strict_types=1);

namespace LaravelDoctor\Analyzers;

use LaravelDoctor\Diagnostic;
use LaravelDoctor\Report;
use Symfony\Component\Finder\Finder;

final class HardcodedAnalyzer implements AnalyzerInterface
{
    private const PATTERNS = [
        'password' => ['/\bpassword\s*=\s*[\'"][^\'"]+[\'"]/i', 'Senha ou credential hardcoded: use variáveis de ambiente (.env) ou secrets.'],
        'secret' => ['/\b(api_key|apikey|secret)\s*=\s*[\'"][^\'"]+[\'"]/i', 'API key ou secret no código: use env() ou config().'],
        'url_http' => ['/https?:\/\/[a-zA-Z0-9.-]+\.[a-z]{2,}(\/[^\s\'"]*)?/i', 'URL hardcoded: use config ou .env (ex: config("app.url")).'],
    ];

    public function analyze(string $projectPath, Report $report, array $config): void
    {
        $ignorePaths = $config['ignore_paths'] ?? ['vendor/', 'node_modules/', 'storage/', 'bootstrap/cache/'];
        $finder = new Finder();
        $finder->files()->in($projectPath)->name('*.php')->path(['app', 'config', 'routes', 'database']);
        foreach ($ignorePaths as $ignore) {
            $finder->notPath($ignore);
        }
        foreach ($finder as $file) {
            $relative = str_replace($projectPath . '/', '', str_replace('\\', '/', $file->getPathname()));
            // Ignorar arquivos em config/ (valores são tipicamente env() ou defaults; evita falsos positivos)
            if (str_starts_with($relative, 'config/')) {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            $lines = explode("\n", $content);
            foreach (self::PATTERNS as $name => [$regex, $recommendation]) {
                foreach ($lines as $num => $line) {
                    // Ignorar linhas onde o valor é env() ou config() (padrão Laravel)
                    if (str_contains($line, 'env(') || str_contains($line, 'config(')) {
                        continue;
                    }
                    if (preg_match($regex, $line)) {
                        $report->add(new Diagnostic(
                            'security',
                            'error',
                            "Possível valor sensível hardcoded ({$name}) em {$relative}",
                            $recommendation,
                            $relative,
                            $num + 1,
                            false
                        ));
                        break;
                    }
                }
            }
        }
    }
}
