<?php
// conexao.php

// Definição das variáveis de conexão para SQL Server
$db_servername = "SimPosto-Luis\SQLEXPRESS"; // Ex: "localhost", "SQLEXPRESS", "IP_DO_SERVIDOR\INSTANCIA"
$db_username = "sa";              // Nome de usuário do SQL Server
$db_password = "SA_0bjetiva";      // Senha do SQL Server
$db_database = "simposto";          // Nome do banco de dados a ser utilizado

// Informações de conexão para sqlsrv_connect
$connectionInfo = array(
    "Database" => $db_database,
    "UID" => $db_username,
    "PWD" => $db_password,
    "CharacterSet" => "UTF-8" // Recomendado para evitar problemas com codificação
);

// Tentativa de estabelecer a conexão com o banco de dados SQL Server
$conexao = sqlsrv_connect($db_servername, $connectionInfo);

// Verificação da conexão
if ($conexao === false) {
    // Se a conexão falhar, loga o erro e encerra o script (ou trata de forma apropriada)
    // Em um ambiente de produção, logue este erro em vez de usar die() diretamente.
    $errors = sqlsrv_errors();
    $error_messages = [];
    if ($errors !== null) {
        foreach ($errors as $error) {
            $error_messages[] = "SQLSTATE: " . $error['SQLSTATE'] . "; Code: " . $error['code'] . "; Message: " . $error['message'];
        }
    }
    $error_msg_log = "Erro de conexão com o banco de dados SQL Server em conexao.php: " . implode(" | ", $error_messages);
    error_log($error_msg_log); // Loga o erro no log do servidor

    // Para scripts de API, você pode querer retornar um JSON de erro aqui.
    // Para páginas web, uma mensagem amigável ou redirecionamento.
    die("Falha na conexão com o banco de dados. Por favor, contate o administrador. Detalhes técnicos foram logados.");
}

// A variável $conexao permanece disponível se este script for incluído por outro.
// Não há uma função sqlsrv_set_charset equivalente direta como mysqli_set_charset,
// a codificação é geralmente tratada na string de conexão (CharacterSet => "UTF-8").
