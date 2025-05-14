<?php
// login.php

// Requer config.php PRIMEIRO para garantir que a sessão seja iniciada antes de qualquer saída.
require_once __DIR__ . '/config.php'; 
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/LogHelper.php';

$logger = new LogHelper($conexao);

// Se o usuário já estiver logado, redireciona para home.php
if (isset($_SESSION['logado']) && $_SESSION['logado'] === true) {
    header('Location: home.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $erro_login = "";

    if (!$conexao) {
        $logger->log('CRITICAL', 'Falha na conexão com o banco de dados em login.php.', ['error' => mysqli_connect_error()]);
        header('Location: index.html?erro=' . urlencode("Falha crítica na conexão. Contate o suporte."));
        exit;
    }

    $usuario_digitado = isset($_POST['usuario']) ? trim($_POST['usuario']) : null;
    $senha_digitada = isset($_POST['senha']) ? $_POST['senha'] : null;

    if (empty($usuario_digitado) || empty($senha_digitada)) {
        $erro_login = "Usuário e Senha são obrigatórios.";
        // Não precisa logar aqui, pois o erro será exibido ao usuário.
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
                    session_regenerate_id(true); // Regenera o ID da sessão para segurança
                    $_SESSION['usuario_id'] = $db_id;
                    $_SESSION['usuario_nome'] = $db_usuario; 
                    $_SESSION['usuario_nome_completo'] = $db_nome_completo; 
                    $_SESSION['usuario_email'] = $db_email; 
                    $_SESSION['logado'] = true;
                    
                    // Gerar um token CSRF inicial na sessão após o login bem-sucedido
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    $logger->log('AUTH_SUCCESS', 'Login bem-sucedido.', ['usuario_id' => $db_id, 'usuario' => $db_usuario]);

                    mysqli_stmt_close($stmt);
                    mysqli_close($conexao);

                    header('Location: home.php'); // Redireciona para home.php
                    exit();
                } else {
                    $erro_login = "Usuário ou senha incorretos.";
                    $logger->log('AUTH_FAILURE', $erro_login, ['usuario_digitado' => $usuario_digitado, 'motivo' => 'Senha não confere']);
                }
            } else {
                $erro_login = "Usuário ou senha incorretos, ou usuário inativo.";
                $logger->log('AUTH_FAILURE', $erro_login, ['usuario_digitado' => $usuario_digitado, 'motivo' => 'Usuário não encontrado ou inativo']);
            }
            mysqli_stmt_close($stmt);
        } else {
            $erro_login = "Erro no sistema ao processar o login. Tente novamente.";
            $logger->log('ERROR', 'Falha ao preparar statement de login.', ['error' => mysqli_error($conexao)]);
        }
    }

    if (!empty($erro_login)) {
        if ($conexao) mysqli_close($conexao);
        header('Location: index.html?erro=' . urlencode($erro_login));
        exit();
    }

} else { 
    // Se não for POST e não estiver logado (verificação no topo), redireciona para index.html
    // Isso também cobre o caso de alguém tentar acessar login.php diretamente via GET sem estar logado.
    if ($conexao) mysqli_close($conexao); // Fecha a conexão se foi aberta
    header('Location: index.html');
    exit();
}
