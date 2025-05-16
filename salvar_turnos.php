<?php
// salvar_turnos.php

require_once __DIR__ . '/config.php'; 
require_once __DIR__ . '/conexao.php'; // SQLSRV
require_once __DIR__ . '/LogHelper.php';
require_once __DIR__ . '/GoogleCalendarHelper.php'; 

$logger = new LogHelper($conexao); // LogHelper já convertido
$gcalHelper = new GoogleCalendarHelper($logger, $conexao); // GoogleCalendarHelper já convertido

header('Content-Type: application/json');

// --- Funções Utilitárias para este script ---
function formatarDataParaBanco($dataStr, $anoReferencia) { 
    if (empty($dataStr)) return null;
    $partes = explode('/', $dataStr);
    if (count($partes) < 2) return null; 

    $dia = trim($partes[0]);
    $mesInput = trim($partes[1]);
    $ano = $anoReferencia; 

    if (isset($partes[2])) { 
        $anoInputString = trim($partes[2]);
        if (strlen($anoInputString) === 4 && ctype_digit($anoInputString)) {
            $ano = $anoInputString;
        } elseif (strlen($anoInputString) === 2 && ctype_digit($anoInputString)) {
            $ano = "20" . $anoInputString; 
        }
    }

    $diaNum = ctype_digit($dia) ? sprintf('%02d', (int)$dia) : null;
    $mesNum = null;
    if (ctype_digit($mesInput)) {
        $mesNum = sprintf('%02d', (int)$mesInput);
    } else { 
        $mapaMeses = ['jan'=>'01','fev'=>'02','mar'=>'03','abr'=>'04','mai'=>'05','jun'=>'06','jul'=>'07','ago'=>'08','set'=>'09','out'=>'10','nov'=>'11','dez'=>'12'];
        $mesNum = $mapaMeses[strtolower(substr($mesInput,0,3))] ?? null;
    }

    if ($diaNum && $mesNum && checkdate((int)$mesNum, (int)$diaNum, (int)$ano)) {
        return "$ano-$mesNum-$diaNum"; 
    }
    global $logger, $userIdLogging; 
    if (isset($logger) && isset($userIdLogging)) {
       $logger->log('WARNING', 'Formato de data inválido recebido (formatarDataParaBanco).', ['data_str' => $dataStr, 'ano_ref' => $anoReferencia, 'user_id' => $userIdLogging]);
    }
    return null;
}

function formatarHoraParaBanco($horaStr) { 
    if (empty($horaStr)) return null;
    if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $horaStr)) {
        return $horaStr . ':00'; 
    }
    if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $horaStr)) {
        return $horaStr; 
    }
    global $logger, $userIdLogging;
     if (isset($logger) && isset($userIdLogging)) {
        $logger->log('WARNING', 'Formato de hora inválido recebido (formatarHoraParaBanco).', ['hora_str' => $horaStr, 'user_id' => $userIdLogging]);
    }
    return null;
}


// --- Verificação de Sessão e CSRF Token ---
$novoCsrfTokenParaCliente = null; 
$userIdLogging = $_SESSION['usuario_id'] ?? null; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado. Sessão inválida.']); exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $logger->log('ERROR', 'JSON de entrada inválido (CSRF check).', ['user_id' => $_SESSION['usuario_id'] ?? null, 'json_error' => json_last_error_msg()]);
        echo json_encode(['success' => false, 'message' => 'Requisição inválida (JSON).']); exit;
    }
    if (!isset($input['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $input['csrf_token'])) {
        $logger->log('SECURITY_WARNING', 'Falha na validação do CSRF token.', ['user_id' => $_SESSION['usuario_id'] ?? null, 'acao' => $input['acao'] ?? 'desconhecida']);
        echo json_encode(['success' => false, 'message' => 'Erro de segurança. Por favor, recarregue a página e tente novamente.']); exit;
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $novoCsrfTokenParaCliente = $_SESSION['csrf_token'];

} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado.']); exit;
    }
    if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32));  }
    $novoCsrfTokenParaCliente = $_SESSION['csrf_token'];
} else { 
    http_response_code(405); 
    echo json_encode(['success' => false, 'message' => 'Método não suportado.']); exit;
}
$userId = $_SESSION['usuario_id'];


