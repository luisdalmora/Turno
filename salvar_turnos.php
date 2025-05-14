<?php
// salvar_turnos.php

require_once __DIR__ . '/config.php'; // Inicia sessão, carrega config
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/LogHelper.php';
require_once __DIR__ . '/GoogleCalendarHelper.php'; // Assume que esta classe lida com a autenticação e chamadas à API

// Configuração de erros (idealmente, em produção, display_errors = Off)
// error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
// ini_set('display_errors', 0); 
// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/php_errors.log'); // Defina um caminho para o log de erros PHP

$logger = new LogHelper($conexao);
$gcalHelper = new GoogleCalendarHelper($logger, $conexao);

header('Content-Type: application/json');

// --- Funções Utilitárias para este script ---
function formatarDataParaMysql($dataStr, $anoReferencia) {
    if (empty($dataStr)) return null;
    $partes = explode('/', $dataStr);
    if (count($partes) < 2) return null; // Dia e Mês são obrigatórios

    $dia = trim($partes[0]);
    $mesInput = trim($partes[1]);
    $ano = $anoReferencia; // Usa o ano de referência passado

    if (isset($partes[2])) { // Se o ano for fornecido na string de data
        $anoInputString = trim($partes[2]);
        if (strlen($anoInputString) === 4 && ctype_digit($anoInputString)) {
            $ano = $anoInputString;
        } elseif (strlen($anoInputString) === 2 && ctype_digit($anoInputString)) {
            $ano = "20" . $anoInputString; // Assume século 21 para anos com 2 dígitos
        }
    }

    $diaNum = ctype_digit($dia) ? sprintf('%02d', (int)$dia) : null;
    $mesNum = null;
    if (ctype_digit($mesInput)) {
        $mesNum = sprintf('%02d', (int)$mesInput);
    } else { // Tenta converter nome do mês (abreviado pt-br)
        $mapaMeses = ['jan'=>'01','fev'=>'02','mar'=>'03','abr'=>'04','mai'=>'05','jun'=>'06','jul'=>'07','ago'=>'08','set'=>'09','out'=>'10','nov'=>'11','dez'=>'12'];
        $mesNum = $mapaMeses[strtolower(substr($mesInput,0,3))] ?? null;
    }

    if ($diaNum && $mesNum && checkdate((int)$mesNum, (int)$diaNum, (int)$ano)) {
        return "$ano-$mesNum-$diaNum";
    }
    global $logger, $userId; // Para logar o erro
    $logger->log('WARNING', 'Formato de data inválido recebido.', ['data_str' => $dataStr, 'ano_ref' => $anoReferencia, 'user_id' => $userId]);
    return null;
}

function formatarHoraParaMysql($horaStr) {
    if (empty($horaStr)) return null;
    // Valida formato HH:MM e converte para HH:MM:SS para o banco
    if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $horaStr)) {
        return $horaStr . ':00';
    }
    // Se já estiver em HH:MM:SS (improvável do input type=time, mas para robustez)
    if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $horaStr)) {
        return $horaStr;
    }
    global $logger, $userId;
    $logger->log('WARNING', 'Formato de hora inválido recebido.', ['hora_str' => $horaStr, 'user_id' => $userId]);
    return null;
}


// --- Verificação de Sessão e CSRF Token ---
$novoCsrfTokenParaCliente = null; 

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
    // Regenera token após uso bem-sucedido da validação para a ação POST
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $novoCsrfTokenParaCliente = $_SESSION['csrf_token'];

} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado.']); exit;
    }
    // Garante que GET também tenha um token para o cliente (ex: primeira carga da página)
    if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32));  }
    $novoCsrfTokenParaCliente = $_SESSION['csrf_token'];
} else { // Outros métodos não são suportados
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Método não suportado.']); exit;
}
$userId = $_SESSION['usuario_id']; // ID do usuário logado


