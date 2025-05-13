<?php
// config.php

// Configurações do Google API
define('GOOGLE_CLIENT_ID', '868632842122-ksbsm15rm7eat6oq0o186g7jmkic313e.apps.googleusercontent.com'); // Cole o Client ID do seu arquivo client_secret.json
define('GOOGLE_CLIENT_SECRET', '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'); // Cole o Client Secret
define('GOOGLE_REDIRECT_URI', 'http://localhost/turno/google_oauth_callback.php'); // Altere para a URL correta do seu ambiente
define('GOOGLE_APPLICATION_NAME', 'Sim Posto Gestao de Turnos');
define('PATH_TO_CLIENT_SECRET_JSON', __DIR__ . '/client_secret.json'); // Caminho para seu arquivo de credenciais

// Configurações de E-mail
define('EMAIL_FROM_ADDRESS', 'postosim8@gmail.com');
define('EMAIL_FROM_NAME', 'Sim Posto Sistema');

// Configurações Gerais
define('SITE_URL', 'http://localhost/turno'); // Altere para a URL base do seu site

// Habilitar exibição de erros (para desenvolvimento) - Remova ou comente em produção
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Iniciar sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
