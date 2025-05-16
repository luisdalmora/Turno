<?php
// gerenciar_implantacoes.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // Deve fornecer a conexão SQLSRV na variável $conexao
require_once __DIR__ . '/LogHelper.php';

$logger = new LogHelper($conexao); // $conexao aqui é a conexão SQLSRV
header('Content-Type: application/json');

// --- Funções Utilitárias ---
function formatarDataParaBancoImpl($dataStr) {
    if (empty($dataStr)) return null;
    // Assume que o JS envia YYYY-MM-DD diretamente do input type="date"
    try {
        $dt = new DateTime($dataStr);
        return $dt->format('Y-m-d'); // SQL Server entende 'Y-m-d'
    } catch (Exception $e) {
        return null;
    }
}

// --- Verificação de Sessão e CSRF Token ---
$novoCsrfTokenParaCliente = null;
$csrfTokenSessionKey = 'csrf_token_implantacoes';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado. Sessão inválida.']); 
        if ($conexao) sqlsrv_close($conexao);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $logger->log('ERROR', 'JSON de entrada inválido (CSRF check implantações).', ['user_id' => $_SESSION['usuario_id'] ?? null, 'json_error' => json_last_error_msg()]);
        echo json_encode(['success' => false, 'message' => 'Requisição inválida (JSON).']); 
        if ($conexao) sqlsrv_close($conexao);
        exit;
    }
    if (!isset($input['csrf_token']) || !isset($_SESSION[$csrfTokenSessionKey]) || !hash_equals($_SESSION[$csrfTokenSessionKey], $input['csrf_token'])) {
        $logger->log('SECURITY_WARNING', 'Falha na validação do CSRF token (implantações).', ['user_id' => $_SESSION['usuario_id'] ?? null, 'acao' => $input['acao'] ?? 'desconhecida']);
        echo json_encode(['success' => false, 'message' => 'Erro de segurança. Por favor, recarregue a página e tente novamente.']); 
        if ($conexao) sqlsrv_close($conexao);
        exit;
    }
    $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));
    $novoCsrfTokenParaCliente = $_SESSION[$csrfTokenSessionKey];

} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado.']); 
        if ($conexao) sqlsrv_close($conexao);
        exit;
    }
    if (empty($_SESSION[$csrfTokenSessionKey])) { 
        $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));  
    }
    $novoCsrfTokenParaCliente = $_SESSION[$csrfTokenSessionKey];
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não suportado.']); 
    if ($conexao) sqlsrv_close($conexao);
    exit;
}
$userId = $_SESSION['usuario_id'];

// --- LÓGICA PARA CARREGAR IMPLANTAÇÕES (GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $anoFiltro = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT) ?: (int)date('Y');
    $mesFiltro = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: (int)date('m');

    // SQL Server: Usar FORMAT (SQL Server 2012+) ou CONVERT
    $sql = "SELECT id, 
                   FORMAT(data_inicio, 'yyyy-MM-dd') AS data_inicio, 
                   FORMAT(data_fim, 'yyyy-MM-dd') AS data_fim, 
                   observacoes
            FROM implantacoes 
            WHERE criado_por_usuario_id = ? 
            AND ( (YEAR(data_inicio) = ? AND MONTH(data_inicio) = ?) OR (YEAR(data_fim) = ? AND MONTH(data_fim) = ?) )
            ORDER BY data_inicio ASC";
    
    $params = [$userId, $anoFiltro, $mesFiltro, $anoFiltro, $mesFiltro];
    $stmt = sqlsrv_query($conexao, $sql, $params); // Para SELECT com parâmetros, sqlsrv_query é mais direto

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $logger->log('ERROR', 'Erro ao executar consulta GET implantações (SQLSRV).', ['error_sqlsrv' => $errors, 'user_id' => $userId]);
        echo json_encode(['success' => false, 'message' => 'Erro ao executar consulta.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        if ($conexao) sqlsrv_close($conexao);
        exit;
    }
    
    $implantacoes_carregadas = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $implantacoes_carregadas[] = $row;
    }
    sqlsrv_free_stmt($stmt);

    echo json_encode(['success' => true, 'message' => 'Implantações carregadas.', 'data' => $implantacoes_carregadas, 'csrf_token' => $novoCsrfTokenParaCliente]);
    if ($conexao) sqlsrv_close($conexao);
    exit;
}

