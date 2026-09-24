<?php
// Small server-side OmniRoute client used only by authorized analytics requests.

require_once __DIR__ . '/ai_config.php';

function askOmniRoute(string $prompt): array
{
    if (omniroute_api_key() === '') {
        return [
            'ok' => false,
            'error' => 'AI analysis is not configured. Set OMNIROUTE_API_KEY on the server.'
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'error' => 'AI analysis is unavailable because PHP cURL is not enabled.'
        ];
    }

    $models = omniroute_models();
    if (!$models) {
        return [
            'ok' => false,
            'error' => 'AI analysis is not configured. Set OMNIROUTE_MODELS on the server.'
        ];
    }

    $lastHttpCode = 0;
    $sawNetworkError = false;

    foreach ($models as $model) {
        $payload = json_encode([
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.2,
            'max_tokens' => 1200
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            return [
                'ok' => false,
                'error' => 'AI analysis could not prepare the request.'
            ];
        }

        $curl = curl_init(omniroute_endpoint());
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . omniroute_api_key()
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => OMNIROUTE_TIMEOUT_SECONDS,
        ]);

        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        // Network/DNS failure: cURL error or no HTTP response. Try next model,
        // but remember it so we can report an endpoint problem afterwards.
        if ($response === false || $curlError !== '' || $httpCode === 0) {
            $sawNetworkError = true;
            continue;
        }

        $lastHttpCode = $httpCode;

        if ($response !== false && $curlError === '' && $httpCode >= 200 && $httpCode < 300) {
            $decoded = json_decode($response, true);
            $text = $decoded['choices'][0]['message']['content'] ?? '';

            if (is_string($text) && trim($text) !== '') {
                return [
                    'ok' => true,
                    'text' => trim($text),
                    'model' => $model
                ];
            }

            continue;
        }

        // Quota/rate-limit and temporary server failures try the next model.
        $retryable = $httpCode === 408 || $httpCode === 409 || $httpCode === 429 || $httpCode >= 500;
        if (!$retryable) {
            if ($httpCode === 401 || $httpCode === 403) {
                return [
                    'ok' => false,
                    'error' => 'AI analysis rejected the API key (HTTP ' . $httpCode . '). Check OMNIROUTE_API_KEY.'
                ];
            }
            if ($httpCode === 404) {
                return [
                    'ok' => false,
                    'error' => 'AI endpoint was not found (HTTP 404 at ' . omniroute_endpoint() . '). Check OMNIROUTE_API_ENDPOINT.'
                ];
            }
            if ($httpCode === 400) {
                return [
                    'ok' => false,
                    'error' => 'AI request was rejected for model "' . $model . '" (HTTP 400). Check OMNIROUTE_MODELS against GET /v1/models on your OmniRoute server.'
                ];
            }
            return [
                'ok' => false,
                'error' => 'AI analysis could not process the request (HTTP ' . $httpCode . ' for model "' . $model . '").'
            ];
        }
    }

    if ($sawNetworkError && $lastHttpCode === 0) {
        return [
            'ok' => false,
            'error' => 'AI server could not be reached at ' . omniroute_endpoint() . '. Check OMNIROUTE_API_ENDPOINT (this host does not resolve) and that your OmniRoute server is running.'
        ];
    }

    return [
        'ok' => false,
        'error' => 'All configured AI models are temporarily unavailable. Please try again later.'
    ];
}
