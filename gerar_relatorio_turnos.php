<?php
// gerar_relatorio_turnos.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/LogHelper.php';

$logger = new LogHelper($conexao);
header('Content-Type: application/json');

// --- Verificação de Sessão e CSRF Token (para GET neste caso) ---
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Sessão inválida.']); exit;
}
$userId = $_SESSION['usuario_id'];

// Validação CSRF para GET (opcional, mas adiciona uma camada se o token for passado na URL)
if (!isset($_GET['csrf_token']) || !isset($_SESSION['csrf_token_reports']) || !hash_equals($_SESSION['csrf_token_reports'], $_GET['csrf_token'])) {
    $logger->log('SECURITY_WARNING', 'Falha CSRF token em gerar_relatorio_turnos (GET).', ['user_id' => $userId]);
    // Não vamos ser tão estritos com GET, mas podemos logar. Em POST seria fatal.
    // echo json_encode(['success' => false, 'message' => 'Erro de segurança (token inválido).']); exit; 
}
// Para GET, geralmente não se regenera o token da sessão, mas se o cliente espera um novo:
$_SESSION['csrf_token_reports'] = bin2hex(random_bytes(32)); // Regenera para a próxima
$novoCsrfTokenParaCliente = $_SESSION['csrf_token_reports'];


$data_inicio_str = $_GET['data_inicio'] ?? null;
$data_fim_str = $_GET['data_fim'] ?? null;
$colaborador_filtro = $_GET['colaborador'] ?? '';

if (empty($data_inicio_str) || empty($data_fim_str)) {
    echo json_encode(['success' => false, 'message' => 'Datas de início e fim são obrigatórias.', 'csrf_token' => $novoCsrfTokenParaCliente]); exit;
}

try {
    $data_inicio_obj = new DateTime($data_inicio_str);
    $data_fim_obj = new DateTime($data_fim_str);
    if ($data_inicio_obj > $data_fim_obj) {
        echo json_encode(['success' => false, 'message' => 'Data de início não pode ser posterior à data de fim.', 'csrf_token' => $novoCsrfTokenParaCliente]); exit;
    }
} catch (Exception $e) {
    $logger->log('WARNING', 'Formato de data inválido para relatório.', ['get_data' => $_GET, 'user_id' => $userId]);
    echo json_encode(['success' => false, 'message' => 'Formato de data inválido (esperado YYYY-MM-DD).', 'csrf_token' => $novoCsrfTokenParaCliente]); exit;
}

$sql = "SELECT 
            t.data, /* Data original para cálculo preciso da duração */
            DATE_FORMAT(t.data, '%d/%m/%Y') AS data_formatada, 
            t.colaborador, 
            t.hora_inicio,
            t.hora_fim,
            TIME_FORMAT(t.hora_inicio, '%H:%i') AS hora_inicio_formatada,
            TIME_FORMAT(t.hora_fim, '%H:%i') AS hora_fim_formatada
        FROM 
            turnos t
        WHERE 
            t.data BETWEEN ? AND ? 
            AND t.criado_por_usuario_id = ? ";

$params = [$data_inicio_obj->format('Y-m-d'), $data_fim_obj->format('Y-m-d'), $userId];
$types = "ssi";

if (!empty($colaborador_filtro)) {
    $sql .= " AND t.colaborador = ? ";
    $params[] = $colaborador_filtro;
    $types .= "s";
}
$sql .= " ORDER BY t.data ASC, t.colaborador ASC, t.hora_inicio ASC";

$stmt = mysqli_prepare($conexao, $sql);
if (!$stmt) {
    $logger->log('ERROR', 'Falha ao preparar statement para gerar relatório.', ['error' => mysqli_error($conexao), 'user_id' => $userId]);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao preparar consulta.', 'csrf_token' => $novoCsrfTokenParaCliente]); exit;
}

mysqli_stmt_bind_param($stmt, $types, ...$params);
if (!mysqli_stmt_execute($stmt)) {
    $logger->log('ERROR', 'Falha ao executar statement para gerar relatório.', ['error' => mysqli_stmt_error($stmt), 'user_id' => $userId]);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao executar consulta.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    mysqli_stmt_close($stmt); exit;
}

$result = mysqli_stmt_get_result($stmt);
$turnos_db = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$turnos_processados = [];
$total_geral_horas_decimal = 0;

foreach ($turnos_db as $turno_db_row) {
    $duracao_decimal = 0;
    $duracao_formatada_str = "00h00min";

    if ($turno_db_row['hora_inicio'] && $turno_db_row['hora_fim']) {
        try {
            // Usar a data original do turno para construir os objetos DateTime para cálculo preciso da duração
            $data_do_turno_original = $turno_db_row['data']; // YYYY-MM-DD
            $inicio = new DateTime($data_do_turno_original . ' ' . $turno_db_row['hora_inicio']);
            $fim = new DateTime($data_do_turno_original . ' ' . $turno_db_row['hora_fim']);

            if ($fim <= $inicio) { // Turno atravessa meia-noite
                $fim->add(new DateInterval('P1D')); // Adiciona 1 dia ao horário de fim
            }
            $intervalo = $inicio->diff($fim);
            $duracao_decimal = $intervalo->h + ($intervalo->i / 60.0);
            if ($intervalo->days > 0) { // Se o intervalo tem dias (ex: turno de 24h ou mais)
                $duracao_decimal += $intervalo->days * 24;
            }
            
            $total_geral_horas_decimal += $duracao_decimal;
            $duracao_formatada_str = sprintf('%02dh%02dmin', $intervalo->h + ($intervalo->days * 24), $intervalo->i);

        } catch (Exception $e) {
            $logger->log('WARNING', 'Erro ao calcular duração de turno para relatório.', ['turno_data' => $turno_db_row, 'error' => $e->getMessage(), 'user_id' => $userId]);
            // Deixa duração como 00h00min se houver erro no cálculo
        }
    }

    $turnos_processados[] = [
        'data_formatada'        => $turno_db_row['data_formatada'],
        'colaborador'           => $turno_db_row['colaborador'],
        'hora_inicio_formatada' => $turno_db_row['hora_inicio_formatada'],
        'hora_fim_formatada'    => $turno_db_row['hora_fim_formatada'],
        'duracao_formatada'     => $duracao_formatada_str
    ];
}

echo json_encode([
    'success'             => true,
    'turnos'              => $turnos_processados,
    'total_geral_horas'   => round($total_geral_horas_decimal, 2),
    'total_turnos'        => count($turnos_processados),
    'csrf_token'          => $novoCsrfTokenParaCliente 
]);

mysqli_close($conexao);
