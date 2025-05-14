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
          <li class="sidebar-nav-item"><a href="relatorio_turnos.php"><i class="fas fa-file-alt"></i> Relatórios</a></li>
          <li class="sidebar-nav-item"><a href="gerenciar_colaboradores.php"><i class="fas fa-users"></i> Colaboradores</a></li> 
          <!-- <li class="sidebar-nav-item"><a href="cadastrar_colaborador.php"><i class="fas fa-user-plus"></i> Cadastrar Colaborador</a></li> --> <!--Remover esse menu por hora-->
          <li class="sidebar-nav-item active"><a href="calendario_fullscreen.php"><i class="fab fa-google"></i> Google Calendar</a></li>
        </ul>
      </nav>
      <div class="sidebar-footer">
        <a href="logout.php" id="logout-link" class="sidebar-nav-item"><i class="fas fa-sign-out-alt"></i> Sair</a>
      </div>
    </aside>

    <div class="dashboard-main-content calendar-fullscreen-main-content">
      <header class="dashboard-header calendar-fullscreen-header">
        <div class="header-title-container">
          <h1><i class="fab fa-google"></i> Calendário Google - Visualização Combinada</h1>
        </div>
        <div id="user-info" class="user-profile-area">
             Olá, <?php echo htmlspecialchars($nomeUsuarioLogado); ?> <i class="fas fa-user-circle"></i>
        </div>
      </header>
      <main class="fullscreen-calendar-iframe-container">
        <iframe src="https://calendar.google.com/calendar/embed?src=<?php echo urlencode($emailUsuarioLogado); ?>&src=pt-br.brazilian%23holiday%40group.v.calendar.google.com&ctz=America%2FSao_Paulo"
                width="100%" height="100%" style="border:none;" frameborder="0" scrolling="no"></iframe>
      </main>
    </div>
  </div>
  <script src="script.js"></script> </body>
</html>
