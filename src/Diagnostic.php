<?php

declare(strict_types=1);

namespace LaravelDoctor;

final class Diagnostic
{
    public function __construct(
        public readonly string $category,
        public readonly string $severity,
        public readonly string $message,
        public readonly string $recommendation,
        public readonly ?string $file = null,
        public readonly ?int $line = null,
        public readonly bool $autoFixable = false,
        public readonly ?string $fixHint = null,
        /** @readonly Tipo de fix seguro: strict_types | tabs | trailing_whitespace */
        public readonly ?string $fixType = null,
    ) {}

    public function toArray(): array
    {
        return [
            'category' => $this->category,
            'severity' => $this->severity,
            'message' => $this->message,
            'recommendation' => $this->recommendation,
            'file' => $this->file,
            'line' => $this->line,
            'autoFixable' => $this->autoFixable,
            'fixHint' => $this->fixHint,
            'fixType' => $this->fixType,
        ];
    }
}
