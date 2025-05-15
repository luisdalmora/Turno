<?php
// processar_cadastro_colaborador.php

require_once __DIR__ . '/config.php'; 
require_once __DIR__ . '/conexao.php'; // SQLSRV
require_once __DIR__ . '/LogHelper.php';

$logger = new LogHelper($conexao); // Já deve estar usando SQLSRV internamente
$adminUserId = $_SESSION['usuario_id'] ?? null; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token_cad_colab']) || !hash_equals($_SESSION['csrf_token_cad_colab'], $_POST['csrf_token'])) {
        $logger->log('SECURITY_WARNING', 'Falha na validação do CSRF token ao cadastrar colaborador.', ['admin_user_id' => $adminUserId, 'posted_token' => $_POST['csrf_token'] ?? 'N/A']);
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Erro de segurança (token inválido). Por favor, tente novamente.'];
        header("Location: cadastrar_colaborador.php"); 
        if ($conexao) sqlsrv_close($conexao);
        exit;
    }

    $nome_completo = isset($_POST['nome_completo']) ? trim($_POST['nome_completo']) : '';
    $email_input = isset($_POST['email']) ? trim($_POST['email']) : null; 
    $cargo_input = isset($_POST['cargo']) ? trim($_POST['cargo']) : null;   
    $ativo = isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1; 

    if (empty($nome_completo)) {
        $_SESSION['flash_message'] = ['type' => 'warning', 'message' => 'Nome completo é obrigatório.'];
        header("Location: cadastrar_colaborador.php");
        if ($conexao) sqlsrv_close($conexao);
        exit;
    }

    $email = null; 
    if (!empty($email_input)) {
        if (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_message'] = ['type' => 'warning', 'message' => 'Formato de e-mail inválido.'];
            header("Location: cadastrar_colaborador.php");
            if ($conexao) sqlsrv_close($conexao);
            exit;
        }
        $email = $email_input;
    }
    
    $cargo = (is_string($cargo_input) && trim($cargo_input) === '') ? null : $cargo_input;

    // SQL Server: Adiciona OUTPUT INSERTED.id (ou nome da sua coluna de identidade)
    $sql = "INSERT INTO colaboradores (nome_completo, email, cargo, ativo) OUTPUT INSERTED.id VALUES (?, ?, ?, ?)";
    $params = array($nome_completo, $email, $cargo, $ativo);
    
    // Para INSERT com OUTPUT, é mais direto usar sqlsrv_query
    $stmt = sqlsrv_query($conexao, $sql, $params);

    if ($stmt) {
        $novo_colaborador_id = null;
        // Tenta buscar o ID retornado pela cláusula OUTPUT
        if (sqlsrv_fetch($stmt) === true) {
            $novo_colaborador_id = sqlsrv_get_field($stmt, 0); // Pega o ID da primeira coluna do resultado
        }
        // sqlsrv_next_result($stmt); // Não necessário para um simples INSERT com OUTPUT

        if ($novo_colaborador_id) {
            $logger->log('INFO', 'Novo colaborador cadastrado com sucesso (SQLSRV).', [
                'colaborador_id' => $novo_colaborador_id,
                'nome' => $nome_completo,
                'admin_user_id' => $adminUserId 
            ]);
            sqlsrv_free_stmt($stmt);
            if ($conexao) sqlsrv_close($conexao);
            
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => "Colaborador '".htmlspecialchars($nome_completo)."' cadastrado com sucesso!"];
            header("Location: gerenciar_colaboradores.php"); 
            exit;
        } else {
            $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
            $error_code = isset($errors[0]['code']) ? $errors[0]['code'] : null;
            $error_message_log = isset($errors[0]['message']) ? $errors[0]['message'] : 'Erro ao inserir ou obter ID do colaborador.';
            
            $logger->log('ERROR', 'Erro ao executar query de cadastro de colaborador ou obter ID (SQLSRV).', [
                'errors_sqlsrv' => $errors, 'nome' => $nome_completo, 'admin_user_id' => $adminUserId
            ]);
            
            $user_message = "Erro ao cadastrar o colaborador.";
            if ($error_code == 2627 || $error_code == 2601) { // Códigos de erro comuns do SQL Server para violação de chave única
                 $user_message = "Erro: O e-mail informado ('".htmlspecialchars($email)."') já está cadastrado para outro colaborador.";
            } else {
                $user_message .= " Detalhe: " . htmlentities($error_message_log);
            }
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => $user_message];
            if(isset($stmt)) sqlsrv_free_stmt($stmt);
            if ($conexao) sqlsrv_close($conexao);
            header("Location: cadastrar_colaborador.php"); 
            exit;
        }
    } else {
        $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
        $error_message_log = isset($errors[0]['message']) ? $errors[0]['message'] : 'Erro ao preparar/executar query.';
        $logger->log('ERROR', 'Erro ao preparar/executar query de cadastro de colaborador (SQLSRV).', ['errors_sqlsrv' => $errors, 'admin_user_id' => $adminUserId]);
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Erro no sistema ao tentar preparar o cadastro: ' . htmlentities($error_message_log) . '. Por favor, tente novamente.'];
        if ($conexao) sqlsrv_close($conexao);
        header("Location: cadastrar_colaborador.php");
        exit;
    }

} else { 
    $logger->log('WARNING', 'Acesso inválido (não POST) à página de processar_cadastro_colaborador.', ['admin_user_id' => $adminUserId]);
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Acesso inválido. Utilize o formulário de cadastro.'];
    header("Location: cadastrar_colaborador.php");
    if ($conexao) sqlsrv_close($conexao);
    exit;
}