// --- LÓGICA PARA CARREGAR TURNOS (GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $anoFiltro = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
    $mesFiltro = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT);

    if ($anoFiltro === null || $anoFiltro === false) $anoFiltro = (int)date('Y');
    if ($mesFiltro === null || $mesFiltro === false) $mesFiltro = (int)date('m');

    if ($mesFiltro < 1 || $mesFiltro > 12) { 
        echo json_encode(['success' => false, 'message' => 'Parâmetros de ano/mês inválidos.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        if ($conexao) sqlsrv_close($conexao);
        exit;
    }
    $sql = "SELECT id, FORMAT(data, 'dd/MM') AS data_formatada, data, 
                   FORMAT(CAST(hora_inicio AS TIME), 'HH:mm') AS hora_inicio, 
                   FORMAT(CAST(hora_fim AS TIME), 'HH:mm') AS hora_fim,       
                   colaborador, google_calendar_event_id, ano 
            FROM turnos 
            WHERE criado_por_usuario_id = ? AND YEAR(data) = ? AND MONTH(data) = ?
            ORDER BY data ASC, hora_inicio ASC";
    
    $params = array($userId, $anoFiltro, $mesFiltro);
    $stmt = sqlsrv_query($conexao, $sql, $params);

    if ($stmt === false) {
        $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
        $logger->log('ERROR', 'Erro ao executar consulta GET turnos (SQLSRV).', ['errors_sqlsrv' => $errors, 'user_id' => $userId]);
        echo json_encode(['success' => false, 'message' => 'Erro ao preparar consulta.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        if ($conexao) sqlsrv_close($conexao);
        exit;
    }
    
    $turnos_carregados = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $turnos_carregados[] = $row;
    }
    sqlsrv_free_stmt($stmt);

    echo json_encode(['success' => true, 'message' => 'Turnos carregados.', 'data' => $turnos_carregados, 'csrf_token' => $novoCsrfTokenParaCliente]);
    if ($conexao) sqlsrv_close($conexao);
    exit;
}

// --- LÓGICA PARA SALVAR OU EXCLUIR TURNOS (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $input['acao'] ?? null;

    if ($acao === 'excluir_turnos') {
        $idsParaExcluir = $input['ids_turnos'] ?? [];
        if (empty($idsParaExcluir)) {
            echo json_encode(['success' => false, 'message' => 'Nenhum ID fornecido para exclusão.', 'csrf_token' => $novoCsrfTokenParaCliente]); exit;
        }
        $idsValidos = array_filter(array_map('intval', $idsParaExcluir), fn($id) => $id > 0);
        if (empty($idsValidos)) {
             echo json_encode(['success' => false, 'message' => 'IDs de turno inválidos.', 'csrf_token' => $novoCsrfTokenParaCliente]); exit;
        }
        
        $placeholders = implode(',', array_fill(0, count($idsValidos), '?'));
        
        $googleAccessToken = $gcalHelper->getAccessTokenForUser($userId);

        $params_gcal_select = $idsValidos;
        array_push($params_gcal_select, $userId); // Adiciona user_id ao final dos parâmetros

        $sql_get_gcal = "SELECT google_calendar_event_id FROM turnos WHERE id IN (" . $placeholders . ") AND criado_por_usuario_id = ?";
        $stmt_gcal = sqlsrv_query($conexao, $sql_get_gcal, $params_gcal_select);

        if ($stmt_gcal) {
            while($evento_gcal = sqlsrv_fetch_array($stmt_gcal, SQLSRV_FETCH_ASSOC)){
                if ($googleAccessToken && !empty($evento_gcal['google_calendar_event_id'])) {
                    $gcalHelper->deleteEvent($userId, $evento_gcal['google_calendar_event_id']);
                }
            }
            sqlsrv_free_stmt($stmt_gcal);
        } else {
             $logger->log('ERROR', 'Falha ao buscar IDs GCal para exclusão (SQLSRV).', ['user_id' => $userId, 'errors' => sqlsrv_errors()]);
        }

        $sql_delete = "DELETE FROM turnos WHERE id IN ($placeholders) AND criado_por_usuario_id = ?";
        $params_delete = $idsValidos; 
        array_push($params_delete, $userId);   
        
        $stmt_delete = sqlsrv_prepare($conexao, $sql_delete, $params_delete); 
        
        if ($stmt_delete && sqlsrv_execute($stmt_delete)) {
            $numLinhasAfetadas = sqlsrv_rows_affected($stmt_delete);
            $logger->log('INFO', "$numLinhasAfetadas turno(s) excluído(s) do BD (SQLSRV).", ['user_id' => $userId, 'ids' => $idsValidos]);
            echo json_encode(['success' => true, 'message' => "$numLinhasAfetadas turno(s) excluído(s) com sucesso.", 'csrf_token' => $novoCsrfTokenParaCliente]);
        } else {
            $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
            $logger->log('ERROR', 'Falha ao executar exclusão de turnos BD (SQLSRV).', ['user_id' => $userId, 'errors_sqlsrv' => $errors]);
            echo json_encode(['success' => false, 'message' => 'Erro ao excluir turnos do banco de dados.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
        if($stmt_delete) sqlsrv_free_stmt($stmt_delete);

    } elseif ($acao === 'salvar_turnos') {
        $dadosTurnosRecebidos = $input['turnos'] ?? [];
        if (empty($dadosTurnosRecebidos) && !is_array($dadosTurnosRecebidos)) {
            echo json_encode(['success'=>false, 'message'=>'Nenhum dado de turno recebido.', 'data'=>[], 'csrf_token'=>$novoCsrfTokenParaCliente]); exit;
        }
        
        $googleAccessToken = $gcalHelper->getAccessTokenForUser($userId);
        $errosOperacao = [];
        $anoReferenciaTurnosSalvos = null; 
        $mesReferenciaRecarga = date('m'); // Default para o mês atual

        // ***** ALTERAÇÃO AQUI: Removida a coluna 'ano' do INSERT e UPDATE *****
        $sql_insert = "INSERT INTO turnos (data, hora_inicio, hora_fim, colaborador, google_calendar_event_id, criado_por_usuario_id) OUTPUT INSERTED.id VALUES (?, ?, ?, ?, ?, ?)";
        $sql_update = "UPDATE turnos SET data = ?, hora_inicio = ?, hora_fim = ?, colaborador = ?, google_calendar_event_id = ? WHERE id = ? AND criado_por_usuario_id = ?";

        foreach ($dadosTurnosRecebidos as $turno) {
            $turnoIdCliente = $turno['id'] ?? null; 
            $dataStr = $turno['data'] ?? null;
            // $anoForm não é mais usado diretamente no INSERT/UPDATE para a coluna 'ano'
            $anoForm = $turno['ano'] ?? date('Y'); // Ainda pode ser usado para formatarDataParaBanco
            
            $horaInicioStr = $turno['hora_inicio'] ?? null;
            $horaFimStr = $turno['hora_fim'] ?? null;
            $colaboradorNome = isset($turno['colaborador']) ? trim($turno['colaborador']) : null;
            
            if (!$anoReferenciaTurnosSalvos) $anoReferenciaTurnosSalvos = $anoForm;
            if(empty($mesReferenciaRecarga) && !empty($dataStr)) { // Define o mês de recarga com base no primeiro turno válido
                 $dataPrimeiroTurno = formatarDataParaBanco($dataStr, $anoForm);
                 if($dataPrimeiroTurno) {
                    try {
                       $dtObjRecarga = new DateTime($dataPrimeiroTurno);
                       $mesReferenciaRecarga = $dtObjRecarga->format('m');
                    } catch(Exception $e) { /* mantém o default */ }
                 }
            }


            $dataFormatadaBanco = formatarDataParaBanco($dataStr, $anoForm); 
            $horaInicioDb = formatarHoraParaBanco($horaInicioStr);       
            $horaFimDb = formatarHoraParaBanco($horaFimStr);             

            if (!$dataFormatadaBanco || !$horaInicioDb || !$horaFimDb || empty($colaboradorNome)) {
                $errosOperacao[] = "Turno para '{$colaboradorNome}' em '{$dataStr}' com dados incompletos/inválidos."; continue;
            }
            
            $inicioTs = strtotime($dataFormatadaBanco . ' ' . $horaInicioDb);
            $fimTs = strtotime($dataFormatadaBanco . ' ' . $horaFimDb);
            if ($fimTs <= $inicioTs) {
                $hFim = (int)explode(':', $horaFimStr)[0];
                $hIni = (int)explode(':', $horaInicioStr)[0];
                if (!($hFim < 6 && $hIni > 18)) { 
                    $errosOperacao[] = "Hora Fim ({$horaFimStr}) deve ser posterior à Hora Início ({$horaInicioStr}) para {$colaboradorNome} em {$dataStr}.";
                    continue;
                }
            }

            $googleEventIdParaSalvar = $turno['google_calendar_event_id_original'] ?? null; 

            if ($googleAccessToken && ($turno['gcal_sync_needed'] ?? false)) { 
                try {
                    $fusoHorario = 'America/Sao_Paulo';
                    $dateTimeInicioGCal = new DateTime($dataFormatadaBanco . ' ' . $horaInicioDb, new DateTimeZone($fusoHorario));
                    $dateTimeFimGCal = new DateTime($dataFormatadaBanco . ' ' . $horaFimDb, new DateTimeZone($fusoHorario));

                    if ($dateTimeFimGCal <= $dateTimeInicioGCal) { 
                        $dateTimeFimGCal->add(new DateInterval('P1D'));
                    }

                    $summary = "Turno: " . $colaboradorNome;
                    $description = "Turno agendado para {$colaboradorNome} de " . $dateTimeInicioGCal->format('d/m/Y H:i') . " até " . $dateTimeFimGCal->format('H:i') . ".";
                    
                    $oldGcalId = $turno['google_calendar_event_id_original'] ?? null;
                    if ($turnoIdCliente && !str_starts_with((string)$turnoIdCliente, "new-") && $oldGcalId) {
                        $gcalHelper->deleteEvent($userId, $oldGcalId); 
                    }
                    $googleEventIdParaSalvar = $gcalHelper->createEvent($userId, $summary, $description, $dateTimeInicioGCal->format(DateTime::RFC3339), $dateTimeFimGCal->format(DateTime::RFC3339), $fusoHorario);
                } catch (Exception $e) {
                    $logger->log('GCAL_ERROR', 'Exceção GCal ao salvar turno: '.$e->getMessage(), ['uid'=>$userId, 'turno_data_cliente'=>$turno]);
                    $errosOperacao[] = "Falha GCal para {$colaboradorNome} em {$dataStr}: ".substr($e->getMessage(),0,50);
                }
            }

            if ($turnoIdCliente && !str_starts_with((string)$turnoIdCliente, "new-")) { // UPDATE
                $turnoIdRealDb = (int)$turnoIdCliente;
                // ***** ALTERAÇÃO AQUI: Removido $anoForm dos parâmetros de update *****
                $params_update = array($dataFormatadaBanco, $horaInicioDb, $horaFimDb, $colaboradorNome, $googleEventIdParaSalvar, $turnoIdRealDb, $userId);
                $stmt_update = sqlsrv_prepare($conexao, $sql_update, $params_update);
                if ($stmt_update && sqlsrv_execute($stmt_update)) {
                    sqlsrv_free_stmt($stmt_update);
                } else {
                    $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
                    $errosOperacao[] = "Erro ao ATUALIZAR turno ID {$turnoIdRealDb}: " . ($errors[0]['message'] ?? 'Erro desconhecido');
                    if($stmt_update) sqlsrv_free_stmt($stmt_update);
                }
            } else { // INSERT
                // ***** ALTERAÇÃO AQUI: Removido $userId e $anoForm dos parâmetros de insert, $userId já está em criado_por_usuario_id. A coluna 'ano' é computada. *****
                $params_insert = array($dataFormatadaBanco, $horaInicioDb, $horaFimDb, $colaboradorNome, $googleEventIdParaSalvar, $userId);
                $stmt_insert = sqlsrv_query($conexao, $sql_insert, $params_insert); 
                
                if ($stmt_insert) {
                    if (sqlsrv_fetch($stmt_insert) === true) {
                        // $novoTurnoId = sqlsrv_get_field($stmt_insert, 0); 
                    } else {
                        $errors_fetch = sqlsrv_errors(SQLSRV_ERR_ALL);
                        $errosOperacao[] = "Erro ao INSERIR turno para {$colaboradorNome} em {$dataStr} (fetch ID): " . ($errors_fetch[0]['message'] ?? 'Não obteve ID');
                    }
                    sqlsrv_free_stmt($stmt_insert);
                } else {
                    $errors_insert = sqlsrv_errors(SQLSRV_ERR_ALL);
                    $errosOperacao[] = "Erro ao INSERIR turno para {$colaboradorNome} em {$dataStr}: " . ($errors_insert[0]['message'] ?? 'Erro desconhecido');
                }
            }
        }

        $anoReferenciaRecarga = $anoReferenciaTurnosSalvos ?? date('Y');
        // A lógica para $mesReferenciaRecarga já está no início do loop.
        
        $sql_recarregar = "SELECT id, FORMAT(data, 'dd/MM') AS data_formatada, data, 
                                  FORMAT(CAST(hora_inicio AS TIME), 'HH:mm') AS hora_inicio, 
                                  FORMAT(CAST(hora_fim AS TIME), 'HH:mm') AS hora_fim, 
                                  colaborador, google_calendar_event_id, ano 
                           FROM turnos WHERE criado_por_usuario_id = ? AND YEAR(data) = ? AND MONTH(data) = ? 
                           ORDER BY data ASC, hora_inicio ASC";
        $params_recarregar = array($userId, (int)$anoReferenciaRecarga, (int)$mesReferenciaRecarga);
        $stmt_recarregar = sqlsrv_query($conexao, $sql_recarregar, $params_recarregar);
        
        $turnosRetorno = [];
        if($stmt_recarregar){
            while ($row = sqlsrv_fetch_array($stmt_recarregar, SQLSRV_FETCH_ASSOC)) {
                $turnosRetorno[] = $row;
            }
            sqlsrv_free_stmt($stmt_recarregar);
        } else {
            $logger->log('ERROR', 'Falha ao recarregar turnos após salvar (SQLSRV).', ['user_id' => $userId, 'errors' => sqlsrv_errors()]);
        }

        if (!empty($errosOperacao)) {
             echo json_encode(['success'=>false, 'message'=>'Ocorreram erros: '.implode("; ",$errosOperacao), 'data'=>$turnosRetorno, 'csrf_token'=>$novoCsrfTokenParaCliente]);
        } else {
            echo json_encode(['success'=>true, 'message'=>'Turnos salvos com sucesso!', 'data'=>$turnosRetorno, 'csrf_token'=>$novoCsrfTokenParaCliente]);
        }
    } else {
        $logger->log('WARNING', 'Ação POST desconhecida em salvar_turnos.', ['acao' => $acao, 'user_id' => $userId]);
        echo json_encode(['success' => false, 'message' => 'Ação desconhecida.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
    if ($conexao) sqlsrv_close($conexao);
    exit;
}

$logger->log('ERROR', 'Método não tratado ou GET sem parâmetros válidos em salvar_turnos.', ['method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A']);
echo json_encode(['success' => false, 'message' => 'Requisição inválida.', 'csrf_token' => $novoCsrfTokenParaCliente ?? bin2hex(random_bytes(32)) ]);
if ($conexao) sqlsrv_close($conexao); 

