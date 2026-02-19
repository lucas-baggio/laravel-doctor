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
            $content = file_get_contents($file->getPathname());
            $relative = str_replace($projectPath . '/', '', $file->getPathname());
            $lines = explode("\n", $content);
            foreach (self::PATTERNS as $name => [$regex, $recommendation]) {
                foreach ($lines as $num => $line) {
                    // Ignorar linhas onde o valor vem de env() ou config() (padrão Laravel - ex: url em config/session)
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