// --- LÓGICA PARA CARREGAR TURNOS (GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $anoFiltro = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
    $mesFiltro = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT);

    // Se ano ou mês não forem passados, usa o ano e mês atuais
    if ($anoFiltro === null || $anoFiltro === false) $anoFiltro = (int)date('Y');
    if ($mesFiltro === null || $mesFiltro === false) $mesFiltro = (int)date('m');


    if ($mesFiltro < 1 || $mesFiltro > 12) { // Validação básica
        echo json_encode(['success' => false, 'message' => 'Parâmetros de ano/mês inválidos.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        exit;
    }

    $sql = "SELECT id, DATE_FORMAT(data, '%d/%m') AS data_formatada, data, 
                   TIME_FORMAT(hora_inicio, '%H:%i') AS hora_inicio, 
                   TIME_FORMAT(hora_fim, '%H:%i') AS hora_fim, 
                   colaborador, google_calendar_event_id 
            FROM turnos 
            WHERE criado_por_usuario_id = ? AND YEAR(data) = ? AND MONTH(data) = ?
            ORDER BY data ASC, hora_inicio ASC";
    $stmt = mysqli_prepare($conexao, $sql);

    if (!$stmt) {
        $logger->log('ERROR', 'Erro preparar consulta GET turnos.', ['error' => mysqli_error($conexao), 'user_id' => $userId]);
        echo json_encode(['success' => false, 'message' => 'Erro ao preparar consulta.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        exit;
    }
    mysqli_stmt_bind_param($stmt, "iii", $userId, $anoFiltro, $mesFiltro);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $turnos_carregados = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => true, 'message' => 'Turnos carregados.', 'data' => $turnos_carregados, 'csrf_token' => $novoCsrfTokenParaCliente]);
    mysqli_close($conexao);
    exit;
}

// --- LÓGICA PARA SALVAR OU EXCLUIR TURNOS (POST) ---
// $input já foi decodificado e CSRF token validado
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
        $tipos_ids = str_repeat('i', count($idsValidos));
        
        $googleAccessToken = $gcalHelper->getAccessTokenForUser($userId);

        $sql_get_gcal = "SELECT google_calendar_event_id FROM turnos WHERE id IN ($placeholders) AND criado_por_usuario_id = ?";
        $stmt_gcal = mysqli_prepare($conexao, $sql_get_gcal);
        mysqli_stmt_bind_param($stmt_gcal, $tipos_ids . 'i', ...array_merge($idsValidos, [$userId]));
        mysqli_stmt_execute($stmt_gcal);
        $result_gcal_ids = mysqli_stmt_get_result($stmt_gcal);
        while($evento_gcal = mysqli_fetch_assoc($result_gcal_ids)){
            if ($googleAccessToken && !empty($evento_gcal['google_calendar_event_id'])) {
                $gcalHelper->deleteEvent($userId, $evento_gcal['google_calendar_event_id']);
            }
        }
        mysqli_stmt_close($stmt_gcal);

        $sql_delete = "DELETE FROM turnos WHERE id IN ($placeholders) AND criado_por_usuario_id = ?";
        $stmt_delete = mysqli_prepare($conexao, $sql_delete);
        mysqli_stmt_bind_param($stmt_delete, $tipos_ids . 'i', ...array_merge($idsValidos, [$userId]));
        
        if (mysqli_stmt_execute($stmt_delete)) {
            $numLinhasAfetadas = mysqli_stmt_affected_rows($stmt_delete);
            $logger->log('INFO', "$numLinhasAfetadas turno(s) excluído(s) do BD.", ['user_id' => $userId, 'ids' => $idsValidos]);
            echo json_encode(['success' => true, 'message' => "$numLinhasAfetadas turno(s) excluído(s) com sucesso.", 'csrf_token' => $novoCsrfTokenParaCliente]);
        } else {
            $logger->log('ERROR', 'Falha ao executar exclusão de turnos BD.', ['user_id' => $userId, 'error' => mysqli_stmt_error($stmt_delete)]);
            echo json_encode(['success' => false, 'message' => 'Erro ao excluir turnos do banco de dados.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
        mysqli_stmt_close($stmt_delete);

    } elseif ($acao === 'salvar_turnos') {
        $dadosTurnosRecebidos = $input['turnos'] ?? [];
        if (empty($dadosTurnosRecebidos) && !is_array($dadosTurnosRecebidos)) {
            echo json_encode(['success'=>false, 'message'=>'Nenhum dado de turno recebido.', 'data'=>[], 'csrf_token'=>$novoCsrfTokenParaCliente]); exit;
        }
        
        $googleAccessToken = $gcalHelper->getAccessTokenForUser($userId);
        $errosOperacao = [];
        $anoReferenciaTurnosSalvos = null; // Para recarregar os turnos do mês/ano corretos

        $sql_insert = "INSERT INTO turnos (data, hora_inicio, hora_fim, colaborador, google_calendar_event_id, criado_por_usuario_id, ano) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = mysqli_prepare($conexao, $sql_insert);
        $sql_update = "UPDATE turnos SET data = ?, hora_inicio = ?, hora_fim = ?, colaborador = ?, google_calendar_event_id = ?, ano = ? WHERE id = ? AND criado_por_usuario_id = ?";
        $stmt_update = mysqli_prepare($conexao, $sql_update);

        if (!$stmt_insert || !$stmt_update) {
            $logger->log('ERROR', 'Falha preparar statements insert/update.', ['user_id'=>$userId, 'err'=>mysqli_error($conexao)]);
            echo json_encode(['success'=>false, 'message'=>'Erro interno (prepare).', 'data'=>[], 'csrf_token'=>$novoCsrfTokenParaCliente]); exit;
        }

        foreach ($dadosTurnosRecebidos as $turno) {
            $turnoIdCliente = $turno['id'] ?? null;
            $dataStr = $turno['data'] ?? null;
            $anoForm = $turno['ano'] ?? date('Y'); // Ano de referência da UI
            $horaInicioStr = $turno['hora_inicio'] ?? null;
            $horaFimStr = $turno['hora_fim'] ?? null;
            $colaboradorNome = isset($turno['colaborador']) ? trim($turno['colaborador']) : null;
            
            if (!$anoReferenciaTurnosSalvos) $anoReferenciaTurnosSalvos = $anoForm;

            $dataFormatadaMysql = formatarDataParaMysql($dataStr, $anoForm);
            $horaInicioDb = formatarHoraParaMysql($horaInicioStr);
            $horaFimDb = formatarHoraParaMysql($horaFimStr);

            if (!$dataFormatadaMysql || !$horaInicioDb || !$horaFimDb || empty($colaboradorNome)) {
                $errosOperacao[] = "Turno para '{$colaboradorNome}' em '{$dataStr}' com dados incompletos/inválidos."; continue;
            }
            
            // Validação Hora Fim > Hora Início (permite virada simples de dia)
            $inicioTs = strtotime($dataFormatadaMysql . ' ' . $horaInicioDb);
            $fimTs = strtotime($dataFormatadaMysql . ' ' . $horaFimDb);

            if ($fimTs <= $inicioTs) {
                // Se fim <= inicio, e não é um caso claro de virada de noite (ex: 22:00 -> 02:00)
                // Hora de fim em $horaFimStr (ex: "02:00"), Hora de inicio em $horaInicioStr (ex: "22:00")
                $hFim = (int)explode(':', $horaFimStr)[0];
                $hIni = (int)explode(':', $horaInicioStr)[0];
                if (!($hFim < 6 && $hIni > 18)) { // Não é uma virada óbvia
                    $errosOperacao[] = "Hora Fim ({$horaFimStr}) deve ser posterior à Hora Início ({$horaInicioStr}) para {$colaboradorNome} em {$dataStr}.";
                    continue;
                }
            }

            $googleEventIdParaSalvar = null;
            if ($googleAccessToken) {
                try {
                    $fusoHorario = 'America/Sao_Paulo';
                    $dateTimeInicioGCal = new DateTime($dataFormatadaMysql . ' ' . $horaInicioDb, new DateTimeZone($fusoHorario));
                    $dateTimeFimGCal = new DateTime($dataFormatadaMysql . ' ' . $horaFimDb, new DateTimeZone($fusoHorario));

                    if ($dateTimeFimGCal <= $dateTimeInicioGCal) { // Se hora fim <= hora inicio, assume dia seguinte para GCal
                        $dateTimeFimGCal->add(new DateInterval('P1D'));
                    }

                    $summary = "Turno: " . $colaboradorNome;
                    $description = "Turno agendado para {$colaboradorNome} de " . $dateTimeInicioGCal->format('d/m/Y H:i') . " até " . $dateTimeFimGCal->format('H:i') . " (ou dia seguinte se aplicável).";
                    
                    $oldGcalId = null;
                    if ($turnoIdCliente && !str_starts_with($turnoIdCliente, "new-")) {
                        $stmt_g_old = mysqli_prepare($conexao, "SELECT google_calendar_event_id FROM turnos WHERE id = ? AND criado_por_usuario_id = ?");
                        mysqli_stmt_bind_param($stmt_g_old, "ii", $turnoIdCliente, $userId); mysqli_stmt_execute($stmt_g_old);
                        $res_g_old = mysqli_stmt_get_result($stmt_g_old);
                        if($r_g_old = mysqli_fetch_assoc($res_g_old)) $oldGcalId = $r_g_old['google_calendar_event_id'];
                        mysqli_stmt_close($stmt_g_old);
                        if($oldGcalId) $gcalHelper->deleteEvent($userId, $oldGcalId);
                    }
                    $googleEventIdParaSalvar = $gcalHelper->createEvent($userId, $summary, $description, $dateTimeInicioGCal->format(DateTime::RFC3339), $dateTimeFimGCal->format(DateTime::RFC3339), $fusoHorario);
                } catch (Exception $e) {
                    $logger->log('GCAL_ERROR', 'Exceção GCal ao salvar: '.$e->getMessage(), ['uid'=>$userId, 'turno'=>$turno]);
                    $errosOperacao[] = "Falha GCal para {$colaboradorNome}: ".substr($e->getMessage(),0,50);
                }
            }

            if ($turnoIdCliente && !str_starts_with($turnoIdCliente, "new-")) { // UPDATE
                $turnoIdRealDb = (int)$turnoIdCliente;
                mysqli_stmt_bind_param($stmt_update, "ssssssii", $dataFormatadaMysql, $horaInicioDb, $horaFimDb, $colaboradorNome, $googleEventIdParaSalvar, $anoForm, $turnoIdRealDb, $userId);
                if (!mysqli_stmt_execute($stmt_update)) $errosOperacao[] = "Erro ao ATUALIZAR turno ID {$turnoIdRealDb}: " . mysqli_stmt_error($stmt_update);
            } else { // INSERT
                mysqli_stmt_bind_param($stmt_insert, "sssssis", $dataFormatadaMysql, $horaInicioDb, $horaFimDb, $colaboradorNome, $googleEventIdParaSalvar, $userId, $anoForm);
                if (!mysqli_stmt_execute($stmt_insert)) $errosOperacao[] = "Erro ao INSERIR turno para {$colaboradorNome} em {$dataStr}: " . mysqli_stmt_error($stmt_insert);
            }
        }
        mysqli_stmt_close($stmt_insert); mysqli_stmt_close($stmt_update);

        // Determina mês e ano para recarregar a lista de turnos
        $mesReferenciaRecarga = date('m');
        $anoReferenciaRecarga = $anoReferenciaTurnosSalvos ?? date('Y');
        if(isset($dadosTurnosRecebidos[0]['data'])) {
            $dataPrimeiroTurnoSalvo = formatarDataParaMysql($dadosTurnosRecebidos[0]['data'], $anoReferenciaRecarga);
            if($dataPrimeiroTurnoSalvo) {
                $mesReferenciaRecarga = date('m', strtotime($dataPrimeiroTurnoSalvo));
            }
        }

        $sql_recarregar = "SELECT id, DATE_FORMAT(data, '%d/%m') AS data_formatada, data, TIME_FORMAT(hora_inicio, '%H:%i') AS hora_inicio, TIME_FORMAT(hora_fim, '%H:%i') AS hora_fim, colaborador, google_calendar_event_id 
                           FROM turnos WHERE criado_por_usuario_id = ? AND YEAR(data) = ? AND MONTH(data) = ? ORDER BY data ASC, hora_inicio ASC";
        $stmt_recarregar = mysqli_prepare($conexao, $sql_recarregar);
        mysqli_stmt_bind_param($stmt_recarregar, "iii", $userId, $anoReferenciaRecarga, $mesReferenciaRecarga);
        mysqli_stmt_execute($stmt_recarregar);
        $result_recarregar = mysqli_stmt_get_result($stmt_recarregar);
        $turnosRetorno = mysqli_fetch_all($result_recarregar, MYSQLI_ASSOC); 
        mysqli_stmt_close($stmt_recarregar);

        if (!empty($errosOperacao)) {
             echo json_encode(['success'=>false, 'message'=>'Ocorreram erros: '.implode("; ",$errosOperacao), 'data'=>$turnosRetorno, 'csrf_token'=>$novoCsrfTokenParaCliente]);
        } else {
            echo json_encode(['success'=>true, 'message'=>'Turnos salvos com sucesso!', 'data'=>$turnosRetorno, 'csrf_token'=>$novoCsrfTokenParaCliente]);
        }
    } else {
        $logger->log('WARNING', 'Ação POST desconhecida.', ['acao' => $acao, 'user_id' => $userId]);
        echo json_encode(['success' => false, 'message' => 'Ação desconhecida.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
    mysqli_close($conexao);
    exit;
}

// Se chegou aqui, método não tratado
$logger->log('ERROR', 'Método não tratado ou GET sem parâmetros válidos.', ['method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A']);
echo json_encode(['success' => false, 'message' => 'Requisição inválida.', 'csrf_token' => $novoCsrfTokenParaCliente ?? bin2hex(random_bytes(32)) ]);
