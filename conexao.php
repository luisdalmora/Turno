<?php
// conexao.php
// Adicione estas linhas no topo para depuração, assim como no seu teste_conexao.php
// ini_set('display_errors', 1); // Movido para config.php
// error_reporting(E_ALL); // Movido para config.php

// Script de Conexão com o Banco de Dados MySQL

// Definição das variáveis de conexão
$db_servername = "localhost";       // Endereço do servidor MySQL (geralmente localhost)
$db_username = "sa";              // Nome de usuário do MySQL
$db_password = "C0nsult0r_";      // Senha do MySQL
$db_database = "simposto";          // Nome do banco de dados a ser utilizado

// Tentativa de estabelecer a conexão com o banco de dados
$conexao = mysqli_connect($db_servername, $db_username, $db_password, $db_database);

// Verificação da conexão
if (!$conexao) {
    // Se a conexão falhar, exibe uma mensagem de erro e encerra o script
    // Em um ambiente de produção, logue este erro em vez de usar die() diretamente se for um script de API.
    $error_msg = "Erro de conexão com o banco de dados em conexao.php: " . mysqli_connect_error() . " (Erro número: " . mysqli_connect_errno() . ")";
    error_log($error_msg); // Loga o erro no log do servidor
    // Para scripts de API, você pode querer retornar um JSON de erro aqui em vez de die()
    die($error_msg); // Mantido por enquanto para consistência com o original
}

// Define o charset da conexão para UTF-8 (recomendado para suportar caracteres especiais)
if (!mysqli_set_charset($conexao, "utf8mb4")) {
    $charset_error_msg = "Atenção: Erro ao definir o charset para utf8mb4: " . mysqli_error($conexao);
    error_log($charset_error_msg);
    trigger_error($charset_error_msg, E_USER_WARNING);
}

// A variável $conexao permanece disponível se este script for incluído por outro.
?>