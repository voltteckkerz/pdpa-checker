import os
import json
import anthropic
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
from fastapi.responses import FileResponse
from pydantic import BaseModel

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

client = anthropic.Anthropic()

MOCK_MODE = os.getenv("MOCK_MODE", "").lower() in ("1", "true", "yes")

def mock_analysis(tab: str) -> dict:
    subject = "data collection form" if tab == "form" else "privacy policy"
    return {
        "score": 64,
        "scoreLabel": "Needs improvement",
        "sections": [
            {"name": "General principle", "status": "warn", "note": "Purpose of collection not clearly stated"},
            {"name": "Notice & choice", "status": "fail", "note": "No explicit consent mechanism found"},
            {"name": "Disclosure", "status": "pass", "note": "Third-party sharing disclosed"},
            {"name": "Security", "status": "warn", "note": "No mention of security safeguards"},
            {"name": "Retention", "status": "fail", "note": "No retention period specified"},
            {"name": "Data integrity", "status": "pass", "note": "Accuracy obligation mentioned"},
            {"name": "Access rights", "status": "warn", "note": "Access request process unclear"},
        ],
        "findings": [
            {"type": "critical", "title": "Missing consent clause", "description": f"The {subject} does not obtain explicit consent before processing personal data, contrary to Section 6 of the PDPA."},
            {"type": "warning", "title": "No retention period", "description": "Section 10 requires personal data not be kept longer than necessary, but no retention period is stated."},
            {"type": "pass", "title": "Disclosure to third parties", "description": "Third-party data sharing is disclosed, satisfying part of Section 8."},
        ],
        "recommendations": [
            "Add an explicit consent checkbox or clause before data collection.",
            "State a clear data retention period and deletion process.",
            "Describe the security measures used to protect personal data.",
        ],
        "_mock": True,
    }

class AnalyseRequest(BaseModel):
    text: str
    tab: str = "policy"

@app.post("/analyse")
async def analyse(req: AnalyseRequest):
    if not req.text or len(req.text.strip()) < 20:
        raise HTTPException(400, "Text too short")

    if MOCK_MODE:
        return mock_analysis(req.tab)

    prompt = f"""You are a Malaysia PDPA (Personal Data Protection Act 2010) compliance expert. Analyse the following {('data collection form' if req.tab == 'form' else 'privacy policy')} and return ONLY a JSON object with this exact structure:

{{
  "score": <integer 0-100>,
  "scoreLabel": "<Excellent|Good|Needs improvement|Poor>",
  "sections": [
    {{"name": "General principle", "status": "pass|warn|fail", "note": "<10 words max>"}},
    {{"name": "Notice & choice", "status": "pass|warn|fail", "note": "<10 words max>"}},
    {{"name": "Disclosure", "status": "pass|warn|fail", "note": "<10 words max>"}},
    {{"name": "Security", "status": "pass|warn|fail", "note": "<10 words max>"}},
    {{"name": "Retention", "status": "pass|warn|fail", "note": "<10 words max>"}},
    {{"name": "Data integrity", "status": "pass|warn|fail", "note": "<10 words max>"}},
    {{"name": "Access rights", "status": "pass|warn|fail", "note": "<10 words max>"}}
  ],
  "findings": [
    {{"type": "critical|warning|pass", "title": "<short title>", "description": "<1-2 sentence explanation referencing PDPA section>"}}
  ],
  "recommendations": ["<actionable recommendation>", "<actionable recommendation>", "<actionable recommendation>"]
}}

Return ONLY the JSON. No markdown, no explanation. Text to analyse:

{req.text}"""

    try:
        message = client.messages.create(
            model="claude-sonnet-4-6",
            max_tokens=1000,
            messages=[{"role": "user", "content": prompt}]
        )
    except anthropic.APIStatusError as e:
        raise HTTPException(e.status_code, e.response.json().get("error", {}).get("message", str(e)))
    except (anthropic.APIError, TypeError) as e:
        raise HTTPException(502, str(e))

    raw = message.content[0].text
    clean = raw.replace("```json", "").replace("```", "").strip()
    try:
        return json.loads(clean)
    except json.JSONDecodeError:
        raise HTTPException(502, "Model returned malformed JSON")

@app.get("/")
async def root():
    return FileResponse(os.path.join(os.path.dirname(__file__), "index.html"))

