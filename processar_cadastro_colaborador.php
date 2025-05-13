<?php
// processar_cadastro_colaborador.php

require_once __DIR__ . '/config.php'; // Inclui configurações e inicia sessão
require_once __DIR__ . '/conexao.php'; // Garante que $conexao está disponível
require_once __DIR__ . '/LogHelper.php';

$logger = new LogHelper($conexao);
$userID = $_SESSION['usuario_id'] ?? null; // Pega o ID do usuário para log, se disponível

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_completo = isset($_POST['nome_completo']) ? trim($_POST['nome_completo']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : null; // Opcional
    $cargo = isset($_POST['cargo']) ? trim($_POST['cargo']) : null;   // Opcional
    $ativo = isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1; // Padrão para ativo (1)

    // Validação Básica
    if (empty($nome_completo)) {
        $logger->log('WARNING', 'Tentativa de cadastro de colaborador com nome completo vazio.', ['post_data' => $_POST, 'user_id' => $userID]);
        // Redireciona de volta com mensagem de erro
        header("Location: cadastrar_colaborador.html?status=erro&msg=" . urlencode("Nome completo é obrigatório."));
        exit;
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $logger->log('WARNING', 'Tentativa de cadastro de colaborador com e-mail inválido.', ['email' => $email, 'user_id' => $userID]);
        header("Location: cadastrar_colaborador.html?status=erro&msg=" . urlencode("Formato de e-mail inválido."));
        exit;
    }
    
    // Garante que o email seja nulo se for uma string vazia após o trim, para respeitar a restrição UNIQUE se não for realmente fornecido
    if (is_string($email) && trim($email) === '') {
        $email = null;
    }
    if (is_string($cargo) && trim($cargo) === '') {
        $cargo = null;
    }

    // Inserção no Banco de Dados
    // Baseado no "Script Banco e tabelas.txt", a tabela 'colaboradores' tem: id, nome_completo, email, cargo, ativo
    $sql = "INSERT INTO colaboradores (nome_completo, email, cargo, ativo) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conexao, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sssi", $nome_completo, $email, $cargo, $ativo);

        if (mysqli_stmt_execute($stmt)) {
            $novo_colaborador_id = mysqli_insert_id($conexao);
            $logger->log('INFO', 'Novo colaborador cadastrado com sucesso.', [
                'colaborador_id' => $novo_colaborador_id,
                'nome' => $nome_completo,
                'user_id_admin' => $userID // ID do usuário do sistema que cadastrou
            ]);
            mysqli_stmt_close($stmt);
            mysqli_close($conexao);
            // Redireciona para uma página de sucesso ou de volta para a lista de colaboradores/dashboard
            header("Location: home.html?status=colab_cadastro_sucesso&nome=" . urlencode($nome_completo));
            exit;
        } else {
            $error_code = mysqli_errno($conexao);
            $error_message = mysqli_stmt_error($stmt);
            $logger->log('ERROR', 'Erro ao executar query de cadastro de colaborador.', [
                'error_code' => $error_code,
                'error_message' => $error_message,
                'nome' => $nome_completo,
                'user_id_admin' => $userID
            ]);
            $user_message = "Erro ao cadastrar o colaborador.";
            if ($error_code == 1062) { // Entrada duplicada para um campo UNIQUE (provavelmente email)
                 $user_message = "Erro ao cadastrar: O e-mail informado já existe para outro colaborador.";
            }
            mysqli_stmt_close($stmt);
            mysqli_close($conexao);
            header("Location: cadastrar_colaborador.html?status=erro&msg=" . urlencode($user_message));
            exit;
        }
    } else {
        $error_message = mysqli_error($conexao);
        $logger->log('ERROR', 'Erro ao preparar query de cadastro de colaborador.', ['error_message' => $error_message, 'user_id_admin' => $userID]);
        mysqli_close($conexao);
        header("Location: cadastrar_colaborador.html?status=erro&msg=" . urlencode("Erro no sistema ao tentar preparar o cadastro."));
        exit;
    }

} else {
    $logger->log('WARNING', 'Acesso inválido (não POST) à página de processamento de cadastro de colaborador.', ['user_id_admin' => $userID]);
    // Redireciona ou mostra erro
    header("Location: cadastrar_colaborador.html?status=erro&msg=" . urlencode("Acesso inválido."));
    exit;
}

if (isset($conexao) && $conexao) { // Garante que a conexão seja fechada se o script sair inesperadamente antes do fechamento explícito
    mysqli_close($conexao);
}
