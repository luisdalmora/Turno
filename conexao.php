<?php
$host = "localhost"; // Normalmente "localhost"
$usuario = "dalmoras";
$senha = "C0nsult0r_";
$banco = "simposto";

$conexao = new mysqli($host, $usuario, $senha, $banco);

if ($conexao->connect_error) {
    die("Erro na conexão: " . $conexao->connect_error);
}
?>