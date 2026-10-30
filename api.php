<?php
/**
 * api.php — public chat endpoint for the embeddable widget.
 * POST JSON: { slug: "resume", messages: [...] }
 */
require __DIR__ . '/_lib.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'POST only']); exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) { http_response_code(400); echo json_encode(['error' => 'invalid json']); exit; }

$slug = trim((string)($input['slug'] ?? ''));
$messages = $input['messages'] ?? [];

if ($slug === '') { http_response_code(400); echo json_encode(['error' => 'slug required']); exit; }
if (!is_array($messages) || count($messages) === 0) {
    http_response_code(400); echo json_encode(['error' => 'messages required']); exit;
}

// Sanity-check shape + cap depth
$messages = array_slice($messages, -20);
foreach ($messages as $m) {
    if (!isset($m['role'], $m['content']) || !in_array($m['role'], ['user', 'assistant'], true)) {
        http_response_code(400); echo json_encode(['error' => 'bad message shape']); exit;
    }
}

$agent = agent_by_slug($slug);
if (!$agent || !$agent['enabled']) {
    http_response_code(404); echo json_encode(['error' => 'agent not found or disabled']); exit;
}

$cfg = cfg();
$model = $agent['model'] ?: $cfg['model'];
$maxTokens = (int)($agent['max_tokens'] ?: $cfg['max_tokens']);
$apiKey = $cfg['anthropic_api_key'];

if (!$apiKey || strpos($apiKey, 'REPLACE') !== false) {
    http_response_code(500); echo json_encode(['error' => 'API key not configured']); exit;
}

$systemPrompt = build_system_prompt($agent);

$payload = [
    'model'      => $model,
    'max_tokens' => $maxTokens,
    'system'     => $systemPrompt,
    'messages'   => $messages,
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
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
