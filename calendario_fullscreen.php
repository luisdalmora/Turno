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
  <title>Calendário Google - Sim Posto</title>
  <link href="src/output.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script defer src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
  <style>
    /* Para garantir que o iframe ocupe o espaço disponível no main */
    .iframe-container {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }
    .iframe-container iframe {
        flex-grow: 1;
        border: none;
    }
  </style>
</head>

<body class="bg-gray-100 font-poppins text-gray-700">
  <div class="flex h-screen overflow-hidden">
    <aside class="w-64 bg-gradient-to-b from-blue-800 to-blue-700 text-indigo-100 flex flex-col flex-shrink-0">
      <div class="h-16 flex items-center px-4 md:px-6 border-b border-white/10">
        <i data-lucide="gauge-circle" class="w-7 h-7 md:w-8 md:h-8 mr-2 md:mr-3 text-white"></i>
        <h2 class="text-lg md:text-xl font-semibold text-white">Sim Posto</h2>
      </div>
      <nav class="flex-grow p-2 space-y-1">
        <a href="home.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm">
          <i data-lucide="layout-dashboard" class="w-5 h-5 mr-3"></i> Dashboard
        </a>
        <a href="relatorio_turnos.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm">
          <i data-lucide="file-text" class="w-5 h-5 mr-3"></i> Relatórios
        </a>
        <a href="gerenciar_colaboradores.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm">
          <i data-lucide="users" class="w-5 h-5 mr-3"></i> Colaboradores
        </a> 
        <a href="calendario_fullscreen.php" class="flex items-center px-3 py-2.5 rounded-lg bg-blue-600 text-white font-medium text-sm">
          <i data-lucide="calendar-days" class="w-5 h-5 mr-3"></i> Google Calendar
        </a>
      </nav>
      <div class="p-2 border-t border-white/10">
        <div class="px-2 py-1">
            <a href="google_auth_redirect.php" class="flex items-center justify-center w-full px-3 py-2 mb-1.5 rounded-lg bg-green-500 hover:bg-green-600 text-white font-medium transition-colors text-sm" id="connect-gcal-btn" style="display: none;">
                <i data-lucide="link" class="w-4 h-4 mr-2"></i> Conectar Google
            </a>
            <button id="disconnect-gcal-btn" class="flex items-center justify-center w-full px-3 py-2 mb-1.5 rounded-lg bg-yellow-500 hover:bg-yellow-600 text-gray-800 font-medium transition-colors text-sm" style="display: none;">
                <i data-lucide="unlink-2" class="w-4 h-4 mr-2"></i> Desconectar Google
            </button>
        </div>
        <div class="px-2 py-1">
            <a href="logout.php" id="logout-link" class="flex items-center justify-center w-full px-3 py-2 rounded-lg bg-red-500 hover:bg-red-600 text-white font-medium transition-colors text-sm">
                <i data-lucide="log-out" class="w-4 h-4 mr-2"></i> Sair
            </a>
        </div>
      </div>
    </aside>

    <div class="flex-grow flex flex-col overflow-hidden">
      <header class="h-16 bg-white shadow-sm flex items-center justify-between px-4 md:px-6 flex-shrink-0">
        <div class="flex items-center">
          <i data-lucide="calendar" class="w-6 h-6 md:w-7 md:h-7 mr-2 md:mr-3 text-blue-600"></i>
          <h1 class="text-md md:text-lg font-semibold text-gray-800">Calendário Google</h1>
        </div>
        <div id="user-info" class="flex items-center text-sm font-medium text-gray-700">
            Olá, <?php echo htmlspecialchars($nomeUsuarioLogado); ?>
            <i data-lucide="circle-user-round" class="w-5 h-5 md:w-6 md:h-6 ml-2 text-blue-600"></i>
        </div>
      </header>

      <main class="flex-grow flex flex-col p-0">  <div class="iframe-container"> <iframe src="https://calendar.google.com/calendar/embed?src=<?php echo urlencode($emailUsuarioLogado); ?>&src=pt-br.brazilian%23holiday%40group.v.calendar.google.com&ctz=America%2FSao_Paulo"
                    width="100%" height="100%" scrolling="no"></iframe>
        </div>
      </main>
    </div>
  </div>
  <script src="script.js"></script> 
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }
      // Script para GCal button status
      if (typeof checkGCalConnectionStatus === 'function') {
        checkGCalConnectionStatus();
      }
    });
  </script>
</body>
</html>