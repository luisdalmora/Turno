<?php
// gerar_relatorio_turnos.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/LogHelper.php';

$logger = new LogHelper($conexao);
header('Content-Type: application/json');

// --- Verificação de Sessão e CSRF Token ---
// Para GET, CSRF pode ser opcional ou vir via parâmetro, mas para ações POST seria no corpo.
// Como este é um GET para buscar dados, a proteção CSRF é menos crítica do que para ações de escrita.
// No entanto, para consistência, se um token for enviado, ele pode ser validado.
// Por simplicidade, vamos focar na autenticação de sessão para este GET.

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Sessão inválida.']);
    exit;
}
$userId = $_SESSION['usuario_id'];

// Se você decidir validar CSRF para GET também (requer que o cliente sempre envie):
/*
if (!isset($_GET['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
    $logger->log('SECURITY_WARNING', 'Falha CSRF token em gerar_relatorio_turnos (GET).', ['user_id' => $userId]);
    echo json_encode(['success' => false, 'message' => 'Erro de segurança.']);
    exit;
}
*/
// Para GET, geralmente não se regenera o token da sessão, mas se for POST, sim.
// Vamos assumir que o token CSRF principal para POSTs está no formulário da página,
// e o JS o envia. Esta resposta JSON pode incluir o token atualizado se o JS precisar dele.
$csrfTokenAtual = $_SESSION['csrf_token'] ?? null;


$data_inicio_str = $_GET['data_inicio'] ?? null;
$data_fim_str = $_GET['data_fim'] ?? null;
$colaborador_filtro = $_GET['colaborador'] ?? '';

if (empty($data_inicio_str) || empty($data_fim_str)) {
    echo json_encode(['success' => false, 'message' => 'Datas de início e fim são obrigatórias.', 'csrf_token' => $csrfTokenAtual]);
    exit;
}

try {
    $data_inicio_obj = new DateTime($data_inicio_str);
    $data_fim_obj = new DateTime($data_fim_str);
    if ($data_inicio_obj > $data_fim_obj) {
        echo json_encode(['success' => false, 'message' => 'Data de início não pode ser posterior à data de fim.', 'csrf_token' => $csrfTokenAtual]);
        exit;
    }
} catch (Exception $e) {
    $logger->log('WARNING', 'Formato de data inválido para relatório.', ['data_inicio' => $data_inicio_str, 'data_fim' => $data_fim_str, 'user_id' => $userId]);
    echo json_encode(['success' => false, 'message' => 'Formato de data inválido (esperado YYYY-MM-DD).', 'csrf_token' => $csrfTokenAtual]);
    exit;
}

$sql = "SELECT 
            DATE_FORMAT(t.data, '%d/%m/%Y') AS data_formatada, 
            t.colaborador, 
            TIME_FORMAT(t.hora_inicio, '%H:%i') AS hora_inicio_formatada,
            TIME_FORMAT(t.hora_fim, '%H:%i') AS hora_fim_formatada,
            t.hora_inicio, /* Para cálculo da duração */
            t.hora_fim    /* Para cálculo da duração */
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
    echo json_encode(['success' => false, 'message' => 'Erro interno ao preparar consulta.', 'csrf_token' => $csrfTokenAtual]);
    exit;
}

mysqli_stmt_bind_param($stmt, $types, ...$params);
if (!mysqli_stmt_execute($stmt)) {
    $logger->log('ERROR', 'Falha ao executar statement para gerar relatório.', ['error' => mysqli_stmt_error($stmt), 'user_id' => $userId]);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao executar consulta.', 'csrf_token' => $csrfTokenAtual]);
    mysqli_stmt_close($stmt);
    exit;
}

$result = mysqli_stmt_get_result($stmt);
$turnos_data = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$turnos_processados = [];
$total_geral_horas_decimal = 0;
$total_turnos_no_periodo = 0;

foreach ($turnos_data as $turno) {
    $duracao_decimal = 0;
    if ($turno['hora_inicio'] && $turno['hora_fim']) {
        $inicio = new DateTime($turno['data'] . ' ' . $turno['hora_inicio']); // Usa data original para cálculo preciso
        $fim = new DateTime($turno['data'] . ' ' . $turno['hora_fim']);
        if ($fim <= $inicio) { // Turno atravessa meia-noite
            $fim->add(new DateInterval('P1D'));
        }
        $intervalo = $inicio->diff($fim);
        $duracao_decimal = $intervalo->h + ($intervalo->i / 60.0);
        $total_geral_horas_decimal += $duracao_decimal;
    }
    $total_turnos_no_periodo++;

    $turnos_processados[] = [
        'data_formatada' => $turno['data_formatada'],
        'colaborador' => $turno['colaborador'],
        'hora_inicio_formatada' => $turno['hora_inicio_formatada'],
        'hora_fim_formatada' => $turno['hora_fim_formatada'],
        'duracao_formatada' => $duracao_decimal > 0 ? sprintf('%02dh%02dmin', floor($duracao_decimal), ($duracao_decimal * 60) % 60) : "00h00min"
    ];
}

echo json_encode([
    'success' => true,
    'turnos' => $turnos_processados,
    'total_geral_horas' => round($total_geral_horas_decimal, 2),
    'total_turnos' => $total_turnos_no_periodo,
    'csrf_token' => $csrfTokenAtual // Envia o token CSRF atual para o cliente (pode não ser necessário para GET)
]);

mysqli_close($conexao);
