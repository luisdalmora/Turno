<?php
// login.php

// session_start(); // Movido para config.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/LogHelper.php';

$logger = new LogHelper($conexao);
// echo "Debug: Script login.php iniciado.<br>"; // DEBUG

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // echo "Debug: É uma requisição POST.<br>"; // DEBUG
    // var_dump($_POST); // DEBUG
    // echo "<br>";

    $erro_login = "";

    if (!$conexao) {
        $logger->log('CRITICAL', 'Falha na conexão com o banco de dados em login.php.', ['error' => mysqli_connect_error()]);
        // header('Location: index.html?erro=' . urlencode("Falha crítica na conexão. Contate o suporte."));
        exit("Execução parada devido à falha na conexão em login.php.");
    }
    // echo "Debug: Conexão com o banco parece OK.<br>"; // DEBUG

    $usuario_digitado = isset($_POST['usuario']) ? trim($_POST['usuario']) : null;
    $senha_digitada = isset($_POST['senha']) ? $_POST['senha'] : null;

    // echo "Debug: Usuário: " . htmlspecialchars($usuario_digitado ?? 'N/A') . ", Senha fornecida: " . (empty($senha_digitada) ? 'VAZIA' : 'FORNECIDA') . "<br>";

    if (empty($usuario_digitado) || empty($senha_digitada)) {
        $erro_login = "Usuário e Senha são obrigatórios.";
        $logger->log('AUTH_FAILURE', $erro_login, ['usuario_digitado' => $usuario_digitado]);
        // echo "Debug: Erro de validação - " . $erro_login . "<br>";
    }

    if (empty($erro_login)) {
        // echo "Debug: Sem erros de validação. Consultando o banco...<br>";
        $sql = "SELECT id, usuario, senha, nome_completo, email FROM usuarios WHERE (usuario = ? OR email = ?) AND ativo = 1 LIMIT 1";
        $stmt = mysqli_prepare($conexao, $sql);

        if ($stmt) {
            // echo "Debug: Statement preparado.<br>";
            mysqli_stmt_bind_param($stmt, "ss", $usuario_digitado, $usuario_digitado); // Permite login com usuário ou e-mail
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) == 1) {
                // echo "Debug: Usuário encontrado no banco.<br>";
                mysqli_stmt_bind_result($stmt, $db_id, $db_usuario, $db_senha_hash, $db_nome_completo, $db_email);
                mysqli_stmt_fetch($stmt);

                if (password_verify($senha_digitada, $db_senha_hash)) {
                    // echo "Debug: Senha verificada com sucesso! Redirecionando para home.html...<br>";
                    
                    session_regenerate_id(true); // Importante para segurança
                    $_SESSION['usuario_id'] = $db_id;
                    $_SESSION['usuario_nome'] = $db_usuario;
                    $_SESSION['usuario_nome_completo'] = $db_nome_completo;
                    $_SESSION['usuario_email'] = $db_email; // Armazenar e-mail na sessão
                    $_SESSION['logado'] = true;

                    $logger->log('AUTH_SUCCESS', 'Login bem-sucedido.', ['usuario_id' => $db_id, 'usuario' => $db_usuario]);

                    mysqli_stmt_close($stmt);
                    mysqli_close($conexao);

                    header('Location: home.html');
                    exit();
                } else {
                    $erro_login = "Usuário ou senha incorretos.";
                    $logger->log('AUTH_FAILURE', $erro_login, ['usuario_digitado' => $usuario_digitado, 'motivo' => 'Senha nao confere']);
                    // echo "Debug: " . $erro_login . "<br>";
                }
            } else {
                $erro_login = "Usuário ou senha incorretos.";
                $logger->log('AUTH_FAILURE', $erro_login, ['usuario_digitado' => $usuario_digitado, 'motivo' => 'Usuario nao encontrado ou inativo']);
                // echo "Debug: " . $erro_login . "<br>";
            }
            mysqli_stmt_close($stmt);
        } else {
            $erro_login = "Erro no sistema ao processar o login. Por favor, tente novamente.";
            $logger->log('ERROR', 'Falha ao preparar statement de login.', ['error' => mysqli_error($conexao)]);
            // echo "Debug: Erro ao preparar statement: " . mysqli_error($conexao) . "<br>";
        }
    }

    if (!empty($erro_login)) {
        // echo "Debug: Erro final antes do redirecionamento para index: " . $erro_login . "<br>";
        if (isset($conexao) && mysqli_ping($conexao)) {
            mysqli_close($conexao);
        }
        // Para ver a mensagem de debug acima, comente temporariamente o header e exit:
        header('Location: index.html?erro=' . urlencode($erro_login));
        exit();
        // exit("Execução parada devido a erro de login: " . htmlspecialchars($erro_login)); // DEBUG
    }

} else {
    $logger->log('WARNING', 'Acesso inválido (não POST) à página de login.');
    if (isset($conexao) && mysqli_ping($conexao)) {
        mysqli_close($conexao);
    }
    header('Location: index.html?erro=' . urlencode("Método de requisição inválido."));
    exit();
}

// Se chegar aqui, algo muito estranho aconteceu.
$logger->log('ERROR', 'Fim inesperado do script login.php.');
if (isset($conexao) && mysqli_ping($conexao)) {
    mysqli_close($conexao);
}
