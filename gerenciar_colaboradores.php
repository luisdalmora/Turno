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
                    <li class="sidebar-nav-item active"><a href="gerenciar_colaboradores.php"><i class="fas fa-users"></i> Colaboradores</a></li> 
                    <li class="sidebar-nav-item"><a href="cadastrar_colaborador.php"><i class="fas fa-user-plus"></i> Cadastrar Colaborador</a></li>
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
                    <h1><i class="fas fa-users-cog"></i> Gerenciar Colaboradores</h1>
                </div>
                <div id="user-info" class="user-profile-area">
                    Olá, <?php echo htmlspecialchars($nomeUsuarioLogado); ?> <i class="fas fa-user-circle"></i>
                </div>
            </header>

            <main class="manage-collaborators-main">
                <section class="dashboard-widget">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2><i class="fas fa-list-ul"></i> Lista de Colaboradores</h2>
                        <a href="cadastrar_colaborador.php" class="action-button green">
                            <i class="fas fa-plus-circle"></i> Novo Colaborador
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
                                <tr><td colspan="6" style="text-align:center;">Carregando colaboradores...</td></tr>
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
                <span class="modal-close-button" id="modal-close-btn">&times;</span>
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
                    <button type="submit" id="save-edit-colab-button" class="action-button primary">Salvar Alterações</button>
                    <button type="button" id="cancel-edit-colab-button" class="action-button secondary">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="script.js"></script> <script src="gerenciar_colaboradores.js"></script>
</body>
</html>
