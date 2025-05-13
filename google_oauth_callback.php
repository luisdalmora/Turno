<?php
// google_oauth_callback.php
require_once __DIR__ . '/config.php'; // Carrega config e inicia sessão
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/LogHelper.php';
require_once __DIR__ . '/GoogleCalendarHelper.php';

$logger = new LogHelper($conexao);

if (!isset($_SESSION['logado']) || !isset($_SESSION['usuario_id'])) {
    $logger->log('GCAL_ERROR', 'Callback do Google recebido, mas usuário não está logado na sessão.', ['query_params' => $_GET]);
    header('Location: index.html?erro=' . urlencode('Sessão inválida durante o callback do Google. Faça login novamente.'));
    exit;
}

$gcalHelper = new GoogleCalendarHelper($logger, $conexao); // Passa a conexão do BD

if (isset($_GET['error'])) {
    // Usuário negou o acesso ou ocorreu um erro no lado do Google
    $logger->log('GCAL_ERROR', 'Erro no callback do Google: ' . $_GET['error'], ['user_id' => $_SESSION['usuario_id'], 'error_details' => $_GET]);
    header('Location: home.html?gcal_status=error&gcal_msg=' . urlencode('Erro ao autorizar com Google Calendar: ' . $_GET['error']));
    exit;
}

if (isset($_GET['code'])) {
    $authCode = $_GET['code'];
    $accessToken = $gcalHelper->exchangeCodeForToken($authCode);

    if ($accessToken) {
        $logger->log('GCAL_SUCCESS', 'Autorização com Google Calendar bem-sucedida e token obtido.', ['user_id' => $_SESSION['usuario_id']]);
        header('Location: home.html?gcal_status=success');
        exit;
    } else {
        $logger->log('GCAL_ERROR', 'Falha ao trocar código por token após callback do Google.', ['user_id' => $_SESSION['usuario_id']]);
        header('Location: home.html?gcal_status=error&gcal_msg=' . urlencode('Falha ao obter token de acesso do Google.'));
        exit;
    }
} else {
    // Nenhum código ou erro, algo está errado
    $logger->log('GCAL_WARNING', 'Callback do Google recebido sem código ou erro.', ['user_id' => $_SESSION['usuario_id'], 'query_params' => $_GET]);
    header('Location: home.html?gcal_status=error&gcal_msg=' . urlencode('Resposta inválida do Google.'));
    exit;
}
