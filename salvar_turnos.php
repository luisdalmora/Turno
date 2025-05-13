<?php
// salvar_turnos.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/LogHelper.php';
require_once __DIR__ . '/GoogleCalendarHelper.php';

error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/php_errors.log'); // Opcional

$logger = new LogHelper($conexao);
$gcalHelper = new GoogleCalendarHelper($logger, $conexao); // Passa a conexão

header('Content-Type: application/json');
$userId = $_SESSION['usuario_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.', 'data' => []]);
    exit;
}

// --- LÓGICA PARA CARREGAR TURNOS (GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Você pode adicionar filtros de ano/mês aqui se desejar, passados via $_GET
    // $anoFiltro = $_GET['ano'] ?? date('Y');
    // $mesFiltro = $_GET['mes'] ?? date('m');
    // Exemplo de query com filtro:
    // $sql_select_todos = "SELECT id, DATE_FORMAT(data, '%d/%b') AS data, hora, colaborador, google_calendar_event_id FROM turnos WHERE criado_por_usuario_id = ? AND YEAR(data) = ? AND MONTH(data) = ? ORDER BY data ASC, hora ASC";
    // $stmt_select = mysqli_prepare($conexao, $sql_select_todos);
    // mysqli_stmt_bind_param($stmt_select, "isi", $userId, $anoFiltro, $mesFiltro);
    
    $sql_select_todos = "SELECT id, DATE_FORMAT(data, '%d/%b') AS data, hora, colaborador, google_calendar_event_id FROM turnos WHERE criado_por_usuario_id = ? ORDER BY data ASC, hora ASC";
    $stmt_select = mysqli_prepare($conexao, $sql_select_todos);

    if (!$stmt_select) {
        echo json_encode(['success' => false, 'message' => 'Erro ao preparar consulta de seleção de turnos.', 'data' => []]);
        exit;
    }
    mysqli_stmt_bind_param($stmt_select, "i", $userId);
    mysqli_stmt_execute($stmt_select);
    $result_select = mysqli_stmt_get_result($stmt_select);
    $turnos_carregados = mysqli_fetch_all($result_select, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_select);

    echo json_encode(['success' => true, 'message' => 'Turnos carregados com sucesso.', 'data' => $turnos_carregados]);
    mysqli_close($conexao);
    exit;
}

