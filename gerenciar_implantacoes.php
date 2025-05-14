<?php
// gerenciar_implantacoes.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/LogHelper.php';

$logger = new LogHelper($conexao);
header('Content-Type: application/json');

// --- Funções Utilitárias (adaptadas de salvar_turnos.php) ---
function formatarDataParaMysqlImpl($dataStr) {
    if (empty($dataStr)) return null;
    // Assume que o JS envia YYYY-MM-DD diretamente do input type="date"
    try {
        $dt = new DateTime($dataStr);
        return $dt->format('Y-m-d');
    } catch (Exception $e) {
        return null;
    }
}

// --- Verificação de Sessão e CSRF Token ---
$novoCsrfTokenParaCliente = null;
$csrfTokenSessionKey = 'csrf_token_implantacoes'; // Token específico para esta funcionalidade

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado. Sessão inválida.']); exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $logger->log('ERROR', 'JSON de entrada inválido (CSRF check implantações).', ['user_id' => $_SESSION['usuario_id'] ?? null, 'json_error' => json_last_error_msg()]);
        echo json_encode(['success' => false, 'message' => 'Requisição inválida (JSON).']); exit;
    }
    if (!isset($input['csrf_token']) || !isset($_SESSION[$csrfTokenSessionKey]) || !hash_equals($_SESSION[$csrfTokenSessionKey], $input['csrf_token'])) {
        $logger->log('SECURITY_WARNING', 'Falha na validação do CSRF token (implantações).', ['user_id' => $_SESSION['usuario_id'] ?? null, 'acao' => $input['acao'] ?? 'desconhecida']);
        echo json_encode(['success' => false, 'message' => 'Erro de segurança. Por favor, recarregue a página e tente novamente.']); exit;
    }
    $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));
    $novoCsrfTokenParaCliente = $_SESSION[$csrfTokenSessionKey];

} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado.']); exit;
    }
    if (empty($_SESSION[$csrfTokenSessionKey])) { $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));  }
    $novoCsrfTokenParaCliente = $_SESSION[$csrfTokenSessionKey];
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não suportado.']); exit;
}
$userId = $_SESSION['usuario_id'];

// --- LÓGICA PARA CARREGAR IMPLANTAÇÕES (GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $anoFiltro = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT) ?: (int)date('Y');
    $mesFiltro = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: (int)date('m');

    // Buscar implantações que iniciam ou terminam no mês/ano especificado
    $sql = "SELECT id, 
                   DATE_FORMAT(data_inicio, '%Y-%m-%d') AS data_inicio, 
                   DATE_FORMAT(data_fim, '%Y-%m-%d') AS data_fim, 
                   observacoes
            FROM implantacoes 
            WHERE criado_por_usuario_id = ? 
            AND ( (YEAR(data_inicio) = ? AND MONTH(data_inicio) = ?) OR (YEAR(data_fim) = ? AND MONTH(data_fim) = ?) )
            ORDER BY data_inicio ASC";
    $stmt = mysqli_prepare($conexao, $sql);

    if (!$stmt) {
        $logger->log('ERROR', 'Erro preparar consulta GET implantações.', ['error' => mysqli_error($conexao), 'user_id' => $userId]);
        echo json_encode(['success' => false, 'message' => 'Erro ao preparar consulta.', 'csrf_token' => $novoCsrfTokenParaCliente]); exit;
    }
    mysqli_stmt_bind_param($stmt, "iiiii", $userId, $anoFiltro, $mesFiltro, $anoFiltro, $mesFiltro);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $implantacoes_carregadas = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => true, 'message' => 'Implantações carregadas.', 'data' => $implantacoes_carregadas, 'csrf_token' => $novoCsrfTokenParaCliente]);
    mysqli_close($conexao);
    exit;
}

