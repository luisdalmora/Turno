<?php
// salvar_turnos.php

require_once __DIR__ . '/config.php'; // Inclui configurações e inicia sessão
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/LogHelper.php';
require_once __DIR__ . '/GoogleCalendarHelper.php'; // Para integração com Google Calendar

$logger = new LogHelper($conexao);
$gcalHelper = new GoogleCalendarHelper($logger, $conexao); // Passa a conexão com BD

header('Content-Type: application/json');

$json_input = file_get_contents('php://input');
$dados_turnos_recebidos = json_decode($json_input, true);

$userId = $_SESSION['usuario_id'] ?? null; // Assume que o usuário que salva o turno é o logado

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Erro: Usuário não autenticado.', 'data' => []]);
    $logger->log('ERROR', 'Tentativa de salvar turnos sem usuário autenticado.', ['input_data' => $dados_turnos_recebidos]);
    mysqli_close($conexao);
    exit;
}


if (!isset($dados_turnos_recebidos) || empty($dados_turnos_recebidos)) {
    echo json_encode(['success' => false, 'message' => 'Nenhum dado de turno foi recebido.', 'data' => []]);
    $logger->log('WARNING', 'salvar_turnos.php chamado sem dados de turno.', ['user_id' => $userId]);
    mysqli_close($conexao);
    exit;
}

// Tentar obter o token de acesso do Google para o usuário logado
$googleAccessToken = $gcalHelper->getAccessTokenForUser($userId);
if (!$googleAccessToken) {
    $logger->log('GCAL_INFO', 'Usuário não tem token do Google Calendar ativo. Eventos não serão criados no Google Calendar.', ['user_id' => $userId]);
    // Não impede o salvamento no sistema, apenas não integra com GCal
}


// Query para inserir/atualizar turnos e buscar o ID do colaborador se necessário
// Assumindo que a tabela 'usuarios' tem 'id' e 'nome_completo'
// E a tabela 'turnos' tem 'colaborador_id' (FK para usuarios.id) em vez de apenas 'colaborador' (nome)

// Primeiro, vamos deletar os turnos existentes para este usuário/mês para simplificar
// Em um sistema real, você faria um UPSERT ou uma lógica mais complexa de merge
// Esta parte é uma simplificação e pode não ser o ideal para todos os cenários.
// Por exemplo, se múltiplos usuários podem gerenciar os mesmos colaboradores.
// Aqui, vamos assumir que os turnos são "propriedade" do usuário logado que os edita.
// E que a tabela de turnos no HTML é a fonte da verdade para o mês em edição.

// $sql_delete_existentes = "DELETE FROM turnos WHERE MONTH(data) = ? AND YEAR(data) = ? AND ... "; // Adicionar filtro por quem pode editar

$sql_insert = "INSERT INTO turnos (data, hora, colaborador, google_calendar_event_id, criado_por_usuario_id) VALUES (?, ?, ?, ?, ?)";
$stmt_insert = mysqli_prepare($conexao, $sql_insert);

if ($stmt_insert === false) {
    $error_msg = 'Erro ao preparar a consulta de inserção de turnos: ' . mysqli_error($conexao);
    $logger->log('ERROR', $error_msg, ['user_id' => $userId]);
    echo json_encode(['success' => false, 'message' => $error_msg, 'data' => []]);
    mysqli_close($conexao);
    exit;
}

$erros_insercao = [];
$turnos_salvos_com_google_id = [];

