# Laravel Doctor

CLI no estilo “agent” que analisa projetos **Laravel** e gera um relatório com diagnóstico sobre arquitetura, qualidade, segurança, documentação e DX (Developer Experience), com sugestões de correção e correções automáticas quando possível.

Inspirado no conceito do React Doctor, adaptado para o ecossistema Laravel/PHP.

---

## Instalação

### Via Composer (projeto existente)

```bash
cd /caminho/do/seu/projeto/laravel
composer require --dev lucas-baggio/laravel-doctor
./vendor/bin/laravel-doctor .
```

### Clone / desenvolvimento

```bash
git clone https://github.com/lucas-baggio/laravel-doctor.git
cd laravel-doctor
composer install
./laravel-doctor /caminho/para/projeto/laravel
```

### Global (opcional)

```bash
composer global require lucas-baggio/laravel-doctor
# Garanta que ~/.composer/vendor/bin está no PATH
laravel-doctor /caminho/para/projeto
```

---

## Uso básico

```bash
# Analisar o diretório atual
laravel-doctor

# Analisar um projeto específico
laravel-doctor /caminho/para/projeto

# Com opções
laravel-doctor /caminho/para/projeto --show-locations
laravel-doctor /caminho/para/projeto --fix
laravel-doctor /caminho/para/projeto --config laravel-doctor.config.php
laravel-doctor /caminho/para/projeto --report report.json
laravel-doctor /caminho/para/projeto --report report.md
laravel-doctor /caminho/para/projeto --ci --min-score 70
```

### Opções CLI

| Opção | Descrição |
|-------|-----------|
| `--fix`, `-f` | Tenta aplicar correções automáticas quando possível (ex.: indicações para strict_types, tabs) |
| `--show-locations` | Mostra arquivos e linhas afetadas em cada diagnóstico |
| `--config <arquivo>`, `-c` | Carrega configurações customizadas (PHP, JSON ou YAML) |
| `--report <formato>` | Salva relatório em arquivo (ex.: `report.json`, `report.md`) |
| `--ci` | Modo CI: termina com código de saída não-zero se a pontuação estiver abaixo do mínimo |
| `--min-score <n>` | Pontuação mínima para o modo `--ci` (padrão: 70) |

---

## Exemplo de saída

```
Laravel Doctor v0.1
Score: 72 / 100 Warning

  ████████████████░░░░░░░░

42 diagnostics across 4 categories in 1.2s

▸ Security
  ✗ Rotas de escrita em api.php: garanta autenticação (sanctum/session)
  ⚠ Model Post sem Policy ou Gate de autorização
▸ Quality
  ⚠ Controller UserController – método store sem docblock
▸ Testability
  ℹ 8 arquivo(s) de teste encontrado(s) em tests/
▸ Environment
  ⚠ Variável recomendada ausente: CACHE_DRIVER
```

---

## Categorias de análise

| Categoria | O que é verificado |
|-----------|---------------------|
| **environment** | `.env` presente, `APP_KEY`, `APP_ENV`, `APP_DEBUG`, `CACHE_DRIVER`, `QUEUE_CONNECTION`, etc. |
| **quality** | Conformidade PSR-12 (strict_types em `app/`, tabs, trailing space), controllers com type-hint e docblocks |
| **documentation** (Testability) | Rotas com testes, existência de `tests/`, `phpunit.xml` |
| **security** | CSRF em rotas web, Policies/Gates para models, uso de `DB::raw`/`whereRaw`, XSS em Blade, valores hardcoded |
| **performance** | (reservado para regras futuras) |

Cada diagnóstico tem:

- **Categoria**: `security`, `quality`, `documentation` (Testability), `environment`
- **Severidade**: `error` \| `warning` \| `info`
- **Mensagem** e **recomendação** de correção

### Pontuação (0–100)

- **Tetos por categoria:** nenhuma categoria tira mais que seu peso: Security 40, Quality 30, Testability 20, Environment 10.
- **Bônus:** +10 se `tests/` tiver mais de 5 arquivos PHP; +5 se existir `composer.lock`.
- O score final é limitado entre 0 e 100. Cores no terminal: vermelho (&lt;50), amarelo (50–80), verde (&gt;80).

---

## Configuração

Use um arquivo de config para ativar/desativar analisadores, ignorar paths e ajustar pesos.

Exemplo **laravel-doctor.config.php** (no seu projeto Laravel):

```php
<?php
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
```

Se você colocar `laravel-doctor.config.php` (ou `.json` / `.yaml`) na **raiz do projeto analisado**, o Doctor carrega automaticamente. Para usar outro arquivo ou caminho:

```bash
laravel-doctor . --config /caminho/outro.config.php
```

Suporte a **JSON** e **YAML**: use `--config config.json` ou `--config config.yaml` com a mesma estrutura (chaves em camelCase ou snake_case conforme o formato).

---

## Regras customizadas (plugins)

1. Implemente a interface do analisador:

```php
<?php
namespace MeuApp\Analyzers;

use LaravelDoctor\Analyzers\AnalyzerInterface;
use LaravelDoctor\Report;

class MinhaRegraAnalyzer implements AnalyzerInterface
{
    public function analyze(string $projectPath, Report $report, array $config): void
    {
        // Sua lógica: $report->add(new Diagnostic(...));
    }
}
```

2. Registre no **config** (se o Doctor carregar analisadores por nome de classe):

```php
'custom_analyzers' => [
    'minha_regra' => \MeuApp\Analyzers\MinhaRegraAnalyzer::class,
],
```

3. Ou estenda o **AnalyzerRegistry** no bootstrap da CLI e chame `$registry->register('minha_regra', new MinhaRegraAnalyzer())`.

Assim você pode adicionar regras específicas do seu time (convenções de nome, módulos obrigatórios, etc.).

---

## Integração CI (GitHub Actions)

Exemplo mínimo no repositório do **projeto Laravel**:

```yaml
# .github/workflows/laravel-doctor.yml
name: Laravel Doctor
on: [push, pull_request]
jobs:
  doctor:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - name: Install dependencies
        run: composer install --no-interaction
      - name: Install Laravel Doctor
        run: composer require --dev lucas-baggio/laravel-doctor
      - name: Run Laravel Doctor
        run: ./vendor/bin/laravel-doctor . --ci --min-score 70 --report report.json
```

A flag **`--ci`** faz o comando falhar (exit code não-zero) se a pontuação estiver abaixo de `--min-score` (padrão 70). Você pode anexar `report.json` como artifact para inspeção.

---

## Estrutura do projeto (monorepo)

```
laravel-doctor/
  src/
    Commands/       # Comando Symfony Console
    Analyzers/      # Analisadores (environment, PSR-12, security, etc.)
    Formatters/     # Saída console, JSON, Markdown
    Config/         # Carregamento de config
  tests/            # PHPUnit
  composer.json
  laravel-doctor    # Binário CLI
  laravel-doctor.config.php  # Exemplo de config
  README.md
  LICENSE
```

---

## Requisitos

- PHP 8.1+
- Extensão `json`
- Projeto alvo: Laravel (estrutura esperada: `app/`, `config/`, `routes/`, `tests/`, etc.)

---

## Licença

MIT.
