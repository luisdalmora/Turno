<?php
// google_revoke_token.php
require_once __DIR__ . '/config.php'; // Carrega config e inicia sessão
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/LogHelper.php';
require_once __DIR__ . '/GoogleCalendarHelper.php';

$logger = new LogHelper($conexao);

if (!isset($_SESSION['logado']) || !isset($_SESSION['usuario_id'])) {
    $logger->log('GCAL_ERROR', 'Tentativa de revogar token sem usuário logado.');
    header('Location: index.html?erro=' . urlencode('Sessão inválida.'));
    exit;
}

$userId = $_SESSION['usuario_id'];
$gcalHelper = new GoogleCalendarHelper($logger, $conexao);
$gcalHelper->revokeTokenForUser($userId);

header('Location: home.php?gcal_status=disconnected');
exit;
