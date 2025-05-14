<?php
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header('Location: index.html?erro=' . urlencode('Acesso negado. Faça login primeiro.'));
    exit;
}
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
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body class="dashboard-body-background">
  <div class="dashboard-layout-container">
    <aside class="dashboard-sidebar">
      <div class="sidebar-header">
        <h3><i class="fas fa-bars"></i> Menu Principal</h3>
      </div>
      <nav>
        <ul>
          <li class="sidebar-nav-item"><a href="home.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <li class="sidebar-nav-item active"><a href="relatorio_turnos.php"><i class="fas fa-calendar-alt"></i> Gerenciar Turnos</a></li>
          <li class="sidebar-nav-item"><a href="#"><i class="fas fa-users"></i> Colaboradores</a></li>
          <li class="sidebar-nav-item"><a href="cadastrar_colaborador.html"><i class="fas fa-user-plus"></i> Cadastrar Colaborador</a></li>
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
          <h1><i class="fas fa-file-alt"></i> Relatório de Turnos Trabalhados</h1>
        </div>
        <div id="user-info" class="user-profile-area">
            Olá, <?php echo htmlspecialchars($nomeUsuarioLogado); ?> <i class="fas fa-user-circle"></i>
        </div>
      </header>

      <main class="report-page-main">
        <section class="dashboard-widget report-filters-widget">
          <h2><i class="fas fa-filter"></i> Filtros do Relatório</h2>
          <form id="report-filters-form" class="filters-form-grid">
            <div class="form-group">
              <label for="filtro-data-inicio">Data Início:</label>
              <input type="date" id="filtro-data-inicio" name="filtro-data-inicio" class="form-control-filter">
            </div>
            <div class="form-group">
              <label for="filtro-data-fim">Data Fim:</label>
              <input type="date" id="filtro-data-fim" name="filtro-data-fim" class="form-control-filter">
            </div>
            <div class="form-group">
              <label for="filtro-colaborador">Colaborador:</label>
              <select id="filtro-colaborador" name="filtro-colaborador" class="form-control-filter">
                <option value="">Todos os Colaboradores</option>
              </select>
            </div>
            <div class="form-group-submit">
              <button type="submit" class="action-button primary"><i class="fas fa-search"></i> Gerar Relatório</button>
            </div>
          </form>
        </section>

        <section class="dashboard-widget report-display-widget">
          <h2><i class="fas fa-list-alt"></i> Resultado do Relatório</h2>
          <div id="report-summary" class="report-summary-info">
          </div>
          <div class="table-responsive">
            <table id="report-table" class="widget-table">
              <thead>
                <tr>
                  <th>Data</th>
                  <th>Colaborador</th>
                  <th>Duração Registrada</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="3" style="text-align:center;">Utilize os filtros acima para gerar o relatório.</td>
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
</body>
</html>