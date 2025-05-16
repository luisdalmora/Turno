<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/LogHelper.php';

$logger = new LogHelper($conexao);
header('Content-Type: application/json'); 

// Validação de método e CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Método não permitido."]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['csrf_token_backup']) || !isset($_SESSION['csrf_token_backup']) || !hash_equals($_SESSION['csrf_token_backup'], $input['csrf_token_backup'])) {
    $logger->log('SECURITY_WARNING', 'Falha CSRF token em backup_database.php.', ['user_id' => $_SESSION['usuario_id'] ?? 'N/A']);
    echo json_encode(['success' => false, 'message' => 'Erro de segurança (token inválido).']);
    exit;
}


if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    $logger->log('SECURITY_WARNING', 'Tentativa de acesso não autenticado ao backup_database.php.');
    echo json_encode(["success" => false, "message" => "Acesso não autorizado."]);
    exit;
}
// Adicionar verificação de role de administrador se necessário:
// if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') { ... }


$dbName = $db_database;
$backupFileBase = $dbName . '_backup_' . date("Ymd_His");
$backupFile = $backupFileBase . '.bak';
$backupFolder = __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR; 

if (!is_dir($backupFolder)) {
    if (!mkdir($backupFolder, 0775, true)) {
        $logger->log('ERROR', 'Falha ao criar pasta de backups.', ['path' => $backupFolder]);
        echo json_encode(["success" => false, "message" => "Erro interno: Não foi possível criar a pasta de backups."]);
        exit;
    }
}
if (!is_writable($backupFolder)) {
    $logger->log('ERROR', 'Pasta de backups sem permissão de escrita para o PHP.', ['path' => $backupFolder]);
    echo json_encode(["success" => false, "message" => "Erro interno: A pasta de backups não tem permissão de escrita para o PHP."]);
    exit;
}

$fullPathToBackup = $backupFolder . $backupFile;
$sqlServerPathToBackup = str_replace('/', '\\', $fullPathToBackup); 

$tsqlBackup = "BACKUP DATABASE [{$dbName}] TO DISK = N'{$sqlServerPathToBackup}' WITH FORMAT, MEDIANAME = N'SQLServerBackups', NAME = N'Full Backup of {$dbName}', STATS = 10, CHECKSUM;";
$logger->log('INFO', 'Tentando executar comando de backup SQL Server.', ['user_id' => $_SESSION['usuario_id'], 'tsql' => $tsqlBackup]);

sqlsrv_configure("WarningsReturnAsErrors", 0);
$stmt = sqlsrv_query($conexao, $tsqlBackup);
sqlsrv_configure("WarningsReturnAsErrors", 1); 

$criticalErrorOccurred = false;
$errorMessagesForLog = [];

if ($stmt === false) {
    $criticalErrorOccurred = true;
    $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    if ($errors !== null) { foreach ($errors as $error) { $errorMessagesForLog[] = "SQLSTATE: {$error['SQLSTATE']}, Code: {$error['code']}, Msg: {$error['message']}"; } }
    $logger->log('ERROR', 'Falha CRÍTICA ao submeter comando de backup.', ['user_id' => $_SESSION['usuario_id'], 'errors' => $errorMessagesForLog]);
} else {
    $allMessages = sqlsrv_errors(SQLSRV_ERR_ALL);
    if ($allMessages !== null) {
        foreach ($allMessages as $messageInfo) {
            $isWarning = strpos($messageInfo['SQLSTATE'], '01') === 0;
            $isPagesProcessedMsg = $messageInfo['code'] == 4035;
            if (!$isWarning && !$isPagesProcessedMsg) {
                $criticalErrorOccurred = true;
                $errorMessagesForLog[] = "SQLSTATE: {$messageInfo['SQLSTATE']}, Code: {$messageInfo['code']}, Msg: {$messageInfo['message']}";
                $logger->log('ERROR', "SQL Server Error During/After Backup: ".end($errorMessagesForLog), ['user_id' => $_SESSION['usuario_id']]);
            } else {
                 $logger->log('INFO', "SQL Server Info/Warning: SQLSTATE: {$messageInfo['SQLSTATE']}, Code: {$messageInfo['code']}, Msg: {$messageInfo['message']}", ['user_id' => $_SESSION['usuario_id']]);
            }
        }
    }
    if($stmt) sqlsrv_free_stmt($stmt);
}

if (!$criticalErrorOccurred) {
    $logger->log('INFO', 'Comando de backup SQL Server processado. Verificando arquivo...', ['user_id' => $_SESSION['usuario_id'], 'file_expected' => $fullPathToBackup]);
    sleep(3); 

    if (file_exists($fullPathToBackup) && filesize($fullPathToBackup) > 0) {
        $logger->log('INFO', 'Backup realizado com sucesso e arquivo verificado.', ['user_id' => $_SESSION['usuario_id'], 'file' => $fullPathToBackup]);
        
        $downloadScriptName = 'download_backup_file.php';
        // Use SITE_URL definida em config.php para construir a URL de download absoluta
        $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : ''; // Garante que não haja barra no final
        $downloadUrl = $siteUrl . '/' . $downloadScriptName . '?file=' . urlencode(basename($fullPathToBackup));
        
        echo json_encode([
            "success" => true, 
            "message" => "Backup do banco de dados concluído com sucesso!",
            "download_url" => $downloadUrl,
            "filename" => basename($fullPathToBackup) // Envia o nome do arquivo também
        ]);
    } else {
        $logger->log('ERROR', 'Arquivo de backup NÃO encontrado ou está vazio após comando.', ['user_id' => $_SESSION['usuario_id'], 'file_expected' => $fullPathToBackup, 'exists' => file_exists($fullPathToBackup), 'size' => file_exists($fullPathToBackup) ? filesize($fullPathToBackup) : 'N/A']);
        echo json_encode(["success" => false, "message" => "Erro: O arquivo de backup não foi gerado corretamente ou não foi encontrado."]);
    }
} else {
    $userVisibleError = "Falha no backup: " . (!empty($errorMessagesForLog) ? end($errorMessagesForLog) : "Erro desconhecido na execução do backup.");
    echo json_encode(["success" => false, "message" => $userVisibleError]);
}

if ($conexao) sqlsrv_close($conexao);
exit;
