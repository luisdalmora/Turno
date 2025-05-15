<?php
// LogHelper.php

class LogHelper {
    private $conexao; // Esta será uma conexão SQLSRV

    public function __construct($db_connection) {
        $this->conexao = $db_connection;
    }

    /**
     * Registra uma mensagem de log no banco de dados.
     *
     * @param string $level Nível do log (e.g., INFO, ERROR, WARNING, AUTH_SUCCESS, AUTH_FAILURE, GCAL_SUCCESS, GCAL_ERROR)
     * @param string $message A mensagem de log.
     * @param array $context Dados contextuais adicionais (serão convertidos para JSON).
     * @param int|null $userId ID do usuário associado ao log (opcional).
     */
    public function log($level, $message, $context = [], $userId = null) {
        if (!$this->conexao) {
            // Não pode logar sem conexão com o BD
            $timestamp = date('Y-m-d H:i:s');
            error_log("{$timestamp} LogHelper: Falha ao logar - Sem conexão com BD. Nível: {$level}, Mensagem: {$message}, Contexto: " . json_encode($context));
            return;
        }

        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        // Contexto pode ser nulo se vazio, para evitar armazenar '[]' explicitamente se a coluna permitir NULL
        $context_json = !empty($context) ? json_encode($context) : null;

        $sql = "INSERT INTO system_logs (log_level, message, context, ip_address, user_id) VALUES (?, ?, ?, ?, ?)";
        
        // Parâmetros para sqlsrv. Note que $userId pode ser null.
        // Se a coluna user_id no banco de dados não permitir NULL e $userId for null,
        // você pode precisar passar DBNull::value ou um valor padrão, ou ajustar a tabela.
        // Para este exemplo, assumimos que a coluna user_id permite NULLs.
        $params = array($level, $message, $context_json, $ip_address, $userId);
        
        // Opções para o statement, se necessário (ex: para Scrollable cursors, mas não para INSERT)
        // $options = array("Scrollable" => SQLSRV_CURSOR_KEYSET);

        $stmt = sqlsrv_prepare($this->conexao, $sql, $params);

        if ($stmt) {
            if (!sqlsrv_execute($stmt)) {
                // Se o log falhar, registra no log de erros do PHP como fallback
                $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
                $timestamp = date('Y-m-d H:i:s');
                error_log("{$timestamp} LogHelper: Falha ao executar statement de log no BD. Nível: {$level}, Mensagem Original: {$message}, Erros SQLSRV: " . json_encode($errors) . ", Contexto: " . json_encode($context));
            }
            sqlsrv_free_stmt($stmt);
        } else {
            // Falha ao preparar o statement
            $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
            $timestamp = date('Y-m-d H:i:s');
            error_log("{$timestamp} LogHelper: Falha ao preparar statement de log no BD. Nível: {$level}, Mensagem Original: {$message}, Erros SQLSRV: " . json_encode($errors) . ", Contexto: " . json_encode($context));
        }
    }
}
