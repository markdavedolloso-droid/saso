<?php
// Temporary local diagnostic page for Gemini configuration.
// Delete this file after setup verification.

require_once __DIR__ . '/ai_config.php';

$apiKeyDetected = gemini_api_key() !== '';
$models = implode(', ', gemini_models());
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gemini Configuration Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 760px;
            margin: 40px auto;
            padding: 0 20px;
            color: #241a1d;
            line-height: 1.6;
        }
        .result {
            padding: 14px 18px;
            margin: 14px 0;
            border-radius: 8px;
            background: #f6f3f4;
            border: 1px solid #e8dfe1;
        }
        .yes { color: #217a4b; font-weight: 700; }
        .no { color: #b42318; font-weight: 700; }
        code { word-break: break-word; }
    </style>
</head>
<body>
    <h1>Gemini Configuration Test</h1>

    <div class="result">
        <strong>API key detected:</strong>
        <span class="<?= $apiKeyDetected ? 'yes' : 'no' ?>">
            <?= $apiKeyDetected ? 'YES' : 'NO' ?>
        </span>
    </div>

    <div class="result">
        <strong>Models:</strong>
        <code><?= htmlspecialchars($models, ENT_QUOTES, 'UTF-8') ?></code>
    </div>

    <p>The API key value is intentionally never displayed.</p>
</body>
</html>
