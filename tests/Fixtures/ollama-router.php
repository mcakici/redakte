<?php

declare(strict_types=1);

// Yalnızca sentetik test sunucusu; gerçek model çalıştırmaz.
header('Content-Type: application/json');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/health') {
    echo '{"ok":true}';
    return;
}
if ($path === '/redirect') {
    header('Location: /echo', true, 302);
    echo '{"redirected":true}';
    return;
}
if ($path === '/failure') {
    http_response_code(500);
    echo '{"error":"sensitive-response-body"}';
    return;
}
if ($path === '/context-error') {
    http_response_code(500);
    echo '{"error":"input length exceeds context window"}';
    return;
}
if ($path === '/invalid') {
    echo 'invalid-json-sensitive-body';
    return;
}
if ($path === '/large') {
    echo str_repeat('x', 4096);
    return;
}
if ($path === '/slow') {
    usleep(500000);
    echo '{"ok":true}';
    return;
}
$payload = json_decode(file_get_contents('php://input'), true);
if ($path === '/echo') {
    echo json_encode(['payload' => $payload, 'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? null]);
    return;
}
if ($path === '/api/show') {
    echo '{"thinking":{"values":[true,false,"low","medium","high"],"default":"medium"}}';
    return;
}
if ($path === '/api/chat') {
    $user = json_decode($payload['messages'][1]['content'], true);
    $entities = [];
    foreach (['zelora tavren' => 'KISI', '43650391326' => 'TCKN', 'migren tanısı' => 'SAGLIK'] as $text => $type) {
        if (str_contains($user['text'], (string) $text) && in_array($type, $user['allowed_types'], true)) {
            $entities[] = ['type' => $type, 'text' => (string) $text, 'occurrence' => 1];
        }
    }
    echo json_encode(['done' => true, 'done_reason' => 'stop', 'message' => [
        'thinking' => 'sensitive-thinking-do-not-export',
        'content' => json_encode(['entities' => $entities], JSON_UNESCAPED_UNICODE),
    ]], JSON_UNESCAPED_UNICODE);
    return;
}
http_response_code(404);
echo '{"error":"unknown test path"}';
