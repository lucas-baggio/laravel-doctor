<?php

declare(strict_types=1);

namespace LaravelDoctor\Analyzers;

use LaravelDoctor\Diagnostic;
use LaravelDoctor\Report;
use Symfony\Component\Finder\Finder;

final class PoliciesAnalyzer implements AnalyzerInterface
{
    public function analyze(string $projectPath, Report $report, array $config): void
    {
        $modelsPath = $projectPath . '/app/Models';
        $policiesPath = $projectPath . '/app/Policies';

        if (!is_dir($modelsPath)) {
            return;
        }

        $models = [];
        $finder = new Finder();
        $finder->files()->in($modelsPath)->name('*.php');
        foreach ($finder as $f) {
            $relativePath = str_replace('\\', '/', $f->getRelativePathname());
            // Ignorar Scopes, Concerns, Traits (não são models que precisam de Policy)
            if (str_contains($relativePath, 'Scopes/')
                || str_contains($relativePath, 'Concerns/')
                || str_contains($relativePath, 'Traits/')) {
                continue;
            }
            $name = $f->getFilenameWithoutExtension();
            if ($name === 'User') {
                continue;
            }
            $models[] = $name;
        }

        $existingPolicies = [];
        if (is_dir($policiesPath)) {
            $finder = new Finder();
            $finder->files()->in($policiesPath)->name('*.php');
            foreach ($finder as $f) {
                $existingPolicies[] = str_replace('Policy', '', $f->getFilenameWithoutExtension());
            }
        }

        $authServiceProvider = $projectPath . '/app/Providers/AuthServiceProvider.php';
        $gateDefinitions = [];
        if (is_file($authServiceProvider)) {
            $content = file_get_contents($authServiceProvider);
            if (preg_match_all('/Gate::(define|resource)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/', $content, $m, PREG_SET_ORDER)) {
                foreach ($m as $match) {
                    $gateDefinitions[] = $match[2];
                }
            }
        }

        foreach ($models as $model) {
            $policyName = $model . 'Policy';
            $hasPolicy = in_array($model, $existingPolicies, true);
            $hasGate = in_array($model, $gateDefinitions, true) || in_array($policyName, $gateDefinitions, true);
            if (!$hasPolicy && !$hasGate) {
                $report->add(new Diagnostic(
                    'security',
                    'warning',
                    "Model {$model} sem Policy ou Gate de autorização",
                    "Crie app/Policies/{$policyName}.php e registre em AuthServiceProvider (Gate::policy ou Gate::resource).",
                    null,
                    null,
                    false
                ));
            }
        }
    }
}
