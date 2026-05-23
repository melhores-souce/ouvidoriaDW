<?php
/**
 * api/config/cors.php — Headers de resposta e helpers
 * Ouvidoria Escolar
 */

declare(strict_types=1);

// Garante que o .env está carregado antes de qualquer getenv()
// (importante quando cors.php é incluído antes de db.php)
require_once __DIR__ . '/env.php';

// ── CORS ─────────────────────────────────────────────────────
define('ALLOWED_ORIGIN', $_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost');

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === ALLOWED_ORIGIN || str_starts_with($origin, 'http://localhost')) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Rate Limiting ─────────────────────────────────────────────
// ATENÇÃO: chame checkRateLimit() somente APÓS session_start()
// para não conflitar com session_set_cookie_params() dos endpoints.
function checkRateLimit(string $key, int $maxAttempts = 5, int $windowSeconds = 300): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();

    $now     = time();
    $session = $_SESSION['rate_limit'][$key] ?? ['count' => 0, 'start' => $now];

    if ($now - $session['start'] > $windowSeconds) {
        $session = ['count' => 0, 'start' => $now];
    }

    $session['count']++;
    $_SESSION['rate_limit'][$key] = $session;

    if ($session['count'] > $maxAttempts) {
        jsonResponse(false, 'Muitas tentativas. Aguarde alguns minutos.', [], 429);
    }
}

// ── Helpers de resposta JSON ──────────────────────────────────
function jsonResponse(bool $success, string $message, array $data = [], int $code = 200): never
{
    http_response_code($code);
    echo json_encode(array_merge(
        ['success' => $success, 'message' => $message],
        $data
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Ler corpo JSON da requisição ──────────────────────────────
function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(false, 'Corpo da requisição inválido.', [], 400);
    }
    return $data ?? [];
}

// ── Sanitização ───────────────────────────────────────────────
function sanitize(mixed $value): string
{
    return htmlspecialchars(strip_tags(trim((string)$value)), ENT_QUOTES, 'UTF-8');
}

// ── Validações ───────────────────────────────────────────────
function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}


// ── Método HTTP obrigatório ───────────────────────────────────
function requireMethod(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        jsonResponse(false, 'Método não permitido.', [], 405);
    }
}