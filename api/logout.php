<?php
/**
 * logout.php — Encerrar sessão
 * Ouvidoria Escolar
 *
 * POST /api/logout.php
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';   // garante que o .env é carregado antes do cors.php
require_once __DIR__ . '/config/cors.php';

// Mesma configuração do login.php e session.php
// precisa ser idêntica para o navegador reconhecer e deletar o cookie certo
$emProducao = ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production') !== 'development';

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $emProducao,
    'httponly' => true,
    'samesite' => 'Strict',
]);

if (session_status() === PHP_SESSION_NONE) session_start();

// Limpa todos os dados da sessão
$_SESSION = [];

// Apaga o cookie no navegador
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// Destroi a sessão no servidor
session_destroy();

jsonResponse(true, 'Sessão encerrada.', ['redirect' => '../login.html']);