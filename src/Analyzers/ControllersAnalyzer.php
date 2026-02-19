<?php

declare(strict_types=1);

namespace LaravelDoctor\Analyzers;

use LaravelDoctor\Diagnostic;
use LaravelDoctor\Report;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

final class ControllersAnalyzer implements AnalyzerInterface
{
    public function analyze(string $projectPath, Report $report, array $config): void
    {
        $appPath = $projectPath . '/app/Http/Controllers';
        if (!is_dir($appPath)) {
            $report->add(new Diagnostic(
                'quality',
                'info',
                'Diretório app/Http/Controllers não encontrado',
                'Nenhum controller para analisar.',
                null,
                null,
                false
            ));
            return;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $finder = new Finder();
        $finder->files()->in($appPath)->name('*.php');

        foreach ($finder as $file) {
            $content = @file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }
            $relative = str_replace($projectPath . '/', '', $file->getPathname());
            try {
                $ast = $parser->parse($content);
            } catch (\Throwable) {
                continue;
            }
            $visitor = new class($report, $relative, $content) extends NodeVisitorAbstract {
                public function __construct(
                    private Report $report,
                    private string $relativeFile,
                    private string $content,
                ) {}

                public function enterNode(Node $node): void
                {
                    if (!$node instanceof Node\Stmt\ClassMethod) {
                        return;
                    }
                    if ($node->name->toString() === '__construct') {
                        return;
                    }
                    $hasDocblock = $node->getDocComment() !== null;
                    $params = $node->params;
                    $missingTypeHint = [];
                    foreach ($params as $param) {
                        if ($param->type === null && !$param->var instanceof Node\Expr\Error) {
                            $missingTypeHint[] = $param->var instanceof Node\Expr\Variable
                                ? '$' . $param->var->name
                                : 'parameter';
                        }
                    }
                    $returnType = $node->getReturnType();
                    $missingReturnType = $returnType === null;

                    if (!$hasDocblock || $missingTypeHint !== [] || $missingReturnType) {
                        $msgs = [];
                        if (!$hasDocblock) {
                            $msgs[] = 'sem docblock';
                        }
                        if ($missingTypeHint !== []) {
                            $msgs[] = 'parâmetros sem type-hint: ' . implode(', ', $missingTypeHint);
                        }
                        if ($missingReturnType) {
                            $msgs[] = 'sem type-hint de retorno';
                        }
                        $this->report->add(new Diagnostic(
                            'quality',
                            'warning',
                            'Controller ' . $this->relativeFile . ' – método ' . $node->name->toString() . ' ' . implode('; ', $msgs),
                            'Adicione docblocks e type-hints (Request, int, JsonResponse, etc.) nos métodos do controller.',
                            $this->relativeFile,
                            $node->getStartLine(),
                            false
                        ));
                    }
                }
            };
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast ?? []);
        }
    }
}
