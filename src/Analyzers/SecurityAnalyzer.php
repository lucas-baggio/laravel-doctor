<?php

declare(strict_types=1);

namespace LaravelDoctor\Analyzers;

use LaravelDoctor\Diagnostic;
use LaravelDoctor\Report;
use Symfony\Component\Finder\Finder;

final class SecurityAnalyzer implements AnalyzerInterface
{
    public function analyze(string $projectPath, Report $report, array $config): void
    {
        $this->checkCsrfOnRoutes($projectPath, $report);
        $this->checkKernelMiddleware($projectPath, $report);
        $this->checkXssAndInjection($projectPath, $report, $config);
    }

    private function checkCsrfOnRoutes(string $projectPath, Report $report): void
    {
        $routesPath = $projectPath . '/routes';
        if (!is_dir($routesPath)) {
            return;
        }
        $apiPath = $routesPath . '/api.php';
        $webPath = $routesPath . '/web.php';
        $content = '';
        if (is_file($apiPath)) {
            $content .= file_get_contents($apiPath);
        }
        if (is_file($webPath)) {
            $content .= file_get_contents($webPath);
        }
        if ($content === '') {
            return;
        }

        $csrfExcluded = preg_match('/VerifyCsrfToken|csrf/', $content);
        if (is_file($webPath)) {
            $webContent = file_get_contents($webPath);
            if (preg_match('/->(post|put|patch|delete)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $webContent, $m)) {
                $report->add(new Diagnostic(
                    'security',
                    'info',
                    'Rotas POST/PUT/PATCH/DELETE em web.php: verifique se o middleware VerifyCsrfToken está aplicado ao grupo web',
                    'Em App\\Http\\Kernel, o grupo web deve incluir \\Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken.',
                    'routes/web.php',
                    null,
                    false
                ));
            }
        }

        if (is_file($apiPath)) {
            $apiContent = file_get_contents($apiPath);
            $hasPost = (bool) preg_match('/->(post|put|patch|delete)\s*\(/', $apiContent);
            if ($hasPost) {
                $report->add(new Diagnostic(
                    'security',
                    'error',
                    'Rotas de escrita (POST/PUT/PATCH/DELETE) em api.php: API stateless pode não usar CSRF; garanta autenticação (sanctum/session)',
                    'Para APIs: use Laravel Sanctum ou outra autenticação e não dependa de CSRF para stateless. Para formulários web, use rotas em web.php com CSRF.',
                    'routes/api.php',
                    null,
                    false
                ));
            }
        }
    }

    private function checkKernelMiddleware(string $projectPath, Report $report): void
    {
        $kernelPath = $projectPath . '/app/Http/Kernel.php';
        if (!is_file($kernelPath)) {
            $report->add(new Diagnostic(
                'security',
                'info',
                'app/Http/Kernel.php não encontrado (Laravel 11+ pode usar bootstrap/app.php)',
                'Verifique se middlewares globais e de grupo estão definidos em bootstrap/app.php ou em Kernel.',
                null,
                null,
                false
            ));
            return;
        }
        $content = file_get_contents($kernelPath);
        if (!str_contains($content, 'VerifyCsrfToken') && !str_contains($content, 'ValidateCsrfToken')) {
            $report->add(new Diagnostic(
                'security',
                'warning',
                'Kernel não referencia VerifyCsrfToken/ValidateCsrfToken no grupo web',
                'Adicione \\Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken ao middleware group "web".',
                'app/Http/Kernel.php',
                null,
                false
            ));
        }
    }

    private function checkXssAndInjection(string $projectPath, Report $report, array $config = []): void
    {
        $appPath = $projectPath . '/app';
        if (!is_dir($appPath)) {
            return;
        }
        $ignorePaths = $config['ignore_paths'] ?? ['vendor/', 'node_modules/'];
        $finder = new Finder();
        $finder->files()->in($appPath)->name('*.php');
        foreach ($ignorePaths as $ignore) {
            $finder->notPath($ignore);
        }
        $dangerous = [
            'DB::raw(' => 'SQL injection: evite DB::raw com input do usuário; use query builder parametrizado.',
            'whereRaw(' => 'SQL injection: use whereRaw apenas com bindings (? ou named).',
            'selectRaw(' => 'SQL injection: use selectRaw com bindings.',
            'orderByRaw(' => 'SQL injection: use orderByRaw com bindings.',
            '{{ ' => 'XSS: em Blade, prefira {{ $var }} que escapa; evite {!! !!} com dados não confiáveis.',
        ];
        foreach ($finder as $file) {
            $content = file_get_contents($file->getPathname());
            $relative = str_replace($projectPath . '/', '', $file->getPathname());
            foreach ($dangerous as $pattern => $recommendation) {
                if (str_contains($content, $pattern)) {
                    $report->add(new Diagnostic(
                        'security',
                        'warning',
                        "Possível risco de segurança em {$relative}: uso de {$pattern}",
                        $recommendation,
                        $relative,
                        null,
                        false
                    ));
                    break;
                }
            }
        }
    }
}
