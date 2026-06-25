# PDPA Compliance Checker

A small web app that analyses privacy policies and data collection forms against Malaysia's Personal Data Protection Act 2010 (PDPA), using Gemini.

Paste in a privacy policy or data collection form — or drag & drop it as a PDF — and get back a compliance score, a breakdown by PDPA principle (notice & choice, disclosure, security, retention, data integrity, access rights), specific findings, and actionable recommendations.

## Features

- Two input modes: paste text directly, or drag & drop (or click to browse) a PDF
- AI-generated compliance score with a visual ring, plus a clickable "bubble" breakdown per PDPA principle — click a principle to see its detail in a floating popover
- Findings and recommendations shown side by side, with a disclaimer footer
- Mock mode for trying the app without burning API credits

## Stack

- **Backend:** Laravel, calling the Gemini API directly over HTTP
- **PDF text extraction:** [smalot/pdfparser](https://github.com/smalot/pdfparser)
- **Frontend:** single static `public/index.html` (no build step, no JS framework)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Set your Gemini API key (get a free one at [aistudio.google.com/apikey](https://aistudio.google.com/apikey)) in `.env`:

```
GEMINI_API_KEY=...
```

> **Windows + Laravel Herd users:** if `composer` isn't on your `PATH`, run it via `php "C:\Users\<you>\.config\herd-lite\bin\composer.phar"` instead.

## Run

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Then open http://localhost:8000.

## Run with Docker

```bash
docker build -t pdpa-checker .
docker run -p 8000:8000 -e GEMINI_API_KEY="..." pdpa-checker
```

## Mock mode

To try the app without an API key, set `MOCK_MODE=true` in `.env` (or pass it as an env var). The `/analyse` endpoint will return a fixed, schema-correct sample result instead of calling the Gemini API:

```bash
MOCK_MODE=true php artisan serve --host=0.0.0.0 --port=8000
```
