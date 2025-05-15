<?php
require_once __DIR__ . '/config.php'; // Garante que a sessão está iniciada

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header('Location: index.html?erro=' . urlencode('Acesso negado. Faça login primeiro.'));
    exit;
}
$nomeUsuarioLogado = $_SESSION['usuario_nome_completo'] ?? 'Usuário';
$emailUsuarioLogado = $_SESSION['usuario_email'] ?? 'primary'; // Usa email do usuário para o calendário principal
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Calendário Google - Tela Cheia</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
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
          <li class="sidebar-nav-item menu-item"><a href="home.php"><i data-lucide="layout-dashboard"></i> Dashboard</a></li>
          <li class="sidebar-nav-item menu-item"><a href="relatorio_turnos.php"><i data-lucide="file-text"></i> Relatórios</a></li>
          <li class="sidebar-nav-item menu-item"><a href="gerenciar_colaboradores.php"><i data-lucide="users"></i> Colaboradores</a></li> 
          <li class="sidebar-nav-item menu-item active"><a href="calendario_fullscreen.php"><i data-lucide="calendar-days"></i> Google Calendar</a></li>
        </ul>
      </nav>
      <div class="sidebar-footer">
        <div class="logout-container">
            <a href="logout.php" id="logout-link" class="sair-btn">
                <i data-lucide="log-out"></i> Sair
            </a>
        </div>
      </div>
    </aside>

    <div class="dashboard-main-content calendar-fullscreen-main-content">
      <header class="dashboard-header calendar-fullscreen-header">
        <div class="header-title-container">
          <h1><i data-lucide="calendar"></i> Calendário Google - Visualização Combinada</h1>
        </div>
        <div id="user-info" class="user-profile-area">
             Olá, <?php echo htmlspecialchars($nomeUsuarioLogado); ?> <i data-lucide="circle-user-round"></i>
        </div>
      </header>
      <main class="fullscreen-calendar-iframe-container">
        <iframe src="https://calendar.google.com/calendar/embed?src=<?php echo urlencode($emailUsuarioLogado); ?>&src=pt-br.brazilian%23holiday%40group.v.calendar.google.com&ctz=America%2FSao_Paulo"
                width="100%" height="100%" style="border:none;" frameborder="0" scrolling="no"></iframe>
      </main>
    </div>
  </div>
  <script src="script.js"></script> 
  <script>
    // Inicializa Lucide Icons
    document.addEventListener('DOMContentLoaded', () => {
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }
    });
  </script>
</body>
</html>