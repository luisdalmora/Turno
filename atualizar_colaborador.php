<?php
// atualizar_colaborador.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // Já deve estar configurado para SQLSRV
require_once __DIR__ . '/LogHelper.php'; // Se LogHelper usa $conexao, ele também precisa ser adaptado internamente

$logger = new LogHelper($conexao);
header('Content-Type: application/json');

// --- Verificação de Sessão e CSRF Token ---
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Sessão inválida.']); exit;
}
$userIdLogado = $_SESSION['usuario_id']; // Para auditoria

$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $logger->log('ERROR', 'JSON de entrada inválido em atualizar_colaborador.', ['user_id' => $userIdLogado, 'json_error' => json_last_error_msg()]);
    echo json_encode(['success' => false, 'message' => 'Requisição inválida (JSON).']); exit;
}

if (!isset($input['csrf_token']) || !isset($_SESSION['csrf_token_colab_manage']) || !hash_equals($_SESSION['csrf_token_colab_manage'], $input['csrf_token'])) {
    $logger->log('SECURITY_WARNING', 'Falha CSRF token em atualizar_colaborador.', ['user_id' => $userIdLogado]);
    echo json_encode(['success' => false, 'message' => 'Erro de segurança. Recarregue a página.']); exit;
}

$_SESSION['csrf_token_colab_manage'] = bin2hex(random_bytes(32));
$novoCsrfToken = $_SESSION['csrf_token_colab_manage'];

// --- Obter e Validar Dados ---
$colab_id = isset($input['colab_id']) ? (int)$input['colab_id'] : 0;
$nome_completo = isset($input['nome_completo']) ? trim($input['nome_completo']) : '';
$email = isset($input['email']) ? trim($input['email']) : null;
$cargo = isset($input['cargo']) ? trim($input['cargo']) : null;

if ($colab_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do colaborador inválido.', 'csrf_token' => $novoCsrfToken]); exit;
}
if (empty($nome_completo)) {
    echo json_encode(['success' => false, 'message' => 'Nome completo é obrigatório.', 'csrf_token' => $novoCsrfToken]); exit;
}
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Formato de e-mail inválido.', 'csrf_token' => $novoCsrfToken]); exit;
}
if (is_string($email) && trim($email) === '') {
    $email = null;
}
if (is_string($cargo) && trim($cargo) === '') {
    $cargo = null;
}

// --- Atualizar no Banco de Dados ---
$sql = "UPDATE colaboradores SET nome_completo = ?, email = ?, cargo = ? WHERE id = ?";
// Para SQLSRV, NULL deve ser passado explicitamente como null, não string vazia para colunas que permitem nulos.
// A lógica de $email = null e $cargo = null acima já cuida disso.
$params = array($nome_completo, $email, $cargo, $colab_id);

$stmt = sqlsrv_prepare($conexao, $sql, $params);

if ($stmt) {
    if (sqlsrv_execute($stmt)) {
        $rows_affected = sqlsrv_rows_affected($stmt);
        if ($rows_affected > 0) {
            $logger->log('INFO', 'Colaborador atualizado com sucesso.', ['colab_id' => $colab_id, 'admin_user_id' => $userIdLogado]);
            echo json_encode(['success' => true, 'message' => 'Colaborador atualizado com sucesso!', 'csrf_token' => $novoCsrfToken]);
        } elseif ($rows_affected === 0) {
            echo json_encode(['success' => true, 'message' => 'Nenhuma alteração detectada ou colaborador não encontrado.', 'csrf_token' => $novoCsrfToken]);
        } else { // $rows_affected === false
            $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
            $logger->log('ERROR', 'Erro ao obter linhas afetadas para atualizar colaborador.', ['colab_id' => $colab_id, 'errors' => $errors, 'admin_user_id' => $userIdLogado]);
            echo json_encode(['success' => false, 'message' => 'Erro ao verificar a atualização do colaborador.', 'csrf_token' => $novoCsrfToken]);
        }
    } else {
        $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
        $error_code = isset($errors[0]['code']) ? $errors[0]['code'] : null;
        $logger->log('ERROR', 'Erro ao executar atualização de colaborador.', ['colab_id' => $colab_id, 'errors' => $errors, 'admin_user_id' => $userIdLogado]);
        
        $user_message = "Erro ao atualizar o colaborador.";
        // Códigos de erro comuns do SQL Server para violação de chave única (UNIQUE constraint)
        // 2627: Violação da restrição %ls '%.*ls'. Não é possível inserir a chave duplicada no objeto '%.*ls'. O valor da chave duplicada é %ls.
        // 2601: Não é possível inserir linha de chave duplicada no objeto '%.*ls' com índice exclusivo '%.*ls'. O valor da chave duplicada é %ls.
        if ($error_code == 2627 || $error_code == 2601) { 
             $user_message = "Erro: O e-mail informado já existe para outro colaborador.";
        }
        echo json_encode(['success' => false, 'message' => $user_message, 'csrf_token' => $novoCsrfToken]);
    }
    sqlsrv_free_stmt($stmt);
} else {
    $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
    $logger->log('ERROR', 'Erro ao preparar statement para atualizar colaborador.', ['errors' => $errors, 'admin_user_id' => $userIdLogado]);
    echo json_encode(['success' => false, 'message' => 'Erro no sistema ao tentar preparar a atualização.', 'csrf_token' => $novoCsrfToken]);
}

if ($conexao) {
    sqlsrv_close($conexao);
}
