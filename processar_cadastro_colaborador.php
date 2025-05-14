<?php
// processar_cadastro_colaborador.php

require_once __DIR__ . '/config.php'; // Inclui configurações e inicia sessão
require_once __DIR__ . '/conexao.php'; 
require_once __DIR__ . '/LogHelper.php';

$logger = new LogHelper($conexao);
$adminUserId = $_SESSION['usuario_id'] ?? null; // ID do usuário admin que está fazendo o cadastro

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Validar CSRF Token
    // Usar o nome de token específico que foi gerado para este formulário
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token_cad_colab']) || !hash_equals($_SESSION['csrf_token_cad_colab'], $_POST['csrf_token'])) {
        $logger->log('SECURITY_WARNING', 'Falha na validação do CSRF token ao cadastrar colaborador.', ['admin_user_id' => $adminUserId, 'posted_token' => $_POST['csrf_token'] ?? 'N/A']);
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Erro de segurança (token inválido). Por favor, tente novamente.'];
        header("Location: cadastrar_colaborador.php"); 
        exit;
    }
    // Opcional: Invalidar o token após o uso para prevenir replay, mas requer regeneração na página do formulário
    // unset($_SESSION['csrf_token_cad_colab']); 
    // Ou, mais comum para formulários tradicionais, o token é regenerado na próxima vez que a página do formulário é carregada.

    $nome_completo = isset($_POST['nome_completo']) ? trim($_POST['nome_completo']) : '';
    $email_input = isset($_POST['email']) ? trim($_POST['email']) : null; 
    $cargo_input = isset($_POST['cargo']) ? trim($_POST['cargo']) : null;   
    $ativo = isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1; // Padrão para ativo (1)

    // 2. Validação dos Dados
    if (empty($nome_completo)) {
        $_SESSION['flash_message'] = ['type' => 'warning', 'message' => 'Nome completo é obrigatório.'];
        header("Location: cadastrar_colaborador.php");
        exit;
    }

    $email = null; // Inicializa como null
    if (!empty($email_input)) {
        if (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_message'] = ['type' => 'warning', 'message' => 'Formato de e-mail inválido.'];
            header("Location: cadastrar_colaborador.php");
            exit;
        }
        $email = $email_input; // Atribui se for válido e não vazio
    }
    
    // Define como NULL se for string vazia após trim, para respeitar constraints UNIQUE do DB
    $cargo = (is_string($cargo_input) && trim($cargo_input) === '') ? null : $cargo_input;


    // 3. Inserção no Banco de Dados
    $sql = "INSERT INTO colaboradores (nome_completo, email, cargo, ativo) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conexao, $sql);

    if ($stmt) {
        // 's' para string, 'i' para integer. Email e Cargo podem ser nulos.
        mysqli_stmt_bind_param($stmt, "sssi", $nome_completo, $email, $cargo, $ativo);

        if (mysqli_stmt_execute($stmt)) {
            $novo_colaborador_id = mysqli_insert_id($conexao);
            $logger->log('INFO', 'Novo colaborador cadastrado com sucesso.', [
                'colaborador_id' => $novo_colaborador_id,
                'nome' => $nome_completo,
                'admin_user_id' => $adminUserId 
            ]);
            mysqli_stmt_close($stmt);
            mysqli_close($conexao);
            
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => "Colaborador '".htmlspecialchars($nome_completo)."' cadastrado com sucesso!"];
            // Redireciona para a página de gerenciamento de colaboradores após sucesso
            header("Location: gerenciar_colaboradores.php"); 
            exit;
        } else {
            $error_code = mysqli_errno($conexao);
            $error_message = mysqli_stmt_error($stmt);
            $logger->log('ERROR', 'Erro ao executar query de cadastro de colaborador.', [
                'error_code' => $error_code, 'error_message' => $error_message,
                'nome' => $nome_completo, 'admin_user_id' => $adminUserId
            ]);
            
            $user_message = "Erro ao cadastrar o colaborador.";
            if ($error_code == 1062) { // Código de erro para entrada duplicada (UNIQUE constraint)
                 $user_message = "Erro: O e-mail informado ('".htmlspecialchars($email)."') já está cadastrado para outro colaborador.";
            }
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => $user_message];
            mysqli_stmt_close($stmt);
            mysqli_close($conexao);
            header("Location: cadastrar_colaborador.php"); // Volta para o formulário em caso de erro
            exit;
        }
    } else {
        $error_message = mysqli_error($conexao);
        $logger->log('ERROR', 'Erro ao preparar query de cadastro de colaborador.', ['error_message' => $error_message, 'admin_user_id' => $adminUserId]);
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Erro no sistema ao tentar preparar o cadastro. Por favor, tente novamente.'];
        if ($conexao) mysqli_close($conexao);
        header("Location: cadastrar_colaborador.php");
        exit;
    }

} else { // Se não for POST
    $logger->log('WARNING', 'Acesso inválido (não POST) à página de processar_cadastro_colaborador.', ['admin_user_id' => $adminUserId]);
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Acesso inválido. Utilize o formulário de cadastro.'];
    header("Location: cadastrar_colaborador.php");
    exit;
}
