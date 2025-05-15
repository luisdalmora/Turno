<?php
// login.php

require_once __DIR__ . '/config.php'; 
require_once __DIR__ . '/conexao.php'; // SQLSRV
require_once __DIR__ . '/LogHelper.php';

$logger = new LogHelper($conexao);

if (isset($_SESSION['logado']) && $_SESSION['logado'] === true) {
    header('Location: home.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $erro_login = "";

    if (!$conexao) {
        // O $conexao já vem de conexao.php (SQLSRV). Se falhou lá, já teria dado die().
        // Este log aqui é mais para um cenário inesperado onde $conexao se torna inválido.
        $logger->log('CRITICAL', 'Conexão com BD indisponível em login.php (SQLSRV).', ['error_details' => sqlsrv_errors()]);
        header('Location: index.html?erro=' . urlencode("Falha crítica na conexão. Contate o suporte."));
        exit;
    }

    $usuario_digitado = isset($_POST['usuario']) ? trim($_POST['usuario']) : null;
    $senha_digitada = isset($_POST['senha']) ? $_POST['senha'] : null;

    if (empty($usuario_digitado) || empty($senha_digitada)) {
        $erro_login = "Usuário e Senha são obrigatórios.";
    }

    if (empty($erro_login)) {
        // SQL Server usa TOP 1 em vez de LIMIT 1.
        $sql = "SELECT TOP 1 id, usuario, senha, nome_completo, email FROM usuarios WHERE (usuario = ? OR email = ?) AND ativo = 1";
        $params = array($usuario_digitado, $usuario_digitado);
        
        // Usar sqlsrv_prepare e sqlsrv_execute para queries parametrizadas
        $stmt = sqlsrv_prepare($conexao, $sql, $params);

        if ($stmt) {
            if (sqlsrv_execute($stmt)) {
                // Tentar buscar o usuário
                $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

                if ($row) { // Usuário encontrado
                    $db_id = $row['id'];
                    $db_usuario = $row['usuario'];
                    $db_senha_hash = $row['senha'];
                    $db_nome_completo = $row['nome_completo'];
                    $db_email = $row['email'];

                    if (password_verify($senha_digitada, $db_senha_hash)) {
                        session_regenerate_id(true); 
                        $_SESSION['usuario_id'] = $db_id;
                        $_SESSION['usuario_nome'] = $db_usuario; 
                        $_SESSION['usuario_nome_completo'] = $db_nome_completo; 
                        $_SESSION['usuario_email'] = $db_email; 
                        $_SESSION['logado'] = true;
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                        $logger->log('AUTH_SUCCESS', 'Login bem-sucedido (SQLSRV).', ['usuario_id' => $db_id, 'usuario' => $db_usuario]);

                        sqlsrv_free_stmt($stmt);
                        if ($conexao) sqlsrv_close($conexao);

                        header('Location: home.php');
                        exit();
                    } else {
                        $erro_login = "Usuário ou senha incorretos.";
                        $logger->log('AUTH_FAILURE', $erro_login, ['usuario_digitado' => $usuario_digitado, 'motivo' => 'Senha não confere (SQLSRV)']);
                    }
                } else { // Nenhum usuário encontrado (sqlsrv_fetch_array retornou null/false)
                    $erro_login = "Usuário ou senha incorretos, ou usuário inativo.";
                    $logger->log('AUTH_FAILURE', $erro_login, ['usuario_digitado' => $usuario_digitado, 'motivo' => 'Usuário não encontrado ou inativo (SQLSRV)']);
                }
            } else {
                 $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
                 $erro_login = "Erro no sistema ao processar o login. Tente novamente.";
                 $logger->log('ERROR', 'Falha ao executar statement de login (SQLSRV).', ['errors_sqlsrv' => $errors]);
            }
            sqlsrv_free_stmt($stmt);
        } else {
            $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
            $erro_login = "Erro no sistema ao processar o login. Tente novamente mais tarde.";
            $logger->log('ERROR', 'Falha ao preparar statement de login (SQLSRV).', ['errors_sqlsrv' => $errors]);
        }
    }

    if (!empty($erro_login)) {
        if ($conexao) sqlsrv_close($conexao);
        header('Location: index.html?erro=' . urlencode($erro_login));
        exit();
    }

} else { 
    if ($conexao) sqlsrv_close($conexao);
    header('Location: index.html');
    exit();
}
