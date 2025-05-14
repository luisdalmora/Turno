<?php
require_once __DIR__ . '/config.php'; // Garante que a sessão está iniciada

// Destruir todas as variáveis de sessão.
$_SESSION = array();

// Se é desejável destruir a sessão completamente, apague também o cookie de sessão.
// Nota: Isso destruirá a sessão, e não apenas os dados da sessão!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir a sessão.
session_destroy();

// Redirecionar para a página de login
header('Location: index.html');
exit;
