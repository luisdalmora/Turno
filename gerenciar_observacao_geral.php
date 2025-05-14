<?php
// gerenciar_observacao_geral.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/LogHelper.php';

$logger = new LogHelper($conexao);
header('Content-Type: application/json');

$settingKey = 'observacoes_gerais';
$novoCsrfTokenParaCliente = null;
$csrfTokenSessionKey = 'csrf_token_obs_geral'; // Token específico

// --- Verificação de Sessão e CSRF Token ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado. Sessão inválida.']); exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $logger->log('ERROR', 'JSON de entrada inválido (CSRF check obs geral).', ['user_id' => $_SESSION['usuario_id'] ?? null, 'json_error' => json_last_error_msg()]);
        echo json_encode(['success' => false, 'message' => 'Requisição inválida (JSON).']); exit;
    }
     if (!isset($input['csrf_token']) || !isset($_SESSION[$csrfTokenSessionKey]) || !hash_equals($_SESSION[$csrfTokenSessionKey], $input['csrf_token'])) {
        $logger->log('SECURITY_WARNING', 'Falha na validação do CSRF token (obs geral).', ['user_id' => $_SESSION['usuario_id'] ?? null]);
        echo json_encode(['success' => false, 'message' => 'Erro de segurança. Recarregue a página.']); exit;
    }
    $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));
    $novoCsrfTokenParaCliente = $_SESSION[$csrfTokenSessionKey];

} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado.']); exit;
    }
    if (empty($_SESSION[$csrfTokenSessionKey])) { $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));  }
    $novoCsrfTokenParaCliente = $_SESSION[$csrfTokenSessionKey];
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não suportado.']); exit;
}
$userId = $_SESSION['usuario_id'];


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "SELECT setting_value FROM system_settings WHERE setting_key = ?";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, "s", $settingKey);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $observacao = $row ? $row['setting_value'] : '';
    echo json_encode(['success' => true, 'observacao' => $observacao, 'csrf_token' => $novoCsrfTokenParaCliente]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $observacaoConteudo = $input['observacao'] ?? '';

    $sql = "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $settingKey, $observacaoConteudo);

    if (mysqli_stmt_execute($stmt)) {
        $logger->log('INFO', 'Observação geral salva.', ['user_id' => $userId, 'setting_key' => $settingKey]);
        echo json_encode(['success' => true, 'message' => 'Observação geral salva com sucesso!', 'csrf_token' => $novoCsrfTokenParaCliente]);
    } else {
        $logger->log('ERROR', 'Falha ao salvar observação geral.', ['user_id' => $userId, 'error' => mysqli_stmt_error($stmt)]);
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar observação geral.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
    mysqli_stmt_close($stmt);
}

mysqli_close($conexao);
