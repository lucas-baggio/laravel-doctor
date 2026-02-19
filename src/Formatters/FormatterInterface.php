<?php

declare(strict_types=1);

namespace LaravelDoctor\Formatters;

use LaravelDoctor\Report;

interface FormatterInterface
{
    public function format(Report $report, bool $verbose): string;
}
