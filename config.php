<?php
// config.php

// --- Configurações de Segurança e Sessão ---
// É uma boa prática configurar o cookie de sessão de forma mais segura.
// Estas configurações devem ser chamadas ANTES de session_start().
if (session_status() == PHP_SESSION_NONE) { // Só configura se a sessão não tiver sido iniciada
    $cookieParams = [
        'lifetime' => 0, // Cookie expira quando o navegador fecha
        'path' => '/',   // Cookie disponível em todo o domínio
        // 'domain' => $_SERVER['HTTP_HOST'], // Descomente e ajuste se precisar restringir o domínio
        'secure' => isset($_SERVER['HTTPS']), // True se HTTPS, crucial para produção
        'httponly' => true, // Previne acesso ao cookie de sessão via JavaScript
        'samesite' => 'Lax' // Ajuda a proteger contra alguns tipos de ataques CSRF
    ];
    session_set_cookie_params($cookieParams);
    session_start();
}

// --- Configurações do Google API ---
define('GOOGLE_CLIENT_ID', '868632842122-nd9rc37fi8llcc5aqge2l66ijtm6i7k4.apps.googleusercontent.com'); // Mantenha seu Client ID
define('GOOGLE_CLIENT_SECRET', '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'); // Mantenha seu Client Secret
define('GOOGLE_REDIRECT_URI', 'http://localhost/turno/google_oauth_callback.php'); // Verifique se esta é a URL correta para seu ambiente
define('GOOGLE_APPLICATION_NAME', 'Sim Posto Gestao de Turnos');
define('PATH_TO_CLIENT_SECRET_JSON', __DIR__ . '/client_secret.json'); // Caminho para seu arquivo de credenciais

// --- Configurações de E-mail (se for usar o EmailHelper.php) ---
define('EMAIL_FROM_ADDRESS', 'postosim8@gmail.com');
define('EMAIL_FROM_NAME', 'Sim Posto Sistema');

// --- Configurações Gerais ---
// Defina a URL base do seu sistema. Importante para links em e-mails, etc.
// Exemplo para desenvolvimento local:
define('SITE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/turno");
// Em produção, você pode querer definir isso manualmente:
// define('SITE_URL', 'https://www.seusite.com/turno');


// --- Configurações de Erro ---
// Em desenvolvimento:
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT); // Mostra todos os erros exceto deprecated e strict notices

// Em produção, você deve configurar:
// ini_set('display_errors', 0);
// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/php_errors.log'); // Defina um caminho seguro e gravável para o log
