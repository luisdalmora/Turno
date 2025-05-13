<?php
// LogHelper.php

class LogHelper {
    private $conexao;

    public function __construct($db_connection) {
        $this->conexao = $db_connection;
    }

    /**
     * Registra uma mensagem de log no banco de dados.
     *
     * @param string $level Nível do log (e.g., INFO, ERROR, WARNING, AUTH_SUCCESS, AUTH_FAILURE, GCAL_SUCCESS, GCAL_ERROR)
     * @param string $message A mensagem de log.
     * @param array $context Dados contextuais adicionais (serão convertidos para JSON).
     * @param int|null $userId ID do usuário associado ao log.
     */
    public function log($level, $message, $context = [], $userId = null) {
        if (!$this->conexao) {
            // Não pode logar sem conexão com o BD
            error_log("LogHelper: Falha ao logar - Sem conexão com BD. Mensagem: " . $message);
            return;
        }

        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $context_json = !empty($context) ? json_encode($context) : null;

        $sql = "INSERT INTO system_logs (log_level, message, context, ip_address, user_id) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conexao, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssssi", $level, $message, $context_json, $ip_address, $userId);
            if (!mysqli_stmt_execute($stmt)) {
                // Se o log falhar, registra no log de erros do PHP como fallback
                error_log("LogHelper: Falha ao executar statement de log no BD: " . mysqli_stmt_error($stmt) . ". Mensagem original: " . $message);
            }
            mysqli_stmt_close($stmt);
        } else {
            error_log("LogHelper: Falha ao preparar statement de log no BD: " . mysqli_error($this->conexao) . ". Mensagem original: " . $message);
        }
    }
}
