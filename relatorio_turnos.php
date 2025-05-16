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
  <link href="src/output.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script defer src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
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
        <a href="relatorio_turnos.php" class="flex items-center px-3 py-2.5 rounded-lg bg-blue-600 text-white font-medium text-sm">
          <i data-lucide="file-text" class="w-5 h-5 mr-3"></i> Relatórios
        </a>
        <a href="gerenciar_colaboradores.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm">
          <i data-lucide="users" class="w-5 h-5 mr-3"></i> Colaboradores
        </a>
        <a href="calendario_fullscreen.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm">
          <i data-lucide="calendar-days" class="w-5 h-5 mr-3"></i> Google Calendar
        </a>
      </nav>
      <div class="p-2 border-t border-white/10">
        <div class="px-2 py-1">
            <a href="google_auth_redirect.php" class="flex items-center justify-center w-full px-3 py-2 mb-1.5 rounded-lg bg-green-500 hover:bg-green-600 text-white font-medium transition-colors text-sm" id="connect-gcal-btn" style="display: none;"> <i data-lucide="link" class="w-4 h-4 mr-2"></i> Conectar Google
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

    <div class="flex-grow flex flex-col overflow-y-auto">
      <header class="h-16 bg-white shadow-sm flex items-center justify-between px-4 md:px-6 flex-shrink-0">
        <div class="flex items-center">
          <i data-lucide="file-pie-chart" class="w-6 h-6 md:w-7 md:h-7 mr-2 md:mr-3 text-blue-600"></i>
          <h1 class="text-md md:text-lg font-semibold text-gray-800">Relatório de Turnos Trabalhados</h1>
        </div>
        <div id="user-info" class="flex items-center text-sm font-medium text-gray-700">
          Olá, <?php echo htmlspecialchars($nomeUsuarioLogado); ?>
          <i data-lucide="circle-user-round" class="w-5 h-5 md:w-6 md:h-6 ml-2 text-blue-600"></i>
        </div>
      </header>

      <main class="flex-grow p-4 md:p-6 space-y-6">
        <section class="bg-white p-4 md:p-6 rounded-lg shadow">
          <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
            <i data-lucide="filter" class="w-5 h-5 mr-2 text-blue-600"></i> Filtros do Relatório
          </h2>
          <form id="report-filters-form" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
            <input type="hidden" id="csrf-token-reports" value="<?php echo htmlspecialchars($csrfTokenReports); ?>">

            <div>
              <label for="filtro-data-inicio" class="block text-sm font-medium text-gray-700 mb-1">Data Início:</label>
              <input type="date" id="filtro-data-inicio" name="filtro-data-inicio" class="form-input block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
            </div>
            <div>
              <label for="filtro-data-fim" class="block text-sm font-medium text-gray-700 mb-1">Data Fim:</label>
              <input type="date" id="filtro-data-fim" name="filtro-data-fim" class="form-input block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
            </div>
            <div>
              <label for="filtro-colaborador" class="block text-sm font-medium text-gray-700 mb-1">Colaborador:</label>
              <select id="filtro-colaborador" name="filtro-colaborador" class="form-select block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <option value="">Todos os Colaboradores</option>
              </select>
            </div>
            <div class="sm:col-span-2 lg:col-span-1">
              <button type="submit" id="generate-report-button" class="w-full flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i data-lucide="search" class="w-4 h-4 mr-2"></i> Gerar Relatório
              </button>
            </div>
          </form>
        </section>

        <section class="bg-white p-4 md:p-6 rounded-lg shadow">
          <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
            <i data-lucide="list-checks" class="w-5 h-5 mr-2 text-blue-600"></i> Resultado do Relatório
          </h2>
          <div id="report-summary" class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-md text-sm text-blue-700">
            <p>Utilize os filtros acima e clique em "Gerar Relatório".</p>
          </div>
          <div class="overflow-x-auto">
            <table id="report-table" class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Colaborador</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hora Início</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hora Fim</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duração</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr>
                  <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">Nenhum relatório gerado ainda.</td>
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
    document.addEventListener('DOMContentLoaded', () => {
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }
      // Script para GCal button status (copiado do script.js ou home.php)
      if (typeof checkGCalConnectionStatus === 'function') {
        checkGCalConnectionStatus();
      }
    });
  </script>
</body>
</html>