<?php
// Server-side Gemini configuration. Never expose the API key to the browser.

if (!function_exists('gemini_load_env')) {
    function gemini_load_env(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $envFile = dirname(__DIR__) . '/.env';
        if (!is_readable($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if ($name === '') {
                continue;
            }
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
gemini_load_env();

const GEMINI_DEFAULT_MODEL = 'gemini-3.8-flash';
const GEMINI_TIMEOUT_SECONDS = 30;

function gemini_env(string $name): string
{
    foreach ([$_SERVER[$name] ?? null, $_ENV[$name] ?? null, getenv($name)] as $value) {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }
    return '';
}

function gemini_api_key(): string
{
    return gemini_env('GEMINI_API_KEY');
}

function gemini_models(): array
{
    $configured = gemini_env('GEMINI_MODELS');
    if ($configured === '') {
        $configured = gemini_env('GEMINI_MODEL') ?: GEMINI_DEFAULT_MODEL;
    }

    $models = array_map('trim', explode(',', $configured));
    $models = array_filter($models, static fn($model) => $model !== '');

    return array_values(array_unique($models));
}

function gemini_model(): string
{
    return gemini_models()[0] ?? GEMINI_DEFAULT_MODEL;
}
