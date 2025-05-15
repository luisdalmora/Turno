<?php
// listar_colaboradores.php
require_once __DIR__ . '/config.php'; 
require_once __DIR__ . '/conexao.php'; // SQLSRV
require_once __DIR__ . '/LogHelper.php';

$logger = new LogHelper($conexao);
header('Content-Type: application/json');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado. Faça login.']);
    if ($conexao) sqlsrv_close($conexao);
    exit;
}
$userId = $_SESSION['usuario_id'];

$colaboradores = [];
$sql = "SELECT id, nome_completo, email, cargo, ativo FROM colaboradores ORDER BY nome_completo ASC";

// Para SELECTs simples sem parâmetros, sqlsrv_query é direto.
// Se houvesse parâmetros, usaríamos sqlsrv_prepare e sqlsrv_execute.
$stmt = sqlsrv_query($conexao, $sql);

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // 'ativo' no SQL Server (tipo BIT) geralmente retorna 0 ou 1. Convertemos para booleano.
        $row['ativo'] = (bool)$row['ativo']; 
        $colaboradores[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    echo json_encode(['success' => true, 'colaboradores' => $colaboradores]);
} else {
    $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
    $logger->log('ERROR', 'Falha ao executar query para listar colaboradores (SQLSRV).', ['errors_sqlsrv' => $errors, 'user_id' => $userId]);
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar lista de colaboradores.']);
}

if ($conexao) {
    sqlsrv_close($conexao);
}
