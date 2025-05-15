<?php
// carregar_feriados.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // SQLSRV
require_once __DIR__ . '/LogHelper.php';
require_once __DIR__ . '/GoogleCalendarHelper.php'; // Se usa $conexao e mysqli_*, precisa ser adaptado.

$logger = new LogHelper($conexao);
$gcalHelper = new GoogleCalendarHelper($logger, $conexao); // Passa a conexão SQLSRV
header('Content-Type: application/json');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}
$userId = $_SESSION['usuario_id'];

$ano = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT) ?: (int)date('Y');
$mes = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: (int)date('m');

$calendarId = 'pt-br.brazilian#holiday@group.v.calendar.google.com';

$timeMin = new DateTimeImmutable("{$ano}-{$mes}-01T00:00:00");
$timeMax = $timeMin->modify('last day of this month')->setTime(23,59,59);

$params = [
    'orderBy' => 'startTime',
    'singleEvents' => true,
    'timeMin' => $timeMin->format(DateTime::RFC3339),
    'timeMax' => $timeMax->format(DateTime::RFC3339)
];

$eventos = $gcalHelper->listEventsFromCalendar($userId, $calendarId, $params);

if ($eventos === null) {
    echo json_encode(['success' => false, 'message' => 'Não foi possível buscar os feriados do Google Calendar. Verifique a conexão com o Google.']);
    if ($conexao) sqlsrv_close($conexao); // Fecha conexão SQLSRV
    exit;
}

$feriadosFormatados = [];
if (!empty($eventos)) {
    foreach ($eventos as $evento) {
        $dataFeriado = '';
        if (!empty($evento->start->date)) {
            $dataFeriado = (new DateTime($evento->start->date))->format('d/m/Y');
        } elseif (!empty($evento->start->dateTime)) {
            $dataFeriado = (new DateTime($evento->start->dateTime))->format('d/m/Y');
        }

        $feriadosFormatados[] = [
            'data' => $dataFeriado,
            'observacao' => $evento->getSummary()
        ];
    }
}

echo json_encode(['success' => true, 'feriados' => $feriadosFormatados]);

if ($conexao) { // Verifica se a conexão existe antes de fechar
    sqlsrv_close($conexao); // Fecha conexão SQLSRV
}
