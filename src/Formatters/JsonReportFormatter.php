<?php

declare(strict_types=1);

namespace LaravelDoctor\Formatters;

use LaravelDoctor\Report;

final class JsonReportFormatter implements FormatterInterface
{
    public function format(Report $report, bool $verbose): string
    {
        $data = $report->toArray();
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
