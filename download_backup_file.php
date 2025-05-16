<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/LogHelper.php'; // Para loggar tentativas de download
// Não precisa de $conexao aqui, a menos que LogHelper exija. Se LogHelper não precisar, pode remover.

// $logger = new LogHelper($conexao_dummy_ou_null_se_nao_usar_db_no_loghelper); 
// Se LogHelper não precisa de conexão para este script, pode instanciar com null.
// Para este exemplo, vamos assumir que LogHelper pode funcionar com $conexao = null ou que não é estritamente necessário aqui.

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // $logger->log('SECURITY_WARNING', 'Tentativa de download de backup não autenticada.');
    header("HTTP/1.1 401 Unauthorized");
    die("Acesso não autorizado.");
}
// Adicionar verificação de role de administrador se necessário:
// if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') { ... die("Permissão negada."); }


if (isset($_GET['file'])) {
    $fileName = basename($_GET['file']); // basename() para segurança, evitar traversal
    $backupFolder = __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR;
    $filePath = $backupFolder . $fileName;

    // Validações de segurança adicionais
    // Garante que o arquivo está dentro da pasta de backups esperada
    if (strpos(realpath($filePath), realpath($backupFolder)) !== 0) {
        // $logger->log('SECURITY_WARNING', 'Tentativa de path traversal no download de backup.', ['requested_file' => $_GET['file'], 'user_id' => $_SESSION['usuario_id']]);
        header("HTTP/1.1 403 Forbidden");
        die("Acesso ao arquivo negado.");
    }
    
    if (preg_match('/^simposto_backup_\d{8}_\d{6}\.bak$/', $fileName) && file_exists($filePath)) {
        // $logger->log('INFO', 'Iniciando download de backup.', ['file' => $fileName, 'user_id' => $_SESSION['usuario_id']]);

        if (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        
        readfile($filePath);
        
        // Opcional: Deletar o arquivo após o download
        // unlink($filePath);
        // $logger->log('INFO', 'Arquivo de backup removido após download.', ['file' => $fileName, 'user_id' => $_SESSION['usuario_id']]);
        exit;
    } else {
        // $logger->log('WARNING', 'Tentativa de download de arquivo de backup inválido ou não encontrado.', ['requested_file' => $_GET['file'], 'path_checked' => $filePath, 'user_id' => $_SESSION['usuario_id']]);
        header("HTTP/1.1 404 Not Found");
        die("Arquivo de backup não encontrado ou inválido.");
    }
} else {
    // $logger->log('WARNING', 'Tentativa de acesso a download_backup_file.php sem parâmetro de arquivo.', ['user_id' => $_SESSION['usuario_id']]);
    header("HTTP/1.1 400 Bad Request");
    die("Nenhum arquivo especificado para download.");
}
