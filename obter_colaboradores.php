<?php
// obter_colaboradores.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php';
// require_once __DIR__ . '/LogHelper.php'; // Descomente se for usar logs aqui

header('Content-Type: application/json');
// $logger = new LogHelper($conexao); // Descomente se for usar logs aqui

// Verificar se o usuário está autenticado, se necessário para esta funcionalidade
// if (!isset($_SESSION['usuario_id'])) {
//     echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
//     exit;
// }

$colaboradores = [];
$sql = "SELECT id, nome_completo FROM colaboradores WHERE ativo = 1 ORDER BY nome_completo ASC";
$result = mysqli_query($conexao, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $colaboradores[] = $row;
    }
    echo json_encode(['success' => true, 'colaboradores' => $colaboradores]);
} else {
    // $logger->log('ERROR', 'Falha ao buscar colaboradores do BD.', ['error' => mysqli_error($conexao)]); // Descomente para logar
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar lista de colaboradores.']);
}

mysqli_close($conexao);
