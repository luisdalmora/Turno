<?php
// cadastrar.php

require_once __DIR__ . '/config.php'; // Inclui configurações e inicia sessão
require_once __DIR__ . '/conexao.php'; // Garante que $conexao está disponível
require_once __DIR__ . '/LogHelper.php';
require_once __DIR__ . '/EmailHelper.php';

$logger = new LogHelper($conexao);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_completo = isset($_POST['nome_completo']) ? trim($_POST['nome_completo']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
    $senha_digitada = isset($_POST['senha']) ? $_POST['senha'] : '';

    // Sanitização básica (mysqli_real_escape_string é melhor usado com prepared statements)
    // Para prepared statements, a sanitização ocorre no bind_param
    $nome_completo_clean = mysqli_real_escape_string($conexao, $nome_completo);
    $email_clean = mysqli_real_escape_string($conexao, $email);
    $usuario_clean = mysqli_real_escape_string($conexao, $usuario);


    if (empty($nome_completo) || empty($email) || empty($usuario) || empty($senha_digitada)) {
        $logger->log('WARNING', 'Tentativa de cadastro com campos obrigatórios vazios.', ['post_data' => $_POST]);
        echo "Erro: Todos os campos são obrigatórios.";
        mysqli_close($conexao);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $logger->log('WARNING', 'Tentativa de cadastro com e-mail inválido.', ['email' => $email]);
        echo "Erro: Formato de e-mail inválido.";
        mysqli_close($conexao);
        exit;
    }

    $senha_hash = password_hash($senha_digitada, PASSWORD_DEFAULT);
    $sql = "INSERT INTO usuarios (nome_completo, email, usuario, senha, ativo) VALUES (?, ?, ?, ?, 1)";
    $stmt = mysqli_prepare($conexao, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssss", $nome_completo, $email, $usuario, $senha_hash);

        if (mysqli_stmt_execute($stmt)) {
            $novo_usuario_id = mysqli_insert_id($conexao); // Pega o ID do usuário recém-cadastrado
            $logger->log('INFO', 'Novo usuário cadastrado com sucesso.', ['usuario_id' => $novo_usuario_id, 'usuario' => $usuario, 'email' => $email]);

            // Enviar e-mail de confirmação
            if (EmailHelper::sendRegistrationConfirmationEmail($email, $nome_completo)) {
                $logger->log('INFO', 'E-mail de confirmação de cadastro enviado.', ['usuario_id' => $novo_usuario_id, 'email' => $email]);
            } else {
                $logger->log('ERROR', 'Falha ao enviar e-mail de confirmação de cadastro.', ['usuario_id' => $novo_usuario_id, 'email' => $email]);
            }

            mysqli_stmt_close($stmt);
            mysqli_close($conexao);
            header("Location: index.html?status=cadastro_sucesso_email_enviado");
            exit;
        } else {
            $error_code = mysqli_errno($conexao);
            $error_message = mysqli_stmt_error($stmt);
            $logger->log('ERROR', 'Erro ao executar query de cadastro.', ['error_code' => $error_code, 'error_message' => $error_message, 'usuario' => $usuario, 'email' => $email]);
            if ($error_code == 1062) {
                 echo "Erro ao cadastrar: O e-mail ou nome de usuário já existe.";
            } else {
                 echo "Erro ao cadastrar o usuário: " . $error_message;
            }
        }
        if (isset($stmt)) mysqli_stmt_close($stmt); // Garante que o statement seja fechado se ainda existir
    } else {
        $error_message = mysqli_error($conexao);
        $logger->log('ERROR', 'Erro ao preparar query de cadastro.', ['error_message' => $error_message]);
        echo "Erro no sistema ao tentar preparar o cadastro: " . $error_message;
    }

} else {
    $logger->log('WARNING', 'Acesso inválido (não POST) à página de cadastro.');
    echo "Acesso inválido à página de cadastro.";
}

if (isset($conexao)) mysqli_close($conexao);
