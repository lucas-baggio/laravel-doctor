<?php

declare(strict_types=1);

namespace LaravelDoctor\Commands;

use LaravelDoctor\Analyzers\AnalyzerRegistry;
use LaravelDoctor\Config\ConfigLoader;
use LaravelDoctor\Formatters\ConsoleFormatter;
use LaravelDoctor\Formatters\JsonReportFormatter;
use LaravelDoctor\Formatters\MarkdownReportFormatter;
use LaravelDoctor\Fixer\SafeFixer;
use LaravelDoctor\Report;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'doctor',
    description: 'Analyzes a Laravel project for architecture, quality, security, documentation and DX',
)]
final class DoctorCommand extends Command
{
    private const VERSION = '0.1';

    public function __construct(
        private ConfigLoader $configLoader,
        private AnalyzerRegistry $analyzerRegistry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('project', InputArgument::OPTIONAL, 'Path to the Laravel project', getcwd())
            ->addOption('fix', 'f', InputOption::VALUE_NONE, 'Attempt to apply automatic fixes when possible')
            ->addOption('show-locations', null, InputOption::VALUE_NONE, 'Show affected files and lines')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Custom config file path')
            ->addOption('report', 'r', InputOption::VALUE_REQUIRED, 'Save report to file (json or markdown)')
            ->addOption('ci', null, InputOption::VALUE_NONE, 'CI mode: exit with non-zero if score is below threshold (default 70)')
            ->addOption('min-score', null, InputOption::VALUE_REQUIRED, 'Minimum score for CI pass (default 70)', '70');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectPath = $input->getArgument('project');
        $projectPath = realpath($projectPath) ?: $projectPath;

        if (!is_dir($projectPath)) {
            $io->error("Project path is not a directory: {$projectPath}");
            return self::FAILURE;
        }

        $configPath = $input->getOption('config');
        $config = $this->configLoader->load($configPath, $projectPath);

        $startTime = microtime(true);
        $report = new Report($projectPath, $config);
        $this->analyzerRegistry->run($projectPath, $report, $config);
        $duration = round(microtime(true) - $startTime, 1);

        $verbose = (bool) $input->getOption('show-locations');
        $formatter = new ConsoleFormatter($output);
        $formatter->output($report, $verbose, $duration);

        if ($input->getOption('fix')) {
            $this->applySafeFixes($report, $projectPath, $output);
        }

        $reportPath = $input->getOption('report');
        if ($reportPath !== null) {
            $this->writeReport($report, $reportPath, $verbose, $output);
        }

        $ci = $input->getOption('ci');
        $minScore = (int) $input->getOption('min-score');
        if ($ci && $report->getScore() < $minScore) {
            $output->writeln("<fg=red>CI: Score {$report->getScore()} is below minimum {$minScore}</>");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function applySafeFixes(Report $report, string $projectPath, OutputInterface $output): void
    {
        $fixer = new SafeFixer($projectPath, $output);
        $count = $fixer->run($report);
        if ($count > 0) {
            $output->writeln('');
            $output->writeln('<fg=green>Aplicadas correções seguras em ' . $count . ' arquivo(s): declare(strict_types=1), tabs → espaços, trailing whitespace.</>');
        }
    }

    private function writeReport(Report $report, string $reportPath, bool $verbose, OutputInterface $output): void
    {
        $ext = strtolower(pathinfo($reportPath, PATHINFO_EXTENSION));
        $formatter = $ext === 'md' || $ext === 'markdown'
            ? new MarkdownReportFormatter()
            : new JsonReportFormatter();
        $content = $formatter->format($report, $verbose);
        if (file_put_contents($reportPath, $content) !== false) {
            $output->writeln("<info>Report saved to: {$reportPath}</info>");
        } else {
            $output->writeln("<error>Could not write report to: {$reportPath}</error>");
        }
    }
}
