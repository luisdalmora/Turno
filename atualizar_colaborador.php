<?php
// atualizar_colaborador.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/LogHelper.php';

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

// Validação CSRF Token (usa o token específico da página de gerenciar colaboradores)
if (!isset($input['csrf_token']) || !isset($_SESSION['csrf_token_colab_manage']) || !hash_equals($_SESSION['csrf_token_colab_manage'], $input['csrf_token'])) {
    $logger->log('SECURITY_WARNING', 'Falha CSRF token em atualizar_colaborador.', ['user_id' => $userIdLogado]);
    echo json_encode(['success' => false, 'message' => 'Erro de segurança. Recarregue a página.']); exit;
}

// Regenera o token CSRF para a próxima requisição na página de gerenciamento
$_SESSION['csrf_token_colab_manage'] = bin2hex(random_bytes(32));
$novoCsrfToken = $_SESSION['csrf_token_colab_manage'];


// --- Obter e Validar Dados ---
$colab_id = isset($input['colab_id']) ? (int)$input['colab_id'] : 0;
$nome_completo = isset($input['nome_completo']) ? trim($input['nome_completo']) : '';
$email = isset($input['email']) ? trim($input['email']) : null; // Email pode ser nulo/opcional
$cargo = isset($input['cargo']) ? trim($input['cargo']) : null;   // Cargo pode ser nulo/opcional

if ($colab_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do colaborador inválido.', 'csrf_token' => $novoCsrfToken]); exit;
}
if (empty($nome_completo)) {
    echo json_encode(['success' => false, 'message' => 'Nome completo é obrigatório.', 'csrf_token' => $novoCsrfToken]); exit;
}
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Formato de e-mail inválido.', 'csrf_token' => $novoCsrfToken]); exit;
}
// Se email for uma string vazia após trim, define como NULL para o banco
if (is_string($email) && trim($email) === '') {
    $email = null;
}
if (is_string($cargo) && trim($cargo) === '') {
    $cargo = null;
}


// --- Atualizar no Banco de Dados ---
$sql = "UPDATE colaboradores SET nome_completo = ?, email = ?, cargo = ? WHERE id = ?";
$stmt = mysqli_prepare($conexao, $sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "sssi", $nome_completo, $email, $cargo, $colab_id);
    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            $logger->log('INFO', 'Colaborador atualizado com sucesso.', ['colab_id' => $colab_id, 'admin_user_id' => $userIdLogado]);
            echo json_encode(['success' => true, 'message' => 'Colaborador atualizado com sucesso!', 'csrf_token' => $novoCsrfToken]);
        } else {
            // Nenhuma linha afetada, pode ser que os dados eram os mesmos ou ID não encontrado (pouco provável se chegou aqui)
            echo json_encode(['success' => true, 'message' => 'Nenhuma alteração detectada ou colaborador não encontrado.', 'csrf_token' => $novoCsrfToken]);
        }
    } else {
        $error_code = mysqli_errno($conexao);
        $error_message = mysqli_stmt_error($stmt);
        $logger->log('ERROR', 'Erro ao executar atualização de colaborador.', ['colab_id' => $colab_id, 'error_code' => $error_code, 'error_message' => $error_message, 'admin_user_id' => $userIdLogado]);
        $user_message = "Erro ao atualizar o colaborador.";
        if ($error_code == 1062) { // Erro de entrada duplicada (provavelmente e-mail)
             $user_message = "Erro: O e-mail informado já existe para outro colaborador.";
        }
        echo json_encode(['success' => false, 'message' => $user_message, 'csrf_token' => $novoCsrfToken]);
    }
    mysqli_stmt_close($stmt);
} else {
    $logger->log('ERROR', 'Erro ao preparar statement para atualizar colaborador.', ['error' => mysqli_error($conexao), 'admin_user_id' => $userIdLogado]);
    echo json_encode(['success' => false, 'message' => 'Erro no sistema ao tentar preparar a atualização.', 'csrf_token' => $novoCsrfToken]);
}

mysqli_close($conexao);
?>
