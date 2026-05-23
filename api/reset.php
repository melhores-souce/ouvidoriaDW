<?php
/**
 * api/reset.php — Etapa 2 da recuperação de senha
 *
 * Recebe: { "token": "abc123...", "senha": "novaSenha123" }
 * Ação  : valida o token, atualiza a senha, invalida o token
 * Retorna: JSON com sucesso ou erro
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/cors.php';

requireMethod('POST');

// --- Lê o body ---
$body  = json_decode(file_get_contents('php://input'), true);
$token = isset($body['token']) ? trim($body['token']) : '';
$senha = isset($body['senha']) ? $body['senha'] : '';

// --- Validações básicas ---
if (empty($token) || strlen($token) !== 64) {
    http_response_code(422);
    echo json_encode(['error' => 'Token inválido.']);
    exit;
}

if (empty($senha) || strlen($senha) < 8) {
    http_response_code(422);
    echo json_encode(['error' => 'A senha deve ter no mínimo 8 caracteres.']);
    exit;
}

// Força senha com pelo menos 1 número e 1 letra
if (!preg_match('/[a-zA-Z]/', $senha) || !preg_match('/[0-9]/', $senha)) {
    http_response_code(422);
    echo json_encode(['error' => 'A senha deve conter letras e números.']);
    exit;
}

try {
    $pdo = Database::connect();

    // Busca o token: deve existir, não ter sido usado e ainda estar no prazo
    $stmt = $pdo->prepare('
        SELECT id, email
        FROM password_resets
        WHERE token     = :token
          AND usado     = 0
          AND expira_em > NOW()
        LIMIT 1
    ');
    $stmt->execute([':token' => $token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        http_response_code(422);
        echo json_encode(['error' => 'Link inválido ou expirado. Solicite um novo.']);
        exit;
    }

    // Confere se o usuário ainda existe e está ativo
    $stmtUsu = $pdo->prepare('SELECT IDusu FROM tbusuarios WHERE email = :email AND ativo = 1 LIMIT 1');
    $stmtUsu->execute([':email' => $reset['email']]);
    $usuario = $stmtUsu->fetch();

    if (!$usuario) {
        http_response_code(422);
        echo json_encode(['error' => 'Usuário não encontrado.']);
        exit;
    }

    // Gera o hash da nova senha (bcrypt, cost 12 — igual ao cadastro.php)
    $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

    // Atualiza a senha
    $pdo->prepare('UPDATE tbusuarios SET senha = :senha WHERE IDusu = :id')
        ->execute([':senha' => $hash, ':id' => $usuario['IDusu']]);

    // Invalida o token para não poder ser usado novamente
    $pdo->prepare('UPDATE password_resets SET usado = 1 WHERE id = :id')
        ->execute([':id' => $reset['id']]);

    echo json_encode(['message' => 'Senha redefinida com sucesso! Você já pode fazer login.']);

} catch (PDOException $e) {
    error_log('[Ouvidoria][reset] Erro BD: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno. Tente novamente.']);
}

exit;