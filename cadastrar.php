<?php
// cadastrar.php

require_once __DIR__ . '/config.php'; // Inclui configurações e inicia sessão
require_once __DIR__ . '/conexao.php'; // Garante que $conexao está disponível e configurado para SQLSRV
require_once __DIR__ . '/LogHelper.php';
require_once __DIR__ . '/EmailHelper.php';

$logger = new LogHelper($conexao);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_completo = isset($_POST['nome_completo']) ? trim($_POST['nome_completo']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
    $senha_digitada = isset($_POST['senha']) ? $_POST['senha'] : '';

    // Com prepared statements, a sanitização direta com mysqli_real_escape_string não é necessária.
    // A parametrização já cuida disso.

    if (empty($nome_completo) || empty($email) || empty($usuario) || empty($senha_digitada)) {
        $logger->log('WARNING', 'Tentativa de cadastro com campos obrigatórios vazios.', ['post_data' => $_POST]);
        echo "Erro: Todos os campos são obrigatórios.";
        if ($conexao) sqlsrv_close($conexao);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $logger->log('WARNING', 'Tentativa de cadastro com e-mail inválido.', ['email' => $email]);
        echo "Erro: Formato de e-mail inválido.";
        if ($conexao) sqlsrv_close($conexao);
        exit;
    }

    $senha_hash = password_hash($senha_digitada, PASSWORD_DEFAULT);
    // Adiciona OUTPUT INSERTED.id (ou o nome da sua coluna de identidade) para pegar o ID
    $sql = "INSERT INTO usuarios (nome_completo, email, usuario, senha, ativo) OUTPUT INSERTED.id VALUES (?, ?, ?, ?, 1)";
    $params = array($nome_completo, $email, $usuario, $senha_hash);
    
    // Para INSERT com OUTPUT, usamos sqlsrv_query diretamente ou sqlsrv_prepare + sqlsrv_execute.
    // sqlsrv_query é mais direto se você não precisa reexecutar o mesmo handle de statement.
    $stmt = sqlsrv_query($conexao, $sql, $params);

    if ($stmt) {
        $novo_usuario_id = null;
        // Para pegar o ID da cláusula OUTPUT
        if (sqlsrv_fetch($stmt) === true) { // Tenta buscar a primeira linha do resultado (que contém o ID)
            $novo_usuario_id = sqlsrv_get_field($stmt, 0); // Pega o valor da primeira coluna (o ID)
        }
        
        // sqlsrv_next_result($stmt); // Necessário se houvesse mais de um resultado (não é o caso aqui para um simples INSERT OUTPUT)


        if ($novo_usuario_id) {
            $logger->log('INFO', 'Novo usuário cadastrado com sucesso.', ['usuario_id' => $novo_usuario_id, 'usuario' => $usuario, 'email' => $email]);

            if (EmailHelper::sendRegistrationConfirmationEmail($email, $nome_completo)) {
                $logger->log('INFO', 'E-mail de confirmação de cadastro enviado.', ['usuario_id' => $novo_usuario_id, 'email' => $email]);
            } else {
                $logger->log('ERROR', 'Falha ao enviar e-mail de confirmação de cadastro.', ['usuario_id' => $novo_usuario_id, 'email' => $email]);
            }

            sqlsrv_free_stmt($stmt);
            if ($conexao) sqlsrv_close($conexao);
            header("Location: index.html?status=cadastro_sucesso_email_enviado");
            exit;
        } else {
            // Se $novo_usuario_id não foi obtido, algo deu errado na execução ou no fetch do OUTPUT
            $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
            $error_code = isset($errors[0]['code']) ? $errors[0]['code'] : null;
            $error_message_log = isset($errors[0]['message']) ? $errors[0]['message'] : 'Erro desconhecido ao inserir ou obter ID.';
            
            $logger->log('ERROR', 'Erro ao executar query de cadastro ou obter ID.', ['errors_sqlsrv' => $errors, 'usuario' => $usuario, 'email' => $email]);
            
            $user_display_error = "Erro ao cadastrar o usuário.";
            if ($error_code == 2627 || $error_code == 2601) { // Códigos de erro para violação de chave única
                 $user_display_error = "Erro ao cadastrar: O e-mail ou nome de usuário já existe.";
            } else {
                 $user_display_error .= " Detalhe: " . htmlentities($error_message_log);
            }
            echo $user_display_error;
        }
        if (isset($stmt)) sqlsrv_free_stmt($stmt);

    } else {
        $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
        $error_message_log = isset($errors[0]['message']) ? $errors[0]['message'] : 'Erro ao preparar query.';
        $logger->log('ERROR', 'Erro ao preparar/executar query de cadastro.', ['errors_sqlsrv' => $errors]);
        echo "Erro no sistema ao tentar preparar o cadastro: " . htmlentities($error_message_log);
    }

} else {
    $logger->log('WARNING', 'Acesso inválido (não POST) à página de cadastro.');
    echo "Acesso inválido à página de cadastro.";
}

if (isset($conexao) && $conexao) sqlsrv_close($conexao); // Garante que a conexão seja fechada se ainda aberta
