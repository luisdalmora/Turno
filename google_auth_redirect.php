<?php
// google_auth_redirect.php
require_once __DIR__ . '/config.php'; // Carrega config e inicia sessão
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/LogHelper.php';
require_once __DIR__ . '/GoogleCalendarHelper.php';

if (!isset($_SESSION['logado']) || !isset($_SESSION['usuario_id'])) {
    // Usuário precisa estar logado para associar a conta Google
    header('Location: index.html?erro=' . urlencode('Você precisa estar logado para conectar com o Google Calendar.'));
    exit;
}

$logger = new LogHelper($conexao);
$gcalHelper = new GoogleCalendarHelper($logger, $conexao); // Passa a conexão do BD

$authUrl = $gcalHelper->getAuthUrl();
$logger->log('GCAL_INFO', 'Redirecionando usuário para autorização do Google.', ['user_id' => $_SESSION['usuario_id'], 'auth_url_domain' => parse_url($authUrl, PHP_URL_HOST)]);

header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
exit;
