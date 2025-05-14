<?php
// gerar_relatorio_turnos.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/LogHelper.php';

$logger = new LogHelper($conexao);
header('Content-Type: application/json');
$userId = $_SESSION['usuario_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

$data_inicio_str = $_GET['data_inicio'] ?? null;
$data_fim_str = $_GET['data_fim'] ?? null;
$colaborador_filtro = $_GET['colaborador'] ?? '';

if (empty($data_inicio_str) || empty($data_fim_str)) {
    echo json_encode(['success' => false, 'message' => 'Datas de início e fim são obrigatórias.']);
    exit;
}

try {
    $data_inicio_obj = new DateTime($data_inicio_str);
    $data_fim_obj = new DateTime($data_fim_str);
    if ($data_inicio_obj > $data_fim_obj) {
        echo json_encode(['success' => false, 'message' => 'Data de início não pode ser posterior à data de fim.']);
        exit;
    }
} catch (Exception $e) {
    $logger->log('WARNING', 'Formato de data inválido para relatório.', ['data_inicio' => $data_inicio_str, 'data_fim' => $data_fim_str, 'user_id' => $userId]);
    echo json_encode(['success' => false, 'message' => 'Formato de data inválido. Use YYYY-MM-DD.']);
    exit;
}

$sql = "SELECT 
            DATE_FORMAT(t.data, '%d/%m/%Y') AS data_formatada, 
            t.colaborador, 
            t.hora AS hora_registrada_como_duracao 
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
$sql .= " ORDER BY t.data ASC, t.colaborador ASC, t.hora ASC";

$stmt = mysqli_prepare($conexao, $sql);

if (!$stmt) {
    $logger->log('ERROR', 'Falha ao preparar statement para relatório de turnos.', ['error' => mysqli_error($conexao), 'sql' => $sql, 'user_id' => $userId]);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao preparar consulta.']);
    exit;
}

mysqli_stmt_bind_param($stmt, $types, ...$params);

if (!mysqli_stmt_execute($stmt)) {
    $logger->log('ERROR', 'Falha ao executar statement para relatório de turnos.', ['error' => mysqli_stmt_error($stmt), 'user_id' => $userId]);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao executar consulta.']);
    mysqli_stmt_close($stmt);
    exit;
}

$result = mysqli_stmt_get_result($stmt);
$turnos_data = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$turnos_processados = [];
$total_geral_horas_trabalhadas = 0;

foreach ($turnos_data as $turno) {
    $duracao_str = $turno['hora_registrada_como_duracao']; // Ex: "04:00:00"
    
    $parts = explode(':', $duracao_str);
    $horas_turno = isset($parts[0]) ? (int)$parts[0] : 0;
    $minutos_turno = isset($parts[1]) ? (int)$parts[1] : 0;
    // Segundos (parts[2]) são ignorados para este cálculo de duração em horas.

    $duracao_decimal_turno = $horas_turno + ($minutos_turno / 60.0);
    $total_geral_horas_trabalhadas += $duracao_decimal_turno;

    $turnos_processados[] = [
        'data_formatada' => $turno['data_formatada'],
        'colaborador' => $turno['colaborador'],
        'duracao_registrada_label' => sprintf('%02d:%02dh', $horas_turno, $minutos_turno)
    ];
}

echo json_encode([
    'success' => true,
    'turnos' => $turnos_processados,
    'total_horas_trabalhadas' => round($total_geral_horas_trabalhadas, 2)
]);

mysqli_close($conexao);
