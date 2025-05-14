<?php
require_once __DIR__ . '/config.php'; // Garante que a sessão está iniciada e carrega configurações

// Verificar se o usuário está logado, redirecionar para o login se não estiver
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sessão expirada ou acesso negado.', 'action' => 'redirect', 'location' => 'index.html']);
        exit;
    }
    header('Location: index.html?erro=' . urlencode('Acesso negado. Faça login primeiro.'));
    exit;
}

// Gerar um token CSRF se não existir um na sessão, ou se for GET e quisermos um novo.
// Para POST, o token será validado e um novo será enviado na resposta JSON.
if (empty($_SESSION['csrf_token']) || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$nomeUsuarioLogado = $_SESSION['usuario_nome_completo'] ?? 'Usuário';

// Determinar o ano e mês a serem exibidos (pode vir de GET parameters no futuro)
$anoExibicao = date('Y');
$mesExibicao = date('m'); // Mês como número (01-12)

$nomesMeses = ["", "Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];
$nomeMesExibicao = $nomesMeses[(int)$mesExibicao];

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - Gestão de Turnos</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="dashboard-body-background">
  <div class="dashboard-layout-container">
    <aside class="dashboard-sidebar">
      <div class="sidebar-header">
        <h3><i class="fas fa-bars"></i> Menu Principal</h3>
      </div>
      <nav>
        <ul>
          <li class="sidebar-nav-item active"><a href="home.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <li class="sidebar-nav-item"><a href="relatorio_turnos.php"><i class="fas fa-file-alt"></i> Relatórios</a></li>
          <li class="sidebar-nav-item"><a href="gerenciar_colaboradores.php"><i class="fas fa-users"></i> Colaboradores</a></li> 
          <!-- <li class="sidebar-nav-item"><a href="cadastrar_colaborador.php"><i class="fas fa-user-plus"></i> Cadastrar Colaborador</a></li> --> <!--Remover esse menu por hora-->
          <li class="sidebar-nav-item"><a href="calendario_fullscreen.php"><i class="fab fa-google"></i> Google Calendar</a></li>
        </ul>
      </nav>
      <div class="sidebar-footer">
        <a href="logout.php" id="logout-link" class="sidebar-nav-item"><i class="fas fa-sign-out-alt"></i> Sair</a>
      </div>
    </aside>

    <div class="dashboard-main-content">
      <header class="dashboard-header">
        <div class="header-title-container">
          <h1><i class="fas fa-gas-pump"></i> Sim Posto - Gestão de Turnos</h1>
        </div>
        <div id="user-info" class="user-profile-area">
          Olá, <?php echo htmlspecialchars($nomeUsuarioLogado); ?> <i class="fas fa-user-circle"></i>
        </div>
      </header>

      <main class="dashboard-grid">
        <section class="dashboard-widget widget-calendar">
          <h2><i class="fas fa-calendar-check"></i> Calendário de Eventos (Google)</h2>
          <div class="calendar-integration-placeholder">
            <iframe src="https://calendar.google.com/calendar/embed?src=<?php echo urlencode($_SESSION['usuario_email'] ?? 'primary'); ?>&src=pt-br.brazilian%23holiday%40group.v.calendar.google.com&ctz=America%2FSao_Paulo"
              style="border:solid 1px #777" width="100%" height="350" frameborder="0" scrolling="no"></iframe>
          </div>
          <section id="google-calendar-section" class="widget-google-calendar-integration">
            <h3><i class="fab fa-google"></i> Integração com Google Calendar</h3>
            <p id="gcal-status-message">Verifique o status da conexão ou conecte sua conta.</p>
            <div class="gcal-buttons">
              <a href="google_auth_redirect.php" class="action-button gcal-button" id="connect-gcal-btn">
                <i class="fab fa-google"></i> Conectar
              </a>
              <button id="disconnect-gcal-btn" class="action-button red gcal-button" style="display: none;">
                <i class="fas fa-unlink"></i> Desconectar
              </button>
            </div>
            <p><small>Permitir que o Sim Posto adicione seus turnos à sua agenda Google.</small></p>
          </section>
        </section>

        <section class="dashboard-widget widget-shifts-table">
          <input type="hidden" id="csrf-token-shifts" value="<?php echo htmlspecialchars($csrfToken); ?>">

          <div class="shifts-table-navigation">
            <button id="prev-month-button" class="action-button"><i class="fas fa-chevron-left"></i> Anterior</button>
            <h2 id="current-month-year-display" data-year="<?php echo $anoExibicao; ?>" data-month="<?php echo $mesExibicao; ?>">
                <i class="fas fa-tasks"></i> Turnos - <?php echo $nomeMesExibicao . ' ' . $anoExibicao; ?>
            </h2>
            <button id="next-month-button" class="action-button">Próximo <i class="fas fa-chevron-right"></i></button>
          </div>
          
          <div class="button-group">
            <button id="add-shift-row-button" class="action-button green"><i class="fas fa-plus-circle"></i> Adicionar Turno</button>
            <button id="delete-selected-shifts-button" class="action-button red"><i class="fas fa-trash-alt"></i> Excluir Selecionados</button>
            <button id="save-shifts-button" class="action-button primary"><i class="fas fa-save"></i> Salvar Alterações</button>
          </div>

          <div class="table-responsive">
             <table id="shifts-table-main"> <thead>
                <tr>
                  <th><input type="checkbox" id="select-all-shifts" title="Selecionar Todos"></th>
                  <th>Dia (dd/Mês)</th>
                  <th>Início (HH:MM)</th>
                  <th>Fim (HH:MM)</th>
                  <th>Colaborador</th>
                  </tr>
              </thead>
              <tbody>
                </tbody>
            </table>
          </div>
        </section>

        <section class="dashboard-widget widget-employee-summary">
          <h2>
            <i class="fas fa-chart-bar"></i>
            <span class="widget-title-text">Resumo de Horas por Colaborador</span>
            (<span id="employee-summary-period"><?php echo $nomeMesExibicao; ?></span>)
          </h2>
          <div class="summary-container">
            <div class="summary-table-container">
              <table id="employee-summary-table">
                <thead>
                  <tr>
                    <th>Colaborador</th>
                    <th>Total de Horas</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
            <div class="summary-chart-container">
              <canvas id="employee-hours-chart"></canvas>
            </div>
          </div>
        </section>
      </main>
    </div>
  </div>
  <script src="script.js"></script>
</body>
</html>