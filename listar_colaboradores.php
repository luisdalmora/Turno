<?php
// listar_colaboradores.php
require_once __DIR__ . '/config.php'; // Inicia sessão, carrega config
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/LogHelper.php';

$logger = new LogHelper($conexao);
header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado. Faça login.']);
    exit;
}
$userId = $_SESSION['usuario_id']; // Para auditoria ou filtros futuros, se necessário

// Token CSRF para GET - opcional, mas pode ser verificado se enviado pelo JS
// Para este GET, o token da sessão principal pode ser suficiente se o JS o enviar.
// No entanto, para consistência, o JS da página de gerenciamento pode usar o token gerado nela.
// $csrfTokenGerenciarColab = $_SESSION['csrf_token_colab_manage'] ?? null;
// if (!isset($_GET['csrf_token']) || !hash_equals($csrfTokenGerenciarColab, $_GET['csrf_token'])) {
//     $logger->log('SECURITY_WARNING', 'Falha CSRF em listar_colaboradores (GET).', ['user_id' => $userId]);
//     // Não é comum bloquear GETs por CSRF, mas pode logar ou ter uma verificação mais branda.
// }


$colaboradores = [];
// A tabela 'colaboradores' tem: id, nome_completo, email, cargo, ativo (BIT: 1 para ativo, 0 para inativo)
$sql = "SELECT id, nome_completo, email, cargo, ativo FROM colaboradores ORDER BY nome_completo ASC";
$stmt = mysqli_prepare($conexao, $sql);

if ($stmt) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        // Converte 'ativo' (BIT) para um booleano ou string mais amigável para o frontend
        $row['ativo'] = (bool)$row['ativo']; 
        $colaboradores[] = $row;
    }
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true, 'colaboradores' => $colaboradores]);
} else {
    $logger->log('ERROR', 'Falha ao preparar statement para listar colaboradores.', ['error' => mysqli_error($conexao), 'user_id' => $userId]);
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar lista de colaboradores.']);
}

mysqli_close($conexao);
?>
