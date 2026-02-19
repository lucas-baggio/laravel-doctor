<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Unit;

use LaravelDoctor\Analyzers\EnvironmentAnalyzer;
use LaravelDoctor\Report;
use PHPUnit\Framework\TestCase;

final class EnvironmentAnalyzerTest extends TestCase
{
    private string $projectPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectPath = sys_get_temp_dir() . '/laravel-doctor-test-' . uniqid();
        mkdir($this->projectPath, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->projectPath)) {
            @unlink($this->projectPath . '/.env');
            @rmdir($this->projectPath);
        }
        parent::tearDown();
    }

    public function test_reports_error_when_env_missing(): void
    {
        $report = new Report($this->projectPath, []);
        $analyzer = new EnvironmentAnalyzer();
        $analyzer->analyze($this->projectPath, $report, []);
        $diagnostics = $report->getDiagnostics();
        self::assertNotEmpty($diagnostics);
        $messages = array_map(static fn ($d) => $d->message, $diagnostics);
        self::assertContains('Arquivo .env não encontrado', $messages);
    }

    public function test_reports_missing_app_key_when_env_exists(): void
    {
        file_put_contents($this->projectPath . '/.env', "APP_ENV=local\nAPP_DEBUG=false\n");
        $report = new Report($this->projectPath, []);
        $analyzer = new EnvironmentAnalyzer();
        $analyzer->analyze($this->projectPath, $report, []);
        $messages = array_map(static fn ($d) => $d->message, $report->getDiagnostics());
        self::assertTrue(
            array_reduce($messages, static fn ($carry, $m) => $carry || str_contains($m, 'APP_KEY'), false),
            'Expected at least one diagnostic about APP_KEY'
        );
    }
}
