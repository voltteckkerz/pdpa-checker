# PDPA Compliance Checker

A small web app that analyses privacy policies and data collection forms against Malaysia's Personal Data Protection Act 2010 (PDPA), using Claude.

Paste in a privacy policy or data collection form and get back a compliance score, a breakdown by PDPA principle (notice & choice, disclosure, security, retention, data integrity, access rights), specific findings, and actionable recommendations.

## Stack

- **Backend:** FastAPI + the [Anthropic Python SDK](https://github.com/anthropics/anthropic-sdk-python)
- **Frontend:** single static `index.html` (no build step)

## Setup

```bash
pip install -r requirements.txt
```

Set your API key (get one at [console.anthropic.com](https://console.anthropic.com/settings/keys)):

```bash
export ANTHROPIC_API_KEY="sk-ant-..."
```

On Windows (PowerShell):

```powershell
$env:ANTHROPIC_API_KEY = "sk-ant-..."
```

## Run

```bash
./run.sh
```

or directly:

```bash
uvicorn main:app --host 0.0.0.0 --port 8000 --reload
```

Then open http://localhost:8000.

## Run with Docker

```bash
docker build -t pdpa-checker .
docker run -p 8000:8000 -e ANTHROPIC_API_KEY="sk-ant-..." pdpa-checker
```

## Mock mode

To try the app without an API key or API credits, run with `MOCK_MODE=true`. The `/analyse` endpoint will return a fixed, schema-correct sample result instead of calling the Anthropic API:

```bash
MOCK_MODE=true uvicorn main:app --host 0.0.0.0 --port 8000 --reload
```
