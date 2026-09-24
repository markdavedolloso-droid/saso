<?php
// Server-side OmniRoute configuration. Never expose this file or the API key to the browser.

// Minimal .env loader so OMNIROUTE_* values in the project-root .env are available via getenv().
if (!function_exists('omniroute_load_env')) {
    function omniroute_load_env(): void
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
            // .env is authoritative so edits take effect without an Apache restart.
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
omniroute_load_env();

const OMNIROUTE_DEFAULT_ENDPOINT = 'https://api.omniroute.ai/v1/chat/completions';
const OMNIROUTE_TIMEOUT_SECONDS = 30;

// On Windows Apache (apache2handler), getenv() keeps returning the startup
// SetEnv value even after putenv(), while $_SERVER/$_ENV reflect updates.
// Read $_SERVER/$_ENV first so .env edits take effect without a restart.
function omniroute_env(string $name): string
{
    foreach ([$_SERVER[$name] ?? null, $_ENV[$name] ?? null, getenv($name)] as $value) {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }
    return '';
}

function omniroute_api_key(): string
{
    return omniroute_env('OMNIROUTE_API_KEY');
}

function omniroute_endpoint(): string
{
    $endpoint = omniroute_env('OMNIROUTE_API_ENDPOINT');
    return $endpoint !== '' ? $endpoint : OMNIROUTE_DEFAULT_ENDPOINT;
}

function omniroute_models(): array
{
    $configured = omniroute_env('OMNIROUTE_MODELS');
    if ($configured === '') {
        $configured = omniroute_env('OMNIROUTE_MODEL');
    }

    if (!is_string($configured) || trim($configured) === '') {
        return [];
    }

    $models = array_map('trim', explode(',', $configured));
    $models = array_filter($models, static fn($model) => $model !== '');

    return array_values(array_unique($models));
}
