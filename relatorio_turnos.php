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

// Gerar/obter token CSRF para esta página
if (empty($_SESSION['csrf_token_reports'])) { // Usando um nome de token específico para esta página/formulário
    $_SESSION['csrf_token_reports'] = bin2hex(random_bytes(32));
}
$csrfTokenReports = $_SESSION['csrf_token_reports'];

$nomeUsuarioLogado = $_SESSION['usuario_nome_completo'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Relatório de Turnos - Sim Posto</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <script defer src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>

<body class="dashboard-body-background">
  <div class="dashboard-layout-container">
    <aside class="dashboard-sidebar">
      <div class="sidebar-header menu-header"> <i data-lucide="gauge-circle" class="sidebar-logo-icon"></i> <h2>Sim Posto</h2>
      </div>
      <nav>
        <ul>
          <li class="sidebar-nav-item menu-item"><a href="home.php"><i data-lucide="layout-dashboard"></i> Dashboard</a></li>
          <li class="sidebar-nav-item menu-item active"><a href="relatorio_turnos.php"><i data-lucide="file-text"></i> Relatórios</a></li>
          <li class="sidebar-nav-item menu-item"><a href="gerenciar_colaboradores.php"><i data-lucide="users"></i> Colaboradores</a></li>
          <li class="sidebar-nav-item menu-item"><a href="calendario_fullscreen.php"><i data-lucide="calendar-days"></i> Google Calendar</a></li>
        </ul>
      </nav>
      <div class="sidebar-footer">
        <div class="logout-container"> <a href="logout.php" id="logout-link" class="sair-btn">
                <i data-lucide="log-out"></i> Sair
            </a>
        </div>
      </div>
    </aside>

    <div class="dashboard-main-content">
      <header class="dashboard-header">
        <div class="header-title-container">
          <h1><i data-lucide="file-pie-chart"></i> Relatório de Turnos Trabalhados</h1>
        </div>
        <div id="user-info" class="user-profile-area">
            Olá, <?php echo htmlspecialchars($nomeUsuarioLogado); ?> <i data-lucide="circle-user-round"></i>
        </div>
      </header>

      <main class="report-page-main">
        <section class="dashboard-widget report-filters-widget">
          <h2><i data-lucide="filter"></i> Filtros do Relatório</h2>
          <form id="report-filters-form" class="filters-form-grid">
            <input type="hidden" id="csrf-token-reports" value="<?php echo htmlspecialchars($csrfTokenReports); ?>">

            <div class="form-group">
              <label for="filtro-data-inicio">Data Início:</label>
              <input type="date" id="filtro-data-inicio" name="filtro-data-inicio" class="form-control-filter" required>
            </div>
            <div class="form-group">
              <label for="filtro-data-fim">Data Fim:</label>
              <input type="date" id="filtro-data-fim" name="filtro-data-fim" class="form-control-filter" required>
            </div>
            <div class="form-group">
              <label for="filtro-colaborador">Colaborador:</label>
              <select id="filtro-colaborador" name="filtro-colaborador" class="form-control-filter">
                <option value="">Todos os Colaboradores</option>
              </select>
            </div>
            <div class="form-group-submit">
              <button type="submit" id="generate-report-button" class="action-button primary"><i data-lucide="search"></i> Gerar Relatório</button>
            </div>
          </form>
        </section>

        <section class="dashboard-widget report-display-widget">
          <h2><i data-lucide="list-checks"></i> Resultado do Relatório</h2>
          <div id="report-summary" class="report-summary-info">
            <p>Utilize os filtros acima e clique em "Gerar Relatório".</p>
          </div>
          <div class="table-responsive">
            <table id="report-table" class="widget-table"> <thead>
                <tr>
                  <th>Data</th>
                  <th>Colaborador</th>
                  <th>Hora Início</th>
                  <th>Hora Fim</th>
                  <th>Duração</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="5" style="text-align:center;">Nenhum relatório gerado ainda.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </main>
    </div>
  </div>
  <script src="script.js"></script> 
  <script src="relatorio_turnos.js"></script> 
  <script>
    // Inicializa Lucide Icons após o DOM estar pronto e os scripts carregados
    document.addEventListener('DOMContentLoaded', () => {
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }
    });
  </script>
</body>
</html>