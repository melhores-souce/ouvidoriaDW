<?php
/**
 * api/forgot.php — Etapa 1 da recuperação de senha
 *
 * Recebe: { "email": "aluno@email.com" }
 * Ação  : gera token, salva no banco e envia e-mail com link
 * Retorna: JSON com mensagem genérica (nunca confirma se o e-mail existe)
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/cors.php';

requireMethod('POST');

// --- Lê e valida o body JSON ---
$body = json_decode(file_get_contents('php://input'), true);
$email = isset($body['email']) ? trim(strtolower($body['email'])) : '';

if (empty($email) || !isValidEmail($email)) {
    jsonResponse(false, 'E-mail inválido.', [], 422);
}

// --- Mensagem genérica: não revela se o e-mail existe ou não ---
// (evita que alguém use a tela para descobrir quais e-mails estão cadastrados)
$resposta = ['message' => 'Se esse e-mail estiver cadastrado, você receberá um link em instantes.'];

try {
    $pdo = Database::connect();

    // Busca o usuário pelo e-mail
    $stmt = $pdo->prepare('SELECT IDusu, nome FROM tbusuarios WHERE email = :email AND ativo = 1 LIMIT 1');
    $stmt->execute([':email' => $email]);
    $usuario = $stmt->fetch();

    // Se não encontrou, retorna a mensagem genérica mesmo assim
    if (!$usuario) {
        jsonResponse(true, $resposta['message']);
        exit;
    }

    // --- Gera token seguro e prazo de 1 hora ---
    $token     = bin2hex(random_bytes(32)); // 64 caracteres hexadecimais
    $expira_em = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Invalida tokens anteriores desse e-mail para não acumular
    $pdo->prepare('UPDATE password_resets SET usado = 1 WHERE email = :email AND usado = 0')
        ->execute([':email' => $email]);

    // Salva o novo token
    $insert = $pdo->prepare('
        INSERT INTO password_resets (email, token, expira_em)
        VALUES (:email, :token, :expira_em)
    ');
    $insert->execute([
        ':email'     => $email,
        ':token'     => $token,
        ':expira_em' => $expira_em,
    ]);

    // --- Monta o link de redefinição ---
    $app_url = $_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost';
    $link    = "$app_url/redefinir.html?token=$token";

    // --- Envia o e-mail ---
    $enviado = enviarEmail(
        para:  $email,
        nome:  $usuario['nome'],
        link:  $link
    );

    if (!$enviado) {
        // Loga o erro internamente mas não expõe ao usuário
        error_log("[Ouvidoria][forgot] Falha ao enviar e-mail para $email");
    }

} catch (PDOException $e) {
    error_log('[Ouvidoria][forgot] Erro BD: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno. Tente novamente.']);
    exit;
}

jsonResponse(true, $resposta['message']);
exit;


// ============================================================
// Função auxiliar — envia o e-mail via SMTP (PHPMailer)
// Variáveis lidas do .env: MAIL_HOST, MAIL_PORT, MAIL_USER,
//                          MAIL_PASS, MAIL_FROM_NAME
// ============================================================
function enviarEmail(string $para, string $nome, string $link): bool
{
    // Se PHPMailer estiver instalado via Composer, use:
    // require_once __DIR__ . '/../../vendor/autoload.php';
    // use PHPMailer\PHPMailer\PHPMailer;

    // --- Configuração SMTP via variáveis de ambiente ---
    $host      = $_ENV['MAIL_HOST']      ?? getenv('MAIL_HOST')      ?: '';
    $port      = $_ENV['MAIL_PORT']      ?? getenv('MAIL_PORT')      ?: 587;
    $user      = $_ENV['MAIL_USER']      ?? getenv('MAIL_USER')      ?: '';
    $pass      = $_ENV['MAIL_PASS']      ?? getenv('MAIL_PASS')      ?: '';
    $fromName  = $_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?: 'Ouvidoria Escolar';

    if (empty($host) || empty($user) || empty($pass)) {
        error_log('[Ouvidoria][forgot] Variáveis MAIL_* não configuradas no .env');
        return false;
    }

    // Corpo do e-mail em HTML
    $assunto = 'Redefinição de senha — Ouvidoria Escolar';
    $corpo   = "
    <div style='font-family:sans-serif;max-width:520px;margin:auto'>
      <h2 style='color:#1a1a1a'>Redefinição de senha</h2>
      <p>Olá, <strong>" . htmlspecialchars($nome) . "</strong>.</p>
      <p>Recebemos uma solicitação para redefinir a senha da sua conta na Ouvidoria Escolar.</p>
      <p>Clique no botão abaixo para criar uma nova senha. O link expira em <strong>1 hora</strong>.</p>
      <p style='margin:24px 0'>
        <a href='" . htmlspecialchars($link) . "'
           style='background:#1d6ed4;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold'>
          Redefinir minha senha
        </a>
      </p>
      <p style='color:#666;font-size:13px'>
        Se você não solicitou isso, ignore este e-mail — sua senha permanece a mesma.
      </p>
      <hr style='border:none;border-top:1px solid #eee;margin:24px 0'>
      <p style='color:#999;font-size:12px'>Ouvidoria Escolar — mensagem automática, não responda.</p>
    </div>";

    // --- Usando PHPMailer (recomendado) ---
    // $mail = new PHPMailer(true);
    // try {
    //     $mail->isSMTP();
    //     $mail->Host       = $host;
    //     $mail->SMTPAuth   = true;
    //     $mail->Username   = $user;
    //     $mail->Password   = $pass;
    //     $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    //     $mail->Port       = $port;
    //     $mail->setFrom($user, $fromName);
    //     $mail->addAddress($para, $nome);
    //     $mail->isHTML(true);
    //     $mail->Subject = $assunto;
    //     $mail->Body    = $corpo;
    //     $mail->send();
    //     return true;
    // } catch (Exception $e) {
    //     error_log('[Ouvidoria][forgot] PHPMailer: ' . $e->getMessage());
    //     return false;
    // }

    // --- Fallback: mail() nativo do PHP (funciona em alguns hosts) ---
    $headers  = "From: $fromName <$user>\r\n";
    $headers .= "Reply-To: $user\r\n";
    $headers .= "Content-Type: text/html; charset=utf-8\r\n";
    return mail($para, $assunto, $corpo, $headers);
}