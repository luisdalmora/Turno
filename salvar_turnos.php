<?php
// salvar_turnos.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/LogHelper.php';
require_once __DIR__ . '/GoogleCalendarHelper.php';

error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/php_errors.log');

$logger = new LogHelper($conexao);
$gcalHelper = new GoogleCalendarHelper($logger, $conexao);

header('Content-Type: application/json');
$userId = $_SESSION['usuario_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.', 'data' => []]);
    exit;
}

// **************************************************************************************
// ** ATENÇÃO: HORA DE INÍCIO FIXA PARA EVENTOS DO GOOGLE CALENDAR **
// Como o campo 'hora' (interface "Turno (Duração HH:MM)") agora é interpretado como DURAÇÃO,
// precisamos de uma hora de início para os eventos do Google Calendar.
// Defina uma HORA DE INÍCIO PADRÃO aqui. Se os seus turnos REAIS têm horas de início
// variáveis que precisam ser refletidas no Google Calendar, esta abordagem é uma
// simplificação e o ideal seria ter um campo "Hora de Início Real" no seu formulário/banco.
// **************************************************************************************
define('HORA_INICIO_PADRAO_GCAL', '00:00:00'); // EXEMPLO: Eventos no GCal começam à meia-noite. AJUSTE SE NECESSÁRIO (ex: '08:00:00').


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql_select_todos = "SELECT id, DATE_FORMAT(data, '%d/%b') AS data, hora, colaborador, google_calendar_event_id FROM turnos WHERE criado_por_usuario_id = ? ORDER BY data ASC, hora ASC";
    $stmt_select = mysqli_prepare($conexao, $sql_select_todos);

    if (!$stmt_select) {
        $logger->log('ERROR', 'Erro ao preparar consulta de seleção de turnos GET.', ['error' => mysqli_error($conexao), 'user_id' => $userId]);
        echo json_encode(['success' => false, 'message' => 'Erro ao preparar consulta.', 'data' => []]);
        exit;
    }
    mysqli_stmt_bind_param($stmt_select, "i", $userId);
    mysqli_stmt_execute($stmt_select);
    $result_select = mysqli_stmt_get_result($stmt_select);
    $turnos_carregados = mysqli_fetch_all($result_select, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_select);

    echo json_encode(['success' => true, 'message' => 'Turnos carregados.', 'data' => $turnos_carregados]);
    mysqli_close($conexao);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $logger->log('ERROR', 'JSON de entrada inválido.', ['user_id' => $userId, 'json_error' => json_last_error_msg()]);
        echo json_encode(['success' => false, 'message' => 'Dados de entrada inválidos.']);
        exit;
    }
    $acao = $input['acao'] ?? null;

    if ($acao === 'excluir_turnos') {
        $idsParaExcluir = $input['ids_turnos'] ?? [];
        if (empty($idsParaExcluir)) {
            echo json_encode(['success' => false, 'message' => 'Nenhum ID fornecido.']);
            exit;
        }
        $idsValidos = array_filter(array_map('intval', $idsParaExcluir), function($id) { return $id > 0; });
        if (empty($idsValidos)) {
            echo json_encode(['success' => false, 'message' => 'IDs inválidos.']);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($idsValidos), '?'));
        $tipos_ids = str_repeat('i', count($idsValidos));
        
        $googleAccessToken = $gcalHelper->getAccessTokenForUser($userId);

        $sql_get_gcal_ids = "SELECT id, google_calendar_event_id FROM turnos WHERE id IN ($placeholders) AND criado_por_usuario_id = ?";
        $stmt_get_gcal = mysqli_prepare($conexao, $sql_get_gcal_ids);
        $params_get_gcal = array_merge($idsValidos, [$userId]);
        mysqli_stmt_bind_param($stmt_get_gcal, $tipos_ids . 'i', ...$params_get_gcal);
        mysqli_stmt_execute($stmt_get_gcal);
        $result_gcal_ids = mysqli_stmt_get_result($stmt_get_gcal);
        $eventos_google_para_deletar = mysqli_fetch_all($result_gcal_ids, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_get_gcal);

        if ($googleAccessToken) {
            foreach ($eventos_google_para_deletar as $evento) {
                if (!empty($evento['google_calendar_event_id'])) {
                    $gcalHelper->deleteEvent($userId, $evento['google_calendar_event_id']);
                }
            }
        } else {
            $logger->log('GCAL_WARNING', 'GCal: Sem Access Token para deletar eventos.', ['user_id' => $userId]);
        }

        $sql_delete = "DELETE FROM turnos WHERE id IN ($placeholders) AND criado_por_usuario_id = ?";
        $stmt_delete = mysqli_prepare($conexao, $sql_delete);
        $params_delete_db = array_merge($idsValidos, [$userId]);
        mysqli_stmt_bind_param($stmt_delete, $tipos_ids . 'i', ...$params_delete_db);
        
        if (mysqli_stmt_execute($stmt_delete)) {
            $numLinhasAfetadas = mysqli_stmt_affected_rows($stmt_delete);
            $logger->log('INFO', "{$numLinhasAfetadas} turno(s) excluído(s).", ['user_id' => $userId, 'ids' => $idsValidos]);
            echo json_encode(['success' => true, 'message' => "{$numLinhasAfetadas} turno(s) excluído(s)."]);
        } else {
            $logger->log('ERROR', 'Falha ao excluir turnos BD.', ['user_id' => $userId, 'error' => mysqli_stmt_error($stmt_delete)]);
            echo json_encode(['success' => false, 'message' => 'Erro ao excluir do banco.']);
        }
        mysqli_stmt_close($stmt_delete);

    } elseif ($acao === 'salvar_turnos') {
        $dados_turnos_recebidos = $input['turnos'] ?? [];
        if (empty($dados_turnos_recebidos) && !is_array($dados_turnos_recebidos)) {
            echo json_encode(['success' => false, 'message' => 'Nenhum dado de turno recebido.', 'data' => []]);
            exit;
        }
        
        $googleAccessToken = $gcalHelper->getAccessTokenForUser($userId);
        $erros_operacao = [];

        $sql_insert = "INSERT INTO turnos (data, hora, colaborador, google_calendar_event_id, criado_por_usuario_id, ano) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_insert = mysqli_prepare($conexao, $sql_insert);

        $sql_update = "UPDATE turnos SET data = ?, hora = ?, colaborador = ?, google_calendar_event_id = ?, ano = ? WHERE id = ? AND criado_por_usuario_id = ?";
        $stmt_update = mysqli_prepare($conexao, $sql_update);

        if (!$stmt_insert || !$stmt_update) {
            $logger->log('ERROR', 'Falha preparar statements insert/update turnos.', ['error' => mysqli_error($conexao), 'user_id' => $userId]);
            echo json_encode(['success' => false, 'message' => 'Erro interno (prepare).', 'data' => []]);
            exit;
        }

        foreach ($dados_turnos_recebidos as $turno) {
            $turno_id_cliente = $turno['id'] ?? null;
            $data_str = $turno['data'] ?? null;
            $hora_duracao_str = $turno['hora'] ?? null; // DURAÇÃO (ex: "04:00")
            $colaborador_nome = isset($turno['colaborador']) ? trim($turno['colaborador']) : null;
            $ano_recebido = $turno['ano'] ?? date('Y');
            $google_event_id_atual_db = null;

            $data_formatada_mysql = null;
            if ($data_str) {
                $partes_data = explode('/', $data_str);
                $ano_final_para_data = $ano_recebido; 
                $dia_str = $partes_data[0] ?? null;
                $mes_input_str = $partes_data[1] ?? null;
                $ano_input_str_temp = $partes_data[2] ?? null;

                if ($ano_input_str_temp) {
                    if (strlen(trim($ano_input_str_temp)) === 4) $ano_final_para_data = trim($ano_input_str_temp);
                    elseif (strlen(trim($ano_input_str_temp)) === 2) $ano_final_para_data = "20" . trim($ano_input_str_temp);
                }

                $dia_num = ($dia_str && ctype_digit(trim($dia_str))) ? sprintf('%02d', (int)trim($dia_str)) : null;
                $mes_num = null;
                if ($mes_input_str) {
                    if (ctype_digit(trim($mes_input_str))) {
                        $mes_num = sprintf('%02d', (int)trim($mes_input_str));
                    } else {
                        $mapa_meses_universal = ['jan'=>'01','fev'=>'02','mar'=>'03','abr'=>'04','mai'=>'05','jun'=>'06','jul'=>'07','ago'=>'08','set'=>'09','out'=>'10','nov'=>'11','dez'=>'12'];
                        $mes_num = $mapa_meses_universal[strtolower(substr(trim($mes_input_str),0,3))] ?? null;
                    }
                }
                if ($dia_num && $mes_num && $ano_final_para_data && checkdate((int)$mes_num, (int)$dia_num, (int)$ano_final_para_data)) {
                    $data_formatada_mysql = "$ano_final_para_data-$mes_num-$dia_num";
                } else {
                     $logger->log('WARNING', 'Formato de data inválido ao salvar turno.', ['data_str' => $data_str, 'user_id' => $userId]);
                }
            }

            if (!$data_formatada_mysql || empty($hora_duracao_str) || empty($colaborador_nome)) {
                $erros_operacao[] = "Dados inválidos: Data='{$data_str}', Duração='{$hora_duracao_str}', Colab='{$colaborador_nome}'.";
                continue;
            }

            $parts_duracao = explode(':', $hora_duracao_str);
            $h_dur = isset($parts_duracao[0]) ? (int)$parts_duracao[0] : 0;
            $m_dur = isset($parts_duracao[1]) ? (int)$parts_duracao[1] : 0;
            if ($h_dur < 0 || $h_dur > 23 || $m_dur < 0 || $m_dur > 59) { // Duração até 23:59
                $erros_operacao[] = "Duração inválida ('{$hora_duracao_str}') para {$colaborador_nome}.";
                continue;
            }
            $hora_para_db = sprintf('%02d:%02d:00', $h_dur, $m_dur); // Formato TIME para salvar a duração

            $google_event_id_para_salvar_no_db = null;
            if ($googleAccessToken) {
                try {
                    $fusoHorario = 'America/Sao_Paulo';
                    $dateTimeInicioGCal = new DateTime($data_formatada_mysql . ' ' . HORA_INICIO_PADRAO_GCAL, new DateTimeZone($fusoHorario));
                    $dateTimeFimGCal = clone $dateTimeInicioGCal;
                    $dateTimeFimGCal->add(new DateInterval("PT{$h_dur}H{$m_dur}M"));

                    $summary = "Turno: " . $colaborador_nome;
                    $description = "Turno para {$colaborador_nome} em " . $dateTimeInicioGCal->format('d/m/Y') . 
                                   " (Início GCal: " . $dateTimeInicioGCal->format('H:i') . ") com duração de {$h_dur}h{$m_dur}min.";
                    
                    if ($turno_id_cliente && !str_starts_with($turno_id_cliente, "new-")) {
                        $stmt_g = mysqli_prepare($conexao, "SELECT google_calendar_event_id FROM turnos WHERE id = ? AND criado_por_usuario_id = ?");
                        mysqli_stmt_bind_param($stmt_g, "ii", $turno_id_cliente, $userId); mysqli_stmt_execute($stmt_g);
                        $res_g = mysqli_stmt_get_result($stmt_g);
                        if ($r_g = mysqli_fetch_assoc($res_g)) $google_event_id_atual_db = $r_g['google_calendar_event_id'];
                        mysqli_stmt_close($stmt_g);
                        if ($google_event_id_atual_db) $gcalHelper->deleteEvent($userId, $google_event_id_atual_db);
                    }
                    
                    $google_event_id_para_salvar_no_db = $gcalHelper->createEvent(
                        $userId, $summary, $description,
                        $dateTimeInicioGCal->format(DateTime::RFC3339), $dateTimeFimGCal->format(DateTime::RFC3339), $fusoHorario);
                } catch (Exception $e) {
                    $logger->log('GCAL_ERROR', 'Exceção GCal: ' . $e->getMessage(), ['turno' => $turno, 'user_id' => $userId]);
                    $erros_operacao[] = "Falha GCal para {$colaborador_nome}: " . substr($e->getMessage(), 0, 50);
                }
            }

            if ($turno_id_cliente && !str_starts_with($turno_id_cliente, "new-")) {
                $turno_id_real_db = (int)$turno_id_cliente;
                mysqli_stmt_bind_param($stmt_update, "sssssii", $data_formatada_mysql, $hora_para_db, $colaborador_nome, $google_event_id_para_salvar_no_db, $ano_recebido, $turno_id_real_db, $userId);
                if (!mysqli_stmt_execute($stmt_update)) $erros_operacao[] = "Erro UPDATE ID {$turno_id_real_db}: " . mysqli_stmt_error($stmt_update);
            } else {
                mysqli_stmt_bind_param($stmt_insert, "ssssis", $data_formatada_mysql, $hora_para_db, $colaborador_nome, $google_event_id_para_salvar_no_db, $userId, $ano_recebido);
                if (!mysqli_stmt_execute($stmt_insert)) $erros_operacao[] = "Erro INSERT ({$data_str}): " . mysqli_stmt_error($stmt_insert);
            }
        }
        mysqli_stmt_close($stmt_insert); mysqli_stmt_close($stmt_update);

        if (!empty($erros_operacao)) {
             echo json_encode(['success' => false, 'message' => 'Ocorreram erros: ' . implode("; ", $erros_operacao), 'data' => []]);
        } else {
            $sql_sel = "SELECT id, DATE_FORMAT(data, '%d/%b') AS data, hora, colaborador, google_calendar_event_id FROM turnos WHERE criado_por_usuario_id = ? ORDER BY data ASC, hora ASC";
            $stmt_sel = mysqli_prepare($conexao, $sql_sel);
            mysqli_stmt_bind_param($stmt_sel, "i", $userId); mysqli_stmt_execute($stmt_sel);
            $res_sel = mysqli_stmt_get_result($stmt_sel);
            $turnos_retorno = mysqli_fetch_all($res_sel, MYSQLI_ASSOC); mysqli_stmt_close($stmt_sel);
            echo json_encode(['success' => true, 'message' => 'Turnos salvos!', 'data' => $turnos_retorno]);
        }
    } else {
        $logger->log('WARNING', 'Ação desconhecida.', ['acao' => $acao, 'user_id' => $userId]);
        echo json_encode(['success' => false, 'message' => 'Ação desconhecida.', 'data' => []]);
    }
    mysqli_close($conexao);
    exit;
}

$logger->log('ERROR', 'Método inválido.', ['method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A']);
echo json_encode(['success' => false, 'message' => 'Método de requisição inválido.', 'data' => []]);
