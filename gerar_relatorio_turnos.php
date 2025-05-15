<?php
// gerar_relatorio_turnos.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // SQLSRV
require_once __DIR__ . '/LogHelper.php';

$logger = new LogHelper($conexao);
header('Content-Type: application/json');

// --- Verificação de Sessão e CSRF Token ---
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Sessão inválida.']); exit;
}
$userId = $_SESSION['usuario_id'];

if (!isset($_GET['csrf_token']) || !isset($_SESSION['csrf_token_reports']) || !hash_equals($_SESSION['csrf_token_reports'], $_GET['csrf_token'])) {
    $logger->log('SECURITY_WARNING', 'Falha CSRF token em gerar_relatorio_turnos (GET).', ['user_id' => $userId]);
    // Não seremos tão estritos com GET para este exemplo, mas em produção considere o impacto.
}
$_SESSION['csrf_token_reports'] = bin2hex(random_bytes(32));
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

// Ajustes para SQL Server: FORMAT para data e hora
$sql = "SELECT 
            t.data, /* Data original para cálculo preciso da duração */
            FORMAT(t.data, 'dd/MM/yyyy') AS data_formatada, 
            t.colaborador, 
            t.hora_inicio,
            t.hora_fim,
            FORMAT(CAST(t.hora_inicio AS TIME), 'HH:mm') AS hora_inicio_formatada, /* Cast para TIME antes de formatar se hora_inicio for DATETIME */
            FORMAT(CAST(t.hora_fim AS TIME), 'HH:mm') AS hora_fim_formatada     /* Cast para TIME antes de formatar se hora_fim for DATETIME */
        FROM 
            turnos t
        WHERE 
            t.data BETWEEN ? AND ? 
            AND t.criado_por_usuario_id = ? ";

$params_query = array($data_inicio_obj->format('Y-m-d'), $data_fim_obj->format('Y-m-d'), $userId);

if (!empty($colaborador_filtro)) {
    $sql .= " AND t.colaborador = ? ";
    $params_query[] = $colaborador_filtro;
}
$sql .= " ORDER BY t.data ASC, t.colaborador ASC, t.hora_inicio ASC";

// Para SELECTs, sqlsrv_query é frequentemente usado.
// Se for usar sqlsrv_prepare, o fluxo é sqlsrv_prepare -> sqlsrv_execute
$stmt = sqlsrv_query($conexao, $sql, $params_query);

if ($stmt === false) {
    $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
    $logger->log('ERROR', 'Falha ao executar query para gerar relatório.', ['errors' => $errors, 'user_id' => $userId]);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao executar consulta.', 'csrf_token' => $novoCsrfTokenParaCliente]); 
    if ($conexao) sqlsrv_close($conexao);
    exit;
}

$turnos_db = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $turnos_db[] = $row;
}
sqlsrv_free_stmt($stmt);

$turnos_processados = [];
$total_geral_horas_decimal = 0;

foreach ($turnos_db as $turno_db_row) {
    $duracao_decimal = 0;
    $duracao_formatada_str = "00h00min";

    // As colunas hora_inicio e hora_fim já vêm do banco.
    // Se forem do tipo TIME no SQL Server, o objeto DateTime pode ser criado diretamente.
    // Se forem DATETIME, a conversão para string 'HH:mm' já foi feita na query.
    // Para cálculo, precisamos da data completa.
    if ($turno_db_row['hora_inicio'] && $turno_db_row['hora_fim']) {
        try {
            // $turno_db_row['data'] é um objeto DateTime se o tipo da coluna no SQL Server for DATE/DATETIME
            // Se for string, precisaremos converter. Assumindo que o driver retorna como objeto DateTime para colunas DATE/DATETIME
            $data_original_turno = ($turno_db_row['data'] instanceof DateTimeInterface) ? $turno_db_row['data']->format('Y-m-d') : $turno_db_row['data'];
            
            // $turno_db_row['hora_inicio'] e ['hora_fim'] são objetos DateTime se o tipo no SQL Server for TIME.
            // Se forem strings 'HH:mm:ss.microssegundos' (comum para tipo TIME do SQL Server via sqlsrv)
            $hora_inicio_str = ($turno_db_row['hora_inicio'] instanceof DateTimeInterface) ? $turno_db_row['hora_inicio']->format('H:i:s') : $turno_db_row['hora_inicio'];
            $hora_fim_str = ($turno_db_row['hora_fim'] instanceof DateTimeInterface) ? $turno_db_row['hora_fim']->format('H:i:s') : $turno_db_row['hora_fim'];

            $inicio = new DateTime($data_original_turno . ' ' . $hora_inicio_str);
            $fim = new DateTime($data_original_turno . ' ' . $hora_fim_str);

            if ($fim <= $inicio) {
                $fim->add(new DateInterval('P1D')); 
            }
            $intervalo = $inicio->diff($fim);
            $duracao_decimal = $intervalo->h + ($intervalo->i / 60.0);
            if ($intervalo->days > 0) { 
                $duracao_decimal += $intervalo->days * 24;
            }
            
            $total_geral_horas_decimal += $duracao_decimal;
            $duracao_formatada_str = sprintf('%02dh%02dmin', $intervalo->h + ($intervalo->days * 24), $intervalo->i);

        } catch (Exception $e) {
            $logger->log('WARNING', 'Erro ao calcular duração de turno para relatório.', ['turno_data' => $turno_db_row, 'error' => $e->getMessage(), 'user_id' => $userId]);
        }
    }

    $turnos_processados[] = [
        'data_formatada'        => $turno_db_row['data_formatada'],
        'colaborador'           => $turno_db_row['colaborador'],
        'hora_inicio_formatada' => $turno_db_row['hora_inicio_formatada'], // Já formatado na query SQL
        'hora_fim_formatada'    => $turno_db_row['hora_fim_formatada'],   // Já formatado na query SQL
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

if ($conexao) {
    sqlsrv_close($conexao);
}
