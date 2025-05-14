<?php
require_once __DIR__ . '/config.php'; // Inicia a sessão para poder destruí-la
$_SESSION = array(); // Limpa todas as variáveis de sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy(); // Destrói a sessão
header('Location: index.html'); // Redireciona para a página de login
exit;
