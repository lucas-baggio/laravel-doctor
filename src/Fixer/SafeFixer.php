<?php

declare(strict_types=1);

namespace LaravelDoctor\Fixer;

use LaravelDoctor\Diagnostic;
use LaravelDoctor\Report;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Aplica apenas correções seguras que não alteram comportamento: strict_types, tabs, trailing whitespace.
 */
final class SafeFixer
{
    private const SAFE_FIX_TYPES = ['strict_types', 'tabs', 'trailing_whitespace'];

    public function __construct(
        private string $projectPath,
        private OutputInterface $output,
    ) {}

    public function run(Report $report): int
    {
        $fixable = array_filter(
            $report->getDiagnostics(),
            static fn (Diagnostic $d) => $d->autoFixable
                && $d->file !== null
                && $d->fixType !== null
                && in_array($d->fixType, self::SAFE_FIX_TYPES, true)
        );
        if ($fixable === []) {
            return 0;
        }

        // Agrupar por arquivo e tipo de fix (aplicar cada fix uma vez por arquivo)
        $byFile = [];
        $projectReal = realpath($this->projectPath) ?: $this->projectPath;
        foreach ($fixable as $d) {
            $path = $this->projectPath . '/' . ltrim($d->file, '/');
            $pathReal = realpath($path);
            if ($pathReal === false
                || !(str_starts_with($pathReal, $projectReal . '/') || str_starts_with($pathReal, $projectReal . DIRECTORY_SEPARATOR))) {
                continue;
            }
            if (!isset($byFile[$pathReal])) {
                $byFile[$pathReal] = [];
            }
            $byFile[$pathReal][$d->fixType] = true;
        }

        $applied = 0;
        $projectReal = realpath($this->projectPath) ?: $this->projectPath;
        foreach ($byFile as $fullPath => $fixTypes) {
            if (!$this->applyFixesToFile($fullPath, array_keys($fixTypes))) {
                continue;
            }
            $applied++;
            $relative = str_replace([$projectReal . '/', $projectReal . DIRECTORY_SEPARATOR], '', $fullPath);
            $this->output->writeln('  <info>Corrigido:</> ' . $relative);
        }

        return $applied;
    }

    /**
     * @param list<string> $fixTypes
     */
    private function applyFixesToFile(string $fullPath, array $fixTypes): bool
    {
        if (!is_file($fullPath) || !is_readable($fullPath) || !is_writable($fullPath)) {
            return false;
        }
        $content = file_get_contents($fullPath);
        if ($content === false) {
            return false;
        }

        $original = $content;

        if (in_array('strict_types', $fixTypes, true)) {
            $content = $this->fixStrictTypes($content);
        }
        if (in_array('tabs', $fixTypes, true)) {
            $content = $this->fixTabs($content);
        }
        if (in_array('trailing_whitespace', $fixTypes, true)) {
            $content = $this->fixTrailingWhitespace($content);
        }

        if ($content === $original) {
            return false;
        }
        return file_put_contents($fullPath, $content) !== false;
    }

    private function fixStrictTypes(string $content): string
    {
        if (str_contains($content, 'declare(strict_types=1)') || str_contains($content, 'declare (strict_types=1)')) {
            return $content;
        }
        $insert = "\n\ndeclare(strict_types=1);\n\n";
        if (preg_match('/^(\s*<\?php)\s*/i', $content, $m)) {
            return preg_replace('/^(\s*<\?php)\s*/i', $m[1] . $insert, $content, 1);
        }
        return $content;
    }

    private function fixTabs(string $content): string
    {
        return str_replace("\t", '    ', $content);
    }

    private function fixTrailingWhitespace(string $content): string
    {
        $content = str_replace(["\r\n", "\r"], ["\n", "\n"], $content);
        $content = preg_replace('/\s+$/m', '', $content);
        $content = rtrim($content, " \t\n\r");
        return $content === '' ? "\n" : $content . "\n";
    }
}
