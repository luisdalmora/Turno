<?php
// obter_colaboradores.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // SQLSRV
// require_once __DIR__ . '/LogHelper.php'; // Descomente se for usar logs

header('Content-Type: application/json');
// $logger = new LogHelper($conexao); // Descomente se for usar logs

// Descomente a verificação de sessão se esta informação for sensível
// if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
//     echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
//     if ($conexao) sqlsrv_close($conexao);
//     exit;
// }

$colaboradores = [];
// Busca apenas colaboradores ativos para os dropdowns
$sql = "SELECT id, nome_completo FROM colaboradores WHERE ativo = 1 ORDER BY nome_completo ASC";

// Para SELECTs simples sem parâmetros, sqlsrv_query é direto.
$stmt = sqlsrv_query($conexao, $sql);

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $colaboradores[] = $row;
    }
    sqlsrv_free_stmt($stmt); // Libera o statement
    echo json_encode(['success' => true, 'colaboradores' => $colaboradores]);
} else {
    $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
    // Se usar logger: $logger->log('ERROR', 'Falha ao buscar colaboradores do BD (SQLSRV).', ['errors_sqlsrv' => $errors]);
    error_log("Erro ao buscar colaboradores em obter_colaboradores.php (SQLSRV): " . print_r($errors, true));
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar lista de colaboradores.']);
}

if ($conexao) {
    sqlsrv_close($conexao);
}
