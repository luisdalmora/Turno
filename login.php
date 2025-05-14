<?php
// login.php

require_once __DIR__ . '/config.php'; // Já inicia a sessão
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/LogHelper.php';

$logger = new LogHelper($conexao);

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $erro_login = "";

    if (!$conexao) {
        $logger->log('CRITICAL', 'Falha na conexão com o banco de dados em login.php.', ['error' => mysqli_connect_error()]);
        // Em vez de die(), redirecionar com erro ou mostrar mensagem amigável.
        header('Location: index.html?erro=' . urlencode("Falha crítica na conexão. Contate o suporte."));
        exit;
    }

    $usuario_digitado = isset($_POST['usuario']) ? trim($_POST['usuario']) : null;
    $senha_digitada = isset($_POST['senha']) ? $_POST['senha'] : null;

    if (empty($usuario_digitado) || empty($senha_digitada)) {
        $erro_login = "Usuário e Senha são obrigatórios.";
        $logger->log('AUTH_FAILURE', $erro_login, ['usuario_digitado' => $usuario_digitado]);
    }

    if (empty($erro_login)) {
        $sql = "SELECT id, usuario, senha, nome_completo, email FROM usuarios WHERE (usuario = ? OR email = ?) AND ativo = 1 LIMIT 1";
        $stmt = mysqli_prepare($conexao, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $usuario_digitado, $usuario_digitado);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) == 1) {
                mysqli_stmt_bind_result($stmt, $db_id, $db_usuario, $db_senha_hash, $db_nome_completo, $db_email);
                mysqli_stmt_fetch($stmt);

                if (password_verify($senha_digitada, $db_senha_hash)) {
                    session_regenerate_id(true);
                    $_SESSION['usuario_id'] = $db_id;
                    $_SESSION['usuario_nome'] = $db_usuario; // Username/login
                    $_SESSION['usuario_nome_completo'] = $db_nome_completo; // Nome completo para exibição
                    $_SESSION['usuario_email'] = $db_email;
                    $_SESSION['logado'] = true;

                    $logger->log('AUTH_SUCCESS', 'Login bem-sucedido.', ['usuario_id' => $db_id, 'usuario' => $db_usuario]);

                    mysqli_stmt_close($stmt);
                    mysqli_close($conexao);

                    // ***** ATUALIZADO AQUI *****
                    header('Location: home.php'); // Redireciona para home.php
                    exit();
                } else {
                    $erro_login = "Usuário ou senha incorretos.";
                    $logger->log('AUTH_FAILURE', $erro_login, ['usuario_digitado' => $usuario_digitado, 'motivo' => 'Senha nao confere']);
                }
            } else {
                $erro_login = "Usuário ou senha incorretos."; // Ou usuário não encontrado/inativo
                $logger->log('AUTH_FAILURE', $erro_login, ['usuario_digitado' => $usuario_digitado, 'motivo' => 'Usuario nao encontrado ou inativo']);
            }
            mysqli_stmt_close($stmt);
        } else {
            $erro_login = "Erro no sistema ao processar o login. Por favor, tente novamente.";
            $logger->log('ERROR', 'Falha ao preparar statement de login.', ['error' => mysqli_error($conexao)]);
        }
    }

    if (!empty($erro_login)) {
        if (isset($conexao) && mysqli_ping($conexao)) {
            mysqli_close($conexao);
        }
        header('Location: index.html?erro=' . urlencode($erro_login));
        exit();
    }

} else {
    $logger->log('WARNING', 'Acesso inválido (não POST) à página de login.');
    if (isset($conexao) && mysqli_ping($conexao)) {
        mysqli_close($conexao);
    }
    header('Location: index.html?erro=' . urlencode("Método de requisição inválido."));
    exit();
}
