<?php
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sessão expirada ou acesso negado.', 'action' => 'redirect', 'location' => 'index.html']);
        exit;
    }
    header('Location: index.html?erro=' . urlencode('Acesso negado. Faça login primeiro.'));
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

if (empty($_SESSION['csrf_token_implantacoes'])) {
    $_SESSION['csrf_token_implantacoes'] = bin2hex(random_bytes(32));
}
$csrfTokenImplantacoes = $_SESSION['csrf_token_implantacoes'];

if (empty($_SESSION['csrf_token_obs_geral'])) {
    $_SESSION['csrf_token_obs_geral'] = bin2hex(random_bytes(32));
}
$csrfTokenObsGeral = $_SESSION['csrf_token_obs_geral'];

$nomeUsuarioLogado = $_SESSION['usuario_nome_completo'] ?? 'Usuário';
$emailUsuarioLogado = $_SESSION['usuario_email'] ?? 'primary';

$anoExibicao = date('Y');
$mesExibicao = date('m');
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
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
  <script defer src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>

<body class="dashboard-body-background">
  <div class="dashboard-layout-container">
    <aside class="dashboard-sidebar">
      <div class="sidebar-header menu-header">
        <i data-lucide="gauge-circle" class="sidebar-logo-icon"></i>
        <h2>Sim Posto</h2>
      </div>
      <nav>
        <ul>
          <li class="sidebar-nav-item menu-item active">
            <a href="home.php"><i data-lucide="layout-dashboard"></i> Dashboard</a>
          </li>
          <li class="sidebar-nav-item menu-item">
            <a href="relatorio_turnos.php"><i data-lucide="file-text"></i> Relatórios</a>
          </li>
          <li class="sidebar-nav-item menu-item">
            <a href="gerenciar_colaboradores.php"><i data-lucide="users"></i> Colaboradores</a>
          </li>
          <li class="sidebar-nav-item menu-item">
            <a href="calendario_fullscreen.php"><i data-lucide="calendar-days"></i> Google Calendar</a>
          </li>
        </ul>
      </nav>
      <div class="sidebar-footer">
        <li class="sidebar-nav-item gcal-sidebar-button-container menu-item">
            <a href="google_auth_redirect.php" class="action-button gcal-button" id="connect-gcal-btn">
                <i data-lucide="link"></i> G Conectar  {/* Lucide 'link' ou 'plug' */}
            </a>
            <button id="disconnect-gcal-btn" class="action-button red gcal-button" style="display: none;">
                <i data-lucide="unlink-2"></i> G Desconectar {/* Lucide 'unlink-2' ou 'plug-zap' */}
            </button>
        </li>
        <div class="logout-container">
            <a href="logout.php" id="logout-link" class="sair-btn">
                <i data-lucide="log-out"></i> Sair
            </a>
        </div>
      </div>
    </aside>

    <div class="dashboard-main-content">
      <header class="dashboard-header">
        <div class="header-title-container">
          <h1><i data-lucide="fuel" style="margin-right: 10px; vertical-align: middle;"></i> Sim Posto - Gestão de Turnos</h1>
        </div>
        <div id="user-info" class="user-profile-area">
          Olá, <?php echo htmlspecialchars($nomeUsuarioLogado); ?> <i data-lucide="circle-user-round" style="margin-left: 8px; vertical-align: middle;"></i>
        </div>
      </header>

      <main class="dashboard-grid">
        <section class="dashboard-widget widget-calendar">
          <h2><i data-lucide="calendar-check-2"></i> Calendário de Eventos (Google)</h2>
          <div class="calendar-integration-placeholder">
            <iframe src="https://calendar.google.com/calendar/embed?src=<?php echo urlencode($emailUsuarioLogado); ?>&src=pt-br.brazilian%23holiday%40group.v.calendar.google.com&ctz=America%2FSao_Paulo"
              style="border:solid 1px #777" width="100%" height="350" frameborder="0" scrolling="no"></iframe>
          </div>
          
          <div class="widget-feriados" style="margin-top: 20px;">
            <h3 class="shifts-table-navigation" style="justify-content: center; margin-bottom:10px; padding: 5px; font-size: 1.1em; border-bottom: 1px solid #eee;">
               <span id="feriados-mes-ano-display"><i data-lucide="calendar-heart"></i> Feriados - Carregando...</span>
            </h3>
            <div class="table-responsive" style="max-height: 200px;">
              <table id="feriados-table" class="widget-table">
                <thead>
                  <tr>
                    <th>DATA</th>
                    <th>OBSERVAÇÃO (FERIADO)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr><td colspan="2" style="text-align:center;">Carregando feriados...</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <section class="dashboard-widget widget-shifts-table">
          <input type="hidden" id="csrf-token-shifts" value="<?php echo htmlspecialchars($csrfToken); ?>">
          <div class="shifts-table-navigation">
            <button id="prev-month-button" class="action-button"><i data-lucide="chevron-left"></i> Anterior</button>
            <h2 id="current-month-year-display" data-year="<?php echo $anoExibicao; ?>" data-month="<?php echo $mesExibicao; ?>">
                <i data-lucide="list-todo"></i> Turnos - <?php echo $nomeMesExibicao . ' ' . $anoExibicao; ?>
            </h2>
            <button id="next-month-button" class="action-button">Próximo <i data-lucide="chevron-right"></i></button>
          </div>
          <div class="button-group">
            <button id="add-shift-row-button" class="action-button green"><i data-lucide="plus-circle"></i> Adicionar Turno</button>
            <button id="delete-selected-shifts-button" class="action-button red"><i data-lucide="trash-2"></i> Excluir Selecionados</button>
            <button id="save-shifts-button" class="action-button primary"><i data-lucide="save"></i> Salvar Alterações</button>
          </div>
          <div class="table-responsive">
             <table id="shifts-table-main">
                <thead>
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
            <i data-lucide="bar-chart-3"></i>
            <span class="widget-title-text">Resumo de Horas por Colaborador</span>
            (<span id="employee-summary-period"><?php echo $nomeMesExibicao; ?></span>)
          </h2>
          <div class="summary-container">
            <div class="summary-table-container">
              <table id="employee-summary-table" class="widget-table">
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

        <section class="dashboard-widget widget-implantacoes-table">
          <input type="hidden" id="csrf-token-implantacoes" value="<?php echo htmlspecialchars($csrfTokenImplantacoes); ?>">
          <div class="shifts-table-navigation">
            <button id="prev-month-implantacoes-button" class="action-button"><i data-lucide="chevron-left"></i> Anterior</button>
            <h2 id="current-month-year-implantacoes-display">
                <i data-lucide="settings-2"></i> Implantações - Mês Ano
            </h2>
            <button id="next-month-implantacoes-button" class="action-button">Próximo <i data-lucide="chevron-right"></i></button>
          </div>
          <div class="button-group">
            <button id="add-implantacao-row-button" class="action-button green"><i data-lucide="plus-circle"></i> Adicionar Implantação</button>
            <button id="delete-selected-implantacoes-button" class="action-button red"><i data-lucide="trash-2"></i> Excluir Selecionadas</button>
            <button id="save-implantacoes-button" class="action-button primary"><i data-lucide="save"></i> Salvar Alterações</button>
          </div>
          <div class="table-responsive">
             <table id="implantacoes-table-main">
                <thead>
                  <tr>
                    <th><input type="checkbox" id="select-all-implantacoes" title="Selecionar Todos"></th>
                    <th>Dia Início</th>
                    <th>Dia Fim</th>
                    <th>Observações</th>
                  </tr>
                </thead>
                <tbody>
                  <tr><td colspan="4" style="text-align:center;">Carregando implantações...</td></tr>
                </tbody>
            </table>
          </div>
        </section>

        <section class="dashboard-widget widget-observacoes-gerais">
          <h2><i data-lucide="notebook-pen"></i> Observações Gerais</h2>
          <input type="hidden" id="csrf-token-obs-geral" value="<?php echo htmlspecialchars($csrfTokenObsGeral); ?>">
          <textarea id="observacoes-gerais-textarea" rows="5" placeholder="Digite aqui qualquer informação importante..."></textarea>
          <button id="salvar-observacoes-gerais-btn" class="action-button primary"><i data-lucide="save"></i> Salvar Observações</button>
        </section>

      </main>
    </div>
  </div>
  <script src="script.js"></script>
  <script>
    // Inicializa Lucide Icons após o DOM estar pronto
    document.addEventListener('DOMContentLoaded', () => {
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }
    });
  </script>
</body>
</html>