// --- LÓGICA PARA SALVAR OU EXCLUIR IMPLANTAÇÕES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $input['acao'] ?? null;

    if ($acao === 'excluir_implantacoes') {
        $idsParaExcluir = $input['ids_implantacoes'] ?? [];
        if (empty($idsParaExcluir)) {
            echo json_encode(['success' => false, 'message' => 'Nenhum ID fornecido para exclusão.', 'csrf_token' => $novoCsrfTokenParaCliente]); exit;
        }
        $idsValidos = array_filter(array_map('intval', $idsParaExcluir), fn($id) => $id > 0);
        if (empty($idsValidos)) {
             echo json_encode(['success' => false, 'message' => 'IDs de implantação inválidos.', 'csrf_token' => $novoCsrfTokenParaCliente]); exit;
        }
        $placeholders = implode(',', array_fill(0, count($idsValidos), '?'));
        $tipos_ids = str_repeat('i', count($idsValidos));
        
        $sql_delete = "DELETE FROM implantacoes WHERE id IN ($placeholders) AND criado_por_usuario_id = ?";
        $stmt_delete = mysqli_prepare($conexao, $sql_delete);
        mysqli_stmt_bind_param($stmt_delete, $tipos_ids . 'i', ...array_merge($idsValidos, [$userId]));
        
        if (mysqli_stmt_execute($stmt_delete)) {
            $numLinhasAfetadas = mysqli_stmt_affected_rows($stmt_delete);
            $logger->log('INFO', "$numLinhasAfetadas implantação(ões) excluída(s) do BD.", ['user_id' => $userId, 'ids' => $idsValidos]);
            echo json_encode(['success' => true, 'message' => "$numLinhasAfetadas implantação(ões) excluída(s) com sucesso.", 'csrf_token' => $novoCsrfTokenParaCliente]);
        } else {
            $logger->log('ERROR', 'Falha ao executar exclusão de implantações BD.', ['user_id' => $userId, 'error' => mysqli_stmt_error($stmt_delete)]);
            echo json_encode(['success' => false, 'message' => 'Erro ao excluir implantações do banco de dados.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
        mysqli_stmt_close($stmt_delete);

    } elseif ($acao === 'salvar_implantacoes') {
        $dadosImplantacoesRecebidas = $input['implantacoes'] ?? [];
        if (empty($dadosImplantacoesRecebidas) || !is_array($dadosImplantacoesRecebidas)) {
            echo json_encode(['success'=>false, 'message'=>'Nenhum dado de implantação recebido.', 'csrf_token'=>$novoCsrfTokenParaCliente]); exit;
        }
        
        $errosOperacao = [];
        $sql_insert = "INSERT INTO implantacoes (data_inicio, data_fim, observacoes, criado_por_usuario_id) VALUES (?, ?, ?, ?)";
        $stmt_insert = mysqli_prepare($conexao, $sql_insert);
        $sql_update = "UPDATE implantacoes SET data_inicio = ?, data_fim = ?, observacoes = ? WHERE id = ? AND criado_por_usuario_id = ?";
        $stmt_update = mysqli_prepare($conexao, $sql_update);

        if (!$stmt_insert || !$stmt_update) {
            $logger->log('ERROR', 'Falha preparar statements insert/update para implantações.', ['user_id'=>$userId, 'err'=>mysqli_error($conexao)]);
            echo json_encode(['success'=>false, 'message'=>'Erro interno (prepare).', 'csrf_token'=>$novoCsrfTokenParaCliente]); exit;
        }

        foreach ($dadosImplantacoesRecebidas as $item) {
            $itemId = $item['id'] ?? null;
            $dataInicioStr = $item['data_inicio'] ?? null;
            $dataFimStr = $item['data_fim'] ?? null;
            $observacoes = isset($item['observacoes']) ? trim($item['observacoes']) : null;

            $dataInicioDb = formatarDataParaMysqlImpl($dataInicioStr);
            $dataFimDb = formatarDataParaMysqlImpl($dataFimStr);

            if (!$dataInicioDb || !$dataFimDb) {
                $errosOperacao[] = "Implantação com datas incompletas/inválidas."; continue;
            }
             if (strtotime($dataFimDb) < strtotime($dataInicioDb)) {
                $errosOperacao[] = "Data Fim não pode ser anterior à Data Início para '{$observacoes}'."; continue;
            }

            if ($itemId && !str_starts_with($itemId, "new-")) { // UPDATE
                $itemIdRealDb = (int)$itemId;
                mysqli_stmt_bind_param($stmt_update, "sssii", $dataInicioDb, $dataFimDb, $observacoes, $itemIdRealDb, $userId);
                if (!mysqli_stmt_execute($stmt_update)) $errosOperacao[] = "Erro ao ATUALIZAR implantação ID {$itemIdRealDb}: " . mysqli_stmt_error($stmt_update);
            } else { // INSERT
                mysqli_stmt_bind_param($stmt_insert, "sssi", $dataInicioDb, $dataFimDb, $observacoes, $userId);
                if (!mysqli_stmt_execute($stmt_insert)) $errosOperacao[] = "Erro ao INSERIR implantação '{$observacoes}': " . mysqli_stmt_error($stmt_insert);
            }
        }
        mysqli_stmt_close($stmt_insert); mysqli_stmt_close($stmt_update);

        if (!empty($errosOperacao)) {
             echo json_encode(['success'=>false, 'message'=>'Ocorreram erros: '.implode("; ",$errosOperacao), 'csrf_token'=>$novoCsrfTokenParaCliente]);
        } else {
            echo json_encode(['success'=>true, 'message'=>'Implantações salvas com sucesso!', 'csrf_token'=>$novoCsrfTokenParaCliente]);
        }
    } else {
        $logger->log('WARNING', 'Ação POST desconhecida em implantações.', ['acao' => $acao, 'user_id' => $userId]);
        echo json_encode(['success' => false, 'message' => 'Ação desconhecida.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
    mysqli_close($conexao);
    exit;
}
