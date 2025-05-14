<?php
// carregar_feriados.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/LogHelper.php';
require_once __DIR__ . '/GoogleCalendarHelper.php'; // Será modificado abaixo

$logger = new LogHelper($conexao);
$gcalHelper = new GoogleCalendarHelper($logger, $conexao);
header('Content-Type: application/json');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}
$userId = $_SESSION['usuario_id'];

// Parâmetros para buscar feriados (ex: para o mês atual)
$ano = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT) ?: (int)date('Y');
$mes = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: (int)date('m');

// Opcional: Adicionar CSRF token GET check se desejado
// $csrfTokenGet = $_GET['csrf_token_feriados'] ?? null;
// $csrfTokenSessionKeyFeriados = 'csrf_token_feriados_get'; // Um token diferente para essa operação GET
// if (!$csrfTokenGet || !isset($_SESSION[$csrfTokenSessionKeyFeriados]) || !hash_equals($_SESSION[$csrfTokenSessionKeyFeriados], $csrfTokenGet)) {
//    $logger->log('SECURITY_WARNING', 'Possível falha CSRF em carregar_feriados (GET).', ['user_id' => $userId]);
//    // echo json_encode(['success' => false, 'message' => 'Erro de segurança.']); exit;
// }
// // Regenerar token para a próxima requisição GET se a verificação for feita
// $_SESSION[$csrfTokenSessionKeyFeriados] = bin2hex(random_bytes(32));


$calendarId = 'pt-br.brazilian#holiday@group.v.calendar.google.com';

// Definir o período para buscar os eventos
$timeMin = new DateTimeImmutable("{$ano}-{$mes}-01T00:00:00");
$timeMax = $timeMin->modify('last day of this month')->setTime(23,59,59);

$params = [
    'orderBy' => 'startTime',
    'singleEvents' => true, // Expande eventos recorrentes
    'timeMin' => $timeMin->format(DateTime::RFC3339),
    'timeMax' => $timeMax->format(DateTime::RFC3339)
];

$eventos = $gcalHelper->listEventsFromCalendar($userId, $calendarId, $params);

if ($eventos === null) { // Indica erro na chamada à API ou token inválido
    echo json_encode(['success' => false, 'message' => 'Não foi possível buscar os feriados do Google Calendar. Verifique a conexão com o Google.']);
    exit;
}

$feriadosFormatados = [];
if (!empty($eventos)) {
    foreach ($eventos as $evento) {
        $dataFeriado = '';
        if (!empty($evento->start->date)) { // Evento de dia inteiro
            $dataFeriado = (new DateTime($evento->start->date))->format('d/m/Y');
        } elseif (!empty($evento->start->dateTime)) { // Evento com hora específica
            $dataFeriado = (new DateTime($evento->start->dateTime))->format('d/m/Y');
        }

        $feriadosFormatados[] = [
            'data' => $dataFeriado,
            'observacao' => $evento->getSummary() // Nome do feriado
            // A coluna "OBSERVAÇÕES" da imagem parece ser o próprio nome do feriado.
            // Se precisar de mais detalhes, e o evento tiver, use $evento->getDescription()
        ];
    }
}

echo json_encode(['success' => true, 'feriados' => $feriadosFormatados]);
mysqli_close($conexao);
