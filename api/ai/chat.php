<?php
declare(strict_types=1);

/**
 * POST /api/ai/chat
 * ProWay Lab chat endpoint — keyword-based matching against knowledge base.
 * Body: { "message": "...", "session_id": "..." }
 */

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/rate-limit.php';

requireMethod('POST');

// Rate limit: 20 messages per session (by IP) per hour
if (!checkRateLimit('chat', 20, 3600)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Demasiados mensajes. Intenta de nuevo en unos minutos.']);
    exit;
}

$body      = getJsonBody();
$message   = trim($body['message'] ?? '');
$sessionId = trim($body['session_id'] ?? '');

if (!$message) {
    echo json_encode(['ok' => false, 'error' => 'Mensaje vacio']);
    exit;
}

// Load knowledge base
$kbFile = __DIR__ . '/../data/knowledge-base-proway.json';
$kb = [];
if (file_exists($kbFile)) {
    $raw = file_get_contents($kbFile);
    $kb = json_decode($raw, true) ?: [];
}

if (empty($kb)) {
    echo json_encode(['ok' => false, 'error' => 'Knowledge base no disponible']);
    exit;
}

// Normalize input
function normalizeText(string $str): string {
    $str = mb_strtolower($str, 'UTF-8');
    // Remove accents
    $str = preg_replace('/[áàäâ]/u', 'a', $str);
    $str = preg_replace('/[éèëê]/u', 'e', $str);
    $str = preg_replace('/[íìïî]/u', 'i', $str);
    $str = preg_replace('/[óòöô]/u', 'o', $str);
    $str = preg_replace('/[úùüû]/u', 'u', $str);
    $str = preg_replace('/[ñ]/u', 'n', $str);
    $str = preg_replace('/[^a-z0-9\s]/u', ' ', $str);
    $str = preg_replace('/\s+/', ' ', $str);
    return trim($str);
}

function tokenize(string $str): array {
    $words = explode(' ', normalizeText($str));
    return array_values(array_filter($words, fn(string $w) => strlen($w) > 2));
}

// Find best match
$tokens = tokenize($message);
$bestEntry = null;
$bestScore = 0;
$fallback = null;

foreach ($kb as $entry) {
    $keywords = $entry['keywords'] ?? [];

    if (in_array('__fallback__', $keywords, true)) {
        $fallback = $entry;
        continue;
    }

    $score = 0;
    foreach ($tokens as $token) {
        foreach ($keywords as $kw) {
            $nkw = normalizeText($kw);
            if ($nkw === $token) {
                $score += 2;
            } elseif (str_contains($nkw, $token) || str_contains($token, $nkw)) {
                $score += 1;
            }
        }
    }

    $normalizedScore = count($keywords) > 0
        ? $score / sqrt(count($keywords))
        : 0;

    if ($normalizedScore > $bestScore) {
        $bestScore = $normalizedScore;
        $bestEntry = $entry;
    }
}

// Use match or fallback
$result = ($bestScore >= 0.5 && $bestEntry) ? $bestEntry : $fallback;

if ($result) {
    echo json_encode([
        'ok'         => true,
        'response'   => $result['answer'],
        'topic'      => $result['topic'] ?? 'unknown',
        'session_id' => $sessionId,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode([
        'ok'       => false,
        'error'    => 'No pude encontrar una respuesta. Contactanos por WhatsApp para ayudarte directamente.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
