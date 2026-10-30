<?php
/**
 * resume-agent-proxy.php
 * Standalone Anthropic API proxy for embeddable resume Q&A widget.
 * Drop on any PHP-enabled host. Set ANTHROPIC_API_KEY below or via env.
 */

// ---- CORS (allow embed on any domain) ----
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

// ---- CONFIG ----
$ANTHROPIC_API_KEY = getenv('ANTHROPIC_API_KEY') ?: 'sk-ant-REPLACE_ME';
$MODEL             = 'claude-haiku-4-5-20251001'; // fast + cheap, ideal for resume Q&A
$MAX_TOKENS        = 1024;
$CANDIDATE_NAME    = 'Kristian Micah Oubre';

// ---- RESUME (PASTE FULL RESUME TEXT BETWEEN THE MARKERS) ----
$RESUME = <<<'RESUME'
[PASTE FULL RESUME HERE — plain text, no markdown needed.
Include: contact info you want public, summary, experience with dates,
skills, projects, education, certifications, anything you want the agent
to be able to answer about.]
RESUME;

// ---- SYSTEM PROMPT ----
$SYSTEM = <<<SYS
You are a professional resume assistant answering questions about {$CANDIDATE_NAME}.

You have access to {$CANDIDATE_NAME}'s full resume below. Your job:
- Answer questions accurately and ONLY based on what is in the resume.
- If a question asks for information not present in the resume, say clearly that the resume doesn't cover it and offer what is in there that's adjacent.
- Be concise. Professional but warm. No filler.
- Never fabricate experience, dates, employers, certifications, or skills.
- If asked for contact info, share only what is explicitly in the resume.
- Decline politely to discuss topics outside {$CANDIDATE_NAME}'s professional background.
- Refer to {$CANDIDATE_NAME} by first name on second mention.

RESUME:
---
{$RESUME}
---
SYS;

// ---- INPUT ----
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input) || empty($input['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'messages array required']);
    exit;
}

// Cap history depth to prevent abuse / runaway costs
$messages = array_slice($input['messages'], -20);

// Sanity-check shape
foreach ($messages as $m) {
    if (!isset($m['role'], $m['content']) || !in_array($m['role'], ['user', 'assistant'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid message shape']);
        exit;
    }
}

// ---- CALL ANTHROPIC ----
$payload = [
    'model'      => $MODEL,
    'max_tokens' => $MAX_TOKENS,
    'system'     => $SYSTEM,
    'messages'   => $messages,
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err      = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'upstream error', 'detail' => $err]);
    exit;
}

http_response_code($status);
echo $response;