foreach ($dados_turnos_recebidos as $turno) {
    $data_str = isset($turno['data']) ? $turno['data'] : null;
    $hora_str = isset($turno['hora']) ? $turno['hora'] : null;
    $colaborador_nome = isset($turno['colaborador']) ? trim($turno['colaborador']) : null;
    $google_event_id_para_salvar = null; // ID do evento do Google Calendar

    // Conversão de Data: '03/mai' para 'YYYY-MM-DD'
    $data_formatada_mysql = null;
    if ($data_str) {
        $partes_data = explode('/', $data_str);
        if (count($partes_data) == 2) {
            $dia = sprintf('%02d', (int)$partes_data[0]);
            $mapa_meses = ['jan' => '01', 'fev' => '02', 'mar' => '03', 'abr' => '04', 'mai' => '05', 'jun' => '06', 'jul' => '07', 'ago' => '08', 'set' => '09', 'out' => '10', 'nov' => '11', 'dez' => '12'];
            $mes_str = strtolower(substr($partes_data[1], 0, 3)); // Pega os 3 primeiros chars do mês
            $mes_num = $mapa_meses[$mes_str] ?? null;
            
            // Tenta pegar o ano do nome da tabela (ex: shifts-table-may-2025) ou usa o atual
            // Para este exemplo, vamos fixar o ano atual ou um ano específico se fornecido
            $ano_especifico = date('Y'); // Ou pegar de algum lugar, ex: $turno['ano'] se vier no JSON
            // No seu HTML, o título da tabela é "Turnos Programados - Maio 2025"
            // Idealmente, o ano deveria vir do cliente ou ser inferido de forma mais robusta.
            // Vamos assumir que os turnos são para o ano atual se não especificado.
            // Se sua tabela `home.html` é "Maio 2025", você precisa enviar este ano para cá.
            // Por agora, usando o ano atual como fallback:
            // $ano_atual = date('Y'); 

            // Para o exemplo: TENTANDO extrair o ano do título da tabela, se possível (requer JS enviar isso)
            // Se o nome da tabela no front-end é 'shifts-table-may-2025', o JS deveria enviar o 2025.
            // Por enquanto, vamos usar o ano atual.
            $ano_para_data = date('Y'); // Em um sistema real, o ano deve ser mais explícito.


            if ($dia && $mes_num) {
                $data_formatada_mysql = "$ano_para_data-$mes_num-$dia";
            }
        }
    }

    if (!$data_formatada_mysql || !$hora_str || !$colaborador_nome) {
        $erros_insercao[] = "Dados inválidos ou ausentes para um dos turnos: " . json_encode($turno);
        continue;
    }

    // **Integração com Google Calendar AQUI**
    if ($googleAccessToken) {
        try {
            $fusoHorario = 'America/Sao_Paulo'; // Ajuste se necessário
            
            $dateTimeInicio = new DateTime($data_formatada_mysql . ' ' . $hora_str, new DateTimeZone($fusoHorario));
            // Assumindo uma duração de turno, por exemplo, 8 horas. Ajuste conforme sua lógica.
            // Ou se você tiver um campo de hora de término.
            $dateTimeFim = clone $dateTimeInicio;
            $duracaoTurno = 'PT8H'; // Exemplo: Período de Tempo de 8 Horas. Ajuste!
            // Você pode ter uma coluna 'duracao' na sua tabela de turnos ou um horário de fim.
            // Se '04:00:00' é a duração, a lógica precisa ser diferente.
            // Se '04:00:00' é o início, você precisa de um fim.
            // Vamos assumir que $hora_str é o INÍCIO e o turno dura 8h.
            $dateTimeFim->add(new DateInterval($duracaoTurno)); 

            $summary = "Turno Sim Posto: " . $colaborador_nome;
            $description = "Turno agendado via Sistema Sim Posto para " . $colaborador_nome . " no dia " . $dateTimeInicio->format('d/m/Y') . " às " . $dateTimeInicio->format('H:i') . ".";
            
            // Para 'attendees', você precisaria do e-mail do colaborador.
            // Se o $colaborador_nome for um usuário do sistema e você tiver o e-mail dele:
            // $email_colaborador = buscar_email_do_colaborador_pelo_nome($conexao, $colaborador_nome);
            // $attendees = $email_colaborador ? [['email' => $email_colaborador]] : [];
            $attendees = []; // Deixe vazio por enquanto ou implemente a busca do e-mail

            $google_event_id_para_salvar = $gcalHelper->createEvent(
                $googleAccessToken,
                $summary,
                $description,
                $dateTimeInicio->format(DateTime::RFC3339),
                $dateTimeFim->format(DateTime::RFC3339),
                $fusoHorario,
                $attendees
            );

            if ($google_event_id_para_salvar) {
                $logger->log('GCAL_SUCCESS', 'Evento de turno criado no Google Calendar.', ['user_id' => $userId, 'turno_data' => $data_formatada_mysql, 'colaborador' => $colaborador_nome, 'google_event_id' => $google_event_id_para_salvar]);
            } else {
                $logger->log('GCAL_ERROR', 'Falha ao criar evento de turno no Google Calendar (retornou null).', ['user_id' => $userId, 'turno_data' => $data_formatada_mysql, 'colaborador' => $colaborador_nome]);
            }
        } catch (Exception $e) {
            $logger->log('GCAL_ERROR', 'Exceção ao tentar criar evento no Google Calendar: ' . $e->getMessage(), ['user_id' => $userId, 'turno_data' => $data_formatada_mysql, 'colaborador' => $colaborador_nome]);
        }
    }
    // Fim da Integração Google Calendar

    mysqli_stmt_bind_param($stmt_insert, "ssssi", $data_formatada_mysql, $hora_str, $colaborador_nome, $google_event_id_para_salvar, $userId);

    if (!mysqli_stmt_execute($stmt_insert)) {
        $erros_insercao[] = "Erro ao inserir turno (" . htmlspecialchars($data_formatada_mysql) . ", " . htmlspecialchars($hora_str) . ", " . htmlspecialchars($colaborador_nome) . "): " . mysqli_stmt_error($stmt_insert);
        $logger->log('ERROR', 'Falha ao executar insert de turno.', ['user_id' => $userId, 'data' => $data_formatada_mysql, 'colaborador' => $colaborador_nome, 'error' => mysqli_stmt_error($stmt_insert)]);
    } else {
         $logger->log('INFO', 'Turno salvo no BD.', ['user_id' => $userId, 'data' => $data_formatada_mysql, 'colaborador' => $colaborador_nome, 'google_event_id' => $google_event_id_para_salvar]);
         // Adicionar ao array de turnos salvos para retorno, se necessário.
    }
}

