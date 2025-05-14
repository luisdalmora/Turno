<?php
require_once __DIR__ . '/config.php'; // Garante que a sessão está iniciada

// Verificar se o usuário está logado, redirecionar para o login se não estiver
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header('Location: index.html?erro=' . urlencode('Acesso negado. Faça login primeiro.'));
    exit;
}
$nomeUsuarioLogado = $_SESSION['usuario_nome_completo'] ?? 'Usuário';

// Token CSRF para este formulário específico
// Usar um nome de token diferente para cada formulário/página de ação é uma boa prática
if (empty($_SESSION['csrf_token_cad_colab'])) {
    $_SESSION['csrf_token_cad_colab'] = bin2hex(random_bytes(32));
}
$csrfTokenCadColab = $_SESSION['csrf_token_cad_colab'];

// Para exibir mensagens de erro/sucesso da tentativa anterior (flash messages)
$flashMessage = null;
if (isset($_SESSION['flash_message'])) {
    $flashMessage = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); // Limpa a mensagem da sessão após lê-la
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Novo Colaborador - Sim Posto</title>
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
                    <li class="sidebar-nav-item active"><a href="cadastrar_colaborador.php"><i class="fas fa-user-plus"></i> Cadastrar Colaborador</a></li>
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
                    <h1><i class="fas fa-user-plus"></i> Cadastrar Novo Colaborador</h1>
                </div>
                <div id="user-info" class="user-profile-area">
                    Olá, <?php echo htmlspecialchars($nomeUsuarioLogado); ?> <i class="fas fa-user-circle"></i>
                </div>
            </header>

            <main>
                <section class="dashboard-widget" style="max-width: 700px; margin: 20px auto;">
                    <div class="login-form-wrapper" style="width: 100%; box-shadow: none; padding:0;"> 
                        <form method="POST" action="processar_cadastro_colaborador.php" style="border-bottom: none;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfTokenCadColab); ?>">

                            <div class="input-group margin-bottom-35">
                                <input class="input-field" type="text" name="nome_completo" id="nome_completo_cad" required aria-label="Nome Completo" />
                                <span class="input-focus-effect" data-placeholder="Nome Completo*"></span>
                            </div>

                            <div class="input-group margin-bottom-35">
                                <input class="input-field" type="email" name="email" id="email_cad" aria-label="E-mail (Opcional)" />
                                <span class="input-focus-effect" data-placeholder="E-mail (Opcional)"></span>
                            </div>

                            <div class="input-group margin-bottom-35">
                                <input class="input-field" type="text" name="cargo" id="cargo_cad" aria-label="Cargo (Opcional)" />
                                <span class="input-focus-effect" data-placeholder="Cargo (Opcional)"></span>
                            </div>

                            <div class="form-group" style="margin-bottom: 35px;">
                                <label for="ativo_cad" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--primary-text-color);">
                                    <i class="fas fa-toggle-on"></i> Status do Colaborador:
                                </label>
                                <select name="ativo" id="ativo_cad" class="form-control-filter" style="height: 52px; font-size: 18px; font-weight: 600; color: var(--input-field-text-color); background-color: transparent; border: none; border-bottom: 2px solid var(--input-border-color); border-radius:0;">
                                    <option value="1" selected>Ativo</option>
                                    <option value="0">Inativo</option>
                                </select>
                            </div>

                            <div class="login-form-button-container" style="margin-top: 20px;">
                                <button class="action-button primary" type="submit" style="width:100%;">
                                    <i class="fas fa-check-circle"></i> Cadastrar Colaborador
                                </button>
                            </div>
                        </form>
                        <ul class="login-utility-links" style="margin-top: 30px; text-align:left;">
                            <li><a href="gerenciar_colaboradores.php" class="utility-text-secondary"><i class="fas fa-arrow-left"></i> Voltar para Gerenciar Colaboradores</a></li>
                        </ul>
                    </div>
                </section>
            </main>
        </div>
    </div>
    <script src="script.js"></script>
    <?php if ($flashMessage && is_array($flashMessage) && isset($flashMessage['message']) && isset($flashMessage['type'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Certifique-se que a função showToast está disponível (do script.js global)
            if (typeof showToast === 'function') {
                showToast('<?php echo addslashes($flashMessage['message']); ?>', '<?php echo addslashes($flashMessage['type']); ?>');
            } else {
                // Fallback caso showToast não esteja definida
                alert('<?php echo ucfirst(addslashes($flashMessage['type'])); ?>: <?php echo addslashes($flashMessage['message']); ?>');
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>