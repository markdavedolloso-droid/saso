# AI-Assisted Analytics

## Provider and model

The system uses OmniRoute's OpenAI-compatible chat completions API. Models are configured as an ordered fallback list through `OMNIROUTE_MODELS`. OmniRoute is used as an interpretation layer over statistics calculated by PHP and MySQL.

## Purpose

The feature summarizes disciplinary patterns and submitted teacher evaluation feedback for authorized SASO personnel. It is a decision-support tool only. Human SASO personnel remain responsible for all administrative, disciplinary, and employment decisions.

## Data flow

MySQL records are aggregated by PHP into counts by violation type, status, category, and month. Evaluation scores are summarized and submitted comments are limited, de-identified, and redacted for emails and phone numbers. The prepared data is sent server-side to OmniRoute only when an administrator selects **Generate AI Insights** or submits **Ask AI**.

The AI never receives student names, student numbers, profile emails, phone numbers, passwords, or database credentials. It cannot query SQL or modify the database.

## Configuration

Set the server environment variable before using the AI feature:

```text
OMNIROUTE_API_KEY=your-server-side-key
OMNIROUTE_API_ENDPOINT=https://api.omniroute.ai/v1/chat/completions
OMNIROUTE_MODELS=provider/model-one,provider/model-two,provider/model-three
```

On Windows/XAMPP, configure these variables in the Apache/PHP environment or start Apache from an environment where they are available. Models are tried from left to right. A rate-limit, quota, timeout, or temporary server error moves to the next model automatically. Do not place the key in PHP page markup, JavaScript, CSS, or a committed file.

The existing analytics page continues to work if the variable is missing or OmniRoute is unavailable; it displays a safe configuration or availability message while retaining local statistics.

## Files

- `ai/ai_config.php`: model, endpoint, timeout, and environment lookup.
- `ai/gemini.php`: server-side OmniRoute cURL client and safe error handling.
- `ai/analytics_ai.php`: aggregated data preparation and constrained prompts.
- `analytics.php`: existing dashboard integration, admin-only actions, and escaped output.

## Testing

1. Open `analytics.php` as an admin with `OMNIROUTE_API_KEY` unset. Confirm local analytics remain visible and no key is displayed.
2. Set a valid key and click **Generate AI Insights**.
3. Submit a normal question through **Ask AI**.
4. Test an empty or over-500-character question; it must be rejected.
5. Test a question containing HTML or SQL text; it must be treated as plain text and must not execute or run SQL.
6. Temporarily make OmniRoute unavailable and confirm the page shows an availability message while local analytics still render.
7. Access `analytics.php` as a non-admin; the existing authorization must redirect the user.

## Limitations

OmniRoute output may be incomplete or mistaken and must be checked against the source records. Small datasets may not support reliable trends. The system deliberately avoids rankings, punishments, sanctions, employment recommendations, and final decisions.