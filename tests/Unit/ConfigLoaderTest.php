<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Unit;

use LaravelDoctor\Config\ConfigLoader;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    public function test_load_returns_default_when_no_path(): void
    {
        $loader = new ConfigLoader();
        $config = $loader->load(null);
        self::assertArrayHasKey('analyzers', $config);
        self::assertArrayHasKey('ignore_paths', $config);
        self::assertArrayHasKey('severity_weights', $config);
        self::assertTrue($config['analyzers']['environment'] ?? false);
    }

    public function test_load_returns_default_when_file_not_found(): void
    {
        $loader = new ConfigLoader();
        $config = $loader->load('/nonexistent/path.php');
        self::assertArrayHasKey('analyzers', $config);
    }
}
