<?php

declare(strict_types=1);

namespace LaravelDoctor\Analyzers;

use LaravelDoctor\Diagnostic;
use LaravelDoctor\Report;
use Symfony\Component\Finder\Finder;

final class Psr12Analyzer implements AnalyzerInterface
{
    public function analyze(string $projectPath, Report $report, array $config): void
    {
        $ignorePaths = $config['ignore_paths'] ?? ['vendor/', 'node_modules/', 'storage/', 'bootstrap/cache/'];
        $appPath = $projectPath . '/app';
        if (!is_dir($appPath)) {
            $report->add(new Diagnostic(
                'quality',
                'info',
                'Diretório app/ não encontrado para análise PSR-12',
                'O foco da análise de qualidade é estritamente o diretório app/.',
                null,
                null,
                false
            ));
            return;
        }

        $finder = new Finder();
        $finder->files()->in($appPath)->name('*.php');
        foreach ($ignorePaths as $ignore) {
            $finder->exclude($ignore);
        }
        $found = false;
        foreach ($finder as $file) {
            $found = true;
            $this->checkFile($file->getPathname(), $report, $projectPath);
        }

        if (!$found) {
            $report->add(new Diagnostic(
                'quality',
                'info',
                'Nenhum arquivo PHP encontrado em app/ para análise PSR-12',
                'Adicione código em app/ ou ajuste ignore_paths na configuração.',
                null,
                null,
                false
            ));
        }
    }

    private function checkFile(string $filePath, Report $report, string $projectPath): void
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return;
        }
        $relative = str_replace($projectPath . '/', '', $filePath);
        $relativeNormalized = str_replace('\\', '/', $relative);

        // Escopo quality: apenas app/ (database/, config/, storage/, bootstrap/cache/ já excluídos pelo iterador)
        if (!str_contains($content, 'declare(strict_types=1)') && !str_contains($content, 'declare (strict_types=1)')) {
            $report->add(new Diagnostic(
                'quality',
                'warning',
                "Arquivo sem declare(strict_types=1): {$relative}",
                'Adicione declare(strict_types=1); no início do arquivo após <?php',
                $relative,
                1,
                true,
                'Add declare(strict_types=1);',
                'strict_types'
            ));
        }

        if (preg_match('/\s+$/m', $content) || substr($content, -1) !== "\n") {
            $report->add(new Diagnostic(
                'quality',
                'info',
                "Possível trailing whitespace ou falta newline no final: {$relative}",
                'Remova espaços no final das linhas e termine o arquivo com uma newline.',
                $relative,
                null,
                true,
                null,
                'trailing_whitespace'
            ));
        }

        if (preg_match('/\t/', $content)) {
            $report->add(new Diagnostic(
                'quality',
                'warning',
                "Arquivo contém tabs (PSR-12 recomenda espaços): {$relative}",
                'Substitua tabs por 4 espaços.',
                $relative,
                null,
                true,
                null,
                'tabs'
            ));
        }
    }
}
