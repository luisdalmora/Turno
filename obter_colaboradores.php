<?php
// obter_colaboradores.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php';
// require_once __DIR__ . '/LogHelper.php'; // Descomente se for usar logs

header('Content-Type: application/json');
// $logger = new LogHelper($conexao); // Descomente se for usar logs

// Descomente a verificação de sessão se apenas usuários logados puderem buscar esta lista
// if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
//     echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
//     exit;
// }

$colaboradores = [];
// Busca apenas colaboradores ativos para os dropdowns
$sql = "SELECT id, nome_completo FROM colaboradores WHERE ativo = 1 ORDER BY nome_completo ASC";
$result = mysqli_query($conexao, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Para o <select> na tabela de turnos, usaremos nome_completo como valor,
        // pois a coluna 'colaborador' na tabela 'turnos' armazena o nome.
        // Se você fosse usar o ID do colaborador na tabela 'turnos', retornaria 'id' aqui também
        // para ser usado como valor da option.
        $colaboradores[] = ['id' => $row['id'], 'nome_completo' => $row['nome_completo']];
    }
    echo json_encode(['success' => true, 'colaboradores' => $colaboradores]);
} else {
    error_log("Erro ao buscar colaboradores em obter_colaboradores.php: " . mysqli_error($conexao));
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar lista de colaboradores.']);
}

mysqli_close($conexao);
