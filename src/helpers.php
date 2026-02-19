<?php

declare(strict_types=1);

if (!function_exists('laravel_doctor_path')) {
    function laravel_doctor_path(string $path = ''): string
    {
        $base = dirname(__DIR__);
        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
}
