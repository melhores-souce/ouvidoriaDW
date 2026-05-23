<?php
/**
 * api/upload.php — Recebe arquivos anexados a uma manifestação
 * Ouvidoria Escolar
 *
 * POST multipart/form-data
 *   campos: IDmanifest (int), arquivos[] (File[])
 *
 * Retorna JSON:
 *   200 { success: true,  salvos: [ {nome, tamanho}, ... ] }
 *   400 { success: false, message: "..." }
 *   413 { success: false, message: "Arquivo muito grande." }
 *   415 { success: false, message: "Tipo de arquivo não permitido." }
 *   500 { success: false, message: "Erro interno." }
 *
 * Segurança aplicada:
 *   - MIME verificado por finfo (não só extensão)
 *   - Nome do arquivo gerado aleatoriamente (evita path traversal)
 *   - Limite de 5MB por arquivo, máximo 5 arquivos por envio
 *   - Pasta de upload fora do escopo público (configurável)
 *   - IDmanifest validado no banco antes de salvar qualquer coisa
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/cors.php';

requireMethod('POST');

/* ── 2. Tipos de arquivo permitidos (MIME real → extensão segura) ── */
const TIPOS_PERMITIDOS = [
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/webp'      => 'webp',
    'application/pdf' => 'pdf',
    // Adicione mais se necessário:
    // 'application/msword'                                                => 'doc',
    // 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
];

const TAMANHO_MAX   = 5 * 1024 * 1024; // 5 MB
const MAX_ARQUIVOS  = 5;               // máximo por envio

/* ── 3. Valida IDmanifest ───────────────────────────────────────── */
$IDmanifest = filter_input(INPUT_POST, 'IDmanifest', FILTER_VALIDATE_INT);

if (!$IDmanifest || $IDmanifest <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'IDmanifest inválido.']);
    exit;
}

/* ── 4. Confirma que a manifestação existe no banco ─────────────── */
try {
    $pdo  = Database::connect();
    $stmt = $pdo->prepare('SELECT IDmanifest FROM tbmanifest WHERE IDmanifest = :id LIMIT 1');
    $stmt->execute([':id' => $IDmanifest]);

    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Manifestação não encontrada.']);
        exit;
    }
} catch (PDOException $e) {
    error_log('[UPLOAD] Erro BD (verificar manifest): ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
    exit;
}

/* ── 5. Verifica se vieram arquivos ─────────────────────────────── */
if (empty($_FILES['arquivos'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nenhum arquivo recebido.']);
    exit;
}

// Normaliza $_FILES['arquivos'] para array de arquivos individuais
$arquivos = [];
foreach ($_FILES['arquivos']['name'] as $i => $nome) {
    $arquivos[] = [
        'name'     => $nome,
        'type'     => $_FILES['arquivos']['type'][$i],
        'tmp_name' => $_FILES['arquivos']['tmp_name'][$i],
        'error'    => $_FILES['arquivos']['error'][$i],
        'size'     => $_FILES['arquivos']['size'][$i],
    ];
}

if (count($arquivos) > MAX_ARQUIVOS) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Máximo de ' . MAX_ARQUIVOS . ' arquivos por envio.']);
    exit;
}

/* ── 6. Define pasta de destino ─────────────────────────────────── */
// Recomendado: pasta FORA da raiz pública para não expor os arquivos.
// Se seu host obrigar pasta pública, troque por:
// __DIR__ . '/../../uploads/manifestacoes/' . $IDmanifest
$pastaBase = dirname(__DIR__) . '/uploads/manifestacoes/' . $IDmanifest;

if (!is_dir($pastaBase) && !mkdir($pastaBase, 0750, true)) {
    error_log('[UPLOAD] Falha ao criar pasta: ' . $pastaBase);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao preparar armazenamento.']);
    exit;
}

// Cria .htaccess na pasta de uploads para bloquear execução de scripts
$htaccess = $pastaBase . '/../.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "Options -Indexes\nphp_flag engine off\nAddType text/plain .php .php5 .phtml\n");
}

/* ── 7. Processa cada arquivo ───────────────────────────────────── */
$salvos = [];
$erros  = [];
$finfo  = new finfo(FILEINFO_MIME_TYPE);

foreach ($arquivos as $arquivo) {

    // Erro de upload do PHP
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        $erros[] = $arquivo['name'] . ': erro no upload (' . $arquivo['error'] . ')';
        continue;
    }

    // Tamanho
    if ($arquivo['size'] > TAMANHO_MAX) {
        $erros[] = $arquivo['name'] . ': excede 5MB';
        continue;
    }

    // MIME real (finfo lê os bytes do arquivo, não confia no header)
    $mimeReal = $finfo->file($arquivo['tmp_name']);
    if (!array_key_exists($mimeReal, TIPOS_PERMITIDOS)) {
        $erros[] = $arquivo['name'] . ': tipo não permitido (' . $mimeReal . ')';
        continue;
    }

    // Gera nome aleatório seguro — evita path traversal e sobrescrita
    $ext       = TIPOS_PERMITIDOS[$mimeReal];
    $nomeSalvo = bin2hex(random_bytes(16)) . '.' . $ext; // ex: a3f9b2c1....jpg
    $destino   = $pastaBase . '/' . $nomeSalvo;

    if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
        error_log('[UPLOAD] move_uploaded_file falhou: ' . $destino);
        $erros[] = $arquivo['name'] . ': falha ao salvar';
        continue;
    }

    // Registra no banco
    try {
        $ins = $pdo->prepare('
            INSERT INTO tbmanifest_arquivos
                (IDmanifest, nome_original, nome_salvo, mime_type, tamanho)
            VALUES
                (:IDmanifest, :nome_original, :nome_salvo, :mime_type, :tamanho)
        ');
        $ins->execute([
            ':IDmanifest'    => $IDmanifest,
            ':nome_original' => mb_substr($arquivo['name'], 0, 255),
            ':nome_salvo'    => $nomeSalvo,
            ':mime_type'     => $mimeReal,
            ':tamanho'       => $arquivo['size'],
        ]);

        $salvos[] = [
            'nome'    => $arquivo['name'],
            'tamanho' => $arquivo['size'],
        ];

    } catch (PDOException $e) {
        // Arquivo salvo no disco mas falhou no banco — remove para não ficar órfão
        @unlink($destino);
        error_log('[UPLOAD] Erro BD (inserir arquivo): ' . $e->getMessage());
        $erros[] = $arquivo['name'] . ': erro ao registrar';
    }
}

/* ── 8. Resposta ────────────────────────────────────────────────── */
// Sucesso parcial (alguns salvos, alguns com erro) também retorna 200
// O frontend mostra quais falharam via toast
http_response_code(200);
echo json_encode([
    'success' => count($salvos) > 0 || count($erros) === 0,
    'salvos'  => $salvos,
    'erros'   => $erros,
    'message' => count($erros) > 0
        ? count($salvos) . ' arquivo(s) salvo(s). ' . count($erros) . ' ignorado(s).'
        : count($salvos) . ' arquivo(s) salvo(s) com sucesso.',
], JSON_UNESCAPED_UNICODE);