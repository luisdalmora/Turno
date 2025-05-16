<?php
// logout.php
require_once __DIR__ . '/config.php'; // Garante que a sessão está iniciada para poder ser destruída

// 1. Limpar todas as variáveis de sessão.
$_SESSION = array();

// 2. Destruir o cookie de sessão, se estiver usando.
//    Isso ajuda a garantir que o cliente realmente perca a referência da sessão.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, // Define um tempo no passado para expirar o cookie
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Finalmente, destruir a sessão no servidor.
session_destroy();

// 4. Redirecionar para a página de login (index.html)
//    Adicionar um status pode ser útil para exibir uma mensagem na página de login.
 header('Location: index.html');
exit;
