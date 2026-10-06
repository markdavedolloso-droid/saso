# AI-Assisted Analytics

## Provider and model

The system uses Google's Gemini `generateContent` API. Models are attempted in the order configured by `GEMINI_MODELS`; temporary overload or quota errors move to the next model.

## Purpose

The feature summarizes aggregate discipline, paper Think Sheet repository, and Good Moral request patterns for authorized SASO personnel. It is a decision-support tool only. Human SASO personnel remain responsible for administrative and disciplinary decisions.

## Data flow

MySQL records are aggregated by PHP into discipline counts by department, type, status, category, and month; Think Sheet repository totals and filed/reviewed counts; and Good Moral request totals by status and month. The prepared data is sent server-side to Gemini only when an administrator selects **Generate AI Insights** or submits **Ask AI**.

The AI never receives student names, student numbers, profile emails, phone numbers, passwords, or database credentials. It cannot query SQL or modify the database.

## Configuration

The project-root `.env` file is ignored by Git. Add the key there:

```text
GEMINI_API_KEY=your-server-side-key
GEMINI_MODELS=gemini-3.8-flash,gemini-3.7-flash,gemini-3.5-flash-lite
```

The key is loaded server-side by PHP and sent to Google's API in the `x-goog-api-key` header. Do not put the key in PHP page markup, JavaScript, CSS, or any committed file. `GEMINI_MODELS` is optional; when omitted, the client uses `gemini-3.8-flash`.

The analytics page continues to show local statistics if the key is missing or Gemini is unavailable; it displays a configuration or availability message for AI actions.

## Files

- `ai/ai_config.php`: model fallback list, timeout, and `.env` lookup.
- `ai/gemini.php`: server-side Gemini cURL client and safe error handling.
- `ai/analytics_ai.php`: aggregated data preparation and constrained prompts.
- `analytics.php`: dashboard integration, admin-only actions, and escaped output.

## Testing

1. Open `analytics.php` as an admin with `GEMINI_API_KEY` unset. Confirm local analytics remain visible and no key is displayed.
2. Set a valid key and click **Generate AI Insights**.
3. Submit a normal question through **Ask AI**.
4. Test an empty or over-500-character question; it must be rejected.
5. Test a question containing HTML or SQL text; it must be treated as plain text and must not execute or run SQL.
6. Temporarily make Gemini unavailable and confirm the page shows an availability message while local statistics still render.
7. Access `analytics.php` as a non-admin; the existing authorization must redirect the user.

## Limitations

Gemini output may be incomplete or mistaken and must be checked against the source records. Small datasets may not support reliable trends. The system deliberately avoids rankings, punishments, sanctions, employment recommendations, and final decisions.