// --- LÓGICA PARA SALVAR OU EXCLUIR IMPLANTAÇÕES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $input['acao'] ?? null;

    if ($acao === 'excluir_implantacoes') {
        $idsParaExcluir = $input['ids_implantacoes'] ?? [];
        if (empty($idsParaExcluir)) {
            echo json_encode(['success' => false, 'message' => 'Nenhum ID fornecido para exclusão.', 'csrf_token' => $novoCsrfTokenParaCliente]);
            if ($conexao) sqlsrv_close($conexao);
            exit;
        }
        $idsValidos = array_filter(array_map('intval', $idsParaExcluir), fn($id) => $id > 0);
        if (empty($idsValidos)) {
             echo json_encode(['success' => false, 'message' => 'IDs de implantação inválidos.', 'csrf_token' => $novoCsrfTokenParaCliente]);
             if ($conexao) sqlsrv_close($conexao);
             exit;
        }
        
        $placeholders = implode(',', array_fill(0, count($idsValidos), '?'));
        $sql_delete = "DELETE FROM implantacoes WHERE id IN ($placeholders) AND criado_por_usuario_id = ?";
        
        // Adiciona o userId ao final do array de parâmetros para o AND criado_por_usuario_id = ?
        $params_delete = array_merge($idsValidos, [$userId]); 
        
        $stmt_delete = sqlsrv_prepare($conexao, $sql_delete, $params_delete); // sqlsrv_prepare não precisa dos tipos como mysqli

        if (!$stmt_delete) {
            $errors = sqlsrv_errors();
            $logger->log('ERROR', 'Falha ao preparar exclusão de implantações BD (SQLSRV).', ['user_id' => $userId, 'error_sqlsrv' => $errors]);
            echo json_encode(['success' => false, 'message' => 'Erro ao preparar exclusão de implantações.', 'csrf_token' => $novoCsrfTokenParaCliente]);
            if ($conexao) sqlsrv_close($conexao);
            exit;
        }

        if (sqlsrv_execute($stmt_delete)) {
            $numLinhasAfetadas = sqlsrv_rows_affected($stmt_delete);
            $logger->log('INFO', "$numLinhasAfetadas implantação(ões) excluída(s) do BD.", ['user_id' => $userId, 'ids' => $idsValidos]);
            echo json_encode(['success' => true, 'message' => "$numLinhasAfetadas implantação(ões) excluída(s) com sucesso.", 'csrf_token' => $novoCsrfTokenParaCliente]);
        } else {
            $errors = sqlsrv_errors();
            $logger->log('ERROR', 'Falha ao executar exclusão de implantações BD (SQLSRV).', ['user_id' => $userId, 'error_sqlsrv' => $errors]);
            echo json_encode(['success' => false, 'message' => 'Erro ao excluir implantações do banco de dados.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
        sqlsrv_free_stmt($stmt_delete);

    } elseif ($acao === 'salvar_implantacoes') {
        $dadosImplantacoesRecebidas = $input['implantacoes'] ?? [];
        if (empty($dadosImplantacoesRecebidas) || !is_array($dadosImplantacoesRecebidas)) {
            echo json_encode(['success'=>false, 'message'=>'Nenhum dado de implantação recebido.', 'csrf_token'=>$novoCsrfTokenParaCliente]);
            if ($conexao) sqlsrv_close($conexao);
            exit;
        }
        
        $errosOperacao = [];
        // SQL Server não tem auto_increment da mesma forma que MySQL com LAST_INSERT_ID() fácil.
        // Se precisar do ID inserido, geralmente se usa OUTPUT INSERTED.id ou consulta SCOPE_IDENTITY() depois.
        // Para simplicidade aqui, não estamos retornando o ID do novo item inserido.
        $sql_insert = "INSERT INTO implantacoes (data_inicio, data_fim, observacoes, criado_por_usuario_id) VALUES (?, ?, ?, ?)";
        $sql_update = "UPDATE implantacoes SET data_inicio = ?, data_fim = ?, observacoes = ? WHERE id = ? AND criado_por_usuario_id = ?";

        foreach ($dadosImplantacoesRecebidas as $item) {
            $itemId = $item['id'] ?? null;
            $dataInicioStr = $item['data_inicio'] ?? null;
            $dataFimStr = $item['data_fim'] ?? null;
            $observacoes = isset($item['observacoes']) ? trim($item['observacoes']) : null;

            $dataInicioDb = formatarDataParaBancoImpl($dataInicioStr);
            $dataFimDb = formatarDataParaBancoImpl($dataFimStr);

            if (!$dataInicioDb || !$dataFimDb) {
                $errosOperacao[] = "Implantação com datas incompletas/inválidas."; continue;
            }
             if (strtotime($dataFimDb) < strtotime($dataInicioDb)) { // strtotime funciona bem com 'Y-m-d'
                $errosOperacao[] = "Data Fim não pode ser anterior à Data Início para '{$observacoes}'."; continue;
            }
            
            // Para PHP < 8.0, use: substr($itemId, 0, strlen("new-")) !== "new-"
            $isUpdate = ($itemId && substr($itemId, 0, 4) !== "new-"); // Adaptação para str_starts_with

            if ($isUpdate) { // UPDATE
                $itemIdRealDb = (int)$itemId;
                $params_update = [$dataInicioDb, $dataFimDb, $observacoes, $itemIdRealDb, $userId];
                $stmt_update = sqlsrv_prepare($conexao, $sql_update, $params_update); // sqlsrv_prepare espera os params aqui
                 if (!$stmt_update) {
                    $errors = sqlsrv_errors();
                    $errosOperacao[] = "Erro ao PREPARAR UPDATE implantação ID {$itemIdRealDb}: " . ($errors[0]['message'] ?? 'Erro desconhecido SQLSRV');
                    continue;
                }
                if (!sqlsrv_execute($stmt_update)) {
                    $errors = sqlsrv_errors();
                    $errosOperacao[] = "Erro ao EXECUTAR UPDATE implantação ID {$itemIdRealDb}: " . ($errors[0]['message'] ?? 'Erro desconhecido SQLSRV');
                }
                if ($stmt_update) sqlsrv_free_stmt($stmt_update);

            } else { // INSERT
                $params_insert = [$dataInicioDb, $dataFimDb, $observacoes, $userId];
                $stmt_insert = sqlsrv_prepare($conexao, $sql_insert, $params_insert); // sqlsrv_prepare espera os params aqui
                 if (!$stmt_insert) {
                    $errors = sqlsrv_errors();
                    $errosOperacao[] = "Erro ao PREPARAR INSERT implantação '{$observacoes}': " . ($errors[0]['message'] ?? 'Erro desconhecido SQLSRV');
                    continue;
                }
                if (!sqlsrv_execute($stmt_insert)) {
                    $errors = sqlsrv_errors();
                    $errosOperacao[] = "Erro ao EXECUTAR INSERT implantação '{$observacoes}': " . ($errors[0]['message'] ?? 'Erro desconhecido SQLSRV');
                }
                 if ($stmt_insert) sqlsrv_free_stmt($stmt_insert);
            }
        }

        if (!empty($errosOperacao)) {
             echo json_encode(['success'=>false, 'message'=>'Ocorreram erros: '.implode("; ", $errosOperacao), 'csrf_token'=>$novoCsrfTokenParaCliente]);
        } else {
            echo json_encode(['success'=>true, 'message'=>'Implantações salvas com sucesso!', 'csrf_token'=>$novoCsrfTokenParaCliente]);
        }
    } else {
        $logger->log('WARNING', 'Ação POST desconhecida em implantações.', ['acao' => $acao, 'user_id' => $userId]);
        echo json_encode(['success' => false, 'message' => 'Ação desconhecida.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
    if ($conexao) sqlsrv_close($conexao);
    exit;
}

// Fechamento final da conexão, caso algum fluxo não tenha fechado antes (pouco provável com os exits)
if ($conexao) {
    sqlsrv_close($conexao);
}
