<?php

declare(strict_types=1);

namespace LaravelDoctor\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use LaravelDoctor\Commands\DoctorCommand;
use LaravelDoctor\Config\ConfigLoader;
use LaravelDoctor\Analyzers\AnalyzerRegistry;

final class DoctorCommandTest extends TestCase
{
    public function test_command_exits_successfully_on_valid_project_dir(): void
    {
        $app = new Application();
        $app->add(new DoctorCommand(new ConfigLoader(), new AnalyzerRegistry()));
        $command = $app->find('doctor');
        $tester = new CommandTester($command);
        $exit = $tester->execute(['project' => __DIR__ . '/../../']);
        $output = $tester->getDisplay();
        self::assertStringContainsString('Laravel Doctor', $output);
        self::assertStringContainsString('Score:', $output);
        self::assertSame(0, $exit);
    }

    public function test_command_fails_on_invalid_dir(): void
    {
        $app = new Application();
        $app->add(new DoctorCommand(new ConfigLoader(), new AnalyzerRegistry()));
        $command = $app->find('doctor');
        $tester = new CommandTester($command);
        $exit = $tester->execute(['project' => '/nonexistent-path-12345']);
        self::assertSame(1, $exit);
    }
}
