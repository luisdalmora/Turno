<?php
// gerenciar_observacao_geral.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // SQLSRV
require_once __DIR__ . '/LogHelper.php';

$logger = new LogHelper($conexao);
header('Content-Type: application/json');

$settingKey = 'observacoes_gerais';
$novoCsrfTokenParaCliente = null;
$csrfTokenSessionKey = 'csrf_token_obs_geral';

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
    $params = array($settingKey);
    $stmt = sqlsrv_query($conexao, $sql, $params); // Para SELECT com parâmetros simples

    if ($stmt) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        $observacao = $row ? $row['setting_value'] : '';
        echo json_encode(['success' => true, 'observacao' => $observacao, 'csrf_token' => $novoCsrfTokenParaCliente]);
    } else {
        $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
        $logger->log('ERROR', 'Falha ao buscar observação geral (SQLSRV).', ['user_id' => $userId, 'setting_key' => $settingKey, 'errors_sqlsrv' => $errors]);
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar observação.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $observacaoConteudo = $input['observacao'] ?? '';

    // SQL Server MERGE statement para funcionalidade "upsert"
    $sql_merge = "
        MERGE system_settings AS Target
        USING (VALUES (?, ?)) AS Source (setting_key_s, setting_value_s)
        ON Target.setting_key = Source.setting_key_s
        WHEN MATCHED THEN
            UPDATE SET Target.setting_value = Source.setting_value_s
        WHEN NOT MATCHED BY TARGET THEN
            INSERT (setting_key, setting_value) VALUES (Source.setting_key_s, Source.setting_value_s);
    ";
    $params_merge = array($settingKey, $observacaoConteudo);
    $stmt_merge = sqlsrv_prepare($conexao, $sql_merge, $params_merge);

    if ($stmt_merge) {
        if (sqlsrv_execute($stmt_merge)) {
            $logger->log('INFO', 'Observação geral salva (SQLSRV).', ['user_id' => $userId, 'setting_key' => $settingKey]);
            echo json_encode(['success' => true, 'message' => 'Observação geral salva com sucesso!', 'csrf_token' => $novoCsrfTokenParaCliente]);
        } else {
            $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
            $logger->log('ERROR', 'Falha ao salvar observação geral (SQLSRV execute).', ['user_id' => $userId, 'errors_sqlsrv' => $errors]);
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar observação geral.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
        sqlsrv_free_stmt($stmt_merge);
    } else {
        $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
        $logger->log('ERROR', 'Falha ao preparar MERGE para observação geral (SQLSRV).', ['user_id' => $userId, 'errors_sqlsrv' => $errors]);
        echo json_encode(['success' => false, 'message' => 'Erro no sistema ao tentar salvar observação.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
}

if ($conexao) {
    sqlsrv_close($conexao);
}
