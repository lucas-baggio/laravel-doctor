<?php

declare(strict_types=1);

namespace LaravelDoctor\Config;

use Symfony\Component\Yaml\Yaml;

final class ConfigLoader
{
    private const DEFAULT_CONFIG = [
        'analyzers' => [
            'environment' => true,
            'psr12' => true,
            'routes_tests' => true,
            'controllers' => true,
            'policies' => true,
            'security' => true,
            'hardcoded' => true,
            'test_coverage' => true,
            'dependencies' => true,
        ],
        'ignore_paths' => [
            'vendor/',
            'node_modules/',
            'storage/',
            'bootstrap/cache/',
        ],
        'severity_weights' => [
            'error' => 10,
            'warning' => 5,
            'info' => 2,
        ],
    ];

    public function load(?string $configPath = null, ?string $projectPath = null): array
    {
        if ($configPath === null && $projectPath !== null) {
            foreach (['laravel-doctor.config.php', 'laravel-doctor.config.json', 'laravel-doctor.config.yaml', 'laravel-doctor.config.yml'] as $name) {
                $candidate = $projectPath . '/' . $name;
                if (is_file($candidate)) {
                    $configPath = $candidate;
                    break;
                }
            }
        }
        if ($configPath !== null && is_file($configPath)) {
            $content = file_get_contents($configPath);
            $ext = strtolower(pathinfo($configPath, PATHINFO_EXTENSION));
            if ($ext === 'php') {
                $custom = (static function () use ($configPath) {
                    return require $configPath;
                })();
                $custom = is_array($custom) ? $custom : [];
            } else {
                $custom = $ext === 'yaml' || $ext === 'yml'
                    ? Yaml::parse($content)
                    : (array) json_decode($content, true);
            }
            return array_replace_recursive(self::DEFAULT_CONFIG, $custom ?: []);
        }
        return self::DEFAULT_CONFIG;
    }
}
