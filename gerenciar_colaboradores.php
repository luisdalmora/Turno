<?php
require_once __DIR__ . '/config.php'; // Garante que a sessão está iniciada

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Redirecionamento para AJAX requests
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sessão expirada ou acesso negado.', 'action' => 'redirect', 'location' => 'index.html']);
        exit;
    }
    header('Location: index.html?erro=' . urlencode('Acesso negado. Faça login primeiro.'));
    exit;
}
$nomeUsuarioLogado = $_SESSION['usuario_nome_completo'] ?? 'Usuário';

// Token CSRF para ações nesta página
if (empty($_SESSION['csrf_token_colab_manage'])) {
    $_SESSION['csrf_token_colab_manage'] = bin2hex(random_bytes(32));
}
$csrfTokenColabManage = $_SESSION['csrf_token_colab_manage'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Colaboradores - Sim Posto</title>
    <link href="src/output.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        /* Estilos para o modal, podem ser migrados para Tailwind se preferir */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            display: none; align-items: center; justify-content: center;
            z-index: 1000; opacity: 0; transition: opacity 0.3s ease-in-out;
        }
        .modal-overlay.show { display: flex; opacity: 1; }
        .modal-content {
            background-color: white; padding: 20px; /* Tailwind: p-5 ou p-6 */ border-radius: 8px; /* Tailwind: rounded-lg */
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15); /* Tailwind: shadow-xl ou shadow-2xl */
            width: 90%; max-width: 500px; position: relative;
            transform: scale(0.95); transition: transform 0.3s ease-in-out;
        }
        .modal-overlay.show .modal-content { transform: scale(1); }
        .modal-close-button {
            position: absolute; top: 10px; right: 15px; /* Tailwind: top-2.5 right-4 ou similar */
            font-size: 1.8em; color: #6b7280; /* Tailwind: text-gray-500 */ cursor: pointer; line-height: 1;
        }
        .modal-close-button:hover { color: #374151; /* Tailwind: hover:text-gray-700 */ }
        .form-group-modal { margin-bottom: 18px; /* Tailwind: mb-4 ou mb-5 */ }
        .form-group-modal label { display: block; margin-bottom: 6px; /* Tailwind: mb-1.5 */ font-weight: 500; /* Tailwind: font-medium */ font-size: 0.9em; /* Tailwind: text-sm */}
        .form-group-modal input[type="text"],
        .form-group-modal input[type="email"] {
             width: 100%; /* Tailwind: w-full */ padding: 10px; /* Tailwind: p-2.5 */ border: 1px solid #d1d5db; /* Tailwind: border border-gray-300 */
             border-radius: 0.375rem; /* Tailwind: rounded-md */ box-sizing: border-box; font-size: 0.95em; /* Tailwind: text-sm */
        }
        .form-group-modal input[type="text"]:focus,
        .form-group-modal input[type="email"]:focus {
            border-color: #4f46e5; /* Tailwind: focus:border-indigo-500 */
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2); /* Tailwind: focus:ring-2 focus:ring-indigo-500/30 ou similar */
            outline: none;
        }
        .modal-actions { margin-top: 25px; /* Tailwind: mt-6 */ display: flex; justify-content: flex-end; gap: 10px; /* Tailwind: space-x-3 */}
        .status-ativo { color: #10b981; /* Tailwind: text-green-600 */ font-weight: bold; /* Tailwind: font-bold */ }
        .status-inativo { color: #ef4444; /* Tailwind: text-red-500 */ font-weight: bold; /* Tailwind: font-bold */ }
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
            <a href="gerenciar_colaboradores.php" class="flex items-center px-3 py-2.5 rounded-lg bg-blue-600 text-white font-medium text-sm">
              <i data-lucide="users" class="w-5 h-5 mr-3"></i> Colaboradores
            </a>
            <a href="calendario_fullscreen.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm">
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

        <div class="flex-grow flex flex-col overflow-y-auto">
            <header class="h-16 bg-white shadow-sm flex items-center justify-between px-4 md:px-6 flex-shrink-0">
                <div class="flex items-center">
                  <i data-lucide="users-cog" class="w-6 h-6 md:w-7 md:h-7 mr-2 md:mr-3 text-blue-600"></i>
                  <h1 class="text-md md:text-lg font-semibold text-gray-800">Gerenciar Colaboradores</h1>
                </div>
                <div id="user-info" class="flex items-center text-sm font-medium text-gray-700">
                  Olá, <?php echo htmlspecialchars($nomeUsuarioLogado); ?>
                  <i data-lucide="circle-user-round" class="w-5 h-5 md:w-6 md:h-6 ml-2 text-blue-600"></i>
                </div>
            </header>

            <main class="flex-grow p-4 md:p-6">
                <section class="bg-white p-4 md:p-6 rounded-lg shadow">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                        <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i data-lucide="list-ul" class="w-5 h-5 mr-2 text-blue-600"></i> Lista de Colaboradores
                        </h2>
                        <a href="cadastrar_colaborador.php" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <i data-lucide="user-plus" class="w-4 h-4 mr-2"></i> Novo Colaborador
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table id="collaborators-table" class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome Completo</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cargo</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr><td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">Carregando colaboradores... <i data-lucide="loader-circle" class="lucide-spin inline-block"></i></td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <div id="edit-collaborator-modal" class="modal-overlay">
        <div class="modal-content">
            <form id="edit-collaborator-form">
                <input type="hidden" id="edit-colab-id" name="colab_id">
                <input type="hidden" id="edit-csrf-token" name="csrf_token" value="<?php echo htmlspecialchars($csrfTokenColabManage); ?>">
                <button type="button" class="modal-close-button" id="modal-close-btn" title="Fechar"><i data-lucide="x"></i></button>
                <h2 class="text-xl font-semibold text-gray-900 mb-4 border-b pb-2">Editar Colaborador</h2>
                
                <div class="form-group-modal">
                    <label for="edit-nome_completo" class="text-sm font-medium text-gray-700">Nome Completo:</label>
                    <input type="text" id="edit-nome_completo" name="nome_completo" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                </div>
                <div class="form-group-modal">
                    <label for="edit-email" class="text-sm font-medium text-gray-700">Email:</label>
                    <input type="email" id="edit-email" name="email" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div class="form-group-modal">
                    <label for="edit-cargo" class="text-sm font-medium text-gray-700">Cargo:</label>
                    <input type="text" id="edit-cargo" name="cargo" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div class="modal-actions">
                    <button type="button" id="cancel-edit-colab-button" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i data-lucide="x-circle" class="w-4 h-4 mr-2"></i> Cancelar
                    </button>
                    <button type="submit" id="save-edit-colab-button" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i data-lucide="check-circle" class="w-4 h-4 mr-2"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="script.js"></script> 
    <script src="gerenciar_colaboradores.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }
      // Script para GCal button status
      if (typeof checkGCalConnectionStatus === 'function') {
        checkGCalConnectionStatus();
      }

      <?php
        if (isset($_SESSION['flash_message']) && is_array($_SESSION['flash_message'])) {
            $flash = $_SESSION['flash_message'];
            echo "if(typeof showToast === 'function'){ showToast('" . addslashes($flash['message']) . "', '" . addslashes($flash['type']) . "', 5000); } else { alert('" . addslashes($flash['message']) . "'); }";
            unset($_SESSION['flash_message']);
        }
      ?>
    });
    </script>
</body>
</html>