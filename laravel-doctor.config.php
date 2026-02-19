<?php

declare(strict_types=1);

/**
 * Laravel Doctor - Configuração customizada
 * Copie para seu projeto e ajuste conforme necessário.
 *
 * Suporte a extensões (skills/rules): adicione analisadores customizados
 * registrando-os no container ou via arquivo de config (ex: analyzers.custom = class name).
 */
return [
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
        // 'custom' => true,  // descomente ao registrar analisador customizado
    ],

    'ignore_paths' => [
        'vendor/',
        'node_modules/',
        'storage/',
        'bootstrap/cache/',
        '*.min.js',
    ],

    'severity_weights' => [
        'error' => 10,
        'warning' => 5,
        'info' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Regras customizadas (plugins)
    |--------------------------------------------------------------------------
    | Implemente LaravelDoctor\Analyzers\AnalyzerInterface e registre aqui
    | ou use o AnalyzerRegistry no código da aplicação.
    */
    // 'custom_analyzers' => [
    //     'my_rule' => \MyApp\Analyzers\MyRuleAnalyzer::class,
    // ],
];
