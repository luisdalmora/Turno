<?php
require_once __DIR__ . '/config.php'; // Garante que a sessão está iniciada

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
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
                    <li class="sidebar-nav-item menu-item active"><a href="gerenciar_colaboradores.php"><i data-lucide="users"></i> Colaboradores</a></li>
                    <li class="sidebar-nav-item menu-item"><a href="calendario_fullscreen.php"><i data-lucide="calendar-days"></i> Google Calendar</a></li>
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

        <div class="dashboard-main-content">
            <header class="dashboard-header">
                <div class="header-title-container">
                    <h1><i data-lucide="users-cog"></i> Gerenciar Colaboradores</h1>
                </div>
                <div id="user-info" class="user-profile-area">
                    Olá, <?php echo htmlspecialchars($nomeUsuarioLogado); ?> <i data-lucide="circle-user-round"></i>
                </div>
            </header>

            <main class="manage-collaborators-main">
                <section class="dashboard-widget">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2><i data-lucide="list-ul"></i> Lista de Colaboradores</h2>
                        <a href="cadastrar_colaborador.php" class="action-button green">
                            <i data-lucide="user-plus"></i> Novo Colaborador
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table id="collaborators-table" class="widget-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome Completo</th>
                                    <th>Email</th>
                                    <th>Cargo</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="6" style="text-align:center;">Carregando colaboradores... <i data-lucide="loader-circle" class="lucide-spin"></i></td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <div id="edit-collaborator-modal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <form id="edit-collaborator-form">
                <input type="hidden" id="edit-colab-id" name="colab_id">
                <input type="hidden" id="edit-csrf-token" name="csrf_token" value="<?php echo htmlspecialchars($csrfTokenColabManage); ?>">
                <span class="modal-close-button" id="modal-close-btn" title="Fechar"><i data-lucide="x"></i></span>
                <h2>Editar Colaborador</h2>
                
                <div class="form-group-modal">
                    <label for="edit-nome_completo">Nome Completo:</label>
                    <input type="text" id="edit-nome_completo" name="nome_completo" required>
                </div>
                <div class="form-group-modal">
                    <label for="edit-email">Email:</label>
                    <input type="email" id="edit-email" name="email">
                </div>
                <div class="form-group-modal">
                    <label for="edit-cargo">Cargo:</label>
                    <input type="text" id="edit-cargo" name="cargo">
                </div>
                <div class="modal-actions">
                    <button type="submit" id="save-edit-colab-button" class="action-button primary"><i data-lucide="check-circle"></i> Salvar Alterações</button>
                    <button type="button" id="cancel-edit-colab-button" class="action-button secondary"><i data-lucide="x-circle"></i> Cancelar</button>
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
      // Se houver mensagens flash da página de cadastro, exiba-as
      <?php
        if (isset($_SESSION['flash_message']) && is_array($_SESSION['flash_message'])) {
            $flash = $_SESSION['flash_message'];
            echo "if(typeof showToast === 'function'){ showToast('" . addslashes($flash['message']) . "', '" . addslashes($flash['type']) . "'); } else { alert('" . addslashes($flash['message']) . "'); }";
            unset($_SESSION['flash_message']);
        }
      ?>
    });
    </script>
</body>
</html>