// --- LÓGICA PARA SALVAR OU EXCLUIR TURNOS (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $acao = $input['acao'] ?? null;

    if ($acao === 'excluir_turnos') {
        // --- LÓGICA DE EXCLUSÃO ---
        $idsParaExcluir = $input['ids_turnos'] ?? [];
        if (empty($idsParaExcluir)) {
            echo json_encode(['success' => false, 'message' => 'Nenhum ID de turno fornecido para exclusão.']);
            exit;
        }
        $idsValidos = array_filter(array_map('intval', $idsParaExcluir), function($id) { return $id > 0; });
        if (empty($idsValidos)) {
            echo json_encode(['success' => false, 'message' => 'IDs de turno inválidos fornecidos.']);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($idsValidos), '?'));
        $tipos_ids = str_repeat('i', count($idsValidos));
        
        // 1. Buscar Google Event IDs antes de deletar do banco
        $sql_get_gcal_ids = "SELECT id, google_calendar_event_id FROM turnos WHERE id IN ($placeholders) AND criado_por_usuario_id = ?";
        $stmt_get_gcal = mysqli_prepare($conexao, $sql_get_gcal_ids);
        $params_get_gcal = array_merge($idsValidos, [$userId]);
        mysqli_stmt_bind_param($stmt_get_gcal, $tipos_ids . 'i', ...$params_get_gcal);
        mysqli_stmt_execute($stmt_get_gcal);
        $result_gcal_ids = mysqli_stmt_get_result($stmt_get_gcal);
        $eventos_google_para_deletar = mysqli_fetch_all($result_gcal_ids, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_get_gcal);

        // 2. Deletar eventos do Google Calendar
        $googleAccessToken = $gcalHelper->getAccessTokenForUser($userId);
        if ($googleAccessToken) {
            foreach ($eventos_google_para_deletar as $evento) {
                if (!empty($evento['google_calendar_event_id'])) {
                    $gcalHelper->deleteEvent($userId, $evento['google_calendar_event_id']);
                    // O log da deleção do GCal já acontece dentro do deleteEvent
                }
            }
        } else {
            $logger->log('GCAL_WARNING', 'Não foi possível deletar eventos do Google Calendar durante a exclusão de turnos: Sem Access Token.', ['user_id' => $userId]);
        }

        // 3. Deletar do banco de dados
        $sql_delete = "DELETE FROM turnos WHERE id IN ($placeholders) AND criado_por_usuario_id = ?";
        $stmt_delete = mysqli_prepare($conexao, $sql_delete);
        $params_delete_db = array_merge($idsValidos, [$userId]);
        mysqli_stmt_bind_param($stmt_delete, $tipos_ids . 'i', ...$params_delete_db);
        
        if (mysqli_stmt_execute($stmt_delete)) {
            $numLinhasAfetadas = mysqli_stmt_affected_rows($stmt_delete);
            $logger->log('INFO', "{$numLinhasAfetadas} turno(s) excluído(s) do BD para o usuário {$userId}.", ['ids' => $idsValidos]);
            echo json_encode(['success' => true, 'message' => "{$numLinhasAfetadas} turno(s) excluído(s) com sucesso."]);
        } else {
            $logger->log('ERROR', 'Falha ao executar exclusão de turnos do BD.', ['user_id' => $userId, 'error' => mysqli_stmt_error($stmt_delete)]);
            echo json_encode(['success' => false, 'message' => 'Erro ao excluir turnos do banco de dados.']);
        }
        mysqli_stmt_close($stmt_delete);

    } elseif ($acao === 'salvar_turnos') {
        // --- LÓGICA DE SALVAR/INSERIR (EXISTENTE E AJUSTADA) ---
        $dados_turnos_recebidos = $input['turnos'] ?? [];

        if (!isset($dados_turnos_recebidos)) { // Checa se a chave 'turnos' existe e não é null
            echo json_encode(['success' => false, 'message' => 'Nenhum dado de turno recebido ou formato inválido.', 'data' => []]);
            exit;
        }
        
        // Considerar deletar os turnos do período ANTES de inserir os novos para sincronizar
        // Ex: Se o $dados_turnos_recebidos for um array vazio, você pode querer deletar todos os turnos do mês/ano atual.
        // Ou, se receber turnos, deletar os existentes daquele mês/ano e inserir os novos.
        // Esta lógica de "delete-then-insert" é comum para sincronização.
        // Por ora, o script apenas insere/atualiza (se o ID for fornecido e a lógica de UPDATE for implementada).
        // A lógica atual apenas INSERE. Se um turno com ID for enviado, ele ainda tentará INSERIR, o que pode falhar ou não ser o desejado.
        // Para uma funcionalidade de "Salvar Alterações" completa, você precisaria:
        // 1. Iterar pelos $dados_turnos_recebidos.
        // 2. Se um turno tem ID, tentar UPDATE.
        // 3. Se não tem ID, tentar INSERT.
        // 4. (Opcional) Turnos que estavam no banco para o período mas não vieram da tela podem ser deletados.
        // Esta é uma lógica mais complexa. Por simplicidade, a lógica abaixo continua com INSERT.
        // E a deleção de turnos do período NÃO está implementada aqui para evitar perda de dados acidental.

        $googleAccessToken = $gcalHelper->getAccessTokenForUser($userId);
        // Assumindo que a tabela 'turnos' tem a coluna 'ano' VARCHAR(4) ou YEAR
        $sql_insert = "INSERT INTO turnos (data, hora, colaborador, google_calendar_event_id, criado_por_usuario_id, ano) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_insert = mysqli_prepare($conexao, $sql_insert);

        if ($stmt_insert === false) {
            echo json_encode(['success' => false, 'message' => 'Erro ao preparar consulta de inserção: ' . mysqli_error($conexao), 'data' => []]);
            exit;
        }
        $erros_insercao = [];

        foreach ($dados_turnos_recebidos as $turno) {
            $turno_id_original = $turno['id'] ?? null; // ID do turno se estiver sendo editado
            $data_str = $turno['data'] ?? null;
            $hora_str = $turno['hora'] ?? null;
            $colaborador_nome = isset($turno['colaborador']) ? trim($turno['colaborador']) : null;
            $ano_recebido = $turno['ano'] ?? date('Y');
            $google_event_id_para_salvar = null;
            $data_formatada_mysql = null;

            // Lógica de conversão de data (revisada para dd/Mês ou dd/mm/aaaa)
            if ($data_str) {
                $partes_data = explode('/', $data_str);
                $ano_final_para_data = $ano_recebido; // Usa o ano da tabela como base
                $dia_str = null; $mes_input_str = null; $ano_input_str = null;

                if (count($partes_data) >= 2) $dia_str = trim($partes_data[0]);
                if (count($partes_data) >= 2) $mes_input_str = trim($partes_data[1]);
                if (count($partes_data) == 3) $ano_input_str = trim($partes_data[2]);
                
                if ($ano_input_str && strlen($ano_input_str) === 4 && ctype_digit($ano_input_str)) {
                    $ano_final_para_data = $ano_input_str; // Prioriza ano da string de data
                }

                $dia_num = ($dia_str && ctype_digit($dia_str)) ? sprintf('%02d', (int)$dia_str) : null;
                $mes_num = null;

                if ($mes_input_str) {
                    if (ctype_digit($mes_input_str)) {
                        $mes_num = sprintf('%02d', (int)$mes_input_str);
                    } else {
                        $mapa_meses_universal = [
                            'jan' => '01', 'feb' => '02', 'mar' => '03', 'apr' => '04', 'may' => '05', 'jun' => '06',
                            'jul' => '07', 'aug' => '08', 'sep' => '09', 'oct' => '10', 'nov' => '11', 'dec' => '12',
                            'fev' => '02', 'mai' => '05', 'ago' => '08', 'set' => '09', 'out' => '10', 'dez' => '12'
                        ];
                        $mes_str_processar = strtolower(substr($mes_input_str, 0, 3));
                        $mes_num = $mapa_meses_universal[$mes_str_processar] ?? null;
                    }
                }

                if ($dia_num && $mes_num && $ano_final_para_data && checkdate((int)$mes_num, (int)$dia_num, (int)$ano_final_para_data)) {
                    $data_formatada_mysql = "$ano_final_para_data-$mes_num-$dia_num";
                } else {
                    $logger->log('WARNING', 'Falha ao formatar data para MySQL.', ['data_str' => $data_str, 'dia' => $dia_num, 'mes_input' => $mes_input_str, 'ano_final' => $ano_final_para_data]);
                }
            }

            if (!$data_formatada_mysql || !$hora_str || !$colaborador_nome) {
                $erros_insercao[] = "Dados inválidos ou ausentes para um dos turnos: Data:'{$data_str}', Hora:'{$hora_str}', Colab:'{$colaborador_nome}', Ano Processado:'{$ano_final_para_data}'";
                continue;
            }

            // Lógica do Google Calendar (createEvent)
            if ($googleAccessToken) {
                try {
                    $fusoHorario = 'America/Sao_Paulo';
                    $dateTimeInicio = new DateTime($data_formatada_mysql . ' ' . $hora_str, new DateTimeZone($fusoHorario));
                    $dateTimeFim = clone $dateTimeInicio;
                    $duracaoPadraoTurno = 'PT8H'; // Ex: 8 horas. Ajuste conforme necessário!
                    $dateTimeFim->add(new DateInterval($duracaoPadraoTurno));
                    $summary = $colaborador_nome;
                    //$summary = "Turno Sim Posto: " . $colaborador_nome;
                    $description = "Turno para " . $colaborador_nome . " em " . $dateTimeInicio->format('d/m/Y H:i');
                    
                    $google_event_id_para_salvar = $gcalHelper->createEvent(
                        $userId, $summary, $description,
                        $dateTimeInicio->format(DateTime::RFC3339),
                        $dateTimeFim->format(DateTime::RFC3339),
                        $fusoHorario
                    );
                } catch (Exception $e) {
                    $logger->log('GCAL_ERROR', 'Exceção ao criar evento GCal: ' . $e->getMessage(), ['turno' => $turno, 'user_id' => $userId]);
                }
            }
            // Adicionar ano na inserção
            mysqli_stmt_bind_param($stmt_insert, "ssssis", $data_formatada_mysql, $hora_str, $colaborador_nome, $google_event_id_para_salvar, $userId, $ano_final_para_data);
            if (!mysqli_stmt_execute($stmt_insert)) {
                $erros_insercao[] = "Erro ao inserir turno ({$data_str}): " . mysqli_stmt_error($stmt_insert);
            }
        }
        mysqli_stmt_close($stmt_insert);

        if (!empty($erros_insercao)) {
             echo json_encode(['success' => false, 'message' => 'Ocorreram erros ao salvar alguns turnos. Detalhes: ' . implode("; ", $erros_insercao), 'data' => []]);
             // Não busca dados atualizados se houve erro
        } else {
            // Busca todos os turnos para retornar ao cliente
            $sql_select_depois_op = "SELECT id, DATE_FORMAT(data, '%d/%b') AS data, hora, colaborador, google_calendar_event_id FROM turnos WHERE criado_por_usuario_id = ? ORDER BY data ASC, hora ASC";
            $stmt_select_depois = mysqli_prepare($conexao, $sql_select_depois_op);
            mysqli_stmt_bind_param($stmt_select_depois, "i", $userId);
            mysqli_stmt_execute($stmt_select_depois);
            $result_select_depois = mysqli_stmt_get_result($stmt_select_depois);
            $turnos_atualizados = mysqli_fetch_all($result_select_depois, MYSQLI_ASSOC);
            mysqli_stmt_close($stmt_select_depois);
            echo json_encode(['success' => true, 'message' => 'Turnos salvos com sucesso!', 'data' => $turnos_atualizados]);
        }

    } else {
        echo json_encode(['success' => false, 'message' => 'Ação desconhecida ou não fornecida.', 'data' => []]);
    }
    mysqli_close($conexao);
    exit;
}

// Se chegar aqui, método não é GET nem POST, ou algo deu muito errado antes.
echo json_encode(['success' => false, 'message' => 'Método de requisição inválido.', 'data' => []]);
