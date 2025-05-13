<?php
// EmailHelper.php
require_once __DIR__ . '/config.php';

class EmailHelper {

    /**
     * Envia um e-mail.
     *
     * @param string $to Destinatário do e-mail.
     * @param string $subject Assunto do e-mail.
     * @param string $message Corpo do e-mail (pode ser HTML).
     * @return bool True se o e-mail foi aceito para entrega, False caso contrário.
     */
    public static function sendEmail($to, $subject, $message) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: ' . EMAIL_FROM_NAME . ' <' . EMAIL_FROM_ADDRESS . '>' . "\r\n";

        // Aviso: A função mail() do PHP tem limitações e depende da configuração do servidor.
        // Para produção, considere bibliotecas como PHPMailer para mais confiabilidade e recursos.
        if (mail($to, $subject, $message, $headers)) {
            return true;
        } else {
            // Logar o erro de envio de e-mail (usando o LogHelper seria ideal aqui, mas para evitar dependência circular ou complexidade inicial)
            error_log("EmailHelper: Falha ao enviar e-mail para {$to} com assunto: {$subject}");
            return false;
        }
    }

    public static function sendPasswordResetEmail($to, $reset_token) {
        $subject = "Redefinição de Senha - Sim Posto";
        $reset_link = SITE_URL . "/resetar_senha.php?token=" . urlencode($reset_token) . "&email=" . urlencode($to);
        $message_body = "
            Olá,<br><br>
            Você solicitou a redefinição de sua senha no sistema Sim Posto.<br>
            Clique no link abaixo para criar uma nova senha:<br>
            <a href='{$reset_link}'>{$reset_link}</a><br><br>
            Se você não solicitou isso, por favor ignore este e-mail.<br><br>
            Atenciosamente,<br>
            Equipe Sim Posto
        ";
        return self::sendEmail($to, $subject, $message_body);
    }

    public static function sendRegistrationConfirmationEmail($to, $nome_usuario) {
        $subject = "Bem-vindo ao Sim Posto!";
        $login_link = SITE_URL . "/index.html";
        $message_body = "
            Olá {$nome_usuario},<br><br>
            Seu cadastro no sistema Sim Posto foi realizado com sucesso!<br>
            Você já pode acessar o sistema utilizando seu usuário e senha.<br>
            Acesse em: <a href='{$login_link}'>{$login_link}</a><br><br>
            Atenciosamente,<br>
            Equipe Sim Posto
        ";
        return self::sendEmail($to, $subject, $message_body);
    }
}
?>