mysqli_stmt_close($stmt_insert);

if (!empty($erros_insercao)) {
    echo json_encode([
        'success' => false,
        'message' => 'Ocorreram erros ao salvar alguns turnos. Detalhes: ' . implode("; ", $erros_insercao),
        'data' => []
    ]);
    mysqli_close($conexao);
    exit;
}

// Após salvar, busca todos os turnos (ou os relevantes) para retornar ao cliente
// Modifique esta query para buscar os turnos relevantes para a visualização atual (ex: por mês/ano)
$sql_select_todos_turnos = "SELECT id, DATE_FORMAT(data, '%d/%b') AS data_formatada, DATE_FORMAT(data, '%Y') AS ano_formatado, hora, colaborador, google_calendar_event_id FROM turnos WHERE criado_por_usuario_id = ? ORDER BY data, hora";
$stmt_select = mysqli_prepare($conexao, $sql_select_todos_turnos);

if (!$stmt_select) {
    $error_msg = 'Erro ao preparar consulta de seleção de turnos: ' . mysqli_error($conexao);
    $logger->log('ERROR', $error_msg, ['user_id' => $userId]);
    echo json_encode(['success' => true, 'message' => 'Turnos salvos, mas erro ao recarregar: ' . $error_msg, 'data' => []]); // Sucesso no save, erro no reload
    mysqli_close($conexao);
    exit;
}

mysqli_stmt_bind_param($stmt_select, "i", $userId);
mysqli_stmt_execute($stmt_select);
$result_select = mysqli_stmt_get_result($stmt_select);

$turnos_atualizados = [];
while ($row = mysqli_fetch_assoc($result_select)) {
    // Reformatar a data para incluir o ano se a coluna 'data_formatada' não o fizer
    // $data_exibicao = $row['data_formatada'] . '/' . $row['ano_formatado']; // Ou ajuste o DATE_FORMAT
    $turnos_atualizados[] = [
        'id' => $row['id'],
        'data' => $row['data_formatada'], // O DATE_FORMAT original é '%d/%b' (ex: 03/Mai)
        'hora' => $row['hora'],
        'colaborador' => $row['colaborador'],
        'google_event_id' => $row['google_calendar_event_id']
    ];
}
mysqli_stmt_close($stmt_select);

echo json_encode([
    'success' => true,
    'message' => 'Turnos salvos e dados recuperados com sucesso! ' . ($googleAccessToken ? 'Tentativa de integração com Google Calendar realizada.' : 'Google Calendar não conectado.'),
    'data' => $turnos_atualizados
]);

mysqli_close($conexao);
