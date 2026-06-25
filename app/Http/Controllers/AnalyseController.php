<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

class AnalyseController extends Controller
{
    public function analyse(Request $request)
    {
        $data = $request->validate([
            'text' => 'required|string',
            'tab' => 'nullable|string|in:policy,form',
        ]);

        $tab = $data['tab'] ?? 'policy';
        $text = trim($data['text']);

        if (strlen($text) < 20) {
            return response()->json(['detail' => 'Text too short'], 400);
        }

        if (filter_var(env('MOCK_MODE', false), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json($this->mockAnalysis($tab));
        }

        $apiKey = env('GEMINI_API_KEY');
        if (! $apiKey) {
            return response()->json(['detail' => 'GEMINI_API_KEY is not set'], 500);
        }

        $subject = $tab === 'form' ? 'data collection form' : 'privacy policy';
        $prompt = <<<PROMPT
        You are a Malaysia PDPA (Personal Data Protection Act 2010) compliance expert. Analyse the following {$subject} and return ONLY a JSON object with this exact structure:

        {
          "score": <integer 0-100>,
          "scoreLabel": "<Excellent|Good|Needs improvement|Poor>",
          "sections": [
            {"name": "General principle", "status": "pass|warn|fail", "note": "<10 words max>"},
            {"name": "Notice & choice", "status": "pass|warn|fail", "note": "<10 words max>"},
            {"name": "Disclosure", "status": "pass|warn|fail", "note": "<10 words max>"},
            {"name": "Security", "status": "pass|warn|fail", "note": "<10 words max>"},
            {"name": "Retention", "status": "pass|warn|fail", "note": "<10 words max>"},
            {"name": "Data integrity", "status": "pass|warn|fail", "note": "<10 words max>"},
            {"name": "Access rights", "status": "pass|warn|fail", "note": "<10 words max>"}
          ],
          "findings": [
            {"type": "critical|warning|pass", "title": "<short title>", "description": "<1-2 sentence explanation referencing PDPA section>"}
          ],
          "recommendations": ["<actionable recommendation>", "<actionable recommendation>", "<actionable recommendation>"]
        }

        Return ONLY the JSON. No markdown, no explanation. Text to analyse:

        {$text}
        PROMPT;

        try {
            $response = Http::post(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key='.$apiKey,
                ['contents' => [['parts' => [['text' => $prompt]]]]]
            );
        } catch (Throwable $e) {
            return response()->json(['detail' => $e->getMessage()], 502);
        }

        if ($response->failed()) {
            $message = $response->json('error.message') ?? $response->body();
            return response()->json(['detail' => $message], $response->status());
        }

        $raw = $response->json('candidates.0.content.parts.0.text');
        $clean = trim(str_replace(['```json', '```'], '', $raw ?? ''));

        $parsed = json_decode($clean, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['detail' => 'Model returned malformed JSON'], 502);
        }

        return response()->json($parsed);
    }

    public function extractPdf(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf',
        ]);

        try {
            $pdf = (new PdfParser())->parseFile($request->file('file')->getPathname());
            $text = trim($pdf->getText());
        } catch (Throwable $e) {
            return response()->json(['detail' => 'Could not read PDF file'], 400);
        }

        if ($text === '') {
            return response()->json(['detail' => 'No extractable text found in this PDF (it may be a scanned image)'], 400);
        }

        return response()->json(['text' => $text]);
    }

    private function mockAnalysis(string $tab): array
    {
        $subject = $tab === 'form' ? 'data collection form' : 'privacy policy';

        return [
            'score' => 64,
            'scoreLabel' => 'Needs improvement',
            'sections' => [
                ['name' => 'General principle', 'status' => 'warn', 'note' => 'Purpose of collection not clearly stated'],
                ['name' => 'Notice & choice', 'status' => 'fail', 'note' => 'No explicit consent mechanism found'],
                ['name' => 'Disclosure', 'status' => 'pass', 'note' => 'Third-party sharing disclosed'],
                ['name' => 'Security', 'status' => 'warn', 'note' => 'No mention of security safeguards'],
                ['name' => 'Retention', 'status' => 'fail', 'note' => 'No retention period specified'],
                ['name' => 'Data integrity', 'status' => 'pass', 'note' => 'Accuracy obligation mentioned'],
                ['name' => 'Access rights', 'status' => 'warn', 'note' => 'Access request process unclear'],
            ],
            'findings' => [
                ['type' => 'critical', 'title' => 'Missing consent clause', 'description' => "The {$subject} does not obtain explicit consent before processing personal data, contrary to Section 6 of the PDPA."],
                ['type' => 'warning', 'title' => 'No retention period', 'description' => 'Section 10 requires personal data not be kept longer than necessary, but no retention period is stated.'],
                ['type' => 'pass', 'title' => 'Disclosure to third parties', 'description' => 'Third-party data sharing is disclosed, satisfying part of Section 8.'],
            ],
            'recommendations' => [
                'Add an explicit consent checkbox or clause before data collection.',
                'State a clear data retention period and deletion process.',
                'Describe the security measures used to protect personal data.',
            ],
            '_mock' => true,
        ];
    }
}
