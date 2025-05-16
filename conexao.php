<?php
// conexao.php (Modificado para SQL Server)

// Definição das variáveis de conexão
$db_servername = "localhost";       // Endereço do servidor SQL Server (pode ser localhost, um IP ou um nome de host)
$db_username = "sa";              // Nome de usuário do SQL Server
$db_password = "SA_0bjetiva";      // Senha do SQL Server
$db_database = "simposto";          // Nome do banco de dados a ser utilizado

// Informações da conexão para SQL Server
$connectionOptions = array(
    "Database" => $db_database,
    "Uid" => $db_username,
    "PWD" => $db_password,
    "CharacterSet" => "UTF-8", // Recomendado para suportar caracteres especiais
    "Encrypt" => false // Opção 1: Desabilitar criptografia (menos seguro)
    // OU
    // "Encrypt" => true, // Manter criptografia (ou omitir, pois é o padrão para drivers mais novos)
    // "TrustServerCertificate" => true // Opção 2: Confiar no certificado do servidor sem validação (menos seguro)
);

// Tentativa de estabelecer a conexão com o banco de dados SQL Server
$conexao = sqlsrv_connect($db_servername, $connectionOptions);

// Verificação da conexão
if ($conexao === false) {
    // Se a conexão falhar, exibe uma mensagem de erro e encerra o script
    // Em um ambiente de produção, logue este erro em vez de usar die() diretamente.
    $errors = sqlsrv_errors();
    $error_messages = array();
    if ($errors !== null) {
        foreach ($errors as $error) {
            $error_messages[] = "SQLSTATE: " . $error['SQLSTATE'] . ", Code: " . $error['code'] . ", Message: " . $error['message'];
        }
    }
    $error_msg = "Erro de conexão com o banco de dados SQL Server em conexao.php: " . implode("; ", $error_messages);
    error_log($error_msg); // Loga o erro no log do servidor
    die($error_msg); 
}

// A variável $conexao permanece disponível se este script for incluído por outro.
