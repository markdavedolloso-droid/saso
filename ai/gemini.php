<?php
// Small server-side Gemini client used only by authorized analytics requests.

require_once __DIR__ . '/ai_config.php';

function askGemini(string $prompt): array
{
    $apiKey = gemini_api_key();
    if ($apiKey === '') {
        return [
            'ok' => false,
            'error' => 'AI analysis is not configured. Set GEMINI_API_KEY in the project .env file.'
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'error' => 'AI analysis is unavailable because PHP cURL is not enabled.'
        ];
    }

    $lastHttpCode = 0;
    $sawNetworkError = false;
    $receivedTextlessResponse = false;

    foreach (gemini_models() as $model) {
        $payload = json_encode([
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 1200
            ]
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            return [
                'ok' => false,
                'error' => 'AI analysis could not prepare the request.'
            ];
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'x-goog-api-key: ' . $apiKey
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => GEMINI_TIMEOUT_SECONDS,
        ]);

        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($response === false || $curlError !== '' || $httpCode === 0) {
            $sawNetworkError = true;
            continue;
        }

        $lastHttpCode = $httpCode;
        $decoded = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            $parts = $decoded['candidates'][0]['content']['parts'] ?? [];
            $text = implode("\n", array_filter(array_column($parts, 'text'), 'is_string'));
            if (trim($text) !== '') {
                return [
                    'ok' => true,
                    'text' => trim($text),
                    'model' => $model
                ];
            }

            $receivedTextlessResponse = true;
            continue;
        }

        if ($httpCode === 401 || $httpCode === 403) {
            return [
                'ok' => false,
                'error' => 'Gemini rejected the API key (HTTP ' . $httpCode . '). Check GEMINI_API_KEY.'
            ];
        }

        $retryable = $httpCode === 404 || $httpCode === 408 || $httpCode === 409 || $httpCode === 429 || $httpCode >= 500;
        if (!$retryable) {
            return [
                'ok' => false,
                'error' => 'Gemini could not process the request (HTTP ' . $httpCode . '). Check the prompt and model access.'
            ];
        }
    }

    if ($lastHttpCode === 429) {
        return [
            'ok' => false,
            'error' => 'Gemini rate limit or quota reached (HTTP 429). Try again later or review your Google AI Studio limits.'
        ];
    }
    if ($lastHttpCode === 404) {
        return [
            'ok' => false,
            'error' => 'None of the configured Gemini models were found (HTTP 404). Check GEMINI_MODELS.'
        ];
    }
    if ($lastHttpCode >= 500 || $lastHttpCode === 408 || $lastHttpCode === 409) {
        return [
            'ok' => false,
            'error' => 'Gemini models are temporarily unavailable. Please try again later.'
        ];
    }
    if ($sawNetworkError && $lastHttpCode === 0) {
        return [
            'ok' => false,
            'error' => 'Gemini could not be reached. Check the internet connection and try again.'
        ];
    }
    if ($receivedTextlessResponse) {
        return [
            'ok' => false,
            'error' => 'Gemini returned no text. The prompt may have been blocked or the response was empty.'
        ];
    }

    return [
        'ok' => false,
        'error' => 'AI analysis could not be completed. Check the configured Gemini models and try again.'
    ];
}
