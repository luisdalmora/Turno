<?php
// alternar_status_colaborador.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // Já deve estar configurado para SQLSRV
require_once __DIR__ . '/LogHelper.php'; // Se LogHelper usa $conexao, ele também precisa ser adaptado internamente

$logger = new LogHelper($conexao); // Assumindo que LogHelper está adaptado ou não usa mysqli_* diretamente
header('Content-Type: application/json');

// --- Verificação de Sessão e CSRF Token ---
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Sessão inválida.']); exit;
}
$userIdLogado = $_SESSION['usuario_id'];

$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $logger->log('ERROR', 'JSON de entrada inválido em alternar_status_colaborador.', ['user_id' => $userIdLogado, 'json_error' => json_last_error_msg()]);
    echo json_encode(['success' => false, 'message' => 'Requisição inválida (JSON).']); exit;
}

if (!isset($input['csrf_token']) || !isset($_SESSION['csrf_token_colab_manage']) || !hash_equals($_SESSION['csrf_token_colab_manage'], $input['csrf_token'])) {
    $logger->log('SECURITY_WARNING', 'Falha CSRF token em alternar_status_colaborador.', ['user_id' => $userIdLogado]);
    echo json_encode(['success' => false, 'message' => 'Erro de segurança. Recarregue a página.']); exit;
}

$_SESSION['csrf_token_colab_manage'] = bin2hex(random_bytes(32));
$novoCsrfToken = $_SESSION['csrf_token_colab_manage'];

// --- Obter e Validar Dados ---
$colab_id = isset($input['colab_id']) ? (int)$input['colab_id'] : 0;
$novo_status = isset($input['novo_status']) ? (int)$input['novo_status'] : null;


if ($colab_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do colaborador inválido.', 'csrf_token' => $novoCsrfToken]); exit;
}
if ($novo_status === null || !in_array($novo_status, [0, 1])) {
    echo json_encode(['success' => false, 'message' => 'Novo status inválido. Deve ser 0 ou 1.', 'csrf_token' => $novoCsrfToken]); exit;
}

// --- Atualizar Status no Banco de Dados ---
$sql = "UPDATE colaboradores SET ativo = ? WHERE id = ?";
$params = array($novo_status, $colab_id); // Parâmetros para sqlsrv_prepare

// sqlsrv_prepare pode retornar false em caso de erro.
$stmt = sqlsrv_prepare($conexao, $sql, $params);

if ($stmt) {
    if (sqlsrv_execute($stmt)) {
        $rows_affected = sqlsrv_rows_affected($stmt);
        if ($rows_affected > 0) {
            $status_texto = $novo_status == 1 ? "ativado" : "desativado";
            $logger->log('INFO', "Status do colaborador ID {$colab_id} alterado para {$status_texto}.", ['admin_user_id' => $userIdLogado]);
            echo json_encode(['success' => true, 'message' => "Colaborador {$status_texto} com sucesso!", 'novo_status_bool' => (bool)$novo_status, 'csrf_token' => $novoCsrfToken]);
        } elseif ($rows_affected === 0) {
            echo json_encode(['success' => true, 'message' => 'Nenhuma alteração de status necessária ou colaborador não encontrado.', 'csrf_token' => $novoCsrfToken]);
        } else { // $rows_affected === false indica erro
            $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
            $logger->log('ERROR', 'Erro ao obter linhas afetadas após atualização de status do colaborador.', ['colab_id' => $colab_id, 'errors' => $errors, 'admin_user_id' => $userIdLogado]);
            echo json_encode(['success' => false, 'message' => 'Erro ao verificar a atualização do status do colaborador.', 'csrf_token' => $novoCsrfToken]);
        }
    } else {
        $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
        $logger->log('ERROR', 'Erro ao executar atualização de status do colaborador.', ['colab_id' => $colab_id, 'errors' => $errors, 'admin_user_id' => $userIdLogado]);
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar o status do colaborador.', 'csrf_token' => $novoCsrfToken]);
    }
    sqlsrv_free_stmt($stmt);
} else {
    $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
    $logger->log('ERROR', 'Erro ao preparar statement para alternar status.', ['errors' => $errors, 'admin_user_id' => $userIdLogado]);
    echo json_encode(['success' => false, 'message' => 'Erro no sistema ao tentar preparar a alteração de status.', 'csrf_token' => $novoCsrfToken]);
}

if ($conexao) {
    sqlsrv_close($conexao